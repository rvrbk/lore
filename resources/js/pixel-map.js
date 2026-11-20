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
};

const FLY = 3;

const FLYERS = [
    { name: 'plane', speed: 16, bob: 0, bitmap: ['...W...', 'W..W...', 'WWWWWWC', 'W..W...', '...W...'] },
    { name: 'ufo', speed: 11, bob: 1.5, bitmap: ['.GGG.', 'GCYCG', 'GYCYG', 'GCYCG', '.GGG.'] },
    { name: 'santa', speed: 13, bob: 1, bitmap: ['RRR...B.B', 'RWRYYYBBB', 'RRR...B.B'] },
    { name: 'rocket', speed: 26, bob: 0, bitmap: ['.R....', 'OYWWWC', '.R....'] },
    { name: 'comet', speed: 30, bob: 0, bitmap: ['.OOY...', 'OOYYYWW', '.OOY...'] },
    { name: 'satellite', speed: 7, bob: 0, bitmap: ['C...C', 'CCWCC', 'C...C'] },
    { name: 'helicopter', speed: 9, bob: 0.5, rotor: 12, bitmap: ['....WW.', 'RRRRWWC', '....WW.'] },
    { name: 'tie-fighter', speed: 18, bob: 0, bitmap: ['D...D', 'D.S.D', 'DSCSD', 'D.S.D', 'D...D'] },
    { name: 'x-wing', speed: 20, bob: 0, bitmap: ['S.S....', '.SSS...', 'RSSSSSW', '.SSS...', 'S.S....'] },
    { name: 'falcon', speed: 14, bob: 0, bitmap: ['.SSSSSSS', 'SSDDDSS.', 'SDDCDDSS', 'SSDDDSS.', '.SSSSSSS'] },
    { name: 'star-destroyer', speed: 6, bob: 0, bitmap: ['CS.........', 'CSSSSS.....', 'CSSDSSSSSSS', 'CSSSSS.....', 'CS.........'] },
    { name: 'death-star', speed: 4, bob: 0, upright: true, bitmap: ['..SSS..', '.SSSSS.', 'SSSSDDS', 'SSSSSSS', 'SSSSSSS', '.SSSSS.', '..SSS..'] },
    { name: 'blimp', speed: 5, bob: 0.5, bitmap: ['.WWWWWW.', 'WRRRRRRC', '.WWWWWW.'] },
];

const FIGHTER = ['XX..', 'XXXW', 'XX..'];
const SIDES = [
    { bitmap: FIGHTER, colors: { X: '#ff2d55' }, laser: '#ff5577', facing: 0 },
    { bitmap: FIGHTER, colors: { X: '#39ff14' }, laser: '#aaff66', facing: Math.PI },
];
const STAR_WARS_SIDES = [
    { bitmap: ['S.S....', '.SSS...', 'RSSSSSW', '.SSS...', 'S.S....'], colors: {}, laser: '#ff4040', facing: 0 },
    { bitmap: ['D...D', 'D.S.D', 'DSCSD', 'D.S.D', 'D...D'], colors: {}, laser: '#5bff5b', facing: Math.PI },
];

document.querySelectorAll('svg[data-pixel-map]').forEach((svg) => {
    const { width, height } = svg.viewBox.baseVal;
    const cols = Math.ceil(width / CELL);
    const rows = Math.ceil(height / CELL);

    const canvas = document.createElement('canvas');
    canvas.width = cols * SCALE;
    canvas.height = rows * SCALE;
    canvas.className = svg.getAttribute('class') ?? '';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', svg.getAttribute('aria-label') ?? '');

    const layer = document.createElement('canvas');
    layer.width = canvas.width;
    layer.height = canvas.height;
    const ctx = layer.getContext('2d');
    const view = canvas.getContext('2d');
    const probe = document.createElement('canvas').getContext('2d');
    const owner = new Array(cols * rows).fill(-1);
    const countries = [];

    svg.querySelectorAll('path').forEach((path, index) => {
        const style = getComputedStyle(path);
        const box = path.getBBox();
        const shape = new Path2D(path.getAttribute('d'));
        const title = path.querySelector('title')?.textContent ?? '';
        countries.push({ title, visited: title.includes(':'), opacity: parseFloat(style.fillOpacity) });

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

    const background = ctx.createLinearGradient(0, 0, 0, canvas.height);
    background.addColorStop(0, '#12062b');
    background.addColorStop(1, '#2a0a3d');
    ctx.fillStyle = background;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const dot = (x, y, color, radius, glow) => {
        ctx.shadowColor = color;
        ctx.shadowBlur = glow;
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc((x + 0.5) * SCALE, (y + 0.5) * SCALE, radius, 0, Math.PI * 2);
        ctx.fill();
    };

    for (let position = 0; position < owner.length; position++) {
        const x = position % cols;
        const y = Math.floor(position / cols);
        const country = countries[owner[position]];

        if (!country) {
            dot(x, y, 'rgba(120, 80, 200, 0.12)', 0.7, 0);
        } else if (country.visited) {
            const heat = Math.round(180 + 75 * country.opacity);
            dot(x, y, `rgb(255, ${255 - heat + 60}, ${heat})`, SCALE * 0.42, 8);
        } else {
            dot(x, y, 'rgba(0, 229, 255, 0.45)', SCALE * 0.3, 3);
        }
    }

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
                view.shadowBlur = 6;
                view.fillStyle = color;
                view.beginPath();
                view.arc((columnIndex - bitmapWidth / 2 + 0.5) * FLY, (rowIndex - bitmapHeight / 2 + 0.5) * FLY, FLY * 0.5, 0, Math.PI * 2);
                view.fill();
            });
        });
        view.restore();
    };

    const createFlyer = (kind, now) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 40;
        const travel = canvas.width + margin * 2;
        const baseY = canvas.height * (0.4 + Math.random() * 0.3);
        const lift = canvas.height * (0.14 + Math.random() * 0.16);
        const duration = (travel / (kind.speed * SCALE)) * 1000;

        return {
            update: (time) => time - now < duration,
            draw: (time) => {
                const t = (time - now) / duration;
                const dx = forward * travel;
                const dy = lift * 4 * (2 * t - 1);
                const x = forward > 0 ? -margin + t * travel : canvas.width + margin - t * travel;
                const y = baseY - lift * (1 - (2 * t - 1) ** 2) + Math.sin(t * 40) * kind.bob * FLY;
                drawBitmap(kind.bitmap, x, y, kind.upright ? 0 : Math.atan2(dy, dx));

                if (kind.rotor) {
                    view.save();
                    view.translate(x, y);
                    view.rotate(((time - now) / 40) % (Math.PI * 2));
                    view.strokeStyle = 'rgba(255, 255, 255, 0.7)';
                    view.lineWidth = 1.5;
                    view.beginPath();
                    view.moveTo(-kind.rotor, 0);
                    view.lineTo(kind.rotor, 0);
                    view.moveTo(0, -kind.rotor);
                    view.lineTo(0, kind.rotor);
                    view.stroke();
                    view.restore();
                }
            },
        };
    };

    const createBattle = (now) => {
        const centerX = canvas.width * (0.25 + Math.random() * 0.5);
        const centerY = canvas.height * (0.3 + Math.random() * 0.3);
        const gap = 70;
        const sides = Math.random() < 0.5 ? STAR_WARS_SIDES : SIDES;
        const ships = sides.flatMap((side, sideIndex) => [0, 1, 2].map((i) => ({
            side: sideIndex,
            x: centerX + (sideIndex === 0 ? -gap : gap) + (Math.random() - 0.5) * 20,
            y: centerY + (i - 1) * 21 + (Math.random() - 0.5) * 10,
            phase: Math.random() * 6,
            hp: 2,
            alive: true,
        })));
        const lasers = [];
        const blasts = [];
        let nextShot = now + 600;
        let endedAt = null;

        const explode = (x, y, time, big) => blasts.push({ x, y, start: time, duration: big ? 700 : 350, radius: big ? 16 : 7 });

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
                    drawBitmap(side.bitmap, ship.x, ship.y + Math.sin(time / 400 + ship.phase) * 3, side.facing, side.colors);
                });
                lasers.forEach((laser) => {
                    const t = (time - laser.start) / laser.duration;
                    const x = laser.from.x + (laser.target.x - laser.from.x) * t;
                    const y = laser.from.y + (laser.target.y - laser.from.y) * t;
                    const tail = Math.max(0, t - 0.12);
                    view.strokeStyle = sides[laser.from.side].laser;
                    view.shadowColor = sides[laser.from.side].laser;
                    view.shadowBlur = 8;
                    view.lineWidth = 1.5;
                    view.beginPath();
                    view.moveTo(laser.from.x + (laser.target.x - laser.from.x) * tail, laser.from.y + (laser.target.y - laser.from.y) * tail);
                    view.lineTo(x, y);
                    view.stroke();
                });
                blasts.forEach((blast) => {
                    const t = (time - blast.start) / blast.duration;
                    view.globalAlpha = 1 - t;
                    view.fillStyle = t < 0.5 ? '#ffe600' : '#ff8a00';
                    view.shadowColor = '#ff8a00';
                    view.shadowBlur = 10;
                    for (let i = 0; i < 8; i++) {
                        const angle = (i / 8) * Math.PI * 2;
                        view.beginPath();
                        view.arc(blast.x + Math.cos(angle) * blast.radius * t, blast.y + Math.sin(angle) * blast.radius * t, 1.5, 0, Math.PI * 2);
                        view.fill();
                    }
                    view.globalAlpha = 1;
                });
                view.shadowBlur = 0;
            },
        };
    };

    const actors = [];
    let running = false;

    const render = (time = 0) => {
        view.drawImage(layer, 0, 0);

        for (let i = actors.length - 1; i >= 0; i--) {
            if (actors[i].update(time)) {
                actors[i].draw(time);
            } else {
                actors.splice(i, 1);
            }
        }
        view.shadowBlur = 0;

        view.fillStyle = 'rgba(0, 0, 0, 0.28)';
        for (let y = 0; y < canvas.height; y += 3) {
            view.fillRect(0, y, canvas.width, 1);
        }
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
            if (!battleActive && Math.random() < 0.2) {
                const battle = createBattle(now);
                battle.isBattle = true;
                actors.push(battle);
            } else {
                actors.push(createFlyer(FLYERS[Math.floor(Math.random() * FLYERS.length)], now));
            }
            if (!running) {
                running = true;
                requestAnimationFrame(frame);
            }
        }
        if (canvas.isConnected) {
            setTimeout(spawn, 5000 + Math.random() * 8000);
        }
    };

    render();

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        setTimeout(spawn, 2000 + Math.random() * 2000);
    }

    canvas.addEventListener('mousemove', (event) => {
        const rect = canvas.getBoundingClientRect();
        const x = Math.floor(((event.clientX - rect.left) / rect.width) * cols);
        const y = Math.floor(((event.clientY - rect.top) / rect.height) * rows);
        const index = owner[y * cols + x] ?? -1;
        canvas.title = index >= 0 ? countries[index].title : '';
    });

    svg.replaceWith(canvas);
});
