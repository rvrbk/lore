const dialog = document.querySelector('[data-palette]');

if (dialog) {
    const input = dialog.querySelector('[data-palette-input]');
    const list = dialog.querySelector('[data-palette-list]');
    const actions = JSON.parse(dialog.dataset.actions || '[]');
    let pages = null;
    let results = [];
    let active = 0;

    const isTyping = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable;

    const loadPages = async () => {
        if (pages === null) {
            const response = await fetch(dialog.dataset.url, { headers: { Accept: 'application/json' } }).catch(() => null);
            pages = response && response.ok ? await response.json() : [];
        }
    };

    const score = (item, query) => {
        const haystack = `${item.title} ${item.space ?? ''}`.toLowerCase();
        const title = item.title.toLowerCase();
        if (title.startsWith(query)) {
            return 0;
        }
        if (title.includes(query)) {
            return 1;
        }

        return haystack.includes(query) ? 2 : 3;
    };

    const render = () => {
        const query = input.value.trim().toLowerCase();
        const items = [
            ...actions.map((a) => ({ ...a, kind: 'action' })),
            ...(pages ?? []).map((p) => ({ ...p, hint: p.space, kind: 'page' })),
        ];
        results = (query === '' ? items.filter((i) => i.kind === 'action').concat(items.filter((i) => i.kind === 'page').slice(0, 8)) : items
            .map((item) => [score(item, query), item])
            .filter(([s]) => s < 3)
            .sort((a, b) => a[0] - b[0] || (a[1].kind === 'action' ? -1 : 1))
            .map(([, item]) => item)).slice(0, 12);
        active = Math.min(active, Math.max(0, results.length - 1));

        list.replaceChildren(...results.map((item, index) => {
            const li = document.createElement('li');
            li.className = `flex cursor-pointer items-center justify-between gap-3 px-4 py-1.5 ${index === active ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-300'}`;
            const title = document.createElement('span');
            title.className = 'truncate';
            title.textContent = item.title;
            const hint = document.createElement('span');
            hint.className = 'shrink-0 text-xs text-zinc-500';
            hint.textContent = item.hint ?? '';
            li.append(title, hint);
            li.addEventListener('mousemove', () => {
                active = index;
                render();
            });
            li.addEventListener('click', () => go(item));

            return li;
        }));
        if (results.length === 0) {
            const li = document.createElement('li');
            li.className = 'px-4 py-2 text-zinc-500';
            li.textContent = 'No matches.';
            list.replaceChildren(li);
        }
    };

    const go = (item) => {
        if (item) {
            window.location.href = item.url;
        }
    };

    const open = async () => {
        input.value = '';
        active = 0;
        dialog.showModal();
        render();
        await loadPages();
        render();
        input.focus();
    };

    input.addEventListener('input', () => {
        active = 0;
        render();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % Math.max(1, results.length);
            render();
            list.children[active]?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            go(results[active]);
        }
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            dialog.open ? dialog.close() : open();
        } else if (event.key === '/' && !isTyping() && !dialog.open && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            document.querySelector('[data-search-input]')?.focus();
        }
    });

    document.querySelectorAll('[data-palette-open]').forEach((button) => button.addEventListener('click', open));
}
