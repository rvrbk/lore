const CELL = 2;
const SCALE = 6;

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
};

const FLY = 2;

const XWING = ['S........', 'SS.......', '.SSS.....', 'RSSSSSSSW', '.SSS.....', 'SS.......', 'S........'];
const TIE = ['D.....D', 'D.....D', 'D..S..D', 'DSSCSSD', 'D..S..D', 'D.....D', 'D.....D'];

const FLYERS = [
    { name: 'plane', speed: 16, bob: 0, bitmap: ['....W......', '....WW.....', 'WW...WW....', 'WWWWWWWWWWC', 'WW...WW....', '....WW.....', '....W......'] },
    { name: 'ufo', speed: 11, bob: 1.5, bitmap: ['....C....', '...CCC...', '.GGGGGGG.', 'GYGYGYGYG', '.GG...GG.'] },
    { name: 'santa', speed: 13, bob: 1, bitmap: ['.....RW...B.B.', 'BBBBBRRR.YBBBB', '.B.B.B.B......'] },
    { name: 'rocket', speed: 26, bob: 0, bitmap: ['..R.......', '.RRWWWWW..', 'OYWWWCWWWR', '.RRWWWWW..', '..R.......'] },
    { name: 'helicopter', speed: 9, bob: 0.5, rotor: 10, bitmap: ['R...RRRR..', 'RRRRRRRRWC', '....RRRR..', '..DDDDDDD.'] },
    { name: 'tie-fighter', speed: 18, bob: 0, bitmap: TIE },
    { name: 'x-wing', speed: 20, bob: 0, bitmap: XWING },
    { name: 'falcon', speed: 14, bob: 0, bitmap: ['..SSSSSS...', '.SSSSSSSSSS', 'SSSDDDCSS..', '.SSSSSSSSSS', '..SSSSSS...'] },
    { name: 'star-destroyer', speed: 6, bob: 0, bitmap: ['C..............', 'CSS............', 'CSSSSS.........', 'CSSSDSSSSSSSSSS', 'CSSSSS.........', 'CSS............', 'C..............'] },
    { name: 'death-star', speed: 4, bob: 0, upright: true, bitmap: ['..SSSSS..', '.SSSSDDS.', 'SSSSDDDSS', 'DDDDDDDDD', 'SSSSSSSSS', 'SSSSSSSSS', '.SSSSSSS.', '..SSSSS..'] },
    { name: 'blimp', speed: 5, bob: 0.5, bitmap: ['...WWWWWWW..', 'R.WRRRRRRRW.', 'RRRRRRRRRRRC', 'R.WRRRRRRRW.', '...WWWWWWW..', '.....DD.....'] },
    { name: 'hot-air-balloon', speed: 4, bob: 1, upright: true, bitmap: ['.RRRRR.', 'RRYRYRR', 'RRRYRRR', 'RRRRRRR', '.RRRRR.', '..RRR..', '...B...', '..BBB..'] },
    { name: 'mothership', speed: 4, bob: 0.5, bitmap: ['.....GGGGG.....', '...GGGGGGGGG...', 'GGGGGGGGGGGGGGG', 'GCYCYCYCYCYCYCG', '.GGGGGGGGGGGGG.', '...Y...Y...Y...'] },
];
['plane', 'ufo', 'santa', 'rocket', 'helicopter', 'tie-fighter', 'x-wing', 'falcon', 'blimp', 'hot-air-balloon'].forEach((name) => {
    FLYERS.find((kind) => kind.name === name).crashable = true;
});

const ROSWELL = { longitude: -104.52, latitude: 33.39 };

const FIGHTER = ['XX..', 'XXXW', 'XX..'];
const SIDES = [
    { bitmap: FIGHTER, colors: { X: '#ff2d55' }, laser: '#ff5577', facing: 0 },
    { bitmap: FIGHTER, colors: { X: '#39ff14' }, laser: '#aaff66', facing: Math.PI },
];
const STAR_WARS_SIDES = [
    { bitmap: XWING, colors: {}, laser: '#ff4040', facing: 0 },
    { bitmap: TIE, colors: {}, laser: '#5bff5b', facing: Math.PI },
];

const mix = (from, to, amount) => `rgb(${from.map((value, index) => Math.round(value + (to[index] - value) * amount)).join(', ')})`;

const THEMES = {
    ocean: {
        label: 'Ocean',
        sea: ['#12062b', '#2a0a3d'],
        land: 'rgb(0, 180, 205)',
        visited: (heat) => mix([255, 150, 20], [255, 235, 90], heat),
    },
    amber: {
        label: 'Amber',
        sea: ['#0a1020', '#131c33'],
        land: 'rgb(75, 106, 136)',
        visited: (heat) => mix([217, 119, 6], [253, 224, 71], heat),
    },
    terminal: {
        label: 'Terminal',
        sea: ['#020a04', '#04140a'],
        land: 'rgb(20, 105, 55)',
        visited: (heat) => mix([34, 197, 94], [190, 255, 170], heat),
    },
    mono: {
        label: 'Mono',
        sea: ['#09090b', '#09090b'],
        land: 'rgb(63, 63, 70)',
        visited: (heat) => mix([113, 113, 122], [250, 250, 250], heat),
    },
};

const DAY_SECONDS = 120;
const EQUATOR_Y = 84;
const DEGREES = Math.PI / 180;

const STYLE_KEY = 'lore.map-style';

const readStyle = () => {
    try {
        const saved = localStorage.getItem(STYLE_KEY);

        return saved && THEMES[saved] ? saved : 'ocean';
    } catch {
        return 'ocean';
    }
};

document.querySelectorAll('svg[data-pixel-map]').forEach((svg) => {
    const { width, height } = svg.viewBox.baseVal;
    const cols = Math.ceil(width / CELL);
    const rows = Math.ceil(height / CELL);

    const canvas = document.createElement('canvas');
    const W = cols * SCALE;
    const H = rows * SCALE;
    canvas.className = `${(svg.getAttribute('class') ?? '').replace('mt-2', '')} block`;
    const sea = document.createElement('div');
    sea.appendChild(canvas);
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', svg.getAttribute('aria-label') ?? '');

    const layer = document.createElement('canvas');
    const ctx = layer.getContext('2d');
    const view = canvas.getContext('2d');
    let res = 1;
    const probe = document.createElement('canvas').getContext('2d');
    const owner = new Array(cols * rows).fill(-1);
    const countries = [];

    svg.querySelectorAll('path').forEach((path, index) => {
        const style = getComputedStyle(path);
        const box = path.getBBox();
        const shape = new Path2D(path.getAttribute('d'));
        const title = path.querySelector('title')?.textContent ?? '';
        countries.push({ shape, title, visited: title.includes(':'), opacity: parseFloat(style.fillOpacity) });

        const fromX = Math.max(0, Math.floor(box.x / CELL));
        const toX = Math.min(cols - 1, Math.floor((box.x + box.width) / CELL));
        const fromY = Math.max(0, Math.floor(box.y / CELL));
        const toY = Math.min(rows - 1, Math.floor((box.y + box.height) / CELL));

        for (let y = fromY; y <= toY; y++) {
            for (let x = fromX; x <= toX; x++) {
                if (probe.isPointInPath(shape, (x + 0.5) * CELL, (y + 0.5) * CELL)) {
                    owner[y * cols + x] = index;
                }
            }
        }
    });

    let styleName = readStyle();
    sea.className = 'mt-2';

    const sinLatitude = new Float32Array(cols * rows);
    const cosLatitude = new Float32Array(cols * rows);
    const longitude = new Float32Array(cols * rows);
    for (let position = 0; position < cols * rows; position++) {
        const latitude = (EQUATOR_Y - ((Math.floor(position / cols) + 0.5) * CELL)) * DEGREES;
        sinLatitude[position] = Math.sin(latitude);
        cosLatitude[position] = Math.cos(latitude);
        longitude[position] = (((position % cols) + 0.5) * CELL - 180) * DEGREES;
    }

    const startedAt = Date.now();
    const startedPerf = performance.now();
    const animateSun = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const sunPosition = () => {
        const moment = new Date(startedAt + (animateSun ? (performance.now() - startedPerf) * (86400 / DAY_SECONDS) : 0));
        const dayOfYear = (moment - Date.UTC(moment.getUTCFullYear(), 0, 0)) / 86400000;
        const hours = moment.getUTCHours() + moment.getUTCMinutes() / 60 + moment.getUTCSeconds() / 3600;

        return {
            declination: -23.44 * DEGREES * Math.cos(((2 * Math.PI) / 365) * (dayOfYear + 10)),
            longitude: (12 - hours) * 15 * DEGREES,
        };
    };

    const paint = () => {
        const theme = THEMES[styleName];
        const sun = sunPosition();
        const sinDeclination = Math.sin(sun.declination);
        const cosDeclination = Math.cos(sun.declination);
        const cell = SCALE * res;
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, layer.width, layer.height);

        const disc = (x, y, diameter) => {
            const radius = diameter / 2;
            const left = Math.round((x + 0.5) * cell - radius);
            const top = Math.round((y + 0.5) * cell - radius);
            for (let row = 0; row < diameter; row++) {
                const offset = row + 0.5 - radius;
                const inset = Math.round(radius - Math.sqrt(Math.max(0, radius * radius - offset * offset)));
                ctx.fillRect(left + inset, top + row, diameter - inset * 2, 1);
            }
        };

        const landDiameter = Math.max(2, Math.round(cell * 0.46));
        const visitedDiameter = Math.max(2, Math.round(cell * 0.62));

        for (let position = 0; position < owner.length; position++) {
            const country = countries[owner[position]];

            if (!country) {
                continue;
            }

            const elevation = sinLatitude[position] * sinDeclination + cosLatitude[position] * cosDeclination * Math.cos(longitude[position] - sun.longitude);
            const light = Math.min(1, Math.max(0, (elevation + 0.08) / 0.2));
            ctx.globalAlpha = Math.round((0.2 + 0.8 * light) * 16) / 16;
            ctx.fillStyle = country.visited ? theme.visited(country.opacity) : theme.land;
            disc(position % cols, Math.floor(position / cols), country.visited ? visitedDiameter : landDiameter);
        }
        ctx.globalAlpha = 1;
        ctx.restore();
    };

    const paintSea = () => {
        const theme = THEMES[styleName];
        sea.style.backgroundImage = `linear-gradient(to bottom, ${theme.sea[0]}, ${theme.sea[1]})`;
    };

    const MAX_CSS_WIDTH = 1024;

    const resize = () => {
        const available = Math.min(sea.clientWidth, MAX_CSS_WIDTH);
        if (!available) {
            return false;
        }
        const ratio = window.devicePixelRatio || 1;
        const cellPixels = Math.max(3, Math.floor((available * ratio) / cols));
        const width = cols * cellPixels;
        const height = rows * cellPixels;
        if (width === canvas.width && height === canvas.height) {
            return false;
        }

        canvas.style.width = `${width / ratio}px`;
        canvas.width = width;
        canvas.height = height;
        layer.width = width;
        layer.height = height;
        res = cellPixels / SCALE;
        ctx.setTransform(res, 0, 0, res, 0, 0);
        view.setTransform(res, 0, 0, res, 0, 0);
        paint();

        return true;
    };

    const drawBitmap = (bitmap, x, y, angle, colors = {}) => {
        const bitmapHeight = bitmap.length;
        const bitmapWidth = Math.max(...bitmap.map((row) => row.length));
        view.save();
        view.translate(x, y);
        view.rotate(angle);
        bitmap.forEach((row, rowIndex) => {
            [...row].forEach((symbol, columnIndex) => {
                if (symbol === '.') {
                    return;
                }
                const color = colors[symbol] ?? PALETTE[symbol];
                view.shadowColor = color;
                view.shadowBlur = 6 * res;
                view.fillStyle = color;
                view.beginPath();
                view.arc((columnIndex - bitmapWidth / 2 + 0.5) * FLY, (rowIndex - bitmapHeight / 2 + 0.5) * FLY, FLY * 0.5, 0, Math.PI * 2);
                view.fill();
            });
        });
        view.restore();
    };

    const drawRotor = (kind, x, y, elapsed) => {
        view.save();
        view.translate(x, y);
        view.rotate((elapsed / 40) % (Math.PI * 2));
        view.strokeStyle = 'rgba(255, 255, 255, 0.7)';
        view.lineWidth = 1.5;
        view.beginPath();
        view.moveTo(-kind.rotor, 0);
        view.lineTo(kind.rotor, 0);
        view.moveTo(0, -kind.rotor);
        view.lineTo(0, kind.rotor);
        view.stroke();
        view.restore();
    };

    const drawBlasts = (blasts, time) => {
        blasts.forEach((blast) => {
            const t = (time - blast.start) / blast.duration;
            if (t < 0) {
                return;
            }
            view.globalAlpha = 1 - t;
            view.fillStyle = t < 0.5 ? '#ffe600' : '#ff8a00';
            view.shadowColor = '#ff8a00';
            view.shadowBlur = 10 * res;
            for (let i = 0; i < 8; i++) {
                const angle = (i / 8) * Math.PI * 2;
                view.beginPath();
                view.arc(blast.x + Math.cos(angle) * blast.radius * t, blast.y + Math.sin(angle) * blast.radius * t, 1, 0, Math.PI * 2);
                view.fill();
            }
            view.globalAlpha = 1;
        });
        view.shadowBlur = 0;
    };

    const createCrash = (kind, now, atRoswell = false) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 30;
        const start = { x: forward > 0 ? -margin : W + margin, y: H * (atRoswell ? 0.12 + Math.random() * 0.12 : 0.3 + Math.random() * 0.2) };
        const impact = atRoswell
            ? { x: (ROSWELL.longitude + 180) * (SCALE / CELL), y: (EQUATOR_Y - ROSWELL.latitude) * (SCALE / CELL) }
            : { x: 0, y: H * (0.68 + Math.random() * 0.17) };
        const peak = {
            x: atRoswell ? start.x + (impact.x - start.x) * 0.6 : W * (0.25 + Math.random() * 0.5),
            y: start.y,
        };
        if (!atRoswell) {
            impact.x = peak.x + forward * (30 + Math.random() * 50);
        }
        const cruise = (Math.hypot(peak.x - start.x, peak.y - start.y) / (kind.speed * SCALE)) * 1000;
        const fall = atRoswell ? 2400 : 1400 + Math.random() * 600;
        const spin = (Math.random() < 0.5 ? -1 : 1) * (2 + Math.random() * 4);
        const puffs = [];
        const blasts = [];
        let nextPuff = now;
        let impactAt = null;

        const position = (time) => {
            const elapsed = time - now;
            if (elapsed < cruise) {
                const t = elapsed / cruise;

                return {
                    x: start.x + (peak.x - start.x) * t,
                    y: start.y + Math.sin(t * 40) * kind.bob * FLY,
                    angle: forward > 0 ? 0 : Math.PI,
                    falling: false,
                };
            }
            const t = Math.min(1, (elapsed - cruise) / fall);
            const angle = kind.upright ? t * spin : Math.atan2(2 * t * (impact.y - peak.y), impact.x - peak.x) + t * t * spin;

            return {
                x: peak.x + (impact.x - peak.x) * t,
                y: peak.y + (impact.y - peak.y) * t * t,
                angle,
                falling: true,
            };
        };

        return {
            update: (time) => {
                if (impactAt === null && time - now >= cruise + fall) {
                    impactAt = time;
                    for (let i = 0; i < 5; i++) {
                        blasts.push({ x: impact.x + (Math.random() - 0.5) * 14, y: impact.y + (Math.random() - 0.5) * 8, start: time + i * 140, duration: 600, radius: 7 + Math.random() * 6 });
                    }
                }
                if (time >= nextPuff && (impactAt === null || time - impactAt < 2500)) {
                    nextPuff = time + (impactAt === null ? 50 : 110);
                    const at = impactAt === null ? position(time) : { ...impact, x: impact.x + (Math.random() - 0.5) * 8 };
                    if (impactAt !== null || at.falling) {
                        puffs.push({ x: at.x, y: at.y, start: time, life: impactAt === null ? 900 : 1500, rise: impactAt === null ? 4 : 14 });
                    }
                }
                for (let i = puffs.length - 1; i >= 0; i--) {
                    if (time - puffs[i].start >= puffs[i].life) {
                        puffs.splice(i, 1);
                    }
                }

                return impactAt === null || time - impactAt < 4000;
            },
            draw: (time) => {
                puffs.forEach((puff) => {
                    const t = (time - puff.start) / puff.life;
                    view.globalAlpha = (1 - t) * 0.7;
                    view.fillStyle = t < 0.3 ? '#ff8a00' : '#6c7090';
                    view.beginPath();
                    view.arc(puff.x, puff.y - t * puff.rise, 1 + t * 2.5, 0, Math.PI * 2);
                    view.fill();
                });
                view.globalAlpha = 1;

                if (impactAt === null) {
                    const at = position(time);
                    drawBitmap(kind.bitmap, at.x, at.y, at.angle);
                    if (kind.rotor) {
                        drawRotor(kind, at.x, at.y, time - now);
                    }
                } else {
                    drawBlasts(blasts, time);
                    const glow = Math.max(0, 1 - (time - impactAt) / 4000);
                    view.globalAlpha = glow * (0.6 + Math.sin(time / 90) * 0.4);
                    view.fillStyle = atRoswell ? '#39ff14' : '#ff8a00';
                    view.shadowColor = view.fillStyle;
                    view.shadowBlur = 8 * res;
                    view.beginPath();
                    view.arc(impact.x, impact.y, 1.6, 0, Math.PI * 2);
                    view.fill();
                    view.globalAlpha = 1;
                    view.shadowBlur = 0;
                }
            },
        };
    };

    const createFlyer = (kind, now) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 30;
        const travel = W + margin * 2;
        const baseY = H * (0.4 + Math.random() * 0.3);
        const lift = H * (0.14 + Math.random() * 0.16);
        const duration = (travel / (kind.speed * SCALE)) * 1000;

        return {
            update: (time) => time - now < duration,
            draw: (time) => {
                const t = (time - now) / duration;
                const dx = forward * travel;
                const dy = lift * 4 * (2 * t - 1);
                const x = forward > 0 ? -margin + t * travel : W + margin - t * travel;
                const y = baseY - lift * (1 - (2 * t - 1) ** 2) + Math.sin(t * 40) * kind.bob * FLY;
                drawBitmap(kind.bitmap, x, y, kind.upright ? 0 : Math.atan2(dy, dx));

                if (kind.rotor) {
                    drawRotor(kind, x, y, time - now);
                }
            },
        };
    };

    const createBattle = (now) => {
        const centerX = W * (0.25 + Math.random() * 0.5);
        const centerY = H * (0.3 + Math.random() * 0.3);
        const gap = 52;
        const sides = Math.random() < 0.5 ? STAR_WARS_SIDES : SIDES;
        const ships = sides.flatMap((side, sideIndex) => [0, 1, 2].map((i) => ({
            side: sideIndex,
            x: centerX + (sideIndex === 0 ? -gap : gap) + (Math.random() - 0.5) * 20,
            y: centerY + (i - 1) * 18 + (Math.random() - 0.5) * 10,
            phase: Math.random() * 6,
            hp: 2,
            alive: true,
        })));
        const lasers = [];
        const blasts = [];
        let nextShot = now + 600;
        let endedAt = null;

        const explode = (x, y, time, big) => blasts.push({ x, y, start: time, duration: big ? 700 : 350, radius: big ? 11 : 5 });

        return {
            update: (time) => {
                const living = [0, 1].map((side) => ships.filter((ship) => ship.alive && ship.side === side));
                if (endedAt === null && (time - now > 9000 || !living[0].length || !living[1].length)) {
                    endedAt = time;
                }
                if (endedAt !== null && time - endedAt > 800) {
                    ships.forEach((ship) => {
                        if (ship.alive) {
                            ship.alive = false;
                            explode(ship.x, ship.y, time, false);
                        }
                    });
                }

                if (endedAt === null && time >= nextShot) {
                    nextShot = time + 250 + Math.random() * 300;
                    const shooter = living[Math.floor(Math.random() * 2)];
                    const shooterShip = shooter[Math.floor(Math.random() * shooter.length)];
                    const enemies = living[1 - shooterShip.side];
                    const target = enemies[Math.floor(Math.random() * enemies.length)];
                    lasers.push({ from: shooterShip, target, start: time, duration: 380 });
                }

                for (let i = lasers.length - 1; i >= 0; i--) {
                    if (time - lasers[i].start >= lasers[i].duration) {
                        const { target } = lasers[i];
                        if (target.alive && Math.random() < 0.6) {
                            target.hp--;
                            explode(target.x, target.y, time, target.hp <= 0);
                            target.alive = target.hp > 0;
                        } else {
                            explode(target.x + (Math.random() - 0.5) * 30, target.y + (Math.random() - 0.5) * 30, time, false);
                        }
                        lasers.splice(i, 1);
                    }
                }
                for (let i = blasts.length - 1; i >= 0; i--) {
                    if (time - blasts[i].start >= blasts[i].duration) {
                        blasts.splice(i, 1);
                    }
                }

                return ships.some((ship) => ship.alive) || lasers.length > 0 || blasts.length > 0;
            },
            draw: (time) => {
                ships.filter((ship) => ship.alive).forEach((ship) => {
                    const side = sides[ship.side];
                    drawBitmap(side.bitmap, ship.x, ship.y + Math.sin(time / 400 + ship.phase) * 2, side.facing, side.colors);
                });
                lasers.forEach((laser) => {
                    const t = (time - laser.start) / laser.duration;
                    const x = laser.from.x + (laser.target.x - laser.from.x) * t;
                    const y = laser.from.y + (laser.target.y - laser.from.y) * t;
                    const tail = Math.max(0, t - 0.12);
                    view.strokeStyle = sides[laser.from.side].laser;
                    view.shadowColor = sides[laser.from.side].laser;
                    view.shadowBlur = 8 * res;
                    view.lineWidth = 1;
                    view.beginPath();
                    view.moveTo(laser.from.x + (laser.target.x - laser.from.x) * tail, laser.from.y + (laser.target.y - laser.from.y) * tail);
                    view.lineTo(x, y);
                    view.stroke();
                });
                drawBlasts(blasts, time);
                view.shadowBlur = 0;
            },
        };
    };

    const actors = [];
    let running = false;

    const render = (time = 0) => {
        view.save();
        view.setTransform(1, 0, 0, 1, 0, 0);
        view.clearRect(0, 0, canvas.width, canvas.height);
        view.drawImage(layer, 0, 0);
        view.restore();

        for (let i = actors.length - 1; i >= 0; i--) {
            if (actors[i].update(time)) {
                actors[i].draw(time);
            } else {
                actors.splice(i, 1);
            }
        }
        view.shadowBlur = 0;

    };

    const frame = (time) => {
        render(time);
        if (actors.length && canvas.isConnected) {
            requestAnimationFrame(frame);
        } else {
            running = false;
        }
    };

    const spawn = () => {
        const now = performance.now();
        if (!document.hidden && actors.length < 4) {
            const battleActive = actors.some((actor) => actor.isBattle);
            if (!battleActive && Math.random() < 0.3) {
                const battle = createBattle(now);
                battle.isBattle = true;
                actors.push(battle);
            } else if (Math.random() < 0.2) {
                actors.push(createCrash(FLYERS.find((kind) => kind.name === 'ufo'), now, true));
            } else {
                const kind = FLYERS[Math.floor(Math.random() * FLYERS.length)];
                actors.push(kind.crashable && Math.random() < 0.5 ? createCrash(kind, now) : createFlyer(kind, now));
            }
            if (!running) {
                running = true;
                requestAnimationFrame(frame);
            }
        }
        if (canvas.isConnected) {
            setTimeout(spawn, 8000 + Math.random() * 10000);
        }
    };

    if (animateSun) {
        const sunTimer = setInterval(() => {
            if (!canvas.isConnected) {
                clearInterval(sunTimer);

                return;
            }
            if (document.hidden || !canvas.width) {
                return;
            }
            paint();
            if (!running) {
                render(performance.now());
            }
        }, 250);
    }

    if (animateSun) {
        setTimeout(spawn, 2000 + Math.random() * 2000);
    }

    canvas.addEventListener('mousemove', (event) => {
        const rect = canvas.getBoundingClientRect();
        const x = Math.floor(((event.clientX - rect.left) / rect.width) * cols);
        const y = Math.floor(((event.clientY - rect.top) / rect.height) * rows);
        const index = owner[y * cols + x] ?? -1;
        canvas.title = index >= 0 ? countries[index].title : '';
    });

    svg.replaceWith(sea);

    const selector = sea.parentElement.querySelector('select[data-map-style]');
    if (selector) {
        selector.innerHTML = Object.entries(THEMES)
            .map(([name, theme]) => `<option value="${name}">${theme.label}</option>`)
            .join('');
        selector.value = styleName;
        selector.addEventListener('change', () => {
            styleName = selector.value;
            try {
                localStorage.setItem(STYLE_KEY, styleName);
            } catch {
                // the choice just won't be remembered
            }
            paint();
            paintSea();
            render(performance.now());
        });
    }
    const observer = new ResizeObserver(() => {
        if (resize()) {
            render(performance.now());
        }
        paintSea();
    });
    observer.observe(canvas);
});
