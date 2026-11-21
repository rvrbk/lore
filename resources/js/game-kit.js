/*
 * Shared bits for the small games: DOM helpers, result messages and the map's UFO sprite.
 */

/*
 * A flying saucer seen from above: a round disk with a dome in the middle and four red and yellow lights on the rim
 * that swap colours every 300 ms, like the UFOs on the world map.
 */
const LIGHTS = [
    [8, 1.6, '#ff2d55', '#ffe600'],
    [14.4, 8, '#ffe600', '#ff2d55'],
    [8, 14.4, '#ff2d55', '#ffe600'],
    [1.6, 8, '#ffe600', '#ff2d55'],
];

export const UFO_SVG = `<svg viewBox="0 0 16 16" class="ufo-hover size-full" style="filter: drop-shadow(0 0 1px #00e5ff)" aria-hidden="true">
<circle cx="8" cy="8" r="7.6" fill="#c4c8e0"/>
<circle cx="8" cy="8" r="6" fill="#6c7090"/>
<circle cx="8" cy="8" r="4.6" fill="none" stroke="#c4c8e0" stroke-width="0.5" stroke-dasharray="1.2 1.2"/>
<circle cx="8" cy="8" r="2.6" fill="#00e5ff"/>
<circle cx="7.1" cy="7.1" r="1" fill="#ffffff"/>
${LIGHTS.map(([x, y, from, to]) => `<circle cx="${x}" cy="${y}" r="1" fill="${from}"><animate attributeName="fill" values="${from};${to}" dur="0.6s" calcMode="discrete" repeatCount="indefinite"/></circle>`).join('')}
</svg>`;

export function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([name, value]) => {
        if (name === 'class') {
            node.className = value;
        } else if (name.startsWith('on')) {
            node.addEventListener(name.slice(2), value);
        } else {
            node.setAttribute(name, value);
        }
    });
    node.append(...children.flat().filter((child) => child !== null && child !== false));
    return node;
}

export const button = (label, onclick, primary = false) =>
    el(
        'button',
        {
            type: 'button',
            onclick,
            class: primary ? 'border border-indigo-500 bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500' : 'border border-zinc-600 bg-zinc-800 px-3 py-1.5 text-sm hover:bg-zinc-700',
        },
        label,
    );

export const selectOf = (options, value, onchange) =>
    el(
        'select',
        { class: 'border border-zinc-600 bg-zinc-900 px-2 py-1.5 text-sm', onchange: (event) => onchange(event.target.value) },
        options.map(([optionValue, label]) => {
            const option = el('option', { value: optionValue }, label);
            option.selected = optionValue === value;
            return option;
        }),
    );

export const verdict = (ok, title, text) =>
    el(
        'div',
        { class: `mt-3 border p-3 text-sm ${ok ? 'border-green-500 bg-green-950/40' : 'border-red-500 bg-red-950/40'}`, role: 'status' },
        el('p', { class: 'font-medium' }, title),
        el('p', { class: 'mt-1 text-zinc-300' }, text),
    );

export const lesson = (text) => el('p', { class: 'mt-2 border-l-2 border-cyan-300 pl-3 text-zinc-300' }, el('span', { class: 'font-medium text-cyan-300' }, 'What you learned: '), text);
