/*
 * Crop Circle Lab: flatten the field into the target crop circle. Pressing a cell flips it and its four neighbours.
 */

import { LEVELS, hintFor, isSolved, parOf, parseGrid, press, startingGrid } from './crop-circle-logic.js';
import { UFO_SVG, button, el, verdict } from './game-kit.js';

const root = document.querySelector('[data-crop-circles]');
const STORAGE_KEY = 'lore.cropcircles.best';
const SEA = '#12062b';
const CROP = '#00b4cd';

const readBest = () => {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? {};
    } catch {
        return {};
    }
};

const saveBest = (index, moves) => {
    try {
        const best = readBest();
        if (best[index] === undefined || moves < best[index]) {
            best[index] = moves;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(best));
        }
    } catch {
        // progress just won't persist
    }
};

const tabs = root.querySelector('[data-crop-tabs]');
const stage = root.querySelector('[data-crop-stage]');

let current = 0;

const dot = (lit) => el('span', { class: 'block size-3/5 rounded-full transition-colors duration-150', style: `background: ${lit ? CROP : '#1d0d44'}; ${lit ? `box-shadow: 0 0 6px ${CROP}` : ''}` });

function miniGrid(grid) {
    return el(
        'div',
        { class: 'grid w-28 gap-px border border-zinc-700 bg-zinc-700', style: `grid-template-columns: repeat(${grid.length}, minmax(0, 1fr))`, role: 'img', 'aria-label': 'Target crop circle' },
        grid.flatMap((row) => row.map((lit) => el('span', { class: 'flex aspect-square items-center justify-center', style: `background: ${SEA}` }, dot(lit)))),
    );
}

function openLevel(index) {
    current = index;
    const level = LEVELS[index];
    const target = parseGrid(level.target);
    const size = target.length;
    const played = [];
    let grid = startingGrid(level);
    let hint = null;
    let solved = false;

    const field = el('div', { class: 'grid max-w-md gap-px border border-zinc-700 bg-zinc-700', style: `grid-template-columns: repeat(${size}, minmax(0, 1fr))`, role: 'group', 'aria-label': 'Crop field' });
    const counter = el('p', { class: 'text-sm text-zinc-300', 'aria-live': 'polite' });
    const result = el('div');
    const undoButton = button('Undo', () => {
        if (solved || played.length === 0) {
            return;
        }
        played.pop();
        grid = startingGrid(level);
        played.forEach(([x, y]) => (grid = press(grid, x, y)));
        hint = null;
        render();
    });

    const render = () => {
        const last = played.at(-1);
        field.replaceChildren(
            ...grid.flatMap((row, y) =>
                row.map((lit, x) => {
                    const cell = el('button', {
                        type: 'button',
                        class: `relative flex aspect-square items-center justify-center ${solved ? '' : 'hover:brightness-125'} focus-visible:outline-2 focus-visible:outline-indigo-300`,
                        style: `background: ${hint && hint[0] === x && hint[1] === y ? '#463a8a' : SEA}`,
                        'aria-label': `Row ${y + 1}, column ${x + 1}, ${lit ? 'crop standing' : 'flattened'}`,
                        onclick: () => play(x, y),
                    }, dot(lit));
                    if (last && last[0] === x && last[1] === y) {
                        const ufo = el('span', { class: 'pointer-events-none absolute inset-[6%]' });
                        ufo.innerHTML = UFO_SVG;
                        cell.append(ufo);
                    }
                    if (solved) {
                        cell.disabled = true;
                    }
                    return cell;
                }),
            ),
        );
        const par = parOf(level);
        const best = readBest()[index];
        counter.textContent = `Moves: ${played.length} · Par: ${par}${best === undefined ? '' : ` · Your best: ${best}`}`;
        undoButton.disabled = solved || played.length === 0;
    };

    const play = (x, y) => {
        if (solved) {
            return;
        }
        played.push([x, y]);
        grid = press(grid, x, y);
        hint = null;
        if (isSolved(grid, target)) {
            solved = true;
            saveBest(index, played.length);
            const par = parOf(level);
            result.replaceChildren(
                verdict(true, `Crop circle complete in ${played.length} moves.`, played.length <= par ? 'That is par or better. The aliens are impressed.' : `Par is ${par}. Can you do it in fewer?`),
                el('p', { class: 'mt-2 text-sm text-zinc-300' }, 'Tip: the order of your moves never matters, and pressing a cell twice cancels out. So each cell is either pressed once or not at all.'),
                el('div', { class: 'mt-3 flex gap-2' }, index + 1 < LEVELS.length ? button('Next level', () => openLevel(index + 1), true) : null, button('Play again', () => openLevel(index))),
            );
            refreshTabs();
        }
        render();
    };

    result.replaceChildren();
    render();
    stage.replaceChildren(
        el('p', { class: 'text-sm text-zinc-300' }, `${level.name}. Press a cell to flip it and the four cells next to it. Make the field match the target circle.`),
        el(
            'div',
            { class: 'mt-4 flex flex-col gap-6 sm:flex-row' },
            el('div', { class: 'w-full max-w-md' }, field, el('div', { class: 'mt-3 flex flex-wrap items-center gap-2' }, undoButton, button('Reset', () => openLevel(index)), button('Hint', () => {
                if (!solved) {
                    hint = hintFor(level, played);
                    render();
                }
            })), el('div', { class: 'mt-2' }, counter), result),
            el('div', {}, el('h2', { class: 'mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400' }, 'Target'), miniGrid(target)),
        ),
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
