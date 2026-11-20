const clocks = document.querySelectorAll('[data-world-clock]');

if (clocks.length) {
    const formatters = new Map();
    const formatterFor = (zone, options, locale = 'en-GB') => {
        const key = locale + zone + JSON.stringify(options);
        if (!formatters.has(key)) {
            formatters.set(key, new Intl.DateTimeFormat(locale, { timeZone: zone, ...options }));
        }

        return formatters.get(key);
    };

    const tick = () => {
        const now = new Date();
        clocks.forEach((clock) => {
            const zone = clock.dataset.worldClock;
            const twelve = clock.dataset.hour12 === '1';
            try {
                clock.querySelector('[data-clock-time]').textContent = twelve
                    ? formatterFor(zone, { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true }, 'en-US').format(now)
                    : formatterFor(zone, { hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(now);
                clock.querySelector('[data-clock-date]').textContent = formatterFor(zone, { weekday: 'short', day: 'numeric', month: 'short' }).format(now).replace(',', '');
            } catch {
                // keep the server-rendered time
            }
        });
    };

    setInterval(tick, 1000);
    tick();
}
