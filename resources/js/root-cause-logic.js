/*
 * Root Cause: an outage puzzle. Exactly one service is really broken and everything that depends on it fails too.
 * The alerts you see are only symptoms: some services are not monitored and some monitors cry wolf.
 * You may probe a service to learn its true state.
 */

/**
 * Every service is monitored unless `monitored: false`. A `flaky` monitor can raise a false alarm (`falseAlarm: true`),
 * but a green flaky monitor can be trusted.
 */
export const LEVELS = [
    {
        name: 'Checkout is down',
        root: 'db',
        services: [
            { id: 'web', x: 50, y: 10, deps: ['api', 'cdn'] },
            { id: 'api', x: 28, y: 38, deps: ['db', 'cache'] },
            { id: 'cdn', x: 76, y: 38, deps: [] },
            { id: 'db', x: 14, y: 66, deps: [] },
            { id: 'cache', x: 46, y: 66, deps: [] },
        ],
    },
    {
        name: 'The silent database',
        root: 'db',
        services: [
            { id: 'web', x: 50, y: 10, deps: ['api', 'cdn'] },
            { id: 'api', x: 28, y: 38, deps: ['db', 'cache'] },
            { id: 'cdn', x: 76, y: 38, deps: [] },
            { id: 'db', x: 14, y: 66, deps: [], monitored: false },
            { id: 'cache', x: 46, y: 66, deps: [] },
        ],
    },
    {
        name: 'Blind spots',
        root: 'queue',
        services: [
            { id: 'web', x: 50, y: 10, deps: ['shop', 'search'] },
            { id: 'shop', x: 24, y: 38, deps: ['queue', 'db'] },
            { id: 'search', x: 76, y: 38, deps: ['index'] },
            { id: 'queue', x: 10, y: 66, deps: [], monitored: false },
            { id: 'db', x: 40, y: 66, deps: [], monitored: false },
            { id: 'index', x: 76, y: 66, deps: [] },
        ],
    },
    {
        name: 'Crying wolf',
        root: 'queue',
        services: [
            { id: 'web', x: 50, y: 10, deps: ['api', 'billing'] },
            { id: 'api', x: 24, y: 36, deps: ['auth', 'db'], flaky: true, falseAlarm: true },
            { id: 'billing', x: 76, y: 36, deps: ['db', 'queue'] },
            { id: 'auth', x: 10, y: 64, deps: [] },
            { id: 'db', x: 38, y: 64, deps: [], monitored: false },
            { id: 'queue', x: 64, y: 64, deps: ['mail'], monitored: false },
            { id: 'mail', x: 90, y: 64, deps: [] },
        ],
    },
    {
        name: 'Rush hour',
        root: 'auth',
        services: [
            { id: 'web', x: 50, y: 8, deps: ['api', 'admin', 'cdn'] },
            { id: 'api', x: 22, y: 30, deps: ['auth', 'orders'] },
            { id: 'admin', x: 56, y: 30, deps: ['auth', 'reports'], flaky: true },
            { id: 'cdn', x: 90, y: 30, deps: [] },
            { id: 'auth', x: 12, y: 54, deps: ['users'], monitored: false },
            { id: 'orders', x: 38, y: 54, deps: ['db'], flaky: true, falseAlarm: true },
            { id: 'reports', x: 66, y: 54, deps: ['db'] },
            { id: 'users', x: 12, y: 76, deps: [], monitored: false },
            { id: 'db', x: 52, y: 76, deps: [] },
        ],
    },
    {
        name: 'Total meltdown',
        root: 'broker',
        services: [
            { id: 'web', x: 50, y: 8, deps: ['api', 'app', 'static'] },
            { id: 'api', x: 20, y: 28, deps: ['auth', 'jobs'], flaky: true, falseAlarm: true },
            { id: 'app', x: 55, y: 28, deps: ['jobs', 'search'] },
            { id: 'static', x: 90, y: 28, deps: [] },
            { id: 'auth', x: 8, y: 50, deps: ['users'] },
            { id: 'jobs', x: 36, y: 50, deps: ['broker'], monitored: false },
            { id: 'search', x: 68, y: 50, deps: ['index'], flaky: true, falseAlarm: true },
            { id: 'users', x: 8, y: 74, deps: [], monitored: false },
            { id: 'broker', x: 36, y: 74, deps: [], monitored: false },
            { id: 'index', x: 68, y: 74, deps: [] },
        ],
    },
];

/**
 * The services that fail when `rootId` is broken: the root itself and everything that depends on it, directly or not.
 */
export function failingSet(level, rootId) {
    const failing = new Set([rootId]);
    let grew = true;
    while (grew) {
        grew = false;
        level.services.forEach((service) => {
            if (!failing.has(service.id) && service.deps.some((dep) => failing.has(dep))) {
                failing.add(service.id);
                grew = true;
            }
        });
    }
    return failing;
}

/**
 * What the dashboard shows per service: 'red', 'green' or 'unknown' (not monitored).
 */
export function alertsOf(level) {
    const failing = failingSet(level, level.root);
    return Object.fromEntries(
        level.services.map((service) => {
            if (service.monitored === false) {
                return [service.id, 'unknown'];
            }
            return [service.id, failing.has(service.id) || service.falseAlarm ? 'red' : 'green'];
        }),
    );
}

/**
 * Every service that could be the root cause given the alerts and the probes made so far.
 *
 * @param {Map<string, boolean>} probes service id => really failing
 */
export function candidates(level, probes) {
    const alerts = alertsOf(level);
    return level.services
        .map((service) => service.id)
        .filter((candidate) => {
            const failing = failingSet(level, candidate);
            return level.services.every((service) => {
                if (probes.has(service.id)) {
                    return probes.get(service.id) === failing.has(service.id);
                }
                if (alerts[service.id] === 'unknown') {
                    return true;
                }
                if (service.flaky) {
                    return alerts[service.id] === 'red' || !failing.has(service.id);
                }
                return (alerts[service.id] === 'red') === failing.has(service.id);
            });
        });
}

/**
 * Par: the most probes an ideal investigator would ever need on this level.
 */
export function parOf(level) {
    const memo = new Map();
    const failingSets = new Map(level.services.map((service) => [service.id, failingSet(level, service.id)]));

    const worst = (options) => {
        if (options.length <= 1) {
            return 0;
        }
        const key = options.join('|');
        if (!memo.has(key)) {
            let best = Infinity;
            level.services.forEach((service) => {
                const failing = options.filter((option) => failingSets.get(option).has(service.id));
                if (failing.length === 0 || failing.length === options.length) {
                    return;
                }
                const passing = options.filter((option) => !failingSets.get(option).has(service.id));
                best = Math.min(best, 1 + Math.max(worst(failing), worst(passing)));
            });
            memo.set(key, best);
        }
        return memo.get(key);
    };

    return worst(candidates(level, new Map()));
}
