const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const SHOW_MS = 15000;
const CLIPBOARD_MS = 30000;

const copy = async (text) => {
    await navigator.clipboard.writeText(text);
    setTimeout(() => navigator.clipboard.writeText('').catch(() => {}), CLIPBOARD_MS);
};

// A password only ever comes from this POST. A 423 means the vault locked (idle time or two-factor), so go and unlock it.
const fetchPassword = async (url) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
    });
    if (response.status === 423 || response.redirected) {
        window.location.reload();
        return null;
    }
    if (!response.ok) {
        throw new Error('Could not fetch the password.');
    }
    return (await response.json()).password;
};

const flash = (button, text) => {
    const original = button.textContent;
    button.textContent = text;
    setTimeout(() => (button.textContent = original), 1500);
};

document.addEventListener('click', async (event) => {
    const reveal = event.target.closest('[data-vault-reveal]');
    const copyPassword = event.target.closest('[data-vault-copy]');
    const copyText = event.target.closest('[data-copy-text]');

    try {
        if (copyText) {
            await copy(copyText.dataset.copyText);
            flash(copyText, 'Copied');
        } else if (reveal) {
            const shown = reveal.closest('li').querySelector('[data-vault-shown]');
            if (!shown.classList.contains('hidden')) {
                shown.textContent = '';
                shown.classList.add('hidden');
                reveal.textContent = 'Reveal';
                return;
            }
            const password = await fetchPassword(reveal.dataset.vaultReveal);
            if (password === null) {
                return;
            }
            shown.textContent = password;
            shown.classList.remove('hidden');
            reveal.textContent = 'Hide';
            setTimeout(() => {
                shown.textContent = '';
                shown.classList.add('hidden');
                reveal.textContent = 'Reveal';
            }, SHOW_MS);
        } else if (copyPassword) {
            const password = await fetchPassword(copyPassword.dataset.vaultCopy);
            if (password !== null) {
                await copy(password);
                flash(copyPassword, 'Copied');
            }
        }
    } catch (error) {
        alert(error.message);
    }
});

const SETS = {
    lower: 'abcdefghijklmnopqrstuvwxyz',
    upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    digits: '0123456789',
    symbols: '!@#$%^&*()-_=+[]{};:,.?',
};

// Uniform pick from crypto.getRandomValues, rejecting values that would bias the result.
const randomIndex = (max) => {
    const limit = Math.floor(0x100000000 / max) * max;
    const buffer = new Uint32Array(1);
    do {
        crypto.getRandomValues(buffer);
    } while (buffer[0] >= limit);
    return buffer[0] % max;
};

export const generatePassword = (length, enabled) => {
    const sets = enabled.map((name) => SETS[name]);
    if (sets.length === 0) {
        return '';
    }
    const all = sets.join('');
    const chars = sets.map((set) => set[randomIndex(set.length)]);
    while (chars.length < length) {
        chars.push(all[randomIndex(all.length)]);
    }
    for (let i = chars.length - 1; i > 0; i--) {
        const j = randomIndex(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
};

document.querySelectorAll('[data-vault-form]').forEach((form) => {
    const input = form.querySelector('[data-vault-password]');
    form.querySelector('[data-gen-run]')?.addEventListener('click', () => {
        const enabled = ['lower', 'upper', 'digits', 'symbols'].filter((name) => form.querySelector(`[data-gen-${name}]`).checked);
        const length = Math.min(128, Math.max(8, parseInt(form.querySelector('[data-gen-length]').value, 10) || 20));
        input.value = generatePassword(length, enabled);
        input.type = 'text';
    });
    form.querySelector('[data-gen-show]')?.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});
