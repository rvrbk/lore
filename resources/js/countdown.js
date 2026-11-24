/**
 * Keeps "in 3h 12m" style labels current without a page reload.
 */
const label = (milliseconds) => {
    const parts = [];
    let seconds = Math.max(0, Math.floor(milliseconds / 1000));
    for (const [unit, size] of [['d', 86400], ['h', 3600], ['m', 60], ['s', 1]]) {
        const amount = Math.floor(seconds / size);
        seconds -= amount * size;
        if (amount > 0) {
            parts.push(amount + unit);
        }
    }

    return parts.slice(0, 2).join(' ') || '0s';
};

const tick = () => {
    const now = Date.now();
    document.querySelectorAll('[data-countdown-start]').forEach((element) => {
        const start = Number(element.dataset.countdownStart);
        const end = Number(element.dataset.countdownEnd);
        element.textContent = now >= end ? 'done' : now >= start ? 'now' : 'in ' + label(start - now);
    });
};

if (document.querySelector('[data-countdown-start]')) {
    tick();
    setInterval(tick, 15000);
}
