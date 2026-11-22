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
    L: '#ff2d55',
    M: '#ffe600',
};

const blinkColors = (time) => (Math.floor(time / 300) % 2 === 0 ? { L: '#ff2d55', M: '#ffe600' } : { L: '#ffe600', M: '#ff2d55' });

const FLY = 2;

const PLANE = ['....L......', '....WW.....', 'WW...WW....', 'LWWWWWWWWWC', 'WW...WW....', '....WW.....', '....M......'];
const UFO = ['..SLS..', '.DDDDD.', 'SDDSDDS', 'LDSWSDL', 'SDDSDDS', '.DDDDD.', '..SLS..'];

const JET = ['..L....', '..WW...', 'WWWWWWC', '..WW...', '..M....'];

const COW = ['.WW.', 'WWWW', 'W..W'];
const UFO_KIND = { name: 'ufo', speed: 11, bob: 1.5, upright: true, blink: true, bitmap: UFO };

const FLYERS = [
    { name: 'plane', speed: 16, bob: 0, blink: true, crashable: true, bitmap: PLANE },
];

const ROSWELL = { longitude: -104.52, latitude: 33.39 };

const SIDES = [
    { bitmap: UFO, colors: { S: '#ff2d55', D: '#8a1230' }, laser: '#ff5577', facing: 0 },
    { bitmap: UFO, colors: {}, laser: '#dfe3ff', facing: 0 },
];

const mix = (from, to, amount) => `rgb(${from.map((value, index) => Math.round(value + (to[index] - value) * amount)).join(', ')})`;

const THEMES = {
    ocean: {
        label: 'Ocean',
        sea: ['#12062b', '#2a0a3d'],
        land: 'rgb(0, 180, 205)',
        visited: (heat) => mix([255, 150, 20], [255, 235, 90], heat),
    },
    nightlights: {
        label: 'Nightlights',
        sea: ['#010208', '#050a1c'],
        land: 'rgb(44, 58, 98)',
        visited: (heat) => mix([255, 170, 60], [255, 240, 170], heat),
        night: true,
        sky: true,
    },
    terminal: {
        label: 'Terminal',
        sea: ['#020a04', '#04140a'],
        land: 'rgb(20, 105, 55)',
        visited: (heat) => mix([34, 197, 94], [190, 255, 170], heat),
    },
    ghosty: {
        label: 'Ghosty',
        sea: ['#05060d', '#0d1020'],
        land: 'rgb(105, 115, 150)',
        visited: (heat) => mix([140, 150, 190], [235, 240, 255], heat),
        fade: 0.55,
        blur: 1.1,
        mist: true,
    },
};

const DAY_SECONDS = 120;
const RADAR_PERIOD = 6000;
const EQUATOR_Y = 84;
const DEGREES = Math.PI / 180;

const STYLE_KEY = 'lore.map-style';

const SOUND_KEY = 'lore.map-sound';

const sound = {
    wanted: false,
    context: null,
    master: null,
    noiseBuffer: null,
    onchange: null,

    ready() {
        return this.wanted && this.context !== null && this.context.state === 'running' && !document.hidden;
    },

    async enable() {
        this.wanted = true;
        if (this.context === null) {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) {
                this.wanted = false;

                return false;
            }
            this.context = new Context();
            this.context.onstatechange = () => this.onchange?.();
            this.master = this.context.createGain();
            this.master.gain.value = 1;
            this.master.connect(this.context.destination);
            this.noiseBuffer = this.context.createBuffer(1, this.context.sampleRate, this.context.sampleRate);
            const data = this.noiseBuffer.getChannelData(0);
            for (let i = 0; i < data.length; i++) {
                data[i] = Math.random() * 2 - 1;
            }
        }
        await this.context.resume();

        return true;
    },

    disable() {
        this.wanted = false;
    },

    output(position, volume, duration) {
        const gain = this.context.createGain();
        const now = this.context.currentTime;
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(volume, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);
        let tail = gain;
        if (this.context.createStereoPanner) {
            const panner = this.context.createStereoPanner();
            panner.pan.value = Math.max(-1, Math.min(1, position * 2 - 1));
            gain.connect(panner);
            tail = panner;
        }
        tail.connect(this.master);

        return gain;
    },

    tone(type, from, to, duration, volume, position, wobble = 0) {
        const now = this.context.currentTime;
        const oscillator = this.context.createOscillator();
        oscillator.type = type;
        oscillator.frequency.setValueAtTime(from, now);
        oscillator.frequency.exponentialRampToValueAtTime(to, now + duration);
        oscillator.connect(this.output(position, volume, duration));
        if (wobble > 0) {
            const lfo = this.context.createOscillator();
            const depth = this.context.createGain();
            lfo.frequency.value = 6;
            depth.gain.value = wobble;
            lfo.connect(depth);
            depth.connect(oscillator.frequency);
            lfo.start(now);
            lfo.stop(now + duration + 0.05);
        }
        oscillator.start(now);
        oscillator.stop(now + duration + 0.05);
    },

    noise(from, to, duration, volume, position) {
        const now = this.context.currentTime;
        const source = this.context.createBufferSource();
        source.buffer = this.noiseBuffer;
        source.loop = true;
        const filter = this.context.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(from, now);
        filter.frequency.exponentialRampToValueAtTime(to, now + duration);
        source.connect(filter);
        filter.connect(this.output(position, volume, duration));
        source.start(now);
        source.stop(now + duration + 0.05);
    },

    play(name, position = 0.5) {
        if (!this.ready()) {
            return;
        }
        switch (name) {
            case 'ping':
                this.tone('sine', 660, 990, 0.3, 0.4, position);
                break;
            case 'laser':
                this.tone('square', 1100, 180, 0.2, 0.16, position);
                break;
            case 'boom':
                this.noise(1400, 70, 0.7, 0.8, position);
                this.tone('sine', 110, 30, 0.6, 0.8, position);
                break;
            case 'pop':
                this.noise(2200, 200, 0.3, 0.4, position);
                break;
            case 'whine':
                this.tone('sawtooth', 1300, 140, 1.4, 0.12, position);
                break;
            case 'ufo':
                this.tone('sine', 330, 300, 1.8, 0.25, position, 40);
                break;
            case 'beam':
                this.tone('sine', 180, 700, 0.6, 0.25, position, 25);
                this.tone('triangle', 90, 95, 3.2, 0.2, position, 8);
                break;
            case 'zap':
                this.tone('sawtooth', 180, 3000, 0.35, 0.2, position);
                this.noise(6000, 300, 0.4, 0.2, position);
                break;
            case 'jet':
                this.noise(300, 2200, 1.2, 0.3, position);
                break;
            default:
                break;
        }
    },
};

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
    sea.style.position = 'relative';

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
            const brightness = theme.night ? (country.visited ? 0.25 + 0.75 * (1 - light) : 0.35 + 0.4 * light) : 0.2 + 0.8 * light;
            ctx.globalAlpha = (Math.round(brightness * 16) / 16) * (theme.fade ?? 1);
            ctx.fillStyle = country.visited ? theme.visited(country.opacity) : theme.land;
            disc(position % cols, Math.floor(position / cols), country.visited ? visitedDiameter : landDiameter);
        }
        ctx.globalAlpha = 1;
        ctx.restore();
    };

    const paintSea = () => {
        const theme = THEMES[styleName];
        sea.style.backgroundImage = `linear-gradient(to bottom, ${theme.sea[0]}, ${theme.sea[1]})`;
        canvas.style.filter = theme.blur ? `blur(${theme.blur}px)` : '';
    };

    const MAX_CSS_WIDTH = 1024;

    let fullscreen = false;
    const TICKER_ROOM = 22;

    const resize = () => {
        const available = fullscreen
            ? Math.min(sea.clientWidth, ((sea.clientHeight - TICKER_ROOM) * cols) / rows)
            : Math.min(sea.clientWidth, MAX_CSS_WIDTH);
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

    const play = (name, x = W / 2) => sound.play(name, x / W);

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
        let whined = false;
        play(kind.name === 'ufo' ? 'ufo' : 'jet', start.x);

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
                if (!whined && time - now >= cruise) {
                    whined = true;
                    play('whine', peak.x);
                }
                if (impactAt === null && time - now >= cruise + fall) {
                    impactAt = time;
                    play('boom', impact.x);
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
                    drawBitmap(kind.bitmap, at.x, at.y, at.angle, kind.blink ? blinkColors(time) : {});
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
        play('jet', forward > 0 ? 0 : W);

        return {
            update: (time) => time - now < duration,
            draw: (time) => {
                const t = (time - now) / duration;
                const dx = forward * travel;
                const dy = lift * 4 * (2 * t - 1);
                const x = forward > 0 ? -margin + t * travel : W + margin - t * travel;
                const y = baseY - lift * (1 - (2 * t - 1) ** 2) + Math.sin(t * 40) * kind.bob * FLY;
                drawBitmap(kind.bitmap, x, y, kind.upright ? 0 : Math.atan2(dy, dx), kind.blink ? blinkColors(time) : {});
            },
        };
    };

    const createBattle = (now) => {
        const centerX = W * (0.25 + Math.random() * 0.5);
        const centerY = H * (0.3 + Math.random() * 0.3);
        const gap = 52;
        const sides = SIDES;
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
        play('ufo', centerX);
        let endedAt = null;

        const explode = (x, y, time, big) => (play(big ? 'boom' : 'pop', x), blasts.push({ x, y, start: time, duration: big ? 700 : 350, radius: big ? 11 : 5 }));

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
                    play('laser', shooterShip.x);
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
                    drawBitmap(side.bitmap, ship.x, ship.y + Math.sin(time / 400 + ship.phase) * 2, side.facing, { ...side.colors, ...blinkColors(time) });
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

    const createChase = (now) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 40;
        const travel = W + margin * 2;
        const duration = (travel / (18 * SCALE)) * 1000;
        const baseY = H * (0.3 + Math.random() * 0.35);
        const weave = 14 + Math.random() * 10;
        const doomed = Math.random() < 0.4;
        const hitAt = now + duration * (0.45 + Math.random() * 0.2);
        const lags = [500, 800, 1100].slice(0, 2 + (Math.random() < 0.5 ? 1 : 0));
        const shots = [];
        const blasts = [];
        const puffs = [];
        let nextShot = now + 600;
        let nextPuff = now;
        let downAt = null;
        let downFrom = null;
        let crashed = false;
        play('jet', forward > 0 ? 0 : W);
        play('ufo', forward > 0 ? 0 : W);

        const leadAt = (time) => {
            const t = (time - now) / duration;

            return {
                x: forward > 0 ? -margin + t * travel : W + margin - t * travel,
                y: baseY + Math.sin(t * 16) * weave,
            };
        };
        const jetAt = (lag, time) => {
            const here = leadAt(time - lag);
            const ahead = leadAt(time - lag + 30);

            return { x: here.x, y: here.y, angle: Math.atan2(ahead.y - here.y, ahead.x - here.x) };
        };
        const ufoAt = (time) => {
            if (downAt === null) {
                return leadAt(time);
            }
            const t = Math.min(1, (time - downAt) / 1300);

            return { x: downFrom.x + forward * 24 * t, y: downFrom.y + (H * 0.86 - downFrom.y) * t * t, angle: t * 5 };
        };

        return {
            update: (time) => {
                const ufo = ufoAt(time);
                if (downAt === null && time >= nextShot && time - now < duration * 0.9) {
                    nextShot = time + 500 + Math.random() * 400;
                    const lag = lags[Math.floor(Math.random() * lags.length)];
                    shots.push({ lag, start: time, duration: 320 });
                    play('laser', jetAt(lag, time).x);
                }
                for (let i = shots.length - 1; i >= 0; i--) {
                    if (time - shots[i].start < shots[i].duration) {
                        continue;
                    }
                    if (doomed && downAt === null && time >= hitAt) {
                        downAt = time;
                        downFrom = leadAt(time);
                        play('boom', downFrom.x);
                        play('whine', downFrom.x);
                        blasts.push({ x: downFrom.x, y: downFrom.y, start: time, duration: 500, radius: 9 });
                    } else if (downAt === null) {
                        play('pop', ufo.x);
                        blasts.push({ x: ufo.x + (Math.random() - 0.5) * 30, y: ufo.y + (Math.random() - 0.5) * 30, start: time, duration: 350, radius: 5 });
                    }
                    shots.splice(i, 1);
                }
                if (downAt !== null && !crashed) {
                    if (time >= nextPuff) {
                        nextPuff = time + 50;
                        puffs.push({ x: ufo.x, y: ufo.y, start: time, life: 900 });
                    }
                    if (time - downAt >= 1300) {
                        crashed = true;
                        play('boom', ufo.x);
                        for (let i = 0; i < 4; i++) {
                            blasts.push({ x: ufo.x + (Math.random() - 0.5) * 12, y: ufo.y + (Math.random() - 0.5) * 6, start: time + i * 130, duration: 600, radius: 7 + Math.random() * 5 });
                        }
                    }
                }
                for (let i = puffs.length - 1; i >= 0; i--) {
                    if (time - puffs[i].start >= puffs[i].life) {
                        puffs.splice(i, 1);
                    }
                }
                for (let i = blasts.length - 1; i >= 0; i--) {
                    if (time - blasts[i].start >= blasts[i].duration) {
                        blasts.splice(i, 1);
                    }
                }

                return time - now < duration + 2000;
            },
            draw: (time) => {
                const ufo = ufoAt(time);
                puffs.forEach((puff) => {
                    const t = (time - puff.start) / puff.life;
                    view.globalAlpha = (1 - t) * 0.7;
                    view.fillStyle = t < 0.3 ? '#ff8a00' : '#6c7090';
                    view.beginPath();
                    view.arc(puff.x, puff.y - t * 4, 1 + t * 2.5, 0, Math.PI * 2);
                    view.fill();
                });
                view.globalAlpha = 1;

                if (!crashed) {
                    drawBitmap(UFO, ufo.x, ufo.y, ufo.angle ?? 0, blinkColors(time));
                }
                lags.forEach((lag) => {
                    const jet = jetAt(lag, time);
                    drawBitmap(JET, jet.x, jet.y, jet.angle, blinkColors(time));
                });
                shots.forEach((shot) => {
                    const jet = jetAt(shot.lag, time);
                    const t = (time - shot.start) / shot.duration;
                    const tail = Math.max(0, t - 0.15);
                    view.strokeStyle = '#ff5577';
                    view.shadowColor = '#ff5577';
                    view.shadowBlur = 8 * res;
                    view.lineWidth = 1;
                    view.beginPath();
                    view.moveTo(jet.x + (ufo.x - jet.x) * tail, jet.y + (ufo.y - jet.y) * tail);
                    view.lineTo(jet.x + (ufo.x - jet.x) * t, jet.y + (ufo.y - jet.y) * t);
                    view.stroke();
                });
                drawBlasts(blasts, time);
            },
        };
    };

    const createAbduction = (now) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 30;
        const startX = forward > 0 ? -margin : W + margin;
        const hover = { x: W * (0.2 + Math.random() * 0.6), y: H * (0.2 + Math.random() * 0.15) };
        const ground = Math.min(H * 0.86, hover.y + H * 0.3);
        const arrive = (Math.abs(hover.x - startX) / (18 * SCALE)) * 1000;
        const linger = 3400;
        const leave = 1300;
        let beamed = false;
        let left = false;
        play('ufo', startX);

        const position = (time) => {
            const elapsed = time - now;
            if (elapsed < arrive) {
                const t = elapsed / arrive;

                return { x: startX + (hover.x - startX) * t, y: hover.y - (1 - t) * 14 + Math.sin(elapsed / 90) * 1.5 };
            }
            if (elapsed < arrive + linger) {
                return { x: hover.x, y: hover.y + Math.sin(elapsed / 220) * 1.5 };
            }
            const t = Math.min(1, (elapsed - arrive - linger) / leave);

            return { x: hover.x + forward * 520 * t * t, y: hover.y - 260 * t * t };
        };

        return {
            update: (time) => {
                if (!beamed && time - now >= arrive) {
                    beamed = true;
                    play('beam', hover.x);
                }
                if (!left && time - now >= arrive + linger) {
                    left = true;
                    play('zap', hover.x);
                }

                return time - now < arrive + linger + leave;
            },
            draw: (time) => {
                const elapsed = time - now;
                const at = position(time);
                const lifting = elapsed - arrive;
                if (lifting > 0 && lifting < linger) {
                    const beam = Math.min(1, lifting / 500) * Math.min(1, (linger - lifting) / 400);
                    view.globalAlpha = beam * (0.22 + Math.sin(time / 60) * 0.06);
                    view.fillStyle = '#b6ff8a';
                    view.beginPath();
                    view.moveTo(at.x - 3, at.y + 6);
                    view.lineTo(at.x + 3, at.y + 6);
                    view.lineTo(at.x + 15, ground);
                    view.lineTo(at.x - 15, ground);
                    view.fill();
                    view.globalAlpha = 1;

                    const rise = Math.min(1, lifting / (linger - 300));
                    drawBitmap(COW, at.x, ground - (ground - at.y - 8) * rise * rise, Math.sin(time / 250) * 0.4, {});
                }
                drawBitmap(UFO, at.x, at.y, 0, blinkColors(time));
            },
        };
    };

    const createWarp = (now) => {
        const forward = Math.random() < 0.5 ? 1 : -1;
        const margin = 30;
        const startX = forward > 0 ? -margin : W + margin;
        const baseY = H * (0.25 + Math.random() * 0.4);
        const warpX = W * (0.3 + Math.random() * 0.4);
        const cruise = (Math.abs(warpX - startX) / (16 * SCALE)) * 1000;
        const flash = 900;
        let zapped = false;
        play('ufo', startX);

        return {
            update: (time) => {
                if (!zapped && time - now >= cruise) {
                    zapped = true;
                    play('zap', warpX);
                }

                return time - now < cruise + flash;
            },
            draw: (time) => {
                const elapsed = time - now;
                if (elapsed < cruise) {
                    const t = elapsed / cruise;
                    drawBitmap(UFO, startX + (warpX - startX) * t, baseY + Math.sin(t * 18) * 8, 0, blinkColors(time));

                    return;
                }
                const t = (elapsed - cruise) / flash;
                view.strokeStyle = '#bfefff';
                view.fillStyle = '#ffffff';
                view.shadowColor = '#7fdfff';
                view.shadowBlur = 10 * res;
                view.globalAlpha = 1 - t;
                view.lineWidth = 1.5;
                view.beginPath();
                view.arc(warpX, baseY, 3 + t * 34, 0, Math.PI * 2);
                view.stroke();
                for (let i = 0; i < 10; i++) {
                    const angle = (i / 10) * Math.PI * 2;
                    view.beginPath();
                    view.arc(warpX + Math.cos(angle) * t * 46, baseY + Math.sin(angle) * t * 46, 1.2, 0, Math.PI * 2);
                    view.fill();
                }
                view.beginPath();
                view.arc(warpX, baseY, Math.max(0, 7 * (1 - t * 3)), 0, Math.PI * 2);
                view.fill();
                view.globalAlpha = 1;
                view.shadowBlur = 0;
            },
        };
    };

    const actors = [];
    let running = false;

    const radarOn = () => animateSun && styleName === 'terminal';

    const mistOn = () => Boolean(THEMES[styleName].mist);

    const skyOn = () => Boolean(THEMES[styleName].sky);

    const STARS = Array.from({ length: 140 }, (_, index) => ({
        x: (index * 0.6180339) % 1,
        y: (index * 0.7548777 + Math.sin(index) * 0.1 + 1) % 1,
        size: 0.4 + ((index * 37) % 10) / 10,
        seconds: 2200 + ((index * 131) % 3800),
        phase: index * 1.9,
    }));

    const SHOOTING_PERIOD = 14000;

    const skyLayer = document.createElement('canvas');
    skyLayer.setAttribute('aria-hidden', 'true');
    skyLayer.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:-1;display:none';
    sea.style.isolation = 'isolate';
    sea.prepend(skyLayer);
    const sky = skyLayer.getContext('2d');

    const drawSky = (time) => {
        if (!skyOn()) {
            skyLayer.style.display = 'none';

            return;
        }
        const width = sea.clientWidth;
        const height = sea.clientHeight;
        if (!width || !height || !canvas.offsetWidth) {
            return;
        }
        const ratio = window.devicePixelRatio || 1;
        if (skyLayer.width !== Math.round(width * ratio) || skyLayer.height !== Math.round(height * ratio)) {
            skyLayer.width = Math.round(width * ratio);
            skyLayer.height = Math.round(height * ratio);
        }
        skyLayer.style.display = 'block';
        sky.setTransform(ratio, 0, 0, ratio, 0, 0);
        sky.clearRect(0, 0, width, height);
        const moment = animateSun ? time : 0;
        STARS.forEach((star) => {
            const twinkle = 0.35 + 0.65 * (Math.sin(moment / star.seconds * Math.PI * 2 + star.phase) * 0.5 + 0.5);
            sky.globalAlpha = twinkle * 0.8;
            sky.fillStyle = star.size > 1.1 ? '#cfe0ff' : '#ffffff';
            sky.fillRect(Math.round(star.x * width), Math.round(star.y * height), star.size, star.size);
        });

        const cycle = Math.floor(moment / SHOOTING_PERIOD);
        const progress = (moment % SHOOTING_PERIOD) / 1600;
        if (animateSun && progress < 1) {
            const startX = (((cycle * 0.37) % 0.6) + 0.1) * width;
            const startY = (((cycle * 0.53) % 0.4) + 0.05) * height;
            const headX = startX + progress * width * 0.22;
            const headY = startY + progress * height * 0.16;
            const trail = sky.createLinearGradient(headX, headY, headX - width * 0.07, headY - height * 0.05);
            trail.addColorStop(0, 'rgba(255, 255, 255, 0.9)');
            trail.addColorStop(1, 'rgba(255, 255, 255, 0)');
            sky.globalAlpha = Math.sin(progress * Math.PI);
            sky.strokeStyle = trail;
            sky.lineWidth = 1.2;
            sky.beginPath();
            sky.moveTo(headX, headY);
            sky.lineTo(headX - width * 0.07, headY - height * 0.05);
            sky.stroke();
        }
        sky.globalAlpha = 1;
    };

    const MIST = Array.from({ length: 13 }, (_, index) => ({
        x: (index * 0.618) % 1,
        y: 0.08 + ((index * 0.37) % 0.84),
        radius: 0.11 + ((index * 0.07) % 0.12),
        seconds: 38 + ((index * 53) % 170),
        sway: 0.02 + ((index * 0.013) % 0.04),
        lobes: Array.from({ length: 7 }, (_, lobe) => ({
            dx: (((lobe * 0.61 + index * 0.37) % 1) * 2 - 1) * 1.6,
            dy: (((lobe * 0.43 + index * 0.29) % 1) * 2 - 1) * 0.45,
            size: 0.6 + ((lobe * 0.27 + index * 0.11) % 0.6),
            drift: 3000 + lobe * 900 + index * 400,
            phase: lobe * 2.1 + index,
        })),
    }));

    const mistLayer = document.createElement('canvas');
    mistLayer.setAttribute('aria-hidden', 'true');
    mistLayer.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:1;display:none';
    sea.append(mistLayer);
    const mist = mistLayer.getContext('2d');

    const drawMist = (time) => {
        if (!mistOn()) {
            mistLayer.style.display = 'none';

            return;
        }
        const width = sea.clientWidth;
        const height = sea.clientHeight;
        if (!width || !height) {
            return;
        }
        const ratio = window.devicePixelRatio || 1;
        if (mistLayer.width !== Math.round(width * ratio) || mistLayer.height !== Math.round(height * ratio)) {
            mistLayer.width = Math.round(width * ratio);
            mistLayer.height = Math.round(height * ratio);
        }
        mistLayer.style.display = 'block';
        mist.setTransform(ratio, 0, 0, ratio, 0, 0);
        mist.clearRect(0, 0, width, height);

        const moment = animateSun ? time : 0;
        MIST.forEach((cloud) => {
            const radius = cloud.radius * height;
            const reach = radius * 2.6;
            const span = width + reach * 2;
            const centerX = ((cloud.x * span + (moment / (cloud.seconds * 1000)) * span) % span) - reach;
            const centerY = (cloud.y + Math.sin(moment / 9000 + cloud.x * 9) * cloud.sway) * height;
            cloud.lobes.forEach((lobe) => {
                const size = radius * lobe.size * (1 + Math.sin(moment / lobe.drift + lobe.phase) * 0.12);
                const x = centerX + lobe.dx * radius + Math.sin(moment / (lobe.drift * 1.7) + lobe.phase) * radius * 0.15;
                const y = centerY + lobe.dy * radius;
                const puff = mist.createRadialGradient(x, y, 0, x, y, size);
                puff.addColorStop(0, 'rgba(225, 235, 255, 0.1)');
                puff.addColorStop(0.5, 'rgba(190, 205, 250, 0.055)');
                puff.addColorStop(1, 'rgba(170, 185, 240, 0)');
                mist.fillStyle = puff;
                mist.fillRect(x - size, y - size, size * 2, size * 2);
            });
        });
    };

    const drawRadar = (time) => {
        const centerX = W / 2;
        const centerY = H / 2;
        const radius = Math.hypot(W, H) / 2;
        const angle = ((time % RADAR_PERIOD) / RADAR_PERIOD) * Math.PI * 2 - Math.PI / 2;
        const wedge = 1.2;

        view.save();
        view.globalCompositeOperation = 'lighter';

        view.strokeStyle = 'rgba(57, 255, 20, 0.14)';
        view.lineWidth = 1;
        [0.25, 0.5, 0.75].forEach((fraction) => {
            view.beginPath();
            view.arc(centerX, centerY, radius * fraction, 0, Math.PI * 2);
            view.stroke();
        });
        view.beginPath();
        view.moveTo(centerX - radius, centerY);
        view.lineTo(centerX + radius, centerY);
        view.moveTo(centerX, centerY - radius);
        view.lineTo(centerX, centerY + radius);
        view.stroke();

        const sweep = view.createConicGradient(angle - wedge, centerX, centerY);
        const edge = wedge / (Math.PI * 2);
        sweep.addColorStop(0, 'rgba(57, 255, 20, 0)');
        sweep.addColorStop(edge, 'rgba(57, 255, 20, 0.3)');
        sweep.addColorStop(Math.min(1, edge + 0.0001), 'rgba(57, 255, 20, 0)');
        sweep.addColorStop(1, 'rgba(57, 255, 20, 0)');
        view.fillStyle = sweep;
        view.fillRect(0, 0, W, H);

        view.strokeStyle = 'rgba(170, 255, 140, 0.85)';
        view.shadowColor = '#39ff14';
        view.shadowBlur = 6 * res;
        view.lineWidth = 1.5;
        view.beginPath();
        view.moveTo(centerX, centerY);
        view.lineTo(centerX + Math.cos(angle) * radius, centerY + Math.sin(angle) * radius);
        view.stroke();
        view.restore();
    };

    const render = (time = 0) => {
        view.save();
        view.setTransform(1, 0, 0, 1, 0, 0);
        view.clearRect(0, 0, canvas.width, canvas.height);
        view.drawImage(layer, 0, 0);
        view.restore();

        if (radarOn()) {
            drawRadar(time);
        }
        drawSky(time);
        drawMist(time);

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
        if ((actors.length || radarOn() || ((mistOn() || skyOn()) && animateSun)) && canvas.isConnected) {
            requestAnimationFrame(frame);
        } else {
            running = false;
        }
    };

    const startLoop = () => {
        if (!running && canvas.isConnected) {
            running = true;
            requestAnimationFrame(frame);
        }
    };

    const spawn = () => {
        const now = performance.now();
        if (!document.hidden && actors.length < 4) {
            const battleActive = actors.some((actor) => actor.isBattle);
            const roll = Math.random();
            if (!battleActive && roll < 0.15) {
                const battle = createBattle(now);
                battle.isBattle = true;
                actors.push(battle);
            } else if (roll < 0.6) {
                const events = [
                    () => createCrash(UFO_KIND, now),
                    () => createCrash(UFO_KIND, now, true),
                    () => createChase(now),
                    () => createChase(now),
                    () => createChase(now),
                    () => createAbduction(now),
                    () => createWarp(now),
                ];
                actors.push(events[Math.floor(Math.random() * events.length)]());
            } else {
                const kind = FLYERS[Math.floor(Math.random() * FLYERS.length)];
                actors.push(kind.crashable && Math.random() < 0.5 ? createCrash(kind, now) : createFlyer(kind, now));
            }
            startLoop();
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

    const newsUrl = svg.dataset.newsUrl;
    if (newsUrl) {
        const bar = document.createElement('div');
        bar.setAttribute('aria-hidden', 'true');
        bar.style.cssText = 'position:absolute;z-index:2;left:0;right:0;bottom:0;display:none;align-items:center;height:22px;overflow:hidden;cursor:pointer;touch-action:manipulation;font:12px/22px ui-monospace,monospace;background:rgba(0,0,0,0.7);border-top:1px solid currentColor';
        const label = document.createElement('span');
        label.style.cssText = 'flex:none;padding:0 8px;font-weight:700;letter-spacing:0.08em;background:#f4f4f5';
        const labelText = document.createElement('span');
        labelText.textContent = 'NEWS';
        labelText.style.color = '#000';
        label.append(labelText);
        const lane = document.createElement('div');
        lane.style.cssText = 'position:relative;flex:1;height:100%;overflow:hidden';
        const track = document.createElement('div');
        track.style.cssText = 'position:absolute;left:0;top:0;white-space:nowrap;will-change:transform';
        lane.append(track);
        bar.append(label, lane);
        sea.append(bar);

        let items = [];
        let loadedAt = 0;
        let index = 0;
        let hovering = false;
        let tapped = false;

        const applyPause = () => {
            track.getAnimations().forEach((animation) => {
                if (hovering || tapped) {
                    animation.pause();
                } else {
                    animation.play();
                }
            });
        };
        bar.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                hovering = true;
                applyPause();
            }
        });
        bar.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                hovering = false;
                applyPause();
            }
        });
        bar.addEventListener('click', () => {
            tapped = !tapped;
            applyPause();
        });
        document.addEventListener('pointerdown', (event) => {
            if (tapped && !bar.contains(event.target)) {
                tapped = false;
                applyPause();
            }
        });

        const load = async () => {
            try {
                const response = await fetch(newsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok) {
                    items = (await response.json()).items ?? [];
                    loadedAt = Date.now();
                }
            } catch {
                // keep scrolling the last headlines
            }
        };

        const chip = (text, background, color) => {
            const span = document.createElement('span');
            span.textContent = text;
            span.style.cssText = `display:inline-block;margin-right:6px;padding:0 6px;font-weight:700;letter-spacing:0.06em;background:${background};color:${color}`;

            return span;
        };

        const renderItem = (item) => {
            const line = document.createElement('span');
            if (item.kind === 'alert') {
                const warning = chip('⚠ ALERT', '#ff2d55', '#fff');
                if (animateSun) {
                    warning.animate([{ opacity: 1 }, { opacity: 0.25 }, { opacity: 1 }], { duration: 900, iterations: Infinity });
                }
                const text = document.createElement('span');
                text.textContent = item.text;
                text.style.cssText = 'font-weight:700;color:#ff6b86;text-shadow:0 0 6px #ff2d55';
                line.append(warning, text);
            } else if (item.kind === 'news') {
                const text = document.createElement('span');
                text.textContent = item.text;
                text.style.cssText = 'font-weight:700;color:#7ee8ff';
                line.append(chip((item.tag || 'NEWS').toUpperCase().slice(0, 14), '#00e5ff', '#000'), text);
            } else {
                line.textContent = item.text;
            }

            return line;
        };

        const run = async () => {
            if (!sea.isConnected) {
                return;
            }
            if (!loadedAt || !items.length || (!animateSun && Date.now() - loadedAt > 120000)) {
                await load();
            }
            if (!items.length) {
                bar.style.display = 'none';
                setTimeout(run, 30000);

                return;
            }

            bar.style.display = 'flex';
            bar.style.color = styleName === 'terminal' ? '#39ff14' : '#e4e4e7';
            track.style.color = bar.style.color;

            if (!animateSun) {
                track.replaceChildren(renderItem(items[index % items.length]));
                index++;
                track.style.transform = 'translateX(8px)';
                setTimeout(run, 8000);

                return;
            }

            const signature = JSON.stringify(items);
            const buildCopy = () => {
                const copy = document.createElement('span');
                items.forEach((item) => {
                    copy.append(renderItem(item), '   ◆   ');
                });

                return copy;
            };
            const copies = [buildCopy()];
            track.replaceChildren(copies[0]);
            const width = copies[0].getBoundingClientRect().width;
            const repeats = width > 0 ? Math.max(1, Math.ceil(lane.clientWidth / width)) : 1;
            const loopWidth = width * repeats;
            for (let count = 1; count < repeats * 2; count++) {
                track.append(buildCopy());
            }
            const cycle = (loopWidth / 70) * 1000;
            track.getAnimations().forEach((animation) => animation.cancel());
            track.animate(
                [{ transform: 'translateX(0)' }, { transform: `translateX(-${loopWidth}px)` }],
                { duration: cycle, easing: 'linear', iterations: Infinity },
            );
            applyPause();

            const refresh = async () => {
                if (!sea.isConnected) {
                    return;
                }
                await load();
                if (JSON.stringify(items) !== signature) {
                    run();

                    return;
                }
                setTimeout(refresh, 120000);
            };
            setTimeout(refresh, 120000);
        };

        setTimeout(run, 2000);
    }

    svg.replaceWith(sea);
    startLoop();

    const soundButton = sea.parentElement.querySelector('button[data-map-sound]');
    if (soundButton) {
        const running = () => sound.wanted && sound.context?.state === 'running';
        const label = () => {
            soundButton.textContent = !sound.wanted ? 'Sound off' : (running() ? 'Sound on' : 'Sound: click to resume');
            soundButton.setAttribute('aria-pressed', String(sound.wanted));
        };
        sound.onchange = label;

        let remembered = false;
        try {
            remembered = localStorage.getItem(SOUND_KEY) === 'on';
        } catch {
            // sound just starts off
        }
        if (remembered) {
            // Browsers only start audio after a click or key press, so resume on the first one anywhere but the button itself.
            sound.enable();
            const unlock = (event) => {
                if (soundButton.contains(event.target)) {
                    return;
                }
                sound.context?.resume();
            };
            const stop = () => {
                if (running()) {
                    ['pointerdown', 'keydown', 'touchend'].forEach((name) => document.removeEventListener(name, unlock));
                }
            };
            ['pointerdown', 'keydown', 'touchend'].forEach((name) => document.addEventListener(name, unlock));
            sound.context?.addEventListener('statechange', stop);
        }
        label();
        soundButton.addEventListener('click', async () => {
            if (running()) {
                sound.disable();
            } else if (await sound.enable()) {
                sound.play('ping');
            }
            try {
                localStorage.setItem(SOUND_KEY, sound.wanted ? 'on' : 'off');
            } catch {
                // the choice just won't be remembered
            }
            label();
        });
    }

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
            startLoop();
        });
    }
    const compact = window.matchMedia('(orientation: landscape) and (max-height: 520px)');
    let dismissed = false;
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.textContent = '✕';
    closeButton.setAttribute('aria-label', 'Leave full screen');
    closeButton.style.cssText = 'position:absolute;top:8px;right:8px;display:none;width:32px;height:32px;border:1px solid rgba(255,255,255,0.3);background:rgba(0,0,0,0.55);color:#e4e4e7;font-size:16px;line-height:1;z-index:2';
    sea.append(closeButton);

    const applyFullscreen = () => {
        const want = compact.matches && !dismissed;
        if (want === fullscreen) {
            return;
        }
        fullscreen = want;
        sea.style.position = want ? 'fixed' : 'relative';
        sea.style.inset = want ? '0' : '';
        sea.style.zIndex = want ? '100' : '';
        sea.style.display = want ? 'flex' : '';
        sea.style.alignItems = want ? 'center' : '';
        sea.style.justifyContent = want ? 'center' : '';
        sea.style.paddingBottom = want ? `${TICKER_ROOM}px` : '';
        sea.style.marginTop = want ? '0' : '';
        canvas.style.maxWidth = want ? 'none' : '';
        closeButton.style.display = want ? 'block' : 'none';
        document.documentElement.style.overflow = want ? 'hidden' : '';
        if (!want && document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
        if (resize()) {
            render(performance.now());
        }
        paintSea();
    };

    compact.addEventListener('change', () => {
        dismissed = false;
        applyFullscreen();
    });
    closeButton.addEventListener('click', () => {
        dismissed = true;
        applyFullscreen();
    });
    canvas.addEventListener('click', () => {
        // The browser only allows real full screen after a tap, so rotating gets the map big and a tap hides the browser bars.
        if (fullscreen && !document.fullscreenElement && sea.requestFullscreen) {
            sea.requestFullscreen().catch(() => {});
        }
    });
    applyFullscreen();

    const observer = new ResizeObserver(() => {
        if (resize()) {
            render(performance.now());
        }
        paintSea();
    });
    observer.observe(canvas);
});
