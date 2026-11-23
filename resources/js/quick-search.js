const form = document.querySelector('[data-quick-search]');

if (form) {
    const input = form.querySelector('[data-search-input]');
    const list = form.querySelector('#quick-search-list');
    let rows = [];
    let active = -1;
    let timer = null;
    let latest = 0;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    };

    const paint = () => {
        [...list.querySelectorAll('[data-row]')].forEach((item, index) => {
            item.classList.toggle('bg-zinc-800', index === active);
            item.setAttribute('aria-selected', String(index === active));
        });
    };

    const render = () => {
        list.replaceChildren();
        let type = null;
        rows.forEach((row, index) => {
            if (row.type !== type) {
                type = row.type;
                const heading = document.createElement('li');
                heading.className = 'px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500';
                heading.setAttribute('role', 'presentation');
                heading.textContent = row.type;
                list.append(heading);
            }
            const item = document.createElement('li');
            item.dataset.row = String(index);
            item.setAttribute('role', 'option');
            const link = document.createElement('a');
            link.href = row.url;
            link.className = 'block px-3 py-1.5 text-zinc-200 hover:bg-zinc-800';
            const title = document.createElement('span');
            title.className = 'block truncate';
            title.textContent = row.title;
            link.append(title);
            if (row.hint) {
                const hint = document.createElement('span');
                hint.className = 'block truncate text-xs text-zinc-500';
                hint.textContent = row.hint;
                link.append(hint);
            }
            item.append(link);
            list.append(item);
        });
        const all = document.createElement('li');
        all.setAttribute('role', 'presentation');
        const allLink = document.createElement('a');
        allLink.href = `${form.action}?q=${encodeURIComponent(input.value.trim())}`;
        allLink.className = 'mt-1 block border-t border-zinc-800 px-3 py-1.5 text-xs text-indigo-300 hover:bg-zinc-800';
        allLink.textContent = 'See all results';
        all.append(allLink);
        list.append(all);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    };

    const search = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            rows = [];
            return close();
        }
        const ticket = ++latest;
        const response = await fetch(`${form.dataset.quickSearch}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } }).catch(() => null);
        if (ticket !== latest || !response || !response.ok) {
            return;
        }
        rows = await response.json();
        if (rows.length === 0) {
            list.replaceChildren();
            const empty = document.createElement('li');
            empty.className = 'px-3 py-2 text-zinc-500';
            empty.textContent = 'Nothing matches.';
            list.append(empty);
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');

            return;
        }
        render();
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 150);
    });
    input.addEventListener('focus', () => {
        if (rows.length > 0 && input.value.trim().length >= 2) {
            list.hidden = false;
        }
    });
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) {
            close();
        }
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        } else if (!list.hidden && rows.length > 0 && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
            paint();
            list.querySelector(`[data-row="${active}"]`)?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            window.location.href = rows[active].url;
        }
    });
}
