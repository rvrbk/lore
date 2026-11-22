const POLL_MS = 30000;

const root = document.querySelector('[data-live-users]');

const part = (text, className) => {
    const span = document.createElement('span');
    span.className = className;
    span.textContent = text;

    return span;
};

let known = null;

const announceArrivals = (sites) => {
    const current = Object.fromEntries(sites.map((site) => [site.name, site.users]));
    if (known !== null) {
        sites.forEach((site) => {
            if (site.users > (known[site.name] ?? 0)) {
                window.dispatchEvent(new CustomEvent('lore:visitor', { detail: { name: site.name, users: site.users } }));
            }
        });
    }
    known = current;
};

const show = (data) => {
    root.replaceChildren();
    announceArrivals(data.sites ?? []);

    if (data.total === null) {
        return;
    }

    root.append(' · ', part(`● ${data.total.toLocaleString('en-US')} live now`, 'text-pink-400'));
    data.sites.forEach((site) => {
        root.append(' · ', part(`${site.name} ${site.users.toLocaleString('en-US')}`, 'text-zinc-300'));
    });
};

const poll = async () => {
    if (!root.isConnected) {
        return;
    }

    if (!document.hidden) {
        try {
            const response = await fetch(root.dataset.liveUsers, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) {
                show(await response.json());
            }
        } catch {
            // keep the last numbers until the next poll
        }
    }

    setTimeout(poll, POLL_MS);
};

if (root) {
    setTimeout(poll, POLL_MS);
}
