/*
 * Train the Pilot: three small levels that teach how a UFO pilot gets its brain.
 * 1. hand-written rules, 2. learning from examples, 3. learning from rewards.
 */

import {
    DEFAULT_REWARDS,
    DIR_NAMES,
    LEVEL_ONE_MAP,
    REWARD_MAP,
    TEST_MAPS,
    TRAINING_MAP,
    TRAINING_STARTS,
    decideByBrain,
    decideByExamples,
    decideByRules,
    isRock,
    makeExample,
    move,
    newBrain,
    preferredDir,
    runPilot,
    sense,
    trainBrain,
} from './pilot-logic.js';

const root = document.querySelector('[data-pilot]');
const DONE_KEY = 'lore.pilot.done';
const STEP_MS = 140;
const ARROWS = { up: '↑', down: '↓', left: '←', right: '→' };
const KEY_DIRS = { ArrowUp: 'up', KeyW: 'up', ArrowDown: 'down', KeyS: 'down', ArrowLeft: 'left', KeyA: 'left', ArrowRight: 'right', KeyD: 'right' };

const UFO_SVG =
    '<svg viewBox="0 0 16 12" class="size-full" aria-hidden="true"><ellipse cx="8" cy="8" rx="7.5" ry="3" fill="#c4c8e0"/><path d="M4 7a4 4 0 0 1 8 0z" fill="#00e5ff"/><circle cx="4" cy="8.2" r="0.8" fill="#ff2d55"/><circle cx="8" cy="9" r="0.8" fill="#ffe600"/><circle cx="12" cy="8.2" r="0.8" fill="#39ff14"/></svg>';

function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([name, value]) => {
        if (name === 'class') {
            node.className = value;
        } else if (name.startsWith('on')) {
            node.addEventListener(name.slice(2), value);
        } else {
            node.setAttribute(name, value);
        }
    });
    node.append(...children.flat().filter((child) => child !== null && child !== false));
    return node;
}

const button = (label, onclick, primary = false) =>
    el(
        'button',
        {
            type: 'button',
            onclick,
            class: primary ? 'border border-indigo-500 bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500' : 'border border-zinc-600 bg-zinc-800 px-3 py-1.5 text-sm hover:bg-zinc-700',
        },
        label,
    );

const selectOf = (options, value, onchange) =>
    el(
        'select',
        { class: 'border border-zinc-600 bg-zinc-900 px-2 py-1.5 text-sm', onchange: (event) => onchange(event.target.value) },
        options.map(([optionValue, label]) => {
            const option = el('option', { value: optionValue }, label);
            option.selected = optionValue === value;
            return option;
        }),
    );

/* Board */

function createBoard(map, label) {
    const grid = el('div', { class: 'grid gap-px border border-zinc-700 bg-zinc-700', style: `grid-template-columns: repeat(${map.w}, minmax(0, 1fr))`, role: 'img', 'aria-label': label });
    const cells = [];
    for (let y = 0; y < map.h; y++) {
        for (let x = 0; x < map.w; x++) {
            const cell = el('div', { class: 'relative flex aspect-square items-center justify-center text-lg leading-none' });
            cells.push(cell);
            grid.append(cell);
        }
    }

    const draw = ({ pos = map.start, trail = [], crashAt = null, arrows = null } = {}) => {
        const visited = new Set(trail.map(([x, y]) => `${x},${y}`));
        cells.forEach((cell, index) => {
            const x = index % map.w;
            const y = Math.floor(index / map.w);
            const here = pos[0] === x && pos[1] === y;
            const isCrash = crashAt !== null && crashAt[0] === x && crashAt[1] === y;
            const isCrystal = map.crystal[0] === x && map.crystal[1] === y;
            const isBeacon = map.beacon !== null && map.beacon[0] === x && map.beacon[1] === y;
            const arrow = arrows ? preferredDir(map, arrows, [x, y]) : null;

            cell.style.background = isRock(map, x, y) ? '#6c7090' : isCrash ? '#8a1230' : visited.has(`${x},${y}`) ? '#2b1668' : '#12062b';
            cell.innerHTML = '';
            if (isCrystal) {
                cell.append(el('span', { class: 'text-cyan-300', 'aria-label': 'crystal' }, '◆'));
            } else if (isBeacon) {
                cell.append(el('span', { class: 'text-yellow-300', 'aria-label': 'beacon' }, '✦'));
            } else if (arrow !== null && !here) {
                cell.append(el('span', { class: 'text-xs text-zinc-500' }, ARROWS[arrow]));
            }
            if (here) {
                const ufo = el('span', { class: 'absolute inset-[12%]' });
                ufo.innerHTML = UFO_SVG;
                cell.append(ufo);
            }
        });
    };

    draw();
    return { el: grid, draw };
}

/* Playback */

let timer = null;
let detach = () => {};

function stopPlayback() {
    clearInterval(timer);
    timer = null;
}

/**
 * Animates one or more runs side by side, then calls onDone.
 */
function play(flights, onDone) {
    stopPlayback();
    let tick = 0;
    const show = () => {
        flights.forEach(({ board, run }) => {
            const index = Math.min(tick, run.path.length - 1);
            const crashed = run.outcome === 'crash' && index === run.path.length - 1;
            board.draw({ pos: crashed ? run.path[index - 1] : run.path[index], trail: run.path.slice(0, index + 1), crashAt: crashed ? run.path[index] : null });
        });
    };
    show();
    timer = setInterval(() => {
        tick++;
        show();
        if (flights.every(({ run }) => tick >= run.path.length - 1)) {
            stopPlayback();
            onDone();
        }
    }, STEP_MS);
}

/* Progress */

const readDone = () => {
    try {
        return JSON.parse(localStorage.getItem(DONE_KEY)) ?? [];
    } catch {
        return [];
    }
};

const markDone = (level) => {
    try {
        localStorage.setItem(DONE_KEY, JSON.stringify([...new Set([...readDone(), level])]));
    } catch {
        // progress just won't persist
    }
};

const verdict = (ok, title, text) =>
    el(
        'div',
        { class: `mt-3 border p-3 text-sm ${ok ? 'border-green-500 bg-green-950/40' : 'border-red-500 bg-red-950/40'}`, role: 'status' },
        el('p', { class: 'font-medium' }, title),
        el('p', { class: 'mt-1 text-zinc-300' }, text),
    );

const lesson = (text) => el('p', { class: 'mt-2 border-l-2 border-cyan-300 pl-3 text-zinc-300' }, el('span', { class: 'font-medium text-cyan-300' }, 'What you learned: '), text);

const place = ([x, y]) => `(${x + 1}, ${y + 1})`;
const crashText = (run) => `crashed into ${run.cause === 'edge' ? 'the edge of space' : 'a rock'} at ${place(run.path.at(-1))}`;

/* Level 1: rules */

function levelRules(stage) {
    let rules = [{ type: 'always', dir: 'right', go: 'right' }];
    const draft = { type: 'blocked', dir: 'right', go: 'down' };
    const board = createBoard(LEVEL_ONE_MAP, 'Level 1 map');
    const rulesList = el('ol', { class: 'space-y-1' });
    const result = el('div');
    const ruleLabel = (rule) => {
        const condition = { always: 'always', blocked: `the way ${rule.dir} is blocked`, clear: `the way ${rule.dir} is clear`, crystal: `the crystal is ${rule.dir}` }[rule.type];
        return `If ${condition}, fly ${rule.go}`;
    };

    const renderRules = () => {
        rulesList.replaceChildren(
            ...rules.map((rule, index) =>
                el(
                    'li',
                    { class: 'flex items-center gap-2 border border-zinc-700 bg-zinc-900 px-2 py-1 text-sm' },
                    el('span', { class: 'w-5 text-zinc-500' }, `${index + 1}.`),
                    el('span', { class: 'flex-1' }, ruleLabel(rule)),
                    button('↑', () => swap(index, index - 1)),
                    button('↓', () => swap(index, index + 1)),
                    button('✕', () => {
                        rules.splice(index, 1);
                        renderRules();
                    }),
                ),
            ),
        );
        if (rules.length === 0) {
            rulesList.append(el('li', { class: 'text-sm text-zinc-500' }, 'No rules yet. The pilot will freeze.'));
        }
    };

    const swap = (from, to) => {
        if (to < 0 || to >= rules.length) {
            return;
        }
        [rules[from], rules[to]] = [rules[to], rules[from]];
        renderRules();
    };

    const launch = () => {
        result.replaceChildren();
        const run = runPilot(LEVEL_ONE_MAP, decideByRules(rules));
        play([{ board, run }], () => {
            const last = run.trace.at(-1);
            if (run.outcome === 'goal') {
                markDone(1);
                refreshTabs();
                result.append(
                    verdict(true, `Crystal reached in ${run.path.length - 1} moves.`, 'Your rules worked.'),
                    lesson('Rules are predictable and you can read exactly why the pilot did something. But you had to think of every situation yourself, and the pilot is only as good as your imagination. Real problems have far too many situations to write down.'),
                );
            } else if (run.outcome === 'crash') {
                result.append(verdict(false, `The pilot ${crashText(run)}.`, `It was following ${last.why} (fly ${last.dir}). Which situation did you forget? Rules are checked from the top and the first match wins, so the order matters.`));
            } else if (run.outcome === 'confused') {
                result.append(verdict(false, `No rule matched at ${place(run.path.at(-1))}, so the pilot froze.`, 'A pilot with rules has no idea what to do in a situation you did not cover. Add a rule that handles it.'));
            } else {
                result.append(verdict(false, 'The pilot ran out of fuel, going in circles.', 'Rules that only react to what is right next to the UFO can loop forever. Rethink what it should do when it is stuck behind a wall.'));
            }
        });
    };

    const addRule = el('div', { class: 'mt-3 flex flex-wrap items-center gap-2' });
    const renderAdd = () => {
        const needsDir = draft.type !== 'always';
        const dirOptions = DIR_NAMES.map((dir) => [dir, dir]);
        addRule.replaceChildren(
            el('span', { class: 'text-sm' }, 'If'),
            selectOf(
                [
                    ['blocked', 'the way is blocked'],
                    ['clear', 'the way is clear'],
                    ['crystal', 'the crystal is'],
                    ['always', 'always'],
                ],
                draft.type,
                (value) => {
                    draft.type = value;
                    renderAdd();
                },
            ),
            needsDir ? selectOf(dirOptions, draft.dir, (value) => (draft.dir = value)) : null,
            el('span', { class: 'text-sm' }, ', fly'),
            selectOf(dirOptions, draft.go, (value) => (draft.go = value)),
            button('Add rule', () => {
                rules.push({ ...draft });
                renderRules();
            }),
        );
    };

    renderRules();
    renderAdd();

    stage.append(
        el('p', { class: 'text-sm text-zinc-300' }, 'The pilot can only sense which neighbouring cells are blocked and roughly where the crystal is. Write it a list of rules. For every move it picks the first rule that matches. Get it to the crystal.'),
        el(
            'div',
            { class: 'mt-4 grid gap-6 lg:grid-cols-[minmax(0,26rem)_1fr]' },
            board.el,
            el('div', {}, el('h2', { class: 'text-sm font-semibold uppercase tracking-wide text-zinc-400' }, 'Rules'), rulesList, addRule, el('div', { class: 'mt-4 flex gap-2' }, button('Launch pilot', launch, true), button('Reset rules', () => {
                rules = [];
                renderRules();
            })), result),
        ),
    );
}

/* Level 2: examples */

const describeExample = ({ features, move: dir }) => {
    const walls = DIR_NAMES.filter((_, i) => features[i]).map((name) => ARROWS[name]).join(' ');
    const bearing = ['', '←', '→'][features[4] === -1 ? 1 : features[4] === 1 ? 2 : 0] + ['', '↑', '↓'][features[5] === -1 ? 1 : features[5] === 1 ? 2 : 0];
    return `walls: ${walls || 'none'} · crystal: ${bearing || 'here'} → fly ${ARROWS[dir]}`;
};

function levelExamples(stage) {
    const examples = [];
    let startIndex = 0;
    let pos = [...TRAINING_STARTS[0]];
    let locked = false;
    const training = () => ({ ...TRAINING_MAP, start: TRAINING_STARTS[startIndex] });
    const board = createBoard(training(), 'Training map');
    const tests = TEST_MAPS.map((map, index) => ({ map, board: createBoard(map, `Test map ${'ABC'[index]}`) }));
    const counter = el('span', { class: 'text-sm text-zinc-400' });
    const list = el('ol', { class: 'mt-2 max-h-48 space-y-px overflow-y-auto border border-zinc-700 bg-zinc-900 p-2 text-xs text-zinc-400' });
    const note = el('p', { class: 'mt-2 min-h-5 text-sm text-zinc-300' });
    const result = el('div');
    let trail = [pos];

    const redraw = () => board.draw({ pos, trail });
    const refresh = () => {
        counter.textContent = `${examples.length} example${examples.length === 1 ? '' : 's'} taught`;
        list.replaceChildren(...examples.map((example, index) => el('li', {}, `#${index + 1} ${describeExample(example)}`)));
        list.scrollTop = list.scrollHeight;
    };
    const reset = (nextStart) => {
        startIndex = nextStart ? (startIndex + 1) % TRAINING_STARTS.length : startIndex;
        pos = [...TRAINING_STARTS[startIndex]];
        trail = [pos];
        board.draw({ pos, trail });
        locked = false;
    };

    const fly = (dir) => {
        if (locked) {
            return;
        }
        const map = training();
        examples.push(makeExample(sense(map, pos), dir));
        const step = move(map, pos, dir);
        if (step.outcome === 'crash') {
            locked = true;
            board.draw({ pos, trail, crashAt: step.next });
            note.textContent = 'Crash! That bad move is now in the pilot\'s memory too. Undo it, or the pilot will copy it.';
            setTimeout(() => reset(false), 700);
        } else if (step.outcome === 'goal') {
            locked = true;
            pos = step.pos;
            trail.push(pos);
            redraw();
            note.textContent = 'Crystal reached! Starting again from a new spot. More variety makes a better teacher.';
            setTimeout(() => reset(true), 900);
        } else {
            pos = step.pos;
            trail.push(pos);
            note.textContent = '';
            redraw();
        }
        refresh();
    };

    const onKey = (event) => {
        if (KEY_DIRS[event.code] && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            fly(KEY_DIRS[event.code]);
        }
    };
    document.addEventListener('keydown', onKey);
    detach = () => document.removeEventListener('keydown', onKey);

    const sendPilot = () => {
        result.replaceChildren();
        const decide = decideByExamples(examples);
        const flights = tests.map(({ map, board: testBoard }) => ({ board: testBoard, run: runPilot(map, decide) }));
        play(flights, () => {
            const reached = flights.filter(({ run }) => run.outcome === 'goal').length;
            if (reached === flights.length) {
                markDone(2);
                refreshTabs();
                result.append(
                    verdict(true, 'The pilot made it on all three maps it has never seen.', `It learned from ${examples.length} examples, without anyone writing a rule.`),
                    lesson('This is supervised learning. The pilot copies the closest thing it has seen before (a nearest neighbour model), so it can handle new situations. Its quality depends entirely on your examples: enough of them, varied, and without mistakes. Garbage in, garbage out.'),
                );
                return;
            }
            const failed = flights.map(({ run }, index) => ({ run, name: 'ABC'[index] })).filter(({ run }) => run.outcome !== 'goal');
            const first = failed[0];
            const last = first.run.trace.at(-1);
            const detail = first.run.outcome === 'confused' ? 'It has no examples to learn from yet.' : `It was following ${last.why}${last.distance > 0 ? ', which only looked similar to the situation it was in' : ''}. ${first.run.outcome === 'crash' ? `It ${crashText(first.run)}.` : 'It ran out of fuel, going in circles.'}`;
            result.append(verdict(false, `${reached} of 3 test maps cleared. Test ${first.name} failed.`, `${detail} Teach it more situations: fly from other starting spots, and undo your mistakes.`));
        });
    };

    const pad = el(
        'div',
        { class: 'mt-3 grid w-40 grid-cols-3 gap-1 lg:hidden', style: 'touch-action: manipulation' },
        ...[null, 'up', null, 'left', 'down', 'right'].map((dir) => (dir ? el('button', { type: 'button', class: 'h-12 border border-zinc-600 bg-zinc-800 text-lg active:bg-zinc-600', 'aria-label': dir, onclick: () => fly(dir) }, ARROWS[dir]) : el('span'))),
    );

    refresh();
    stage.append(
        el('p', { class: 'text-sm text-zinc-300' }, 'Now there are no rules. Teach by example: fly the training map yourself (arrow keys or WASD). Every move you make is saved as an example of "in this situation, do this". Then send the pilot to three maps it has never seen.'),
        el(
            'div',
            { class: 'mt-4 grid gap-6 lg:grid-cols-[minmax(0,26rem)_1fr]' },
            el('div', {}, el('h2', { class: 'mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400' }, 'Training map'), board.el, pad, note),
            el(
                'div',
                {},
                el('div', { class: 'flex flex-wrap items-center gap-2' }, counter, button('Undo last', () => {
                    examples.pop();
                    refresh();
                }), button('Clear all', () => {
                    examples.length = 0;
                    refresh();
                })),
                list,
                el('div', { class: 'mt-4' }, button('Send the pilot to the test maps', sendPilot, true)),
                el('div', { class: 'mt-4 grid grid-cols-3 gap-3' }, tests.map(({ board: testBoard }, index) => el('figure', {}, testBoard.el, el('figcaption', { class: 'mt-1 text-center text-xs text-zinc-400' }, `Test ${'ABC'[index]}`)))),
                result,
            ),
        ),
    );
}

/* Level 3: rewards */

function levelRewards(stage) {
    const rewards = { ...DEFAULT_REWARDS };
    let brain = newBrain(REWARD_MAP);
    const board = createBoard(REWARD_MAP, 'Reward map');
    const result = el('div');
    const status = el('p', { class: 'text-sm text-zinc-400' });

    const redraw = () => {
        board.draw({ arrows: brain.episodes > 0 ? brain : null });
        status.textContent = `The pilot has practised ${brain.episodes} flights. The arrows show where it thinks it should fly from each cell.`;
    };

    const slider = (name, label, min, max, hint) => {
        const value = el('output', { class: 'w-10 text-right tabular-nums' }, String(rewards[name]));
        const input = el('input', { type: 'range', min, max, step: 1, value: rewards[name], class: 'w-full accent-indigo-500', 'aria-label': label });
        input.addEventListener('input', () => {
            rewards[name] = Number(input.value);
            value.textContent = input.value;
        });
        return el('label', { class: 'block text-sm' }, el('span', { class: 'flex justify-between gap-2' }, el('span', {}, label), value), input, el('span', { class: 'text-xs text-zinc-500' }, hint));
    };

    const train = () => {
        result.replaceChildren();
        trainBrain(REWARD_MAP, brain, rewards, 200);
        redraw();
    };

    const testFlight = () => {
        result.replaceChildren();
        const run = runPilot(REWARD_MAP, decideByBrain(REWARD_MAP, brain));
        const beaconHits = run.path.filter(([x, y]) => x === REWARD_MAP.beacon[0] && y === REWARD_MAP.beacon[1]).length;
        board.draw({ pos: REWARD_MAP.start, arrows: null });
        play([{ board, run }], () => {
            if (run.outcome === 'goal') {
                markDone(3);
                refreshTabs();
                result.append(
                    verdict(true, `Crystal reached in ${run.path.length - 1} moves.`, `It collected the beacon ${beaconHits} time${beaconHits === 1 ? '' : 's'} on the way.`),
                    lesson('This is reinforcement learning. Nobody told the pilot how to fly, only what is worth points. It does whatever scores highest, which is not always what you meant. Designing the reward is half the work, and a sloppy reward gets exploited.'),
                );
            } else if (run.outcome === 'fuel' && beaconHits >= 3) {
                result.append(verdict(false, `The pilot circled the beacon ${beaconHits} times and never touched the crystal.`, 'That is reward hacking. Farming the beacon over and over scores more than finishing the job. Make the beacon less rewarding, or make wasting time cost something.'));
            } else if (run.outcome === 'crash') {
                result.append(verdict(false, `The pilot ${crashText(run)}.`, brain.episodes === 0 ? 'It has not practised yet. Press "Practise 200 flights" first.' : 'Either it has not practised enough, or crashing does not cost enough for it to care.'));
            } else {
                result.append(verdict(false, 'The pilot ran out of fuel without reaching the crystal.', 'Practise some more, or change what the rewards encourage.'));
            }
        });
    };

    redraw();
    stage.append(
        el('p', { class: 'text-sm text-zinc-300' }, 'The pilot now learns by trial and error. You cannot give it rules or examples, only rewards: points for what it does. It flies hundreds of practice flights and works out what scores best. Pick the rewards so it ends up at the crystal.'),
        el(
            'div',
            { class: 'mt-4 grid gap-6 lg:grid-cols-[minmax(0,26rem)_1fr]' },
            el('div', {}, board.el, status),
            el(
                'div',
                { class: 'space-y-3' },
                el('h2', { class: 'text-sm font-semibold uppercase tracking-wide text-zinc-400' }, 'Rewards'),
                slider('crystal', 'Reach the crystal ◆', 0, 100, 'once, then the flight ends'),
                slider('crash', 'Crash', -100, 0, 'rock or edge, flight ends'),
                slider('step', 'Every move', -5, 0, 'a cost for wasting fuel'),
                slider('beacon', 'Touch the beacon ✦', -10, 10, 'every single time, it never runs out'),
                el('div', { class: 'flex flex-wrap gap-2' }, button('Practise 200 flights', train, true), button('Test flight', testFlight), button('Forget everything', () => {
                    brain = newBrain(REWARD_MAP);
                    result.replaceChildren();
                    redraw();
                })),
                result,
            ),
        ),
    );
}

/* Shell */

const LEVELS = [
    ['1. Rules', levelRules],
    ['2. Examples', levelExamples],
    ['3. Rewards', levelRewards],
];

const tabs = root.querySelector('[data-pilot-tabs]');
const stage = root.querySelector('[data-pilot-stage]');
let current = 0;

function refreshTabs() {
    const done = readDone();
    tabs.replaceChildren(
        ...LEVELS.map(([label], index) =>
            el(
                'button',
                {
                    type: 'button',
                    'aria-current': index === current ? 'true' : 'false',
                    class: `border px-3 py-1.5 text-sm ${index === current ? 'border-indigo-500 bg-zinc-800 font-medium' : 'border-zinc-700 hover:bg-zinc-800'}`,
                    onclick: () => openLevel(index),
                },
                label,
                done.includes(index + 1) ? el('span', { class: 'ml-2 text-green-400', 'aria-label': 'completed' }, '✓') : null,
            ),
        ),
    );
}

function openLevel(index) {
    stopPlayback();
    detach();
    detach = () => {};
    current = index;
    stage.replaceChildren();
    LEVELS[index][1](stage);
    refreshTabs();
}

openLevel(0);
