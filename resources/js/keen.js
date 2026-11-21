/*
 * Commander Lore: a small Keen-style platformer. Pure canvas, no assets.
 * Run, jump, pogo, raygun; collect candy, dodge the yorps, reach the exit.
 */

const T = 16;
const VW = 320;
const VH = 192;
const STEP = 1000 / 60;
const GRID_H = 14;

const RUN = 1.6;
const GRAVITY = 0.35;
const MAX_FALL = 7;
const JUMP = -6.2;
const POGO_LOW = -4.8;
const POGO_HIGH = -8.6;
const COYOTE_FRAMES = 5;
const BUFFER_FRAMES = 6;
const START_LIVES = 3;
const START_AMMO = 5;

const HIGH_SCORE_KEY = 'lore.keen.high';
const MUTE_KEY = 'lore.keen.mute';

const makeLevel = (name, width, build) => {
    const grid = Array.from({ length: GRID_H }, () => Array(width).fill('.'));
    const api = {
        set: (x, y, ch) => {
            if (grid[y] && x >= 0 && x < width) {
                grid[y][x] = ch;
            }
        },
        line: (x, y, length, ch) => {
            for (let i = 0; i < length; i++) {
                api.set(x + i, y, ch);
            }
        },
        col: (x, y, length, ch) => {
            for (let i = 0; i < length; i++) {
                api.set(x, y + i, ch);
            }
        },
        ground: (pits) => {
            for (let x = 0; x < width; x++) {
                if (!pits.some(([from, length]) => x >= from && x < from + length)) {
                    api.col(x, GRID_H - 2, 2, '#');
                }
            }
        },
    };
    build(api);

    return { name, width, grid };
};

const LEVELS = [
    makeLevel('Mars Landing', 64, (L) => {
        L.ground([[13, 2], [27, 3], [44, 2]]);
        L.set(1, 11, 'S');
        L.line(5, 11, 3, 'o');
        L.line(13, 9, 2, 'o');
        L.set(17, 11, 'Y');
        L.line(20, 9, 5, '=');
        L.line(21, 8, 3, 'o');
        L.set(25, 11, 'a');
        L.line(27, 9, 3, 'o');
        L.line(32, 7, 6, '=');
        L.line(33, 6, 4, 'o');
        L.line(35, 5, 2, 'g');
        L.set(38, 11, 'Y');
        L.line(41, 11, 2, '#');
        L.line(44, 9, 2, 'o');
        L.line(49, 11, 2, '^');
        L.set(54, 11, 'Y');
        L.line(56, 11, 3, 'o');
        L.set(61, 11, 'E');
    }),
    makeLevel('Crater Run', 90, (L) => {
        L.ground([[10, 2], [20, 3], [33, 2], [47, 3], [62, 2], [75, 3]]);
        L.set(1, 11, 'S');
        L.line(4, 11, 3, 'o');
        L.set(6, 11, 'Y');
        L.line(10, 9, 2, 'o');
        L.set(14, 11, 'a');
        L.set(15, 11, 'Y');
        L.line(20, 9, 3, 'o');
        L.line(24, 11, 2, 'o');
        L.set(26, 11, 'Y');
        L.line(28, 11, 2, '^');
        L.set(31, 11, 'a');
        L.line(33, 9, 2, 'o');
        L.col(36, 11, 1, '#');
        L.col(37, 10, 2, '#');
        L.col(38, 9, 3, '#');
        L.line(39, 9, 3, '#');
        L.line(38, 8, 4, 'o');
        L.set(40, 11, 'Y');
        L.line(43, 7, 5, '=');
        L.line(44, 6, 3, 'o');
        L.line(44, 5, 3, 'g');
        L.line(47, 9, 3, 'o');
        L.line(52, 11, 2, '^');
        L.set(55, 11, 'Y');
        L.line(56, 7, 5, '=');
        L.line(57, 6, 3, 'o');
        L.line(62, 9, 2, 'o');
        L.set(68, 11, 'Y');
        L.line(70, 11, 3, 'o');
        L.line(75, 9, 3, 'o');
        L.line(80, 11, 2, '^');
        L.set(83, 11, 'Y');
        L.set(87, 11, 'E');
    }),
    makeLevel('Vorticon Fortress', 100, (L) => {
        L.ground([[12, 2], [26, 3], [40, 2], [60, 11], [82, 3]]);
        L.set(1, 11, 'S');
        L.line(4, 11, 3, 'o');
        L.set(8, 11, 'Y');
        L.line(12, 9, 2, 'o');
        L.line(16, 11, 2, '^');
        L.set(19, 11, 'a');
        L.set(21, 11, 'Y');
        L.line(26, 9, 3, 'o');
        L.col(30, 10, 2, '#');
        L.col(31, 9, 3, '#');
        L.col(32, 8, 4, '#');
        L.line(33, 8, 4, '#');
        L.line(33, 7, 4, 'o');
        L.col(37, 10, 2, '#');
        L.set(38, 11, 'Y');
        L.line(40, 9, 2, 'o');
        L.line(44, 7, 5, '=');
        L.line(45, 6, 3, 'o');
        L.line(45, 5, 3, 'g');
        L.set(46, 11, 'Y');
        L.line(50, 11, 2, '^');
        L.set(54, 11, 'a');
        L.set(56, 11, 'Y');
        L.line(61, 10, 2, '=');
        L.line(65, 9, 2, '=');
        L.line(69, 10, 2, '=');
        L.line(61, 9, 2, 'o');
        L.line(65, 8, 2, 'o');
        L.line(69, 9, 2, 'o');
        L.line(63, 4, 4, '=');
        L.line(64, 3, 2, 'g');
        L.set(74, 11, 'Y');
        L.line(77, 11, 2, '^');
        L.set(80, 11, 'a');
        L.line(82, 9, 3, 'o');
        L.set(87, 11, 'Y');
        L.set(91, 11, 'Y');
        L.line(88, 11, 2, 'o');
        L.col(94, 10, 2, '#');
        L.set(97, 11, 'E');
    }),
];

const KEY_ACTIONS = {
    ArrowLeft: 'left',
    KeyA: 'left',
    ArrowRight: 'right',
    KeyD: 'right',
    ArrowUp: 'jump',
    KeyW: 'jump',
    KeyZ: 'jump',
    Space: 'jump',
    KeyX: 'pogo',
    ArrowDown: 'pogo',
    KeyS: 'pogo',
    KeyC: 'fire',
    ShiftLeft: 'fire',
    ShiftRight: 'fire',
    KeyP: 'pause',
    Escape: 'pause',
    KeyM: 'mute',
    Enter: 'start',
};

const root = document.querySelector('[data-keen]');
const canvas = root.querySelector('canvas');
const ctx = canvas.getContext('2d');
canvas.width = VW;
canvas.height = VH;
ctx.imageSmoothingEnabled = false;

const store = {
    get: (key, fallback) => {
        try {
            return localStorage.getItem(key) ?? fallback;
        } catch {
            return fallback;
        }
    },
    set: (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch {
            // storage unavailable; the game still plays
        }
    },
};

const down = new Set();
const taps = new Set();
let muted = store.get(MUTE_KEY, '0') === '1';
let audio = null;

const beep = (freq, duration = 0.08, type = 'square', volume = 0.04, slide = 0) => {
    if (muted) {
        return;
    }
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        const osc = audio.createOscillator();
        const gain = audio.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, audio.currentTime);
        if (slide) {
            osc.frequency.linearRampToValueAtTime(freq + slide, audio.currentTime + duration);
        }
        gain.gain.setValueAtTime(volume, audio.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + duration);
        osc.connect(gain).connect(audio.destination);
        osc.start();
        osc.stop(audio.currentTime + duration);
    } catch {
        // audio unavailable
    }
};

const sfx = {
    jump: () => beep(300, 0.12, 'square', 0.03, 260),
    pogo: () => beep(180, 0.08, 'triangle', 0.05, 120),
    candy: () => beep(880, 0.08, 'square', 0.03, 440),
    gem: () => beep(660, 0.2, 'triangle', 0.05, 660),
    ammo: () => beep(520, 0.1, 'sawtooth', 0.03, 200),
    fire: () => beep(900, 0.1, 'sawtooth', 0.03, -700),
    hit: () => beep(240, 0.12, 'square', 0.04, -150),
    die: () => beep(400, 0.5, 'sawtooth', 0.05, -360),
    win: () => beep(500, 0.4, 'triangle', 0.06, 700),
};

const game = {
    state: 'title',
    levelIndex: 0,
    score: 0,
    lives: START_LIVES,
    ammo: START_AMMO,
    high: Number(store.get(HIGH_SCORE_KEY, '0')) || 0,
    levelStartScore: 0,
    levelStartAmmo: START_AMMO,
    timer: 0,
    tick: 0,
    level: null,
    player: null,
    enemies: [],
    bullets: [],
    items: new Map(),
    particles: [],
    cam: { x: 0, y: 0 },
    pausedFrom: null,
};

const tileAt = (x, y) => {
    const { grid, width } = game.level;
    if (x < 0 || x >= width || y < 0) {
        return '#';
    }
    if (y >= GRID_H) {
        return '.';
    }

    return grid[y][x];
};

const loadLevel = (index) => {
    const def = LEVELS[index];
    game.levelIndex = index;
    game.level = { name: def.name, width: def.width, grid: def.grid.map((row) => [...row]) };
    game.enemies = [];
    game.bullets = [];
    game.particles = [];
    game.items = new Map();
    game.score = game.levelStartScore;
    game.ammo = game.levelStartAmmo;

    for (let y = 0; y < GRID_H; y++) {
        for (let x = 0; x < def.width; x++) {
            const ch = game.level.grid[y][x];
            if (ch === 'S') {
                game.player = {
                    x: x * T + 3,
                    y: y * T + 1,
                    w: 10,
                    h: 15,
                    vx: 0,
                    vy: 0,
                    facing: 1,
                    onGround: false,
                    coyote: 0,
                    buffer: 0,
                    pogo: false,
                    step: 0,
                    cooldown: 0,
                    dead: false,
                };
            } else if (ch === 'Y') {
                game.enemies.push({ x: x * T + 1, y: y * T + 2, w: 14, h: 14, vx: -0.5, vy: 0, alive: true });
            } else if ('oga'.includes(ch)) {
                game.items.set(`${x},${y}`, ch);
            }
            if ('SYoga'.includes(ch)) {
                game.level.grid[y][x] = '.';
            }
        }
    }
    snapCamera();
};

const startRun = () => {
    game.score = 0;
    game.lives = START_LIVES;
    game.levelStartScore = 0;
    game.levelStartAmmo = START_AMMO;
    loadLevel(0);
    game.state = 'play';
};

const moveX = (e, dx) => {
    const dir = Math.sign(dx);
    e.x += dx;
    if (!dir) {
        return false;
    }
    const tx = Math.floor((dir > 0 ? e.x + e.w : e.x) / T);
    for (let ty = Math.floor(e.y / T); ty <= Math.floor((e.y + e.h - 0.01) / T); ty++) {
        if (tileAt(tx, ty) === '#') {
            e.x = dir > 0 ? tx * T - e.w : (tx + 1) * T;

            return true;
        }
    }

    return false;
};

const moveY = (e, dy) => {
    const dir = Math.sign(dy);
    const previousBottom = e.y + e.h;
    e.y += dy;
    if (!dir) {
        return false;
    }
    const ty = Math.floor((dir > 0 ? e.y + e.h : e.y) / T);
    for (let tx = Math.floor(e.x / T); tx <= Math.floor((e.x + e.w - 0.01) / T); tx++) {
        const tile = tileAt(tx, ty);
        const solid = tile === '#' || (tile === '=' && dir > 0 && previousBottom <= ty * T + 0.01);
        if (solid) {
            e.y = dir > 0 ? ty * T - e.h : (ty + 1) * T;

            return true;
        }
    }

    return false;
};

const overlaps = (a, b) => a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;

const burst = (x, y, color, count = 8) => {
    for (let i = 0; i < count; i++) {
        const angle = (Math.PI * 2 * i) / count;
        game.particles.push({ x, y, vx: Math.cos(angle) * 1.6, vy: Math.sin(angle) * 1.6 - 0.6, life: 22, color });
    }
};

const addScore = (points) => {
    game.score += points;
};

const killPlayer = () => {
    const p = game.player;
    if (p.dead) {
        return;
    }
    p.dead = true;
    p.vy = -5;
    p.vx = 0;
    game.state = 'dying';
    game.timer = 90;
    sfx.die();
};

const finishRun = (won) => {
    if (game.score > game.high) {
        game.high = game.score;
        store.set(HIGH_SCORE_KEY, String(game.high));
    }
    game.state = won ? 'won' : 'over';
    game.timer = 0;
};

const updatePlayer = () => {
    const p = game.player;
    const dirInput = (down.has('right') ? 1 : 0) - (down.has('left') ? 1 : 0);
    const jumpHeld = down.has('jump');

    if (taps.has('pogo')) {
        p.pogo = !p.pogo;
        sfx.pogo();
    }
    if (taps.has('jump')) {
        p.buffer = BUFFER_FRAMES;
    }
    p.buffer = Math.max(0, p.buffer - 1);
    p.cooldown = Math.max(0, p.cooldown - 1);

    if (dirInput) {
        p.facing = dirInput;
    }
    p.vx = dirInput * (p.pogo ? RUN * 0.85 : RUN);

    if (p.onGround) {
        p.coyote = COYOTE_FRAMES;
    } else {
        p.coyote = Math.max(0, p.coyote - 1);
    }

    if (p.pogo && p.onGround) {
        p.vy = jumpHeld ? POGO_HIGH : POGO_LOW;
        p.onGround = false;
        sfx.pogo();
    } else if (p.buffer > 0 && p.coyote > 0 && !p.pogo) {
        p.vy = JUMP;
        p.buffer = 0;
        p.coyote = 0;
        p.onGround = false;
        sfx.jump();
    }

    if (!p.pogo && !jumpHeld && p.vy < -2.5) {
        p.vy = -2.5;
    }

    if (taps.has('fire') && p.cooldown === 0) {
        if (game.ammo > 0) {
            game.ammo--;
            p.cooldown = 14;
            game.bullets.push({ x: p.x + (p.facing > 0 ? p.w : -5), y: p.y + 6, w: 5, h: 3, vx: p.facing * 4.5, life: 80 });
            sfx.fire();
        } else {
            beep(120, 0.06, 'square', 0.03);
            p.cooldown = 14;
        }
    }

    p.vy = Math.min(p.vy + GRAVITY, MAX_FALL);

    moveX(p, p.vx);
    const collided = moveY(p, p.vy);
    p.onGround = collided && p.vy > 0;
    if (collided) {
        p.vy = 0;
    }

    if (p.onGround && p.vx !== 0) {
        p.step += 0.18;
    }

    const cx = Math.floor((p.x + p.w / 2) / T);
    const cy = Math.floor((p.y + p.h / 2) / T);
    for (const [tx, ty] of [[cx, cy], [cx, Math.floor((p.y + p.h - 1) / T)]]) {
        const key = `${tx},${ty}`;
        const item = game.items.get(key);
        if (item) {
            game.items.delete(key);
            const cxp = tx * T + 8;
            const cyp = ty * T + 8;
            if (item === 'o') {
                addScore(100);
                sfx.candy();
                burst(cxp, cyp, '#ff7ac8', 5);
            } else if (item === 'g') {
                addScore(500);
                sfx.gem();
                burst(cxp, cyp, '#00e5ff', 10);
            } else {
                game.ammo += 5;
                sfx.ammo();
                burst(cxp, cyp, '#ffe600', 6);
            }
        }
    }

    const footTile = tileAt(cx, Math.floor((p.y + p.h - 2) / T));
    const bodyTile = tileAt(cx, cy);
    if (footTile === '^' || bodyTile === '^') {
        const spikeBox = { x: cx * T + 2, y: (footTile === '^' ? Math.floor((p.y + p.h - 2) / T) : cy) * T + 8, w: 12, h: 8 };
        if (overlaps(p, spikeBox)) {
            killPlayer();

            return;
        }
    }

    if (bodyTile === 'E' || footTile === 'E') {
        completeLevel();

        return;
    }

    if (p.y > GRID_H * T + 24) {
        killPlayer();
    }
};

const completeLevel = () => {
    addScore(1000);
    sfx.win();
    game.state = 'complete';
    game.timer = 120;
};

const updateEnemies = () => {
    const p = game.player;
    for (const enemy of game.enemies) {
        if (!enemy.alive) {
            continue;
        }
        enemy.vy = Math.min(enemy.vy + GRAVITY, MAX_FALL);
        const blocked = moveX(enemy, enemy.vx);
        const grounded = moveY(enemy, enemy.vy);
        if (grounded) {
            enemy.vy = 0;
            const frontX = Math.floor((enemy.vx > 0 ? enemy.x + enemy.w + 1 : enemy.x - 1) / T);
            const below = Math.floor((enemy.y + enemy.h + 1) / T);
            const ledge = tileAt(frontX, below) === '.' || tileAt(frontX, below) === '^';
            if (blocked || ledge) {
                enemy.vx = -enemy.vx;
            }
        } else if (blocked) {
            enemy.vx = -enemy.vx;
        }
        if (enemy.y > GRID_H * T + 40) {
            enemy.alive = false;
        }
        if (!p.dead && overlaps({ x: p.x + 1, y: p.y + 1, w: p.w - 2, h: p.h - 2 }, enemy)) {
            killPlayer();
        }
    }
};

const updateBullets = () => {
    for (const bullet of game.bullets) {
        bullet.x += bullet.vx;
        bullet.life--;
        if (tileAt(Math.floor((bullet.vx > 0 ? bullet.x + bullet.w : bullet.x) / T), Math.floor(bullet.y / T)) === '#') {
            bullet.life = 0;
            burst(bullet.x, bullet.y, '#ffe600', 4);
        }
        for (const enemy of game.enemies) {
            if (enemy.alive && bullet.life > 0 && overlaps(bullet, enemy)) {
                enemy.alive = false;
                bullet.life = 0;
                addScore(200);
                sfx.hit();
                burst(enemy.x + 7, enemy.y + 7, '#39ff14', 10);
            }
        }
    }
    game.bullets = game.bullets.filter((bullet) => bullet.life > 0);
};

const updateParticles = () => {
    for (const particle of game.particles) {
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.vy += 0.08;
        particle.life--;
    }
    game.particles = game.particles.filter((particle) => particle.life > 0);
};

const snapCamera = () => {
    const p = game.player;
    const maxX = game.level.width * T - VW;
    const maxY = GRID_H * T - VH;
    game.cam.x = Math.round(Math.max(0, Math.min(maxX, p.x + p.w / 2 - VW / 2)));
    game.cam.y = Math.round(Math.max(0, Math.min(maxY, p.y + p.h / 2 - VH / 2)));
};

const followCamera = () => {
    const p = game.player;
    const maxX = game.level.width * T - VW;
    const maxY = GRID_H * T - VH;
    const targetX = Math.max(0, Math.min(maxX, p.x + p.w / 2 - VW / 2 + p.facing * 20));
    const targetY = Math.max(0, Math.min(maxY, p.y + p.h / 2 - VH / 2));
    game.cam.x += (targetX - game.cam.x) * 0.12;
    game.cam.y += (targetY - game.cam.y) * 0.12;
};

const handleMeta = () => {
    if (taps.has('mute')) {
        muted = !muted;
        store.set(MUTE_KEY, muted ? '1' : '0');
    }
    if (taps.has('pause')) {
        if (game.state === 'play') {
            game.pausedFrom = game.state;
            game.state = 'paused';
        } else if (game.state === 'paused') {
            game.state = game.pausedFrom ?? 'play';
        }
    }
};

const update = () => {
    game.tick++;
    handleMeta();

    switch (game.state) {
        case 'title':
        case 'over':
        case 'won':
            if (taps.has('start') || taps.has('jump') || taps.has('fire')) {
                startRun();
            }
            break;
        case 'play':
            updatePlayer();
            if (game.state === 'play') {
                updateEnemies();
                updateBullets();
            }
            updateParticles();
            followCamera();
            break;
        case 'dying': {
            const p = game.player;
            p.vy = Math.min(p.vy + GRAVITY, MAX_FALL);
            p.y += p.vy;
            updateParticles();
            if (--game.timer <= 0) {
                game.lives--;
                if (game.lives <= 0) {
                    finishRun(false);
                } else {
                    loadLevel(game.levelIndex);
                    game.state = 'play';
                }
            }
            break;
        }
        case 'complete':
            updateParticles();
            if (--game.timer <= 0) {
                if (game.levelIndex + 1 >= LEVELS.length) {
                    finishRun(true);
                } else {
                    game.levelStartScore = game.score;
                    game.levelStartAmmo = game.ammo;
                    loadLevel(game.levelIndex + 1);
                    game.state = 'play';
                }
            }
            break;
        default:
            break;
    }

    taps.clear();
};

const rect = (x, y, w, h, color) => {
    ctx.fillStyle = color;
    ctx.fillRect(Math.round(x), Math.round(y), w, h);
};

const PALETTE = {
    W: '#ffffff',
    C: '#00e5ff',
    G: '#39ff14',
    R: '#ff2d55',
    Y: '#ffe600',
    O: '#ff8a00',
    S: '#c4c8e0',
    D: '#6c7090',
    P: '#ff7ac8',
    V: '#9b5de5',
};

const SEA = '#12062b';
const LAND = '#00b4cd';

const bitmap = (rows, x, y, scale = 1, mirror = false) => {
    rows.forEach((row, ry) => {
        for (let rx = 0; rx < row.length; rx++) {
            const color = PALETTE[row[mirror ? row.length - 1 - rx : rx]];
            if (color) {
                rect(x + rx * scale, y + ry * scale, scale, scale, color);
            }
        }
    });
};

const drawBackground = () => {
    ctx.fillStyle = SEA;
    ctx.fillRect(0, 0, VW, VH);
};

const drawTile = (tx, ty, ch) => {
    const x = tx * T;
    const y = ty * T;
    if (ch === '#') {
        rect(x, y, T, T, LAND);
        if (tileAt(tx, ty - 1) !== '#') {
            rect(x, y, T, 2, PALETTE.C);
        }
    } else if (ch === '=') {
        rect(x, y, T, 4, PALETTE.S);
    } else if (ch === '^') {
        ctx.fillStyle = PALETTE.R;
        for (let i = 0; i < 2; i++) {
            ctx.beginPath();
            ctx.moveTo(x + i * 8, y + T);
            ctx.lineTo(x + i * 8 + 4, y + 8);
            ctx.lineTo(x + i * 8 + 8, y + T);
            ctx.fill();
        }
    } else if (ch === 'E') {
        rect(x, y - 16, T, 32, PALETTE.Y);
        rect(x + 3, y - 12, 10, 28, SEA);
        rect(x + 10, y + 2, 2, 2, PALETTE.Y);
    }
};

const GEM = ['..Y..', '.YYY.', 'YYYYY', '.YYY.', '..Y..'];

const drawItem = (tx, ty, kind) => {
    const bob = game.tick % 60 < 30 ? 0 : 1;
    const x = tx * T;
    const y = ty * T + bob;
    if (kind === 'o') {
        rect(x + 4, y + 4, 8, 8, PALETTE.P);
        rect(x + 6, y + 6, 2, 2, PALETTE.W);
    } else if (kind === 'g') {
        bitmap(GEM, x + 3, y + 3, 2);
    } else {
        rect(x + 3, y + 5, 10, 7, PALETTE.O);
        rect(x + 5, y + 7, 6, 2, PALETTE.Y);
    }
};

const YORP = ['..VVV..', '.VVVVV.', 'VVWWWVV', 'VVWWDVV', 'VVVVVVV', 'VVVVVVV'];
const YORP_FEET = ['VV...VV', '.V.V.V.'];

const drawEnemy = (enemy) => {
    const rows = [...YORP, YORP_FEET[Math.floor(game.tick / 10) % 2]];
    if (enemy.vx < 0) {
        rows[3] = 'VVDWWVV';
    }
    bitmap(rows, enemy.x, enemy.y, 2);
};

const SPRITE_TOP = [
    '..YYYYYY..',
    '.YYYYYYYY.',
    '.YYWWWDWY.',
    '..YWWWWW..',
    '..GGGGGG..',
    '.GGGGGGGG.',
    '.GGGGGGGG.',
    '..GGGGGG..',
    '..SSSSSS..',
];

const SPRITE_LEGS = {
    idle: ['..SS..SS..', '..SS..SS..', '..SS..SS..', '..SS..SS..', '.RRR..RRR.', '.RRR..RRR.'],
    runA: ['..SS..SS..', '.SS....SS.', 'SS......SS', 'SS......SS', 'RR......RR', 'RR......RR'],
    runB: ['..SSSSSS..', '...SSSS...', '...SSSS...', '...SSSS...', '..RRRRRR..', '..RRRRRR..'],
    jump: ['.SS....SS.', 'SS......SS', 'SS......SS', 'RR......RR', 'RR......RR', '..........'],
    pogo: ['...SSSS...', '...RRRR...', '....DD....', '....DD....', '....DD....', '...DDDD...'],
};

const drawPlayer = () => {
    const p = game.player;
    let name = 'idle';
    if (p.dead || !p.onGround) {
        name = p.pogo && !p.dead ? 'pogo' : 'jump';
    } else if (p.pogo) {
        name = 'pogo';
    } else if (p.vx !== 0) {
        name = Math.floor(p.step) % 2 ? 'runA' : 'runB';
    }

    bitmap([...SPRITE_TOP, ...SPRITE_LEGS[name]], Math.round(p.x), Math.round(p.y), 1, p.facing < 0);

    if (p.cooldown > 8) {
        const gunX = Math.round(p.x) + (p.facing > 0 ? 9 : -4);
        rect(gunX, Math.round(p.y) + 6, 5, 2, PALETTE.D);
        rect(gunX + (p.facing > 0 ? 4 : 0), Math.round(p.y) + 6, 1, 2, PALETTE.R);
    }
};

const drawWorld = () => {
    ctx.save();
    ctx.translate(-Math.round(game.cam.x), -Math.round(game.cam.y));
    const startX = Math.max(0, Math.floor(game.cam.x / T) - 1);
    const endX = Math.min(game.level.width, Math.ceil((game.cam.x + VW) / T) + 1);
    const startY = Math.max(0, Math.floor(game.cam.y / T) - 1);
    const endY = Math.min(GRID_H, Math.ceil((game.cam.y + VH) / T) + 1);

    for (let y = startY; y < endY; y++) {
        for (let x = startX; x < endX; x++) {
            const ch = game.level.grid[y][x];
            if (ch !== '.') {
                drawTile(x, y, ch);
            }
            const item = game.items.get(`${x},${y}`);
            if (item) {
                drawItem(x, y, item);
            }
        }
    }
    for (const enemy of game.enemies) {
        if (enemy.alive) {
            drawEnemy(enemy);
        }
    }
    for (const bullet of game.bullets) {
        rect(bullet.x, bullet.y, bullet.w, bullet.h, '#ff2d55');
    }
    if (game.state !== 'complete') {
        drawPlayer();
    }
    for (const particle of game.particles) {
        rect(particle.x, particle.y, 2, 2, particle.color);
    }
    ctx.restore();
};

const text = (value, x, y, { size = 8, color = '#ffffff', align = 'left' } = {}) => {
    ctx.font = `${size}px "Press Start 2P", monospace`;
    ctx.textAlign = align;
    ctx.textBaseline = 'top';
    ctx.fillStyle = '#000000';
    ctx.fillText(value, x + 1, y + 1);
    ctx.fillStyle = color;
    ctx.fillText(value, x, y);
};

const drawHud = () => {
    rect(0, 0, VW, 13, '#2a0a3d');
    text(`SCORE ${String(game.score).padStart(6, '0')}`, 4, 3, { size: 7, color: '#ffe600' });
    text(`AMMO ${game.ammo}`, 138, 3, { size: 7, color: '#ff7ac8' });
    text(`LIVES ${game.lives}`, 216, 3, { size: 7, color: '#39ff14' });
    text(`L${game.levelIndex + 1}`, 300, 3, { size: 7, color: '#00e5ff' });
    if (game.player?.pogo) {
        text('POGO', 4, 16, { size: 6, color: '#aab4c4' });
    }
};

const panel = (lines) => {
    rect(0, 0, VW, VH, 'rgba(8, 4, 20, 0.78)');
    lines.forEach(([value, y, options]) => text(value, VW / 2, y, { align: 'center', ...options }));
};

const render = () => {
    if (game.state === 'title') {
        drawBackground();
        panel([
            ['COMMANDER', 40, { size: 20, color: '#ffe600' }],
            ['LORE', 66, { size: 20, color: '#ff7ac8' }],
            ['Collect the candy. Dodge the yorps.', 106, { size: 7, color: '#c4c8e0' }],
            ['Reach the exit of every world.', 118, { size: 7, color: '#c4c8e0' }],
            [game.tick % 60 < 40 ? 'PRESS ENTER OR TAP TO START' : '', 148, { size: 8, color: '#ffffff' }],
            [game.high ? `HIGH SCORE ${String(game.high).padStart(6, '0')}` : '', 170, { size: 7, color: '#39ff14' }],
        ]);

        return;
    }

    drawBackground();
    drawWorld();
    drawHud();

    if (game.state === 'paused') {
        panel([
            ['PAUSED', 80, { size: 16, color: '#ffe600' }],
            ['P or Esc to resume', 112, { size: 7, color: '#c4c8e0' }],
        ]);
    } else if (game.state === 'complete') {
        panel([
            [game.level.name.toUpperCase(), 66, { size: 10, color: '#39ff14' }],
            ['CLEARED  +1000', 90, { size: 10, color: '#ffe600' }],
        ]);
    } else if (game.state === 'over') {
        panel([
            ['GAME OVER', 56, { size: 16, color: '#ff2d55' }],
            [`SCORE ${String(game.score).padStart(6, '0')}`, 92, { size: 9, color: '#ffe600' }],
            [`HIGH  ${String(game.high).padStart(6, '0')}`, 108, { size: 9, color: '#39ff14' }],
            ['ENTER OR TAP TO TRY AGAIN', 140, { size: 7, color: '#ffffff' }],
        ]);
    } else if (game.state === 'won') {
        panel([
            ['YOU WIN!', 48, { size: 16, color: '#ffe600' }],
            ['Mars is safe again.', 78, { size: 8, color: '#c4c8e0' }],
            [`SCORE ${String(game.score).padStart(6, '0')}`, 100, { size: 9, color: '#ff7ac8' }],
            [`HIGH  ${String(game.high).padStart(6, '0')}`, 116, { size: 9, color: '#39ff14' }],
            ['ENTER OR TAP TO PLAY AGAIN', 146, { size: 7, color: '#ffffff' }],
        ]);
    }
};

const setKey = (action, isDown) => {
    if (isDown) {
        if (!down.has(action)) {
            taps.add(action);
        }
        down.add(action);
    } else {
        down.delete(action);
    }
};

const isTyping = (target) => target instanceof HTMLElement && target.closest('input, textarea, select, [contenteditable]');

window.addEventListener('keydown', (event) => {
    const action = KEY_ACTIONS[event.code];
    if (!action || event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
        return;
    }
    event.preventDefault();
    if (!event.repeat) {
        setKey(action, true);
    }
});

window.addEventListener('keyup', (event) => {
    const action = KEY_ACTIONS[event.code];
    if (action) {
        setKey(action, false);
    }
});

window.addEventListener('blur', () => {
    down.clear();
    if (game.state === 'play') {
        game.pausedFrom = 'play';
        game.state = 'paused';
    }
});

root.querySelectorAll('[data-keen-key]').forEach((button) => {
    const action = button.dataset.keenKey;
    button.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        button.setPointerCapture?.(event.pointerId);
        setKey(action, true);
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((name) => button.addEventListener(name, () => setKey(action, false)));
    button.addEventListener('contextmenu', (event) => event.preventDefault());
});

canvas.addEventListener('pointerdown', () => {
    if (['title', 'over', 'won'].includes(game.state)) {
        setKey('start', true);
        setKey('start', false);
    } else if (game.state === 'paused') {
        setKey('pause', true);
        setKey('pause', false);
    }
});

let last = performance.now();
let accumulator = 0;

const frame = (now) => {
    accumulator += Math.min(now - last, 100);
    last = now;
    while (accumulator >= STEP) {
        update();
        accumulator -= STEP;
    }
    render();
    requestAnimationFrame(frame);
};

loadLevel(0);
requestAnimationFrame(frame);
