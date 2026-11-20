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
};

const FLYERS = [
    {
        name: 'plane',
        speed: 16,
        bob: 0,
        bitmap: [
            '...W...',
            'W..W...',
            'WWWWWWC',
            'W..W...',
            '...W...',
        ],
    },
    {
        name: 'ufo',
        speed: 11,
        bob: 1.5,
        bitmap: [
            '.GGG.',
            'GCYCG',
            'GYCYG',
            'GCYCG',
            '.GGG.',
        ],
    },
    {
        name: 'santa',
        speed: 13,
        bob: 1,
        bitmap: [
            'RRR...B.B',
            'RWRYYYBBB',
            'RRR...B.B',
        ],
    },
    {
        name: 'rocket',
        speed: 26,
        bob: 0,
        bitmap: [
            '.R....',
            'OYWWWC',
            '.R....',
        ],
    },
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

    const render = (flyer = null, now = 0) => {
        view.drawImage(layer, 0, 0);

        if (flyer) {
            const elapsed = (now - flyer.start) / 1000;
            const offset = elapsed * flyer.kind.speed;
            const bitmapWidth = Math.max(...flyer.kind.bitmap.map((row) => row.length));
            const head = flyer.leftToRight ? offset - bitmapWidth : cols - offset;
            const lift = Math.sin(elapsed * 2.2) * flyer.kind.bob;

            flyer.kind.bitmap.forEach((row, rowIndex) => {
                [...row].forEach((symbol, columnIndex) => {
                    if (symbol === '.') {
                        return;
                    }
                    const column = flyer.leftToRight ? columnIndex : row.length - 1 - columnIndex;
                    const x = Math.round(head + column + (flyer.leftToRight ? 0 : bitmapWidth - row.length));
                    const y = Math.round(flyer.row + lift + rowIndex);
                    view.shadowColor = PALETTE[symbol];
                    view.shadowBlur = 10;
                    view.fillStyle = PALETTE[symbol];
                    view.beginPath();
                    view.arc((x + 0.5) * SCALE, (y + 0.5) * SCALE, SCALE * 0.42, 0, Math.PI * 2);
                    view.fill();
                });
            });
            view.shadowBlur = 0;
            flyer.finished = head > cols || head < -bitmapWidth - 1;
        }

        view.fillStyle = 'rgba(0, 0, 0, 0.28)';
        for (let y = 0; y < canvas.height; y += 3) {
            view.fillRect(0, y, canvas.width, 1);
        }
    };

    render();

    const launch = () => {
        const kind = FLYERS[Math.floor(Math.random() * FLYERS.length)];
        const flyer = {
            kind,
            leftToRight: Math.random() < 0.5,
            row: 4 + Math.floor(Math.random() * Math.max(1, rows * 0.5 - kind.bitmap.length)),
            start: performance.now(),
            finished: false,
        };
        const frame = (now) => {
            render(flyer, now);
            if (flyer.finished) {
                schedule();
            } else {
                requestAnimationFrame(frame);
            }
        };
        requestAnimationFrame(frame);
    };

    const schedule = () => setTimeout(launch, 12000 + Math.random() * 18000);

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        setTimeout(launch, 3000 + Math.random() * 3000);
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
