/*
 * Crop Circle Lab: a Lights-Out style puzzle. Pressing a cell flips it and its four neighbours.
 * Every level is built by scrambling its target with a known list of presses, so it is always solvable.
 */

const PRESS_SHAPE = [[0, 0], [1, 0], [-1, 0], [0, 1], [0, -1]];

export const parseGrid = (rows) => rows.map((row) => [...row].map((symbol) => symbol === '#'));

export function press(grid, x, y) {
    const next = grid.map((row) => [...row]);
    PRESS_SHAPE.forEach(([dx, dy]) => {
        if (next[y + dy]?.[x + dx] !== undefined) {
            next[y + dy][x + dx] = !next[y + dy][x + dx];
        }
    });
    return next;
}

export const isSolved = (grid, target) => grid.every((row, y) => row.every((cell, x) => cell === target[y][x]));

/**
 * The cells whose press parity is odd: pressing a cell twice cancels out and the order never matters.
 */
export function oddPresses(presses) {
    const counts = new Map();
    presses.forEach(([x, y]) => counts.set(`${x},${y}`, !counts.get(`${x},${y}`)));
    return [...counts].filter(([, odd]) => odd).map(([key]) => key.split(',').map(Number));
}

export const startingGrid = (level) => level.presses.reduce((grid, [x, y]) => press(grid, x, y), parseGrid(level.target));

export const parOf = (level) => oddPresses(level.presses).length;

/**
 * A press that is still needed to finish the level, or null when nothing is left.
 */
export function hintFor(level, played) {
    const needed = new Map(oddPresses(level.presses).map(([x, y]) => [`${x},${y}`, [x, y]]));
    oddPresses(played).forEach(([x, y]) => (needed.has(`${x},${y}`) ? needed.delete(`${x},${y}`) : needed.set(`${x},${y}`, [x, y])));
    return needed.values().next().value ?? null;
}

export const LEVELS = [
    { name: 'First circle', target: ['.#.', '###', '.#.'], presses: [[0, 0], [2, 1]] },
    { name: 'The ring', target: ['###', '#.#', '###'], presses: [[1, 1], [0, 2], [2, 0]] },
    { name: 'Diamond', target: ['.##.', '#..#', '#..#', '.##.'], presses: [[0, 0], [3, 1], [2, 2]] },
    { name: 'Wide circle', target: ['.###.', '#...#', '#...#', '#...#', '.###.'], presses: [[2, 2], [0, 4], [4, 1], [1, 3]] },
    { name: 'Crossed fields', target: ['#...#', '.#.#.', '..#..', '.#.#.', '#...#'], presses: [[0, 0], [4, 2], [2, 3], [1, 1], [3, 4]] },
    { name: 'Rising peak', target: ['..#..', '.###.', '#####', '.....', '.....'], presses: [[1, 4], [3, 2], [4, 0], [0, 3], [2, 1]] },
    { name: 'Hollow ring', target: ['.####.', '#....#', '#....#', '#....#', '#....#', '.####.'], presses: [[0, 0], [5, 3], [2, 2], [4, 5], [1, 4], [3, 1]] },
    { name: 'The great circle', target: ['..###..', '.#...#.', '#..#..#', '#.###.#', '#..#..#', '.#...#.', '..###..'], presses: [[3, 3], [0, 6], [6, 1], [2, 4], [5, 5], [1, 2], [4, 0]] },
];
