(function () {
    try {
        var override = localStorage.getItem('lore-theme');
        var hour = new Date().getHours();
        var night = override === 'night' || override === 'normal' ? override === 'night' : hour >= 22 || hour < 6;
        document.documentElement.dataset.theme = night ? 'night' : 'normal';
    } catch (error) {}
})();
