export const DEFAULT_BASE_URL = 'https://lore.verbeek.ug';
const LIST_TTL_MS = 60_000;

export async function getSettings() {
    const { baseUrl, autoFill } = await chrome.storage.sync.get(['baseUrl', 'autoFill']);

    return { baseUrl: (baseUrl || DEFAULT_BASE_URL).replace(/\/+$/, ''), autoFill: autoFill === true };
}

export async function getBaseUrl() {
    return (await getSettings()).baseUrl;
}

/** The host of a saved URL, with or without a scheme. Null when it is empty or not a URL. */
export function hostOf(value) {
    if (!value) {
        return null;
    }
    try {
        const url = new URL(/^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : `https://${value}`);

        return url.hostname.replace(/^www\./, '').toLowerCase();
    } catch {
        return null;
    }
}

/** True when both hosts are the same site: equal, or one is a subdomain of the other (accounts.example.com and example.com). */
export function sameSite(tabHost, itemHost) {
    if (!tabHost || !itemHost) {
        return false;
    }

    return tabHost === itemHost || tabHost.endsWith(`.${itemHost}`) || itemHost.endsWith(`.${tabHost}`);
}

/** A page we are willing to put a password into: https, or http on this machine. */
export function isFillable(url) {
    try {
        const { protocol, hostname } = new URL(url);

        return protocol === 'https:' || (protocol === 'http:' && ['localhost', '127.0.0.1'].includes(hostname));
    } catch {
        return false;
    }
}

export async function api(path, options = {}) {
    const baseUrl = await getBaseUrl();

    return fetch(`${baseUrl}${path}`, {
        credentials: 'include',
        cache: 'no-store',
        ...options,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });
}

/**
 * The saved logins without passwords. Returns { status: 'ok', items, csrf } or { status: 'signed-out' | 'locked' | 'error' }.
 * Without a query the list is kept for a minute in session storage (memory only, gone when the browser closes).
 */
export async function fetchItems(query = '', { fresh = false } = {}) {
    if (!query && !fresh) {
        const { list } = await chrome.storage.session.get('list');
        if (list && Date.now() - list.at < LIST_TTL_MS) {
            return { status: 'ok', items: list.items, csrf: list.csrf };
        }
    }

    const response = await api(`/vault/items${query ? `?q=${encodeURIComponent(query)}` : ''}`);
    if (response.status === 401) {
        return { status: 'signed-out' };
    }
    if (response.status === 423) {
        return { status: 'locked' };
    }
    if (!response.ok) {
        return { status: 'error', code: response.status };
    }

    const { items, csrf } = await response.json();
    if (!query) {
        await chrome.storage.session.set({ list: { items, csrf, at: Date.now() } });
    }

    return { status: 'ok', items, csrf };
}

/** One password, on request. Throws an Error with `code` 'locked' or 'error'. */
export async function revealPassword(item, csrf) {
    const response = await api(new URL(item.reveal).pathname, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
    if (response.status === 423) {
        throw Object.assign(new Error('The vault is locked.'), { code: 'locked' });
    }
    if (!response.ok) {
        throw Object.assign(new Error(`Lore answered ${response.status}.`), { code: 'error' });
    }

    return (await response.json()).password;
}

/** Runs inside every frame of the page: is there a visible password field? Self-contained, it is serialised into the page. */
export function probeLoginForm() {
    return [...document.querySelectorAll('input[type=password]')].some((el) => !el.disabled && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
}

/**
 * Runs inside every frame of the page. Self-contained, it is serialised into the page and keeps nothing.
 * The top frame is always filled. A nested frame is only filled when it is on the same site as the saved login,
 * so an embedded third-party frame never receives the password.
 */
export function fillLoginForm(username, password, itemHost) {
    if (window !== window.top) {
        const host = location.hostname.replace(/^www\./, '').toLowerCase();
        if (!itemHost || !(host === itemHost || host.endsWith(`.${itemHost}`) || itemHost.endsWith(`.${host}`))) {
            return { username: false, password: false };
        }
    }

    const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    const setValue = (el, value) => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const inputs = [...document.querySelectorAll('input')].filter((el) => visible(el) && !el.disabled && !el.readOnly);
    const passwordField = inputs.find((el) => el.type === 'password');
    const candidates = inputs.filter((el) => ['text', 'email', 'tel', ''].includes(el.type));
    let userField = candidates.find((el) => /username|email/.test(el.autocomplete));

    if (!userField && passwordField) {
        userField = candidates.filter((el) => el.compareDocumentPosition(passwordField) & Node.DOCUMENT_POSITION_FOLLOWING).at(-1);
    }
    userField ??= passwordField ? undefined : candidates[0];

    if (userField && username) {
        setValue(userField, username);
    }
    if (passwordField) {
        setValue(passwordField, password);
    }

    return { username: !!(userField && username), password: !!passwordField };
}

/** Fills the tab (all frames, see fillLoginForm) and says what was filled. */
export async function fillTab(tabId, item, password) {
    const results = await chrome.scripting.executeScript({ target: { tabId, allFrames: true }, func: fillLoginForm, args: [item.username || '', password, hostOf(item.url)] });

    return { username: results.some((r) => r.result?.username), password: results.some((r) => r.result?.password) };
}

export async function tabHasLoginForm(tabId) {
    const results = await chrome.scripting.executeScript({ target: { tabId, allFrames: true }, func: probeLoginForm });

    return results.some((r) => r.result === true);
}
