/*
 * UFO Invaders: Space Invaders, but the invaders are the UFOs from the world map.
 * Flat neon colours and tiny bitmaps, like the map. Pure canvas, no assets.
 */

const VW = 240;
const VH = 280;
const STEP = 1000 / 60;
const SCALE = 2;

const SEA = '#12062b';
const LAND = '#00b4cd';

const PALETTE = {
    W: '#ffffff',
    C: '#00e5ff',
    G: '#39ff14',
    R: '#ff2d55',
    Y: '#ffe600',
    O: '#ff8a00',
    B: '#b5651d',
    S: '#c4c8e0',
    D: '#6c7090',
    P: '#ff7ac8',
    V: '#9b5de5',
    L: '#ff2d55',
    M: '#ffe600',
};

const UFO = ['..SLS..', '.MDDDM.', 'SDDSDDS', 'LDSWSDL', 'SDDSDDS', '.MDDDM.', '..SLS..'];
const UNICORN = ['.......Y.', '..L...YW.', '.LMLWWWWW', 'LMBWWWWW.', '.BBWWWWW.', '.W.W.W.W.', '.W.W.W.W.'];
const SHIP = ['...C...', '...W...', '..WWW..', '.WWWWW.', 'WWWWWWW', 'L.....M'];

const UFO_KINDS = [
    { colors: { S: '#ff2d55', D: '#8a1230' }, points: 30 },
    { colors: {}, points: 20 },
    { colors: { S: '#9b5de5', D: '#4a2a7a' }, points: 20 },
    { colors: { S: '#39ff14', D: '#1a7a0a' }, points: 10 },
    { colors: { S: '#ff8a00', D: '#8a4a00' }, points: 10 },
];

const COLUMNS = 8;
const GAP_X = 22;
const GAP_Y = 18;
const UFO_SIZE = 7 * SCALE;
const EDGE = 8;

const SHIP_W = 7 * SCALE;
const SHIP_H = 6 * SCALE;
const SHIP_Y = VH - 34;
const GROUND_Y = VH - 16;
const BUNKER_Y = VH - 78;

const BUNKER_SHAPE = ['..#######..', '.#########.', '###########', '###########', '###########', '####...####', '###.....###', '###.....###'];
const BUNKER_CELL = 2;
const BUNKER_COUNT = 4;
const BUNKER_W = 11 * BUNKER_CELL;
const BUNKER_GAP = 38;

const START_LIVES = 3;
const HIGH_SCORE_KEY = 'lore.invaders.high';
const MUTE_KEY = 'lore.keen.mute';

const KEY_ACTIONS = {
    ArrowLeft: 'left',
    KeyA: 'left',
    ArrowRight: 'right',
    KeyD: 'right',
    ArrowUp: 'fire',
    KeyW: 'fire',
    KeyZ: 'fire',
    Space: 'fire',
    KeyP: 'pause',
    Escape: 'pause',
    KeyM: 'mute',
    Enter: 'start',
};

const root = document.querySelector('[data-invaders]');
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
            osc.frequency.linearRampToValueAtTime(Math.max(30, freq + slide), audio.currentTime + duration);
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
    fire: () => beep(880, 0.1, 'square', 0.03, -600),
    boom: () => beep(200, 0.18, 'sawtooth', 0.05, -150),
    bolt: () => beep(300, 0.12, 'sawtooth', 0.02, -200),
    march: (alt) => beep(alt ? 90 : 70, 0.07, 'square', 0.05),
    hit: () => beep(160, 0.5, 'sawtooth', 0.06, -140),
    bonus: () => beep(520, 0.3, 'triangle', 0.05, 520),
    level: () => beep(400, 0.4, 'triangle', 0.05, 500),
};

const game = {
    state: 'title',
    score: 0,
    high: Number(store.get(HIGH_SCORE_KEY, '0')) || 0,
    lives: START_LIVES,
    level: 1,
    tick: 0,
    timer: 0,
    ship: { x: VW / 2 - SHIP_W / 2, invulnerable: 0 },
    bullet: null,
    fireCooldown: 0,
    ufos: [],
    formation: { x: 0, y: 0, dir: 1, timer: 0, alt: false },
    bolts: [],
    boltTimer: 60,
    bunkers: [],
    bonus: null,
    bonusTimer: 600,
    particles: [],
};

const rect = (x, y, w, h, color) => {
    ctx.fillStyle = color;
    ctx.fillRect(Math.round(x), Math.round(y), w, h);
};

const bitmap = (rows, x, y, { scale = SCALE, mirror = false, colors = {} } = {}) => {
    const blink = Math.floor(game.tick / 20) % 2 === 0;
    const lights = blink ? { L: PALETTE.R, M: PALETTE.Y } : { L: PALETTE.Y, M: PALETTE.R };
    rows.forEach((row, ry) => {
        for (let rx = 0; rx < row.length; rx++) {
            const ch = row[mirror ? row.length - 1 - rx : rx];
            const color = colors[ch] ?? lights[ch] ?? PALETTE[ch];
            if (color) {
                rect(x + rx * scale, y + ry * scale, scale, scale, color);
            }
        }
    });
};

const burst = (x, y, color, count = 10) => {
    for (let i = 0; i < count; i++) {
        const angle = (Math.PI * 2 * i) / count + Math.random();
        const speed = 0.6 + Math.random() * 1.4;
        game.particles.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, life: 24 + Math.random() * 12, color });
    }
};

const makeBunkers = () => {
    const total = BUNKER_COUNT * BUNKER_W + (BUNKER_COUNT - 1) * BUNKER_GAP;
    const start = (VW - total) / 2;

    return Array.from({ length: BUNKER_COUNT }, (_, i) => ({
        x: start + i * (BUNKER_W + BUNKER_GAP),
        y: BUNKER_Y,
        cells: BUNKER_SHAPE.map((row) => [...row].map((ch) => ch === '#')),
    }));
};

const spawnFormation = () => {
    game.ufos = [];
    for (let row = 0; row < UFO_KINDS.length; row++) {
        for (let col = 0; col < COLUMNS; col++) {
            game.ufos.push({ row, col, alive: true });
        }
    }
    const startY = 30 + Math.min(game.level - 1, 5) * 8;
    game.formation = { x: 12, y: startY, dir: 1, timer: 0, alt: false };
};

const ufoPosition = (ufo) => ({ x: game.formation.x + ufo.col * GAP_X, y: game.formation.y + ufo.row * GAP_Y });

const startLevel = () => {
    spawnFormation();
    game.bunkers = makeBunkers();
    game.bolts = [];
    game.bullet = null;
    game.bonus = null;
    game.bonusTimer = 600 + Math.random() * 300;
    game.boltTimer = 60;
};

const startRun = () => {
    game.score = 0;
    game.lives = START_LIVES;
    game.level = 1;
    game.ship = { x: VW / 2 - SHIP_W / 2, invulnerable: 0 };
    startLevel();
    game.state = 'play';
};

const aliveUfos = () => game.ufos.filter((ufo) => ufo.alive);

const stepInterval = () => {
    const alive = aliveUfos().length;
    const base = Math.max(2, Math.ceil(alive * 0.85) - (game.level - 1) * 3);

    return Math.max(2, base);
};

const clearBunkerUnder = (bunker, box) => {
    bunker.cells.forEach((row, cy) => {
        row.forEach((filled, cx) => {
            if (!filled) {
                return;
            }
            const px = bunker.x + cx * BUNKER_CELL;
            const py = bunker.y + cy * BUNKER_CELL;
            if (px < box.x + box.w && px + BUNKER_CELL > box.x && py < box.y + box.h && py + BUNKER_CELL > box.y) {
                row[cx] = false;
            }
        });
    });
};

const stepFormation = () => {
    const f = game.formation;
    const alive = aliveUfos();
    if (alive.length === 0) {
        return;
    }
    const stepX = 3;
    const left = Math.min(...alive.map((ufo) => ufo.col)) * GAP_X + f.x;
    const right = Math.max(...alive.map((ufo) => ufo.col)) * GAP_X + f.x + UFO_SIZE;

    if ((f.dir > 0 && right + stepX > VW - EDGE) || (f.dir < 0 && left - stepX < EDGE)) {
        f.dir = -f.dir;
        f.y += 8;
    } else {
        f.x += f.dir * stepX;
    }
    f.alt = !f.alt;
    sfx.march(f.alt);

    for (const ufo of alive) {
        const { x, y } = ufoPosition(ufo);
        for (const bunker of game.bunkers) {
            clearBunkerUnder(bunker, { x, y, w: UFO_SIZE, h: UFO_SIZE });
        }
        if (y + UFO_SIZE >= SHIP_Y) {
            loseGame();

            return;
        }
    }
};

const loseGame = () => {
    if (game.score > game.high) {
        game.high = game.score;
        store.set(HIGH_SCORE_KEY, String(game.high));
    }
    game.state = 'over';
};

const hitBunker = (x, y) => {
    for (const bunker of game.bunkers) {
        const cx = Math.floor((x - bunker.x) / BUNKER_CELL);
        const cy = Math.floor((y - bunker.y) / BUNKER_CELL);
        if (cy < 0 || cy >= bunker.cells.length || cx < 0 || cx >= bunker.cells[0].length || !bunker.cells[cy][cx]) {
            continue;
        }
        for (let dy = -1; dy <= 1; dy++) {
            for (let dx = -1; dx <= 1; dx++) {
                const nx = cx + dx;
                const ny = cy + dy;
                const inside = ny >= 0 && ny < bunker.cells.length && nx >= 0 && nx < bunker.cells[0].length;
                if (inside && (dx === 0 && dy === 0 || Math.random() < 0.55)) {
                    bunker.cells[ny][nx] = false;
                }
            }
        }

        return true;
    }

    return false;
};

const fireBolt = () => {
    const alive = aliveUfos();
    if (alive.length === 0) {
        return;
    }
    const bottoms = new Map();
    for (const ufo of alive) {
        if (!bottoms.has(ufo.col) || ufo.row > bottoms.get(ufo.col).row) {
            bottoms.set(ufo.col, ufo);
        }
    }
    const shooters = [...bottoms.values()];
    const shooter = shooters[Math.floor(Math.random() * shooters.length)];
    const { x, y } = ufoPosition(shooter);
    game.bolts.push({ x: x + UFO_SIZE / 2 - 1, y: y + UFO_SIZE, speed: 1.4 + Math.min(game.level, 8) * 0.12 });
    sfx.bolt();
};

const hitShip = () => {
    sfx.hit();
    burst(game.ship.x + SHIP_W / 2, SHIP_Y + SHIP_H / 2, PALETTE.W, 16);
    burst(game.ship.x + SHIP_W / 2, SHIP_Y + SHIP_H / 2, PALETTE.R, 10);
    game.lives--;
    game.bolts = [];
    game.bullet = null;
    game.state = 'dying';
    game.timer = 60;
};

const updatePlay = () => {
    const ship = game.ship;
    const move = (down.has('right') ? 1 : 0) - (down.has('left') ? 1 : 0);
    ship.x = Math.max(EDGE / 2, Math.min(VW - SHIP_W - EDGE / 2, ship.x + move * 2));
    ship.invulnerable = Math.max(0, ship.invulnerable - 1);
    game.fireCooldown = Math.max(0, game.fireCooldown - 1);

    if (down.has('fire') && !game.bullet && game.fireCooldown === 0) {
        game.bullet = { x: ship.x + SHIP_W / 2 - 1, y: SHIP_Y - 4 };
        game.fireCooldown = 8;
        sfx.fire();
    }

    if (++game.formation.timer >= stepInterval()) {
        game.formation.timer = 0;
        stepFormation();
        if (game.state !== 'play') {
            return;
        }
    }

    if (--game.boltTimer <= 0) {
        const maxBolts = Math.min(3 + Math.floor(game.level / 2), 6);
        if (game.bolts.length < maxBolts) {
            fireBolt();
        }
        game.boltTimer = Math.max(16, 50 - game.level * 4 + Math.random() * 40);
    }

    if (--game.bonusTimer <= 0 && !game.bonus) {
        const fromLeft = Math.random() < 0.5;
        game.bonus = { x: fromLeft ? -20 : VW + 2, y: 18, dir: fromLeft ? 1 : -1 };
        game.bonusTimer = 800 + Math.random() * 600;
    }
    if (game.bonus) {
        game.bonus.x += game.bonus.dir * 0.9;
        if (game.bonus.x < -24 || game.bonus.x > VW + 24) {
            game.bonus = null;
        }
    }

    updateBullet();
    updateBolts();

    if (aliveUfos().length === 0 && game.state === 'play') {
        sfx.level();
        game.state = 'levelup';
        game.timer = 100;
    }
};

const updateBullet = () => {
    const bullet = game.bullet;
    if (!bullet) {
        return;
    }
    bullet.y -= 5;
    if (bullet.y < 12) {
        game.bullet = null;

        return;
    }
    if (hitBunker(bullet.x, bullet.y) || hitBunker(bullet.x, bullet.y + 4)) {
        game.bullet = null;

        return;
    }
    if (game.bonus && bullet.x >= game.bonus.x && bullet.x <= game.bonus.x + 18 && bullet.y >= game.bonus.y && bullet.y <= game.bonus.y + 14) {
        const points = [50, 100, 150, 300][Math.floor(Math.random() * 4)];
        game.score += points;
        game.bonus.points = points;
        game.particles.push({ x: game.bonus.x + 4, y: game.bonus.y, vx: 0, vy: -0.3, life: 50, color: PALETTE.Y, label: String(points) });
        burst(game.bonus.x + 9, game.bonus.y + 7, PALETTE.P, 14);
        game.bonus = null;
        game.bullet = null;
        sfx.bonus();

        return;
    }
    for (const ufo of game.ufos) {
        if (!ufo.alive) {
            continue;
        }
        const { x, y } = ufoPosition(ufo);
        if (bullet.x >= x && bullet.x <= x + UFO_SIZE && bullet.y >= y && bullet.y <= y + UFO_SIZE) {
            ufo.alive = false;
            game.score += UFO_KINDS[ufo.row].points;
            burst(x + UFO_SIZE / 2, y + UFO_SIZE / 2, UFO_KINDS[ufo.row].colors.S ?? PALETTE.S, 12);
            sfx.boom();
            game.bullet = null;

            return;
        }
    }
};

const updateBolts = () => {
    for (const bolt of game.bolts) {
        bolt.y += bolt.speed;
        if (hitBunker(bolt.x, bolt.y + 5)) {
            bolt.dead = true;
            continue;
        }
        if (bolt.y > GROUND_Y) {
            bolt.dead = true;
            burst(bolt.x, GROUND_Y, PALETTE.C, 4);
            continue;
        }
        const ship = game.ship;
        if (ship.invulnerable === 0 && bolt.x >= ship.x && bolt.x <= ship.x + SHIP_W && bolt.y + 6 >= SHIP_Y && bolt.y <= SHIP_Y + SHIP_H) {
            bolt.dead = true;
            hitShip();

            return;
        }
    }
    game.bolts = game.bolts.filter((bolt) => !bolt.dead);
};

const updateParticles = () => {
    for (const particle of game.particles) {
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.life--;
    }
    game.particles = game.particles.filter((particle) => particle.life > 0);
};

const handleMeta = () => {
    if (taps.has('mute')) {
        muted = !muted;
        store.set(MUTE_KEY, muted ? '1' : '0');
    }
    if (taps.has('pause')) {
        if (game.state === 'play') {
            game.state = 'paused';
        } else if (game.state === 'paused') {
            game.state = 'play';
        }
    }
};

const update = () => {
    game.tick++;
    handleMeta();

    switch (game.state) {
        case 'title':
        case 'over':
            if (taps.has('start') || taps.has('fire')) {
                startRun();
            }
            break;
        case 'play':
            updatePlay();
            updateParticles();
            break;
        case 'dying':
            updateParticles();
            if (--game.timer <= 0) {
                if (game.lives <= 0) {
                    loseGame();
                } else {
                    game.ship.invulnerable = 120;
                    game.state = 'play';
                }
            }
            break;
        case 'levelup':
            updateParticles();
            if (--game.timer <= 0) {
                game.level++;
                startLevel();
                game.state = 'play';
            }
            break;
        default:
            break;
    }

    taps.clear();
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

const pad = (value) => String(value).padStart(5, '0');

const panel = (lines) => {
    rect(0, 0, VW, VH, 'rgba(18, 6, 43, 0.85)');
    lines.forEach(([value, y, options]) => text(value, VW / 2, y, { align: 'center', ...options }));
};

const drawShip = (x, y) => bitmap(SHIP, x, y);

const render = () => {
    rect(0, 0, VW, VH, SEA);
    for (let i = 0; i < 40; i++) {
        rect((i * 61) % VW, (i * 37) % (GROUND_Y - 30) + 22, 1, 1, PALETTE.D);
    }
    rect(0, GROUND_Y, VW, VH - GROUND_Y, LAND);
    rect(0, GROUND_Y, VW, 2, PALETTE.C);

    if (game.state === 'title') {
        panel([
            ['UFO', 60, { size: 20, color: PALETTE.R }],
            ['INVADERS', 88, { size: 16, color: PALETTE.Y }],
            ['The map is under attack.', 130, { size: 7, color: PALETTE.S }],
            ['Shoot the UFOs. Hide behind', 146, { size: 7, color: PALETTE.S }],
            ['the bunkers. Catch the unicorn.', 158, { size: 7, color: PALETTE.S }],
            [game.tick % 60 < 40 ? 'PRESS ENTER OR TAP' : '', 196, { size: 8 }],
            [game.high ? `HIGH SCORE ${pad(game.high)}` : '', 224, { size: 7, color: PALETTE.G }],
        ]);
        bitmap(UFO, VW / 2 - 21, 30, { scale: 6 - 2 });

        return;
    }

    for (const bunker of game.bunkers) {
        bunker.cells.forEach((row, cy) => {
            row.forEach((filled, cx) => {
                if (filled) {
                    rect(bunker.x + cx * BUNKER_CELL, bunker.y + cy * BUNKER_CELL, BUNKER_CELL, BUNKER_CELL, PALETTE.G);
                }
            });
        });
    }

    for (const ufo of game.ufos) {
        if (ufo.alive) {
            const { x, y } = ufoPosition(ufo);
            bitmap(UFO, x, y, { colors: UFO_KINDS[ufo.row].colors });
        }
    }

    if (game.bonus) {
        bitmap(UNICORN, game.bonus.x, game.bonus.y, { mirror: game.bonus.dir < 0 });
    }

    if (game.state !== 'dying' && game.state !== 'over' && (game.ship.invulnerable % 10 < 6)) {
        drawShip(Math.round(game.ship.x), SHIP_Y);
    }

    if (game.bullet) {
        rect(game.bullet.x, game.bullet.y, 2, 6, PALETTE.W);
    }
    for (const bolt of game.bolts) {
        rect(bolt.x, bolt.y, 2, 6, game.tick % 8 < 4 ? PALETTE.R : PALETTE.Y);
    }
    for (const particle of game.particles) {
        if (particle.label) {
            text(particle.label, particle.x, particle.y, { size: 6, color: particle.color });
        } else {
            rect(particle.x, particle.y, 2, 2, particle.color);
        }
    }

    rect(0, 0, VW, 14, '#2a0a3d');
    text(`SCORE ${pad(game.score)}`, 4, 4, { size: 7, color: PALETTE.Y });
    text(`HI ${pad(game.high)}`, VW - 4, 4, { size: 7, color: PALETTE.G, align: 'right' });
    text(`LVL ${game.level}`, VW / 2 + 10, 4, { size: 7, color: PALETTE.C, align: 'center' });
    for (let i = 0; i < game.lives - (game.state === 'dying' ? 0 : 1); i++) {
        drawShip(6 + i * (SHIP_W + 4), VH - 13);
    }

    if (game.state === 'paused') {
        panel([
            ['PAUSED', 120, { size: 16, color: PALETTE.Y }],
            ['P or Esc to resume', 150, { size: 7, color: PALETTE.S }],
        ]);
    } else if (game.state === 'levelup') {
        panel([
            [`WAVE ${game.level} CLEARED`, 120, { size: 10, color: PALETTE.G }],
            ['They are coming back faster...', 146, { size: 7, color: PALETTE.S }],
        ]);
    } else if (game.state === 'over') {
        panel([
            ['GAME OVER', 90, { size: 16, color: PALETTE.R }],
            [`SCORE ${pad(game.score)}`, 130, { size: 9, color: PALETTE.Y }],
            [`HIGH  ${pad(game.high)}`, 148, { size: 9, color: PALETTE.G }],
            ['ENTER OR TAP TO TRY AGAIN', 186, { size: 7 }],
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
        game.state = 'paused';
    }
});

root.querySelectorAll('[data-game-key]').forEach((button) => {
    const action = button.dataset.gameKey;
    button.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        button.setPointerCapture?.(event.pointerId);
        setKey(action, true);
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((name) => button.addEventListener(name, () => setKey(action, false)));
    button.addEventListener('contextmenu', (event) => event.preventDefault());
});

canvas.addEventListener('pointerdown', () => {
    if (['title', 'over'].includes(game.state)) {
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

requestAnimationFrame(frame);
