/*! Lore heatmap tracker: sends the position and the clicked element (never typed text) of every click to Lore. */
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

    var clean = function (text, max) {
        return String(text || '').replace(/\s+/g, ' ').trim().slice(0, max);
    };

    // What was clicked: the nearest link, button or form control, with its visible label. Typed values are never read.
    var describe = function (target) {
        var node = target && target.nodeType === 1 ? target : (target && target.parentElement);
        if (!node) {
            return {};
        }
        var control = node.closest('a, button, input, select, textarea, summary, label, [role="button"], [role="link"], [role="tab"]');
        var element = control || node;
        var tag = element.tagName.toLowerCase();
        var type = (element.getAttribute('type') || '').toLowerCase();
        var label = clean(element.getAttribute('aria-label') || element.getAttribute('title'), 100);
        if (!label && (tag === 'input' || tag === 'select' || tag === 'textarea')) {
            label = clean((type === 'submit' || type === 'button') ? element.value : (element.getAttribute('placeholder') || element.getAttribute('name')), 100);
        }
        if (!label && control) {
            label = clean(element.innerText || element.textContent, 100);
        }
        if (!label) {
            var image = element.tagName === 'IMG' ? element : element.querySelector('img');
            label = clean(image && image.getAttribute('alt'), 100);
        }
        if (!label && !control && clean(element.textContent, 41).length <= 40) {
            label = clean(element.textContent, 40);
        }
        var href = '';
        if (tag === 'a' && element.href) {
            try {
                var url = new URL(element.href, location.href);
                href = (url.origin === location.origin ? '' : url.host) + url.pathname;
            } catch (error) {
                href = '';
            }
        }
        return { e: tag === 'a' ? 'link' : (control ? tag : 'other'), l: label, h: href.slice(0, 200) };
    };

    document.addEventListener('click', function (event) {
        var target = describe(event.target);
        var width = Math.max(document.documentElement.scrollWidth, window.innerWidth);
        var body = JSON.stringify({
            k: key,
            p: location.pathname,
            x: Math.min(1000, Math.max(0, Math.round(event.pageX / width * 1000))),
            y: Math.max(0, Math.round(event.pageY)),
            w: window.innerWidth,
            e: target.e,
            l: target.l,
            h: target.h
        });
        if (!(navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
            fetch(endpoint, { method: 'POST', body: body, keepalive: true, mode: 'no-cors' }).catch(function () {});
        }
    }, true);
})();
