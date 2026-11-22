const targets = document.querySelectorAll('[data-weather]');

if (targets.length > 0) {
    const url = document.querySelector('meta[name=weather-url]')?.content;

    const render = (data) => {
        targets.forEach((target) => {
            const weather = data[target.dataset.weather];
            if (!weather) {
                return;
            }
            target.textContent = target.dataset.weatherFormat === 'long'
                ? `${weather.icon} ${weather.temp}° ↑${weather.sunrise} ↓${weather.sunset}`
                : `${weather.icon} ${weather.temp}°`;
            target.title = `${weather.label}, ${weather.temp}°C · sunrise ${weather.sunrise}, sunset ${weather.sunset}`;
        });
    };

    if (url) {
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : {}))
            .then(render)
            .catch(() => {});
    }
}
