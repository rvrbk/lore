/*! Lore heatmap tracker: sends the position of every click to Lore. */
(function () {
    var script = document.currentScript;
    // The Heatmaps page frames the site to draw over it; clicks made there are not visitors.
    if (!script || window.top !== window.self) {
        return;
    }
    var key = script.getAttribute('data-key');
    var endpoint = new URL(script.src).origin + '/api/heat';
    if (!key) {
        return;
    }

    document.addEventListener('click', function (event) {
        var width = Math.max(document.documentElement.scrollWidth, window.innerWidth);
        var body = JSON.stringify({
            k: key,
            p: location.pathname,
            x: Math.min(1000, Math.max(0, Math.round(event.pageX / width * 1000))),
            y: Math.max(0, Math.round(event.pageY)),
            w: window.innerWidth
        });
        if (!(navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
            fetch(endpoint, { method: 'POST', body: body, keepalive: true, mode: 'no-cors' }).catch(function () {});
        }
    }, true);
})();
