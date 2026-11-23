const inputs = document.querySelectorAll('input[name="tags"]');

if (inputs.length > 0) {
    let candidates = null;

    const load = async () => {
        if (candidates === null) {
            candidates = [];
            const response = await fetch(document.querySelector('meta[name="tag-suggestions-url"]')?.content ?? '', { headers: { Accept: 'application/json' } }).catch(() => null);
            candidates = response && response.ok ? await response.json() : [];
        }
    };

    const slug = (text) => text.trim().replace(/^#/, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    inputs.forEach((input) => {
        const list = document.createElement('ul');
        list.hidden = true;
        list.className = 'absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-zinc-700 bg-zinc-900 py-1 text-sm shadow-lg';
        input.parentElement.classList.add('relative');
        input.after(list);
        input.setAttribute('autocomplete', 'off');

        let matches = [];
        let active = 0;

        const segment = () => {
            const before = input.value.slice(0, input.selectionStart ?? input.value.length);
            const start = before.lastIndexOf(',') + 1;

            return { start, text: before.slice(start) };
        };

        const close = () => {
            list.hidden = true;
            matches = [];
        };

        const render = () => {
            list.replaceChildren(...matches.map((match, index) => {
                const item = document.createElement('li');
                item.className = `flex cursor-pointer items-center justify-between px-3 py-1.5 ${index === active ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-300'}`;
                const name = document.createElement('span');
                name.textContent = match.name;
                const kind = document.createElement('span');
                kind.className = 'text-xs text-zinc-500';
                kind.textContent = match.kind;
                item.append(name, kind);
                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    choose(index);
                });

                return item;
            }));
            list.hidden = matches.length === 0;
        };

        const choose = (index) => {
            const { start } = segment();
            const end = input.selectionStart ?? input.value.length;
            const rest = input.value.slice(end).replace(/^[^,]*/, '');
            const head = input.value.slice(0, start).replace(/\s*$/, '');
            input.value = `${head}${head === '' ? '' : ' '}${matches[index].name}, ${rest.replace(/^,\s*/, '')}`.replace(/,\s*$/, ', ');
            close();
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const update = async () => {
            await load();
            const needle = slug(segment().text);
            if (needle === '') {
                return close();
            }
            const used = new Set(input.value.split(',').map(slug));
            matches = candidates
                .filter((candidate) => candidate.name.includes(needle) && !used.has(candidate.name))
                .sort((a, b) => Number(b.name.startsWith(needle)) - Number(a.name.startsWith(needle)))
                .slice(0, 8);
            active = 0;
            render();
        };

        input.addEventListener('input', update);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', (event) => {
            if (list.hidden) {
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
                render();
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                choose(active);
            } else if (event.key === 'Escape') {
                event.stopPropagation();
                close();
            }
        });
    });
}
