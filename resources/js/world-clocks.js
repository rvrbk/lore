import { timeFormatter } from './local-time.js';

const clocks = document.querySelectorAll('[data-world-clock]');

if (clocks.length) {
    const timeFormatters = new Map();
    const dateFormatters = new Map();

    const tick = () => {
        const now = new Date();
        clocks.forEach((clock) => {
            const zone = clock.dataset.worldClock;
            try {
                if (!timeFormatters.has(zone)) {
                    timeFormatters.set(zone, timeFormatter(zone, true));
                    dateFormatters.set(zone, new Intl.DateTimeFormat(undefined, { timeZone: zone, weekday: 'short', day: 'numeric', month: 'short' }));
                }
                clock.querySelector('[data-clock-time]').textContent = timeFormatters.get(zone).format(now);
                clock.querySelector('[data-clock-date]').textContent = dateFormatters.get(zone).format(now).replace(',', '');
            } catch {
                // keep the server-rendered time
            }
        });
    };

    setInterval(tick, 1000);
    tick();
}
