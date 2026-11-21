/*
 * Train the Pilot: the game rules and the three "brains" (hand-written rules,
 * nearest-neighbour examples, Q-learning). No DOM in here.
 */

export const DIRS = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] };
export const DIR_NAMES = Object.keys(DIRS);
export const MAX_STEPS = 40;

const cellKey = (x, y) => `${x},${y}`;

/**
 * Map legend: # rock, S start, X crystal, B beacon, . open space.
 */
export function parseMap(rows) {
    const map = { w: rows[0].length, h: rows.length, rocks: new Set(), start: null, crystal: null, beacon: null };
    rows.forEach((row, y) => {
        [...row].forEach((char, x) => {
            if (char === '#') {
                map.rocks.add(cellKey(x, y));
            } else if (char === 'S') {
                map.start = [x, y];
            } else if (char === 'X') {
                map.crystal = [x, y];
            } else if (char === 'B') {
                map.beacon = [x, y];
            }
        });
    });
    return map;
}

export const isRock = (map, x, y) => map.rocks.has(cellKey(x, y));
const isBlocked = (map, x, y) => x < 0 || y < 0 || x >= map.w || y >= map.h || isRock(map, x, y);
const same = (a, b) => a !== null && a[0] === b[0] && a[1] === b[1];

/**
 * What the pilot can see: which neighbours are blocked and where the crystal lies.
 */
export function sense(map, [x, y]) {
    const blocked = {};
    DIR_NAMES.forEach((dir) => {
        blocked[dir] = isBlocked(map, x + DIRS[dir][0], y + DIRS[dir][1]);
    });
    return { blocked, bearX: Math.sign(map.crystal[0] - x), bearY: Math.sign(map.crystal[1] - y) };
}

export function crystalIs(situation, dir) {
    return { right: situation.bearX > 0, left: situation.bearX < 0, up: situation.bearY < 0, down: situation.bearY > 0 }[dir];
}

/**
 * One move. Returns the new position and what happened: ok, goal or crash.
 */
export function move(map, pos, dir) {
    const next = [pos[0] + DIRS[dir][0], pos[1] + DIRS[dir][1]];
    if (isBlocked(map, next[0], next[1])) {
        return { pos, next, outcome: 'crash', cause: isRock(map, next[0], next[1]) ? 'rock' : 'edge' };
    }
    return { pos: next, next, outcome: same(map.crystal, next) ? 'goal' : 'ok', onBeacon: same(map.beacon, next) };
}

/**
 * Fly a pilot (a function from a situation to a decision) until it reaches the crystal, crashes, freezes or runs out of fuel.
 *
 * @return {{path: number[][], outcome: string, trace: object[], cause?: string}}
 */
export function runPilot(map, decide) {
    let pos = [...map.start];
    const path = [pos];
    const trace = [];

    for (let step = 0; step < MAX_STEPS; step++) {
        const decision = decide(sense(map, pos), pos);
        if (!decision) {
            return { path, trace, outcome: 'confused' };
        }
        trace.push(decision);
        const result = move(map, pos, decision.dir);
        if (result.outcome === 'crash') {
            return { path: [...path, result.next], trace, outcome: 'crash', cause: result.cause };
        }
        pos = result.pos;
        path.push(pos);
        if (result.outcome === 'goal') {
            return { path, trace, outcome: 'goal' };
        }
    }

    return { path, trace, outcome: 'fuel' };
}

/* Level 1: hand-written rules */

export function ruleMatches(rule, situation) {
    if (rule.type === 'blocked') {
        return situation.blocked[rule.dir];
    }
    if (rule.type === 'clear') {
        return !situation.blocked[rule.dir];
    }
    if (rule.type === 'crystal') {
        return crystalIs(situation, rule.dir);
    }
    return true;
}

export const decideByRules = (rules) => (situation) => {
    const index = rules.findIndex((rule) => ruleMatches(rule, situation));
    return index < 0 ? null : { dir: rules[index].go, why: `rule ${index + 1}` };
};

/* Level 2: learning from examples (nearest neighbour) */

const WALL_WEIGHT = 3;

const featuresOf = (situation) => [
    situation.blocked.up,
    situation.blocked.down,
    situation.blocked.left,
    situation.blocked.right,
    situation.bearX,
    situation.bearY,
];

export const makeExample = (situation, dir) => ({ features: featuresOf(situation), move: dir });

/**
 * Picks the move of the closest examples (most votes wins, ties go to the oldest example).
 */
export const decideByExamples = (examples) => (situation) => {
    if (examples.length === 0) {
        return null;
    }
    const features = featuresOf(situation);
    let best = Infinity;
    let closest = [];
    examples.forEach((example, index) => {
        const distance = example.features.reduce((total, value, i) => total + (i < 4 ? Number(value !== features[i]) * WALL_WEIGHT : Math.abs(value - features[i])), 0);
        if (distance < best) {
            best = distance;
            closest = [index];
        } else if (distance === best) {
            closest.push(index);
        }
    });

    const votes = {};
    closest.forEach((index) => {
        votes[examples[index].move] = (votes[examples[index].move] ?? 0) + 1;
    });
    const dir = Object.keys(votes).sort((a, b) => votes[b] - votes[a])[0];
    const example = closest.find((index) => examples[index].move === dir);

    return { dir, why: `example #${example + 1}`, example, distance: best };
};

/* Level 3: learning from rewards (Q-learning) */

const ALPHA = 0.5;
const GAMMA = 0.95;
const EXPLORE = 0.25;

export const newBrain = (map) => ({ q: new Float64Array(map.w * map.h * 4), episodes: 0 });

const slot = (map, [x, y]) => (y * map.w + x) * 4;

function bestAction(map, brain, pos, random) {
    const base = slot(map, pos);
    const values = DIR_NAMES.map((_, i) => brain.q[base + i]);
    const top = Math.max(...values);
    const options = values.flatMap((value, i) => (value === top ? [i] : []));
    return options[random === null ? 0 : Math.floor(random() * options.length)];
}

/**
 * Plays episodes and updates the brain. The pilot only ever sees the rewards, never the map's meaning.
 */
export function trainBrain(map, brain, rewards, episodes, random = Math.random) {
    for (let episode = 0; episode < episodes; episode++) {
        let pos = [...map.start];
        for (let step = 0; step < MAX_STEPS; step++) {
            const action = random() < EXPLORE ? Math.floor(random() * 4) : bestAction(map, brain, pos, random);
            const result = move(map, pos, DIR_NAMES[action]);
            let reward = rewards.step;
            if (result.outcome === 'crash') {
                reward += rewards.crash;
            } else if (result.outcome === 'goal') {
                reward += rewards.crystal;
            } else if (result.onBeacon) {
                reward += rewards.beacon;
            }

            const done = result.outcome !== 'ok';
            let future = 0;
            if (!done) {
                const nextBase = slot(map, result.pos);
                future = GAMMA * Math.max(...DIR_NAMES.map((_, i) => brain.q[nextBase + i]));
            }
            const index = slot(map, pos) + action;
            brain.q[index] += ALPHA * (reward + future - brain.q[index]);

            if (done) {
                break;
            }
            pos = result.pos;
        }
        brain.episodes++;
    }
}

export const decideByBrain = (map, brain) => (situation, pos) => ({
    dir: DIR_NAMES[bestAction(map, brain, pos, null)],
    why: 'highest expected reward',
});

/**
 * The direction the brain prefers in a cell, or null if it has never learned anything there.
 */
export function preferredDir(map, brain, pos) {
    const base = slot(map, pos);
    if (DIR_NAMES.every((_, i) => brain.q[base + i] === 0)) {
        return null;
    }
    return DIR_NAMES[bestAction(map, brain, pos, null)];
}

/* Levels */

export const LEVEL_ONE_MAP = parseMap([
    '....#....',
    '.S..#.X..',
    '....#....',
    '....#....',
    '....#....',
    '.........',
    '....#....',
]);

export const TRAINING_STARTS = [[1, 1], [0, 6], [5, 0], [0, 3], [4, 6], [10, 0]];

export const TRAINING_MAP = parseMap([
    '...........',
    '.S...#.....',
    '...#.......',
    '.....#..#..',
    '..#.....#X.',
    '...........',
    '...#.......',
]);

export const TEST_MAPS = [
    parseMap(['.........', '.........', '.S..#..X.', '....#....', '.........', '.......#.']),
    parseMap(['.......X.', '.........', '...#.....', '.S.......', '.....#...', '.........']),
    parseMap(['.........', '.....#...', '.S.#.....', '.......X.', '.#.......', '.........']),
];

export const REWARD_MAP = parseMap(['.......', '.S.B.#X', '...#...', '.......']);

export const DEFAULT_REWARDS = { crystal: 10, crash: -10, step: 0, beacon: 3 };
