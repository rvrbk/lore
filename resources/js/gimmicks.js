const root = document.documentElement;
const DEGREES = Math.PI / 180;

const sunElevation = (moment) => {
    const dayOfYear = (moment - Date.UTC(moment.getUTCFullYear(), 0, 0)) / 86400000;
    const declination = -23.44 * DEGREES * Math.cos(((2 * Math.PI) / 365) * (dayOfYear + 10));
    const hours = moment.getUTCHours() + moment.getUTCMinutes() / 60;
    const longitude = -moment.getTimezoneOffset() / 4;
    const latitude = 45 * DEGREES;
    const hourAngle = (hours * 15 + longitude - 180) * DEGREES;
    const sine = Math.sin(latitude) * Math.sin(declination) + Math.cos(latitude) * Math.cos(declination) * Math.cos(hourAngle);

    return Math.asin(sine) / DEGREES;
};

export const dayTint = (elevation) => {
    if (elevation >= 12) {
        return { phase: 'day', color: 'rgba(0, 0, 0, 0)' };
    }
    if (elevation >= -4) {
        const warmth = (12 - elevation) / 16;

        return { phase: 'twilight', color: `rgba(255, 140, 40, ${(0.07 * warmth).toFixed(3)})` };
    }
    const depth = Math.min(1, (-4 - elevation) / 14);

    return { phase: 'night', color: `rgba(20, 40, 140, ${(0.05 + 0.09 * depth).toFixed(3)})` };
};

const applyDaylight = () => {
    const tint = dayTint(sunElevation(new Date()));
    root.style.setProperty('--day-tint', tint.color);
    root.dataset.daylight = tint.phase;
};

applyDaylight();
setInterval(applyDaylight, 60000);

const KONAMI = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
const ARCADE_SECONDS = 30;
let progress = 0;
let arcadeTimer = null;

const isTyping = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable;

const startArcade = () => {
    clearTimeout(arcadeTimer);
    root.classList.add('arcade');
    window.dispatchEvent(new CustomEvent('lore:arcade', { detail: { on: true } }));

    const toast = document.createElement('div');
    toast.className = 'arcade-toast';
    toast.textContent = '+30 LIVES';
    toast.setAttribute('role', 'status');
    document.body.append(toast);
    setTimeout(() => toast.remove(), 2600);

    arcadeTimer = setTimeout(() => {
        root.classList.remove('arcade');
        window.dispatchEvent(new CustomEvent('lore:arcade', { detail: { on: false } }));
    }, ARCADE_SECONDS * 1000);
};

document.addEventListener('keydown', (event) => {
    if (isTyping() || event.ctrlKey || event.metaKey || event.altKey) {
        progress = 0;

        return;
    }
    const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    progress = key === KONAMI[progress] ? progress + 1 : key === KONAMI[0] ? 1 : 0;
    if (progress === KONAMI.length) {
        progress = 0;
        startArcade();
    }
});
