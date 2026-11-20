const CELL = 2;
const SCALE = 6;

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

    const ctx = canvas.getContext('2d');
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

    ctx.shadowBlur = 0;
    ctx.fillStyle = 'rgba(0, 0, 0, 0.28)';
    for (let y = 0; y < canvas.height; y += 3) {
        ctx.fillRect(0, y, canvas.width, 1);
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
