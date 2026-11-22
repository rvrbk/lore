const escapeHtml = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const wrap = (name, text) => (text === '' ? '' : `<span class="ce-${name}">${escapeHtml(text)}</span>`);

const tokenize = (text, pattern, classify) => {
    let html = '';
    let last = 0;
    for (const match of text.matchAll(pattern)) {
        html += escapeHtml(text.slice(last, match.index)) + wrap(classify(match), match[0]);
        last = match.index + match[0].length;
    }

    return html + escapeHtml(text.slice(last));
};

const GROUP_RULES = /^@(media|supports|layer|container|document|scope|starting-style)\b/;

const formatSelector = (text) => {
    if (text.trimStart().startsWith('@')) {
        return tokenize(text, /@[\w-]+/g, () => 'at');
    }

    return tokenize(text, /\[[^\]]*\]|::?[\w-]+(?:\([^)]*\))?|\.[\w-]+|#[\w-]+/g, (match) => {
        const first = match[0][0];

        return first === '[' ? 'attr' : first === ':' ? 'pseudo' : first === '.' ? 'class' : 'id';
    });
};

const formatValue = (text) =>
    tokenize(text, /"[^"\n]*"?|'[^'\n]*'?|!important|#[0-9a-fA-F]{3,8}\b|[a-zA-Z-]+(?=\()|-?(?:\d+\.?\d*|\.\d+)[a-zA-Z%]*/g, (match) => {
        const token = match[0];

        return token[0] === '"' || token[0] === "'"
            ? 'string'
            : token === '!important'
              ? 'important'
              : token[0] === '#'
                ? 'hex'
                : /^-?[a-zA-Z]/.test(token)
                  ? 'func'
                  : 'number';
    });

export const highlightCss = (source) => {
    let html = '';
    let buffer = '';
    let inValue = false;
    const stack = [];
    const inRule = () => stack[stack.length - 1] === 'rule';

    const flush = () => {
        html += inRule() ? (inValue ? formatValue(buffer) : wrap('prop', buffer)) : formatSelector(buffer);
        buffer = '';
    };

    let index = 0;
    while (index < source.length) {
        const char = source[index];

        if (char === '/' && source[index + 1] === '*') {
            flush();
            const end = source.indexOf('*/', index + 2);
            const stop = end === -1 ? source.length : end + 2;
            html += wrap('comment', source.slice(index, stop));
            index = stop;
            continue;
        }

        if ((char === '"' || char === "'") && inRule()) {
            let stop = index + 1;
            while (stop < source.length && source[stop] !== char && source[stop] !== '\n') {
                stop += source[stop] === '\\' ? 2 : 1;
            }
            buffer += source.slice(index, Math.min(stop + 1, source.length));
            index = stop + 1;
            continue;
        }

        if (char === '{') {
            const isGroup = !inRule() && GROUP_RULES.test(buffer.trim());
            inValue = false;
            html += formatSelector(buffer);
            buffer = '';
            html += '<span class="ce-punct">{</span>';
            stack.push(isGroup ? 'group' : 'rule');
        } else if (char === '}') {
            flush();
            inValue = false;
            stack.pop();
            html += '<span class="ce-punct">}</span>';
        } else if (char === ';') {
            flush();
            inValue = false;
            html += '<span class="ce-punct">;</span>';
        } else if (char === ':' && inRule() && !inValue && !/^[^;{}]*\{/.test(source.slice(index + 1))) {
            flush();
            inValue = true;
            html += '<span class="ce-punct">:</span>';
        } else {
            buffer += char;
        }
        index++;
    }
    flush();

    return `${html}\n`;
};

const mount = (textarea) => {
    const root = document.createElement('div');
    root.className = `css-editor ${textarea.className}`.trim();
    const gutter = document.createElement('div');
    gutter.className = 'css-editor-gutter';
    gutter.setAttribute('aria-hidden', 'true');
    const body = document.createElement('div');
    body.className = 'css-editor-body';
    const code = document.createElement('pre');
    code.setAttribute('aria-hidden', 'true');

    textarea.className = 'css-editor-input';
    textarea.wrap = 'off';
    textarea.replaceWith(root);
    body.append(code, textarea);
    root.append(gutter, body);

    const refresh = () => {
        code.innerHTML = highlightCss(textarea.value);
        const lines = textarea.value.split('\n').length;
        gutter.textContent = Array.from({ length: lines }, (_, line) => line + 1).join('\n');
    };
    const sync = () => {
        code.scrollTop = textarea.scrollTop;
        code.scrollLeft = textarea.scrollLeft;
        gutter.scrollTop = textarea.scrollTop;
    };

    textarea.addEventListener('input', () => {
        refresh();
        sync();
    });
    textarea.addEventListener('scroll', sync);
    textarea.form?.addEventListener('reset', () => setTimeout(refresh));
    refresh();
};

document.querySelectorAll('textarea[data-css-editor]').forEach(mount);
