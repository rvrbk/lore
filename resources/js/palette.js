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

    const CITIES = {
        amsterdam: 'Europe/Amsterdam', london: 'Europe/London', paris: 'Europe/Paris', berlin: 'Europe/Berlin', kampala: 'Africa/Kampala',
        nairobi: 'Africa/Nairobi', cairo: 'Africa/Cairo', dubai: 'Asia/Dubai', mumbai: 'Asia/Kolkata', delhi: 'Asia/Kolkata',
        singapore: 'Asia/Singapore', tokyo: 'Asia/Tokyo', seoul: 'Asia/Seoul', sydney: 'Australia/Sydney', auckland: 'Pacific/Auckland',
        'new york': 'America/New_York', chicago: 'America/Chicago', denver: 'America/Denver', 'los angeles': 'America/Los_Angeles',
        'sao paulo': 'America/Sao_Paulo', utc: 'UTC',
    };

    const randomBelow = (limit) => {
        const value = new Uint32Array(1);
        crypto.getRandomValues(value);

        return value[0] % limit;
    };

    const timeIn = (zone) => new Intl.DateTimeFormat('en-GB', { timeZone: zone, weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date());

    const COMMANDS = [
        ['/roll', 'Roll dice, e.g. /roll 2d20'],
        ['/coin', 'Flip a coin'],
        ['/uuid', 'Generate a UUID'],
        ['/time', 'Time in a city, e.g. /time Tokyo'],
        ['/random', 'Open a random page'],
    ];

    const runCommand = (raw) => {
        const [name, ...rest] = raw.trim().toLowerCase().split(/\s+/);
        const argument = rest.join(' ');
        const result = (title, hint, copy) => [{ title, hint, kind: 'result', copy }];

        if (name === '/roll') {
            const match = /^(\d{1,2})?d(\d{1,4})$/.exec(argument || 'd6');
            if (!match) {
                return result('Try /roll d20 or /roll 3d6', '', null);
            }
            const count = Math.max(1, Number(match[1] ?? 1));
            const sides = Math.max(2, Number(match[2]));
            const rolls = Array.from({ length: count }, () => randomBelow(sides) + 1);
            const total = rolls.reduce((sum, value) => sum + value, 0);

            return result(`🎲 ${total}`, count > 1 ? `${rolls.join(' + ')} · Enter copies` : 'Enter copies', String(total));
        }
        if (name === '/coin') {
            const face = randomBelow(2) === 0 ? 'Heads' : 'Tails';

            return result(`🪙 ${face}`, 'Enter copies', face);
        }
        if (name === '/uuid') {
            const uuid = crypto.randomUUID();

            return result(uuid, 'Enter copies', uuid);
        }
        if (name === '/time') {
            const key = argument.replace(/^in\s+/, '');
            const zone = key === '' ? Intl.DateTimeFormat().resolvedOptions().timeZone : (CITIES[key] ?? (key.includes('/') ? key.replace(/(^|[/_])(\w)/g, (_, edge, letter) => edge + letter.toUpperCase()) : null));
            try {
                return result(`🕒 ${timeIn(zone)}`, zone, null);
            } catch {
                return result(`Unknown city "${key}"`, 'Try Tokyo, Kampala, New York or Europe/Paris', null);
            }
        }
        if (name === '/random') {
            const list = pages ?? [];

            return list.length ? [{ ...list[randomBelow(list.length)], hint: 'Random page', kind: 'page' }] : result('No pages yet', '', null);
        }

        return COMMANDS.filter(([command]) => command.startsWith(name)).map(([command, hint]) => ({ title: command, hint, kind: 'command', fill: `${command} ` }));
    };

    let commandItems = [];

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
        results = query.startsWith('/') ? commandItems : (query === '' ? items.filter((i) => i.kind === 'action').concat(items.filter((i) => i.kind === 'page').slice(0, 8)) : items
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
            title.textContent = item.kind === 'page' && item.weather ? `${item.weather.icon} ${item.title}` : item.title;
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

    const go = async (item) => {
        if (!item) {
            return;
        }
        if (item.kind === 'command') {
            input.value = item.fill;
            input.dispatchEvent(new Event('input'));
        } else if (item.kind === 'result') {
            if (item.copy) {
                try {
                    await navigator.clipboard.writeText(item.copy);
                    item.hint = 'Copied';
                } catch {
                    item.hint = 'Copy not allowed here';
                }
                render();
            }
        } else {
            window.location.href = item.url;
        }
    };

    const open = async () => {
        input.value = '';
        commandItems = [];
        active = 0;
        dialog.showModal();
        render();
        await loadPages();
        if (input.value.trimStart().startsWith('/')) {
            commandItems = runCommand(input.value.trimStart());
        }
        render();
        input.focus();
    };

    input.addEventListener('input', () => {
        active = 0;
        commandItems = input.value.trimStart().startsWith('/') ? runCommand(input.value.trimStart()) : [];
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
