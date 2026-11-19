const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

const initEditor = (root) => {
    const area = root.querySelector('textarea');
    const preview = root.querySelector('[data-md-preview]');
    const status = root.querySelector('[data-md-status]');
    const fileInput = root.querySelector('[data-md-file]');
    let timer = null;
    let latest = 0;

    const changed = () => area.dispatchEvent(new Event('input', { bubbles: true }));

    const replaceSelection = (text, selectFrom = null, selectTo = null) => {
        const start = area.selectionStart;
        area.setRangeText(text, start, area.selectionEnd, 'end');
        if (selectFrom !== null) {
            area.setSelectionRange(start + selectFrom, start + selectTo);
        }
        area.focus();
        changed();
    };

    const wrap = (before, after, placeholder) => {
        const selected = area.value.slice(area.selectionStart, area.selectionEnd) || placeholder;
        replaceSelection(before + selected + after, before.length, before.length + selected.length);
    };

    const prefixLines = (prefix) => {
        const value = area.value;
        const start = value.lastIndexOf('\n', area.selectionStart - 1) + 1;
        let end = value.indexOf('\n', area.selectionEnd);
        end = end === -1 ? value.length : end;
        const block = value.slice(start, end).split('\n').map((line, i) => (prefix === '1. ' ? `${i + 1}. ` : prefix) + line).join('\n');
        area.setRangeText(block, start, end, 'select');
        area.focus();
        changed();
    };

    const actions = {
        bold: () => wrap('**', '**', 'bold text'),
        italic: () => wrap('*', '*', 'italic text'),
        code: () => wrap('`', '`', 'code'),
        link: () => wrap('[', '](https://)', 'link text'),
        wiki: () => wrap('[[', ']]', 'Page title'),
        heading: () => prefixLines('## '),
        ul: () => prefixLines('- '),
        ol: () => prefixLines('1. '),
        task: () => prefixLines('- [ ] '),
        quote: () => prefixLines('> '),
        attach: () => fileInput.click(),
    };

    root.querySelectorAll('[data-md]').forEach((button) => {
        button.addEventListener('click', () => actions[button.dataset.md]());
    });

    area.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && ['b', 'i'].includes(event.key.toLowerCase())) {
            event.preventDefault();
            actions[event.key.toLowerCase() === 'b' ? 'bold' : 'italic']();
        }
    });

    const renderPreview = async () => {
        const ticket = ++latest;
        const response = await fetch(root.dataset.previewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ body: area.value, space: root.dataset.space || null }),
        }).catch(() => null);

        if (response && response.ok && ticket === latest) {
            preview.innerHTML = (await response.json()).html || '<p class="text-zinc-500">Nothing to preview yet.</p>';
        }
    };

    area.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(renderPreview, 350);
    });

    const upload = async (file) => {
        status.textContent = `Uploading ${file.name || 'image'}…`;
        const form = new FormData();
        form.append('file', file, file.name || `pasted-${Date.now()}.png`);

        const response = await fetch(root.dataset.uploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: form,
        }).catch(() => null);

        if (!response || !response.ok) {
            const message = response && response.status === 422 ? (await response.json()).message : 'Upload failed.';
            status.textContent = message;
            return;
        }
        const result = await response.json();
        const label = result.name.replace(/[[\]]/g, '');
        replaceSelection(`${result.is_image ? '!' : ''}[${label}](${result.url})\n`);
        status.textContent = '';
    };

    fileInput.addEventListener('change', async () => {
        for (const file of fileInput.files) {
            await upload(file);
        }
        fileInput.value = '';
    });

    area.addEventListener('paste', async (event) => {
        const files = [...(event.clipboardData?.files ?? [])];
        if (files.length === 0) {
            return;
        }
        event.preventDefault();
        for (const file of files) {
            await upload(file);
        }
    });

    area.addEventListener('dragover', (event) => {
        if (event.dataTransfer?.types.includes('Files')) {
            event.preventDefault();
        }
    });

    area.addEventListener('drop', async (event) => {
        const files = [...(event.dataTransfer?.files ?? [])];
        if (files.length === 0) {
            return;
        }
        event.preventDefault();
        for (const file of files) {
            await upload(file);
        }
    });

    renderPreview();
};

document.querySelectorAll('[data-md-editor]').forEach(initEditor);
