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
                clock.querySelector('[data-clock-time]').textContent = timeFormatters.get(key).format(now);
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
