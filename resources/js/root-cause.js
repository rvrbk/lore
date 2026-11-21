/*
 * Root Cause: find the one broken service behind a wall of alerts, with as few probes as possible.
 */

import { LEVELS, alertsOf, candidates, failingSet, parOf } from './root-cause-logic.js';
import { button, el, verdict } from './game-kit.js';

const root = document.querySelector('[data-root-cause]');
const STORAGE_KEY = 'lore.rootcause.best';
const WRONG_GUESS_COST = 2;
const SVG = 'http://www.w3.org/2000/svg';
const NODE_W = 24;
const NODE_H = 9;

const RED = '#ff2d55';
const GREEN = '#39ff14';
const GREY = '#6c7090';

const tabs = root.querySelector('[data-rc-tabs]');
const stage = root.querySelector('[data-rc-stage]');
let current = 0;

const readBest = () => {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? {};
    } catch {
        return {};
    }
};

const saveBest = (index, score) => {
    try {
        const best = readBest();
        if (best[index] === undefined || score < best[index]) {
            best[index] = score;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(best));
        }
    } catch {
        // progress just won't persist
    }
};

function svg(tag, attrs = {}, ...children) {
    const node = document.createElementNS(SVG, tag);
    Object.entries(attrs).forEach(([name, value]) => node.setAttribute(name, value));
    node.append(...children.filter((child) => child !== null));
    return node;
}

function openLevel(index) {
    current = index;
    const level = LEVELS[index];
    const alerts = alertsOf(level);
    const byId = Object.fromEntries(level.services.map((service) => [service.id, service]));
    const par = parOf(level);
    const probes = new Map();
    const ruledOut = new Set();
    let probeCount = 0;
    let wrong = 0;
    let selected = null;
    let solved = false;

    const graph = svg('svg', { viewBox: `0 0 100 ${Math.max(...level.services.map((s) => s.y)) + NODE_H + 8}`, class: 'w-full max-w-2xl border border-zinc-700', style: 'background: #12062b', role: 'group', 'aria-label': 'Service dependency graph' });
    const panel = el('div', { class: 'mt-3 min-h-24 border border-zinc-700 bg-zinc-900 p-3 text-sm' });
    const counter = el('p', { class: 'mt-3 text-sm text-zinc-300', 'aria-live': 'polite' });
    const result = el('div');

    const statusOf = (service) => {
        if (probes.has(service.id)) {
            return probes.get(service.id) ? 'probed-failing' : 'probed-ok';
        }
        return alerts[service.id];
    };

    const describe = (service) => {
        const status = statusOf(service);
        const monitor = service.monitored === false ? 'Not monitored, so there is no alert to go on.' : `Monitor says ${alerts[service.id] === 'red' ? 'failing' : 'healthy'}.${service.flaky ? ' This monitor is flaky: a red alert might be a false alarm, but green can be trusted.' : ''}`;
        const deps = service.deps.length > 0 ? `Depends on ${service.deps.join(', ')}.` : 'Depends on nothing.';
        const probed = status === 'probed-failing' ? ' Probed: really failing.' : status === 'probed-ok' ? ' Probed: really healthy.' : '';
        return `${monitor}${probed} ${deps}`;
    };

    const drawGraph = () => {
        graph.replaceChildren(
            svg('defs', {}, svg('marker', { id: `arrow-${index}`, viewBox: '0 0 6 6', refX: 5, refY: 3, markerWidth: 3, markerHeight: 3, orient: 'auto' }, svg('path', { d: 'M0 0L6 3L0 6z', fill: GREY }))),
            ...level.services.flatMap((service) =>
                service.deps.map((depId) => {
                    const dep = byId[depId];
                    const active = selected !== null && (selected === service.id || selected === depId);
                    return svg('line', {
                        x1: service.x,
                        y1: service.y + NODE_H / 2,
                        x2: dep.x,
                        y2: dep.y - NODE_H / 2 - 0.5,
                        stroke: active ? '#7ee3ee' : GREY,
                        'stroke-width': active ? 0.6 : 0.4,
                        'marker-end': `url(#arrow-${index})`,
                    });
                }),
            ),
            ...level.services.map((service) => {
                const status = statusOf(service);
                const colour = status === 'red' || status === 'probed-failing' ? RED : status === 'green' || status === 'probed-ok' ? GREEN : GREY;
                const filled = status.startsWith('probed');
                const label = `${service.id}${service.flaky ? ' ~' : ''}`;
                const mark = status === 'probed-failing' ? ' ✖' : status === 'probed-ok' ? ' ✔' : '';
                const node = svg(
                    'g',
                    { tabindex: 0, role: 'button', 'aria-label': `${service.id}: ${describe(service)}`, 'aria-pressed': String(selected === service.id), style: `cursor: pointer; opacity: ${ruledOut.has(service.id) ? 0.45 : 1}`, class: 'focus-visible:outline-none' },
                    svg('rect', {
                        x: service.x - NODE_W / 2,
                        y: service.y - NODE_H / 2,
                        width: NODE_W,
                        height: NODE_H,
                        fill: filled ? colour : '#1d0d44',
                        stroke: selected === service.id ? '#ffffff' : colour,
                        'stroke-width': selected === service.id ? 0.9 : 0.6,
                        'stroke-dasharray': status === 'unknown' ? '1.5 1' : '0',
                    }),
                    svg('text', { x: service.x, y: service.y + 1.1, 'text-anchor': 'middle', 'font-size': 3.2, fill: filled ? '#12062b' : '#ffffff', 'font-weight': filled ? 700 : 400 }, document.createTextNode(label + mark)),
                );
                const choose = () => {
                    selected = service.id;
                    render();
                };
                node.addEventListener('click', choose);
                node.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        choose();
                    }
                });
                return node;
            }),
        );
    };

    const probe = (service) => {
        probeCount++;
        probes.set(service.id, failingSet(level, level.root).has(service.id));
        render();
    };

    const declare = (service) => {
        if (service.id === level.root) {
            solved = true;
            const score = probeCount + wrong * WRONG_GUESS_COST;
            saveBest(index, score);
            const chain = level.services.filter((s) => s.id !== level.root && failingSet(level, level.root).has(s.id)).map((s) => s.id);
            result.replaceChildren(
                verdict(true, `Root cause found: ${service.id}.`, `${chain.length > 0 ? `Its failure cascaded to ${chain.join(', ')}. ` : ''}${score <= par ? 'Par or better, a clean investigation.' : `Par is ${par}. A smarter probe order would do it in fewer.`}`),
                el('div', { class: 'mt-3 flex gap-2' }, index + 1 < LEVELS.length ? button('Next incident', () => openLevel(index + 1), true) : null, button('Replay', () => openLevel(index))),
            );
            refreshTabs();
        } else {
            wrong++;
            ruledOut.add(service.id);
            probes.set(service.id, failingSet(level, level.root).has(service.id));
        }
        render();
    };

    const renderPanel = () => {
        panel.replaceChildren();
        if (solved) {
            panel.append(el('p', { class: 'text-zinc-300' }, 'Incident closed.'));
            return;
        }
        if (selected === null) {
            panel.append(el('p', { class: 'text-zinc-400' }, 'Select a service to inspect it.'));
            return;
        }
        const service = byId[selected];
        const alreadyProbed = probes.has(service.id);
        const suspects = candidates(level, probes).length;
        panel.append(
            el('p', { class: 'font-medium' }, service.id),
            el('p', { class: 'mt-1 text-zinc-300' }, describe(service)),
            wrongNote(service),
            el(
                'div',
                { class: 'mt-3 flex flex-wrap gap-2' },
                alreadyProbed ? null : button('Probe (costs 1)', () => probe(service)),
                ruledOut.has(service.id) ? null : button('This is the root cause', () => declare(service), true),
            ),
            suspects === 1 && !alreadyProbed ? el('p', { class: 'mt-2 text-xs text-zinc-500' }, 'Hint: the evidence already points to a single suspect.') : null,
        );
    };

    const wrongNote = (service) => (ruledOut.has(service.id) ? el('p', { class: 'mt-1 text-red-300' }, `Wrong guess (+${WRONG_GUESS_COST}). ${service.id} is ${probes.get(service.id) ? 'failing, but only because something it depends on is broken' : 'actually fine'}.`) : null);

    const render = () => {
        drawGraph();
        renderPanel();
        const score = probeCount + wrong * WRONG_GUESS_COST;
        const best = readBest()[index];
        counter.textContent = `Probes: ${probeCount}${wrong > 0 ? ` · Wrong guesses: ${wrong} (+${wrong * WRONG_GUESS_COST})` : ''} · Score: ${score} · Par: ${par}${best === undefined ? '' : ` · Your best: ${best}`}`;
    };

    result.replaceChildren();
    render();
    stage.replaceChildren(
        el('p', { class: 'text-sm text-zinc-300' }, `${level.name}. Red means the monitor is alarmed, green means healthy, dashed grey means nobody is watching it. ~ marks a flaky monitor.`),
        el('div', { class: 'mt-4' }, graph, counter, panel, result),
    );
    refreshTabs();
}

function refreshTabs() {
    const best = readBest();
    tabs.replaceChildren(
        ...LEVELS.map((level, index) =>
            el(
                'button',
                {
                    type: 'button',
                    title: level.name,
                    'aria-current': index === current ? 'true' : 'false',
                    class: `border px-3 py-1.5 text-sm ${index === current ? 'border-indigo-500 bg-zinc-800 font-medium' : 'border-zinc-700 hover:bg-zinc-800'}`,
                    onclick: () => openLevel(index),
                },
                String(index + 1),
                best[index] === undefined ? null : el('span', { class: 'ml-2 text-green-400', 'aria-label': 'completed' }, '✓'),
            ),
        ),
    );
}

openLevel(0);
