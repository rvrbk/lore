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
