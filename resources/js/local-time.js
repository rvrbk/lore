/**
 * Times follow the visitor's own device: 12 or 24 hour notation comes from the browser/OS locale.
 */
export const uses12Hours = () => new Intl.DateTimeFormat(undefined, { hour: 'numeric' }).resolvedOptions().hour12 === true;

export const timeFormatter = (zone, seconds = false) => {
    const twelve = uses12Hours();

    return new Intl.DateTimeFormat(undefined, {
        timeZone: zone,
        hour: twelve ? 'numeric' : '2-digit',
        minute: '2-digit',
        ...(seconds ? { second: '2-digit' } : {}),
        hour12: twelve,
    });
};

document.querySelectorAll('[data-local-time]').forEach((element) => {
    try {
        element.textContent = timeFormatter(element.dataset.zone).format(new Date(element.dataset.localTime));
    } catch {
        // keep the server-rendered time
    }
});

document.querySelectorAll('[data-hour12-field]').forEach((field) => {
    field.value = uses12Hours() ? '1' : '0';
});
