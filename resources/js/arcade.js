const root = document.querySelector('[data-arcade]');

if (root) {
    const canvas = root.querySelector('[data-arcade-canvas]');
    const context = canvas.getContext('2d');
    const scoreText = root.querySelector('[data-arcade-score]');
    const timeText = root.querySelector('[data-arcade-time]');
    const bestText = root.querySelector('[data-arcade-best]');
    const message = root.querySelector('[data-arcade-message]');
    const target = Number(root.dataset.target) || 10;
    const SECONDS = 30;
    const KEY = 'lore.arcade-best';

    let best = 0;
    try {
        best = Number(localStorage.getItem(KEY)) || 0;
    } catch {
        best = 0;
    }
    bestText.textContent = String(best);

    let ufos = [];
    let sparks = [];
    let score = 0;
    let endsAt = 0;
    let running = false;
    let aim = { x: canvas.width / 2, y: canvas.height / 2 };
    let nextSpawn = 0;

    const spawn = (time) => {
        const fromLeft = Math.random() < 0.5;
        ufos.push({
            x: fromLeft ? -30 : canvas.width + 30,
            y: 50 + Math.random() * (canvas.height - 150),
            vx: (fromLeft ? 1 : -1) * (1.2 + Math.random() * 1.8 + score * 0.03),
            phase: Math.random() * 6,
            size: 18 + Math.random() * 10,
        });
        nextSpawn = time + 350 + Math.random() * 500;
    };

    const draw = (time) => {
        context.fillStyle = '#0f0b23';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#463a8a';
        for (let i = 0; i < 40; i++) {
            context.fillRect((i * 97) % canvas.width, (i * 53) % canvas.height, 2, 2);
        }
        ufos.forEach((ufo) => {
            const y = ufo.y + Math.sin(time / 300 + ufo.phase) * 10;
            context.fillStyle = '#c3baee';
            context.fillRect(ufo.x - ufo.size, y, ufo.size * 2, ufo.size * 0.35);
            context.fillStyle = '#7ee3ee';
            context.fillRect(ufo.x - ufo.size * 0.45, y - ufo.size * 0.4, ufo.size * 0.9, ufo.size * 0.4);
            context.fillStyle = '#ff2d55';
            for (let i = -1; i <= 1; i++) {
                context.fillRect(ufo.x + i * ufo.size * 0.6 - 2, y + ufo.size * 0.12, 4, 4);
            }
            ufo.drawnY = y;
        });
        sparks.forEach((spark) => {
            context.fillStyle = spark.colour;
            context.fillRect(spark.x, spark.y, 3, 3);
        });
        context.strokeStyle = '#39ff14';
        context.lineWidth = 2;
        context.beginPath();
        context.arc(aim.x, aim.y, 12, 0, Math.PI * 2);
        context.moveTo(aim.x - 18, aim.y);
        context.lineTo(aim.x + 18, aim.y);
        context.moveTo(aim.x, aim.y - 18);
        context.lineTo(aim.x, aim.y + 18);
        context.stroke();
    };

    const finish = () => {
        running = false;
        if (score > best) {
            best = score;
            try {
                localStorage.setItem(KEY, String(best));
            } catch {
                // best score just is not remembered
            }
        }
        bestText.textContent = String(best);
        message.textContent = score > target
            ? `${score} UFOs. You beat this week's ${target.toLocaleString('en-US')} visitors!`
            : `${score} UFOs. ${(target - score + 1).toLocaleString('en-US')} more to beat this week's ${target.toLocaleString('en-US')} visitors.`;
    };

    const tick = (time) => {
        if (!running) {
            draw(time);

            return;
        }
        const left = Math.max(0, Math.ceil((endsAt - time) / 1000));
        timeText.textContent = String(left);
        if (time >= nextSpawn) {
            spawn(time);
        }
        ufos.forEach((ufo) => {
            ufo.x += ufo.vx;
        });
        ufos = ufos.filter((ufo) => ufo.x > -60 && ufo.x < canvas.width + 60);
        sparks.forEach((spark) => {
            spark.x += spark.vx;
            spark.y += spark.vy;
            spark.life -= 1;
        });
        sparks = sparks.filter((spark) => spark.life > 0);
        draw(time);
        if (time >= endsAt) {
            finish();
            draw(time);

            return;
        }
        requestAnimationFrame(tick);
    };

    const position = (event) => {
        const box = canvas.getBoundingClientRect();

        return { x: ((event.clientX - box.left) / box.width) * canvas.width, y: ((event.clientY - box.top) / box.height) * canvas.height };
    };

    const shoot = () => {
        if (!running) {
            return;
        }
        const hit = ufos.findIndex((ufo) => Math.abs(aim.x - ufo.x) < ufo.size + 6 && Math.abs(aim.y - (ufo.drawnY ?? ufo.y)) < ufo.size);
        if (hit >= 0) {
            const [ufo] = ufos.splice(hit, 1);
            score += 1;
            scoreText.textContent = String(score);
            for (let i = 0; i < 14; i++) {
                sparks.push({ x: ufo.x, y: ufo.drawnY ?? ufo.y, vx: (Math.random() - 0.5) * 6, vy: (Math.random() - 0.5) * 6, life: 20, colour: ['#ffe600', '#ff8a00', '#ff2d55'][i % 3] });
            }
        }
    };

    canvas.addEventListener('pointermove', (event) => {
        aim = position(event);
    });
    canvas.addEventListener('pointerdown', (event) => {
        aim = position(event);
        shoot();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === ' ' && running) {
            event.preventDefault();
            shoot();
        }
    });
    root.querySelector('[data-arcade-start]').addEventListener('click', () => {
        ufos = [];
        sparks = [];
        score = 0;
        scoreText.textContent = '0';
        running = true;
        endsAt = performance.now() + SECONDS * 1000;
        nextSpawn = 0;
        message.textContent = 'Go!';
        requestAnimationFrame(tick);
    });

    draw(0);
}
