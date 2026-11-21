/*
 * Shared bits for the small games: DOM helpers, result messages and the map's UFO sprite.
 */

/*
 * The UFO from the world map: the same 7x7 dot bitmap seen from above, with just four red and yellow lights swapping every 300 ms.
 */
const UFO_BITMAP = ['..SLS..', '.DDDDD.', 'SDDSDDS', 'LDSWSDL', 'SDDSDDS', '.DDDDD.', '..SLS..'];
const UFO_COLORS = { W: '#ffffff', S: '#c4c8e0', D: '#6c7090' };
const LIGHT_COLORS = { L: ['#ff2d55', '#ffe600'] };

export const UFO_SVG = `<svg viewBox="0 0 7 7" class="ufo-hover size-full" style="filter: drop-shadow(0 0 1px #00e5ff)" aria-hidden="true">${UFO_BITMAP.flatMap((row, y) =>
    [...row].flatMap((symbol, x) => {
        if (symbol === '.') {
            return [];
        }
        const light = LIGHT_COLORS[symbol];
        const dot = light
            ? `<circle cx="${x + 0.5}" cy="${y + 0.5}" r="0.5" fill="${light[0]}"><animate attributeName="fill" values="${light[0]};${light[1]}" dur="0.6s" calcMode="discrete" repeatCount="indefinite"/></circle>`
            : `<circle cx="${x + 0.5}" cy="${y + 0.5}" r="0.5" fill="${UFO_COLORS[symbol]}"/>`;
        return [dot];
    }),
).join('')}</svg>`;

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
