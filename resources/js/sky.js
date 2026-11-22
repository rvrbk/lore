const root = document.querySelector('[data-sky]');

const SYNODIC_DAYS = 29.530588853;
const NEW_MOON = Date.UTC(2000, 0, 6, 18, 14);
const PHASES = [
    ['🌑', 'New moon'], ['🌒', 'Waxing crescent'], ['🌓', 'First quarter'], ['🌔', 'Waxing gibbous'],
    ['🌕', 'Full moon'], ['🌖', 'Waning gibbous'], ['🌗', 'Last quarter'], ['🌘', 'Waning crescent'],
];

export const moon = (moment = new Date()) => {
    const age = (((moment.getTime() - NEW_MOON) / 86400000) % SYNODIC_DAYS + SYNODIC_DAYS) % SYNODIC_DAYS;
    const [icon, name] = PHASES[Math.floor((age / SYNODIC_DAYS) * 8 + 0.5) % 8];
    const lit = Math.round(((1 - Math.cos((2 * Math.PI * age) / SYNODIC_DAYS)) / 2) * 100);
    const toFull = age < SYNODIC_DAYS / 2 ? SYNODIC_DAYS / 2 - age : SYNODIC_DAYS - age + SYNODIC_DAYS / 2;

    return { icon, name, lit, fullInDays: Math.round(toFull) };
};

const minutesOf = (clock) => {
    const [hours, minutes] = clock.split(':').map(Number);

    return hours * 60 + minutes;
};

const localMinutes = (zone) => {
    const text = new Intl.DateTimeFormat('en-GB', { timeZone: zone, hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date());

    return minutesOf(text);
};

if (root) {
    const phase = moon();
    root.querySelector('[data-sky-moon]').textContent = `${phase.icon} ${phase.name} · ${phase.lit}% lit · full moon ${phase.fullInDays === 0 ? 'tonight' : `in ${phase.fullInDays} days`}`;

    const url = document.querySelector('meta[name=weather-url]')?.content;
    const zone = root.dataset.skyZone;
    if (url && zone) {
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : {}))
            .then((data) => {
                const weather = data[zone];
                if (!weather) {
                    return;
                }
                const rise = minutesOf(weather.sunrise);
                const set = minutesOf(weather.sunset);
                const now = localMinutes(zone);
                const text = root.querySelector('[data-sky-sun-text]');
                const sun = root.querySelector('[data-sky-sun]');
                if (now >= rise && now <= set) {
                    const fraction = (now - rise) / (set - rise);
                    sun.setAttribute('cx', String(5 + 90 * fraction));
                    sun.setAttribute('cy', String(36 - 32 * Math.sin(Math.PI * fraction)));
                    sun.hidden = false;
                    text.textContent = `☀ ↑${weather.sunrise} ↓${weather.sunset} · ${Math.round((set - now) / 60 * 10) / 10} h of light left`;
                } else {
                    text.textContent = `🌙 ↑${weather.sunrise} ↓${weather.sunset} · the sun is down`;
                }
            })
            .catch(() => {});
    }
}
