import { trombone } from './fx.js';

const POLL_MS = 5000;

let lastFinished;

const indicators = document.querySelectorAll('[data-deploy-indicator]');

const label = (active) => {
    const running = active.filter((deployment) => deployment.status === 'running');
    const names = (running.length ? running : active).map((deployment) => deployment.site);
    const shown = names.length > 2 ? `${names.length} sites` : names.join(', ');

    return `${running.length ? 'Deploying' : 'Deploy queued'}: ${shown}`;
};

const MAX_BALLS = 3;

const renderBalls = (container, count) => {
    container.replaceChildren();
    for (let i = 0; i < Math.min(count, MAX_BALLS); i++) {
        const ball = document.createElement('span');
        ball.className = 'size-3 animate-pulse rounded-full bg-amber-400';
        container.append(ball);
    }
    if (count > MAX_BALLS) {
        const more = document.createElement('span');
        more.textContent = `+${count - MAX_BALLS}`;
        container.append(more);
    }
};

const baseTitle = document.title;
let dueReminders = Number(document.querySelector('meta[name="due-reminders"]')?.content) || 0;
let activeDeployments = [];

/** Put what needs attention in front of the tab title: due reminders and a running deploy. */
const updateTitle = () => {
    const running = activeDeployments.some((deployment) => deployment.status === 'running');
    const parts = [];
    if (dueReminders > 0) {
        parts.push(`(${dueReminders})`);
    }
    if (activeDeployments.length) {
        parts.push(running ? 'Deploying' : 'Deploy queued');
    }
    document.title = parts.length ? `${parts.join(' ')} · ${baseTitle}` : baseTitle;
};

const show = (active) => {
    activeDeployments = active;
    updateTitle();
    indicators.forEach((indicator) => {
        indicator.hidden = active.length === 0;
        const balls = indicator.querySelector('[data-deploy-balls]');
        if (balls) {
            renderBalls(balls, active.length);
        }
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
                const data = await response.json();
                dueReminders = data.reminders ?? dueReminders;
                show(data.active);
                const latest = data.latest?.id ?? null;
                if (lastFinished !== undefined && latest !== null && latest !== lastFinished && !data.latest.ok) {
                    trombone();
                }
                lastFinished = latest;
            }
        } catch {
            // the site restarts while Lore deploys itself; keep the last state until it answers again
        }
    }

    setTimeout(poll, POLL_MS);
};

if (indicators.length) {
    const initial = indicators[0].hidden ? [] : [{ status: indicators[0].textContent.includes('Deploying') ? 'running' : 'requested' }];
    activeDeployments = initial;
    updateTitle();
    setTimeout(poll, POLL_MS);
}
