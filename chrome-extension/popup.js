import { fetchItems, fillTab, getBaseUrl, hostOf, isFillable, revealPassword, sameSite } from './shared.js';

const statusEl = document.getElementById('status');
const listEl = document.getElementById('list');
const searchEl = document.getElementById('search');

let baseUrl;
let csrf = '';
let items = [];
let tab = null;
let tabHost = null;

function setStatus(text, isError = false) {
    statusEl.textContent = text;
    statusEl.className = isError ? 'error' : '';
}

function openInTab(path) {
    chrome.tabs.create({ url: `${baseUrl}${path}` });
    window.close();
}

function statusWithLink(text, label, path) {
    setStatus(text, true);
    statusEl.append(' ');
    const link = Object.assign(document.createElement('a'), { href: '#', textContent: label });
    link.addEventListener('click', (event) => { event.preventDefault(); openInTab(path); });
    statusEl.append(link);
}

async function load(query = '') {
    const result = await fetchItems(query, { fresh: true });

    if (result.status === 'signed-out') {
        statusWithLink('Sign in to Lore first.', 'Open Lore', '/login');

        return false;
    }
    if (result.status === 'locked') {
        statusWithLink('The vault is locked. Unlock it in Lore (two-factor and password), then reopen this.', 'Unlock', '/vault');

        return false;
    }
    if (result.status === 'error') {
        setStatus(`Lore answered ${result.code}.`, true);

        return false;
    }

    csrf = result.csrf;
    items = result.items;

    return true;
}

function render() {
    const searching = searchEl.value.trim() !== '';
    const shown = searching ? items : items.filter((item) => sameSite(tabHost, hostOf(item.url)));

    listEl.replaceChildren();
    if (!shown.length) {
        setStatus(searching ? 'No logins found.' : tabHost ? `No login saved for ${tabHost}. Search to pick another.` : 'Search to find a login.');

        return;
    }
    setStatus('');

    for (const item of shown) {
        const li = document.createElement('li');
        const text = document.createElement('span');
        text.className = 'text';
        const name = Object.assign(document.createElement('span'), { className: 'name', textContent: item.name });
        const user = Object.assign(document.createElement('span'), { className: 'user', textContent: item.username || '' });
        text.append(name, user);

        const matches = sameSite(tabHost, hostOf(item.url));
        if (!matches) {
            text.append(Object.assign(document.createElement('span'), { className: 'other', textContent: item.url ? `Saved for ${hostOf(item.url) ?? item.url}` : 'No site saved' }));
        }

        const fill = Object.assign(document.createElement('button'), { textContent: 'Fill', type: 'button' });
        fill.addEventListener('click', () => fillItem(item, matches, fill));
        li.append(text, fill);
        listEl.append(li);
    }
}

async function fillItem(item, matches, button) {
    if (!tab || !/^https?:/.test(tab.url || '')) {
        setStatus('This page cannot be filled.', true);

        return;
    }
    if (!isFillable(tab.url)) {
        setStatus('Refusing to fill a page that is not on HTTPS.', true);

        return;
    }
    if (!matches && !confirm(`This login is saved for ${hostOf(item.url) ?? 'another site'}, not ${tabHost}. Fill it here anyway?`)) {
        return;
    }

    button.disabled = true;
    try {
        const password = await revealPassword(item, csrf);
        const result = await fillTab(tab.id, item, password);

        setStatus(result.password ? 'Filled.' : result.username ? 'Filled the username. Come back for the password on the next step.' : 'No login form found on this page.', !result.password && !result.username);
    } catch (error) {
        setStatus(error.code === 'locked' ? 'The vault locked again. Unlock it in Lore.' : `Could not fill: ${error.message}`, true);
    } finally {
        button.disabled = false;
    }
}

async function init() {
    baseUrl = await getBaseUrl();
    [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    tabHost = /^https?:/.test(tab?.url || '') ? hostOf(tab.url) : null;
    document.getElementById('host').textContent = tabHost ?? '';

    document.getElementById('open-vault').addEventListener('click', (event) => { event.preventDefault(); openInTab('/vault'); });
    document.getElementById('open-options').addEventListener('click', (event) => { event.preventDefault(); chrome.runtime.openOptionsPage(); });

    if (!(await load())) {
        return;
    }
    render();

    let timer;
    searchEl.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (await load(searchEl.value.trim())) {
                render();
            }
        }, 200);
    });
}

init();
