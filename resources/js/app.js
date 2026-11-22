import './board.js';
import './editor.js';
import './css-editor.js';
import './palette.js';
import './pixel-map.js';
import './live-users.js';
import './deploy-status.js';
import './local-time.js';
import './world-clocks.js';

const sidebar = document.getElementById('sidebar');
const navToggle = document.querySelector('[data-nav-toggle]');
const navBackdrop = document.querySelector('[data-nav-backdrop]');

if (sidebar && navToggle && navBackdrop) {
    const setNav = (open) => {
        sidebar.toggleAttribute('data-open', open);
        navBackdrop.hidden = !open;
        navToggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('overflow-hidden', open);
    };

    navToggle.addEventListener('click', () => setNav(!sidebar.hasAttribute('data-open')));
    navBackdrop.addEventListener('click', () => setNav(false));
    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a, [data-palette-open]')) {
            setNav(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setNav(false);
        }
    });
}

document.querySelectorAll('[data-inline-editor]').forEach((root) => {
    const form = root.querySelector('[data-edit]');
    const views = root.querySelectorAll('[data-view]');
    const title = form.elements.title;
    const body = form.elements.body;
    const tags = form.elements.tags;
    const original = { title: title.value, body: body.value, tags: tags.value };
    let editing = false;

    const isDirty = () => title.value !== original.title || body.value !== original.body || tags.value !== original.tags;
    const fit = () => {
        body.style.height = 'auto';
        body.style.height = `${Math.max(body.scrollHeight + 2, 320)}px`;
    };

    const open = (focus = 'body') => {
        editing = true;
        views.forEach((el) => (el.hidden = true));
        form.hidden = false;
        fit();
        const field = focus === 'title' ? title : body;
        field.focus();
        field.setSelectionRange(field.value.length, field.value.length);
    };

    const close = () => {
        if (isDirty() && !confirm('Discard your changes?')) {
            return;
        }
        title.value = original.title;
        body.value = original.body;
        tags.value = original.tags;
        body.dispatchEvent(new Event('input'));
        editing = false;
        form.hidden = true;
        views.forEach((el) => (el.hidden = false));
    };

    root.querySelectorAll('[data-click-to-edit]').forEach((el) => {
        el.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, summary') || window.getSelection().toString() !== '') {
                return;
            }
            open(el.dataset.clickToEdit);
        });
    });

    root.querySelector('[data-edit-trigger]').addEventListener('click', () => open());
    root.querySelector('[data-edit-cancel]').addEventListener('click', close);
    body.addEventListener('input', fit);

    form.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', () => {
        original.title = title.value;
        original.body = body.value;
        original.tags = tags.value;
    });

    window.addEventListener('beforeunload', (event) => {
        if (editing && isDirty()) {
            event.preventDefault();
        }
    });

    if (root.dataset.open === 'true') {
        open();
    }
});
