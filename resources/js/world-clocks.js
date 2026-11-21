import { uses12Hours } from './local-time.js';

const clocks = document.querySelectorAll('[data-world-clock]');

if (clocks.length) {
    const storageKey = 'world-clocks-12h';
    const readStored = () => {
        try {
            return localStorage.getItem(storageKey);
        } catch {
            return null;
        }
    };
    const stored = readStored();
    let twelve = stored === null ? uses12Hours() : stored === '1';

    const timeFormatters = new Map();
    const dateFormatters = new Map();

    const tick = () => {
        const now = new Date();
        clocks.forEach((clock) => {
            const zone = clock.dataset.worldClock;
            try {
                if (!timeFormatters.has(zone)) {
                    dateFormatters.set(zone, new Intl.DateTimeFormat(undefined, { timeZone: zone, weekday: 'short', day: 'numeric', month: 'short' }));
                }
                const key = `${zone}|${twelve}`;
                if (!timeFormatters.has(key)) {
                    timeFormatters.set(key, new Intl.DateTimeFormat(undefined, { timeZone: zone, hour: twelve ? 'numeric' : '2-digit', minute: '2-digit', hour12: twelve }));
                }
                timeFormatters.set(zone, true);
                const seconds = document.createElement('sup');
                seconds.className = 'ml-px text-xs font-medium text-indigo-300/70';
                seconds.textContent = String(now.getSeconds()).padStart(2, '0');
                const nodes = [];
                let hour = 0;
                let minute = 0;
                timeFormatters.get(key).formatToParts(now).forEach((part) => {
                    if (part.type === 'hour') {
                        hour = Number(part.value) % 12;
                    } else if (part.type === 'minute') {
                        minute = Number(part.value);
                    }
                    nodes.push(document.createTextNode(part.value));
                    if (part.type === 'minute') {
                        nodes.push(seconds);
                    }
                });
                clock.querySelector('[data-clock-time]').replaceChildren(...nodes);
                const rotate = (hand, degrees) => clock.querySelector(`[data-hand="${hand}"]`).setAttribute('transform', `rotate(${degrees} 24 16)`);
                rotate('hour', hour * 30 + minute * 0.5);
                rotate('minute', minute * 6);
                rotate('second', now.getSeconds() * 6);
                clock.querySelector('[data-clock-date]').textContent = dateFormatters.get(zone).format(now).replace(',', '');
            } catch {
                // keep the server-rendered time
            }
        });
    };

    document.querySelectorAll('[data-clock-time]').forEach((button) => {
        button.addEventListener('click', () => {
            twelve = !twelve;
            try {
                localStorage.setItem(storageKey, twelve ? '1' : '0');
            } catch {
                // preference just won't persist
            }
            tick();
        });
    });

    setInterval(tick, 1000);
    tick();
}

const list = document.querySelector('[data-clock-list]');

if (list) {
    let dragged = null;

    list.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-clock-id]');
        if (dragged) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', dragged.dataset.clockId);
            dragged.classList.add('opacity-40');
        }
    });

    list.addEventListener('dragover', (event) => {
        const target = event.target.closest('[data-clock-id]');
        if (!dragged || !target || target === dragged) {
            return;
        }
        event.preventDefault();
        const box = target.getBoundingClientRect();
        const after = event.clientX > box.left + box.width / 2;
        list.insertBefore(dragged, after ? target.nextSibling : target);
    });

    list.addEventListener('dragend', () => {
        if (!dragged) {
            return;
        }
        dragged.classList.remove('opacity-40');
        dragged = null;
        saveOrder();
    });

    const saveOrder = () => {
        const order = [...list.querySelectorAll('[data-clock-id]')].map((item) => Number(item.dataset.clockId));
        fetch(list.dataset.reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ order }),
        });
    };
}

const headerClocks = document.querySelectorAll('[data-header-clock]');

if (headerClocks.length) {
    const storageKey = 'world-clocks-12h';
    const readTwelve = () => {
        try {
            const stored = localStorage.getItem(storageKey);

            return stored === null ? uses12Hours() : stored === '1';
        } catch {
            return uses12Hours();
        }
    };

    const tickHeader = () => {
        const twelve = readTwelve();
        headerClocks.forEach((clock) => {
            try {
                clock.querySelector('[data-header-clock-time]').textContent = new Intl.DateTimeFormat(undefined, { timeZone: clock.dataset.headerClock, hour: twelve ? 'numeric' : '2-digit', minute: '2-digit', hour12: twelve }).format(new Date());
            } catch {
                // keep the server-rendered time
            }
        });
    };

    headerClocks.forEach((clock) => clock.addEventListener('click', () => {
        try {
            localStorage.setItem(storageKey, readTwelve() ? '0' : '1');
        } catch {
            // preference just won't persist
        }
        tickHeader();
    }));

    setInterval(tickHeader, 1000);
    tickHeader();
}
