const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

export const confetti = () => {
    if (reducedMotion()) {
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;z-index:10002;pointer-events:none;image-rendering:pixelated';
    canvas.width = Math.ceil(window.innerWidth / 4);
    canvas.height = Math.ceil(window.innerHeight / 4);
    document.body.append(canvas);
    const context = canvas.getContext('2d');
    const colours = ['#ff2d55', '#ffe600', '#39ff14', '#00e5ff', '#9b5de5', '#ff8a00'];
    const bits = Array.from({ length: 160 }, () => ({
        x: canvas.width / 2 + (Math.random() - 0.5) * 20,
        y: canvas.height * 0.4,
        vx: (Math.random() - 0.5) * 3,
        vy: -Math.random() * 3.2 - 0.5,
        colour: colours[Math.floor(Math.random() * colours.length)],
    }));
    const start = performance.now();
    const frame = (time) => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        bits.forEach((bit) => {
            bit.vy += 0.05;
            bit.x += bit.vx;
            bit.y += bit.vy;
            context.fillStyle = bit.colour;
            context.fillRect(Math.round(bit.x), Math.round(bit.y), 1, 1);
        });
        if (time - start < 3000) {
            requestAnimationFrame(frame);
        } else {
            canvas.remove();
        }
    };
    requestAnimationFrame(frame);
};

export const trombone = () => {
    const Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) {
        return;
    }
    try {
        const context = new Context();
        const notes = [[311, 0, 0.35], [294, 0.35, 0.35], [277, 0.7, 0.35], [262, 1.05, 0.9]];
        notes.forEach(([frequency, offset, length]) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sawtooth';
            oscillator.frequency.value = frequency;
            if (offset === 1.05) {
                oscillator.frequency.linearRampToValueAtTime(frequency * 0.94, context.currentTime + offset + length);
            }
            gain.gain.setValueAtTime(0.0001, context.currentTime + offset);
            gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + offset + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + offset + length);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(context.currentTime + offset);
            oscillator.stop(context.currentTime + offset + length + 0.05);
        });
        setTimeout(() => context.close(), 2500);
    } catch {
        // the browser blocks sound until the page has been used
    }
};
