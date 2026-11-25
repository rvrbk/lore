import { DEFAULT_BASE_URL, getSettings } from './shared.js';

const input = document.getElementById('base');
const statusEl = document.getElementById('status');

const auto = document.getElementById('auto');

getSettings().then((settings) => { input.value = settings.baseUrl; auto.checked = settings.autoFill; });

document.getElementById('save').addEventListener('click', async () => {
    let url;
    try {
        url = new URL(input.value.trim() || DEFAULT_BASE_URL);
    } catch {
        statusEl.textContent = 'That is not a valid address.';

        return;
    }
    const local = ['localhost', '127.0.0.1'].includes(url.hostname);
    if (url.protocol !== 'https:' && !local) {
        statusEl.textContent = 'Use an https address.';

        return;
    }

    const granted = await chrome.permissions.request({ origins: [`${url.origin}/*`] });
    if (!granted) {
        statusEl.textContent = 'Permission to reach that address was not granted.';

        return;
    }

    let autoFill = auto.checked;
    if (autoFill && !(await chrome.permissions.request({ origins: ['https://*/*'] }))) {
        autoFill = false;
        auto.checked = false;
        statusEl.textContent = 'Automatic fill needs permission for all https sites, so it stays off. ';
    }

    await chrome.storage.sync.set({ baseUrl: url.origin, autoFill });
    await chrome.storage.session.remove('list');
    statusEl.textContent += 'Saved.';
});
