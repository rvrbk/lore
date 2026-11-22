const POLL_MS = 5000;

const indicators = document.querySelectorAll('[data-deploy-indicator]');

const label = (active) => {
    const running = active.filter((deployment) => deployment.status === 'running');
    const names = (running.length ? running : active).map((deployment) => deployment.site);
    const shown = names.length > 2 ? `${names.length} sites` : names.join(', ');

    return `${running.length ? 'Deploying' : 'Deploy queued'}: ${shown}`;
};

const show = (active) => {
    indicators.forEach((indicator) => {
        indicator.hidden = active.length === 0;
        const text = indicator.querySelector('[data-deploy-text]');
        if (text && active.length) {
            text.textContent = label(active);
        }
    });
};

const poll = async () => {
    if (!document.hidden) {
        try {
            const response = await fetch(indicators[0].dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) {
                show((await response.json()).active);
            }
        } catch {
            // the site restarts while Lore deploys itself; keep the last state until it answers again
        }
    }

    setTimeout(poll, POLL_MS);
};

if (indicators.length) {
    setTimeout(poll, POLL_MS);
}
