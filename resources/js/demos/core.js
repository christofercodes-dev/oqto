/*
 * Gemensam kärna för de interaktiva demona på funktionssidorna. Varje demo
 * har ett tillstånd, en vy som returnerar HTML och en uppsättning åtgärder.
 * Elementen styrs med attribut:
 *   data-act="namn" data-arg="värde"   klick ("namn:id" skickar id som tredje argument)
 *   data-input="namn"                  skriver i ett fält (körs vid varje tangenttryck)
 *   data-change="namn"                 väljer i en select / datumfält
 * Efter varje åtgärd ritas demot om, med bibehållen fokus och scrollposition
 * (fält som ska behålla fokus markeras med data-key, scrollytor med data-scroll).
 * All data i demona är påhittad.
 */

export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

export const num = (value) => parseFloat(String(value ?? '').replace(/\s/g, '').replace(',', '.')) || 0;

export const fmt = (n) => n.toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const fmtQ = (n) => n.toLocaleString('sv-SE', { maximumFractionDigits: 2 });

export const addDays = (iso, days) => {
    const d = new Date(iso + 'T12:00:00');

    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
};

export const longDate = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString('sv-SE', { day: 'numeric', month: 'long' });

export const todayISO = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export const initials = (name) => {
    const words = name.split(/\s+/).filter((w) => !/^(AB|HB|KB)$/i.test(w));

    return (words.length > 1 ? words[0][0] + words[1][0] : (words[0] || '').slice(0, 2)).toUpperCase();
};

export const svg = (path, size = 16, stroke = 2, color = 'currentColor') =>
    `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${path}"/></svg>`;

export const ICON = {
    check: 'm5 12.5 4.5 4.5L19 7.5',
    chevronRight: 'm9 18 6-6-6-6',
    chevronLeft: 'm15 18-6-6 6-6',
    chevronUp: 'm18 15-6-6-6 6',
    chevronDown: 'm6 9 6 6 6-6',
    search: 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zm9 16-3.5-3.5',
    plus: 'M12 5v14M5 12h14',
    trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14',
    bank: 'M3 21h18M5 21V10M19 21V10M10 21v-7h4v7M12 3 3 9h18z',
    bolt: 'M13 2 3 14h9l-1 8 10-12h-9z',
};

/**
 * @param {HTMLElement} root
 * @param {object} state
 * @param {(state: object) => string} view
 * @param {Record<string, (arg: any, event: Event) => void>} actions
 */
export function createDemo(root, state, view, actions) {

    const render = () => {

        const active = document.activeElement;
        const key = root.contains(active) ? active.dataset.key : null;
        const selection = key && typeof active.selectionStart === 'number' ? [active.selectionStart, active.selectionEnd] : null;

        const scrolls = {};

        root.querySelectorAll('[data-scroll]').forEach((el) => { scrolls[el.dataset.scroll] = el.scrollTop; });

        root.innerHTML = view(state);

        root.querySelectorAll('[data-scroll]').forEach((el) => {
            if (scrolls[el.dataset.scroll] !== undefined) el.scrollTop = scrolls[el.dataset.scroll];
        });

        if (key) {
            const el = root.querySelector(`[data-key="${key}"]`);

            if (el) {
                el.focus({ preventScroll: true });

                if (selection && el.setSelectionRange) {
                    try { el.setSelectionRange(selection[0], selection[1]); } catch (e) { /* fältet stöder inte markering */ }
                }
            }
        }
    };

    // "namn:id" anropar åtgärden "namn" med id som tredje argument (för listor av steg/rader).
    const run = (name, arg, event) => {
        const [base, suffix] = String(name).split(':');
        const action = actions[base];

        if (!action) return;

        action(arg, event, suffix);
        render();
    };

    root.addEventListener('click', (event) => {
        const target = event.target.closest('[data-act]');

        if (!target || !root.contains(target) || target.matches('input, select, textarea')) return;

        run(target.dataset.act, target.dataset.arg, event);
    });

    root.addEventListener('input', (event) => {
        const target = event.target.closest('[data-input]');

        if (target) run(target.dataset.input, target.value, event);
    });

    root.addEventListener('change', (event) => {
        const target = event.target.closest('[data-change]');

        if (target) run(target.dataset.change, target.value, event);
    });

    root.addEventListener('keydown', (event) => {
        if (actions.shortcut && (event.metaKey || event.ctrlKey) && event.key === 'Enter') {
            event.preventDefault();
            run('shortcut', null, event);
        }
    });

    render();

    return { render, state };
}

/** Segmentväljare ("pillerna" i designen). options: [[värde, etikett], ...] */
export const segmented = (options, current, act, extraClass = '') =>
    `<div class="dm-seg ${extraClass}">${options.map(([value, label]) =>
        `<button type="button" class="dm-seg-btn${String(value) === String(current) ? ' is-on' : ''}" data-act="${act}" data-arg="${esc(value)}">${esc(label)}</button>`
    ).join('')}</div>`;
