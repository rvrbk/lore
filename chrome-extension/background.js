import { fetchItems, fillTab, getSettings, hostOf, isFillable, revealPassword, sameSite, tabHasLoginForm } from './shared.js';

const GREEN = '#2e7d32';
const AMBER = '#b9770e';
const autoFilled = new Map();

function matchesFor(url, items) {
    const host = hostOf(url);

    return host ? items.filter((item) => sameSite(host, hostOf(item.url))) : [];
}

async function updateBadge(tabId, url) {
    if (!/^https?:/.test(url || '')) {
        await chrome.action.setBadgeText({ tabId, text: '' });

        return null;
    }

    const result = await fetchItems();
    if (result.status !== 'ok') {
        await chrome.action.setBadgeBackgroundColor({ tabId, color: AMBER });
        await chrome.action.setBadgeText({ tabId, text: result.status === 'error' ? '' : '!' });

        return null;
    }

    const matches = matchesFor(url, result.items);
    await chrome.action.setBadgeBackgroundColor({ tabId, color: GREEN });
    await chrome.action.setBadgeText({ tabId, text: matches.length ? String(matches.length) : '' });

    return { matches, csrf: result.csrf };
}

/** Opt-in: when exactly one login matches the site and the page has a login form, fill it without a click. */
async function autoFill(tabId, url, found) {
    const { autoFill: enabled } = await getSettings();
    if (!enabled || !found || found.matches.length !== 1 || !isFillable(url)) {
        return;
    }

    const key = `${tabId}:${url}`;
    if (autoFilled.get(tabId) === key) {
        return;
    }
    autoFilled.set(tabId, key);

    try {
        if (!(await tabHasLoginForm(tabId))) {
            return;
        }
        const item = found.matches[0];
        await fillTab(tabId, item, await revealPassword(item, found.csrf));
    } catch {
        // No permission for this site, a locked vault or a closed tab: nothing to do, the popup still works.
    }
}

async function refresh(tabId, url) {
    const found = await updateBadge(tabId, url);

    return found;
}

chrome.tabs.onActivated.addListener(async ({ tabId }) => {
    const tab = await chrome.tabs.get(tabId);
    await refresh(tabId, tab.url);
});

chrome.tabs.onUpdated.addListener(async (tabId, change, tab) => {
    if (change.status === 'loading') {
        autoFilled.delete(tabId);
    }
    if (change.status === 'complete') {
        autoFill(tabId, tab.url, await refresh(tabId, tab.url));
    }
});

chrome.tabs.onRemoved.addListener((tabId) => autoFilled.delete(tabId));
