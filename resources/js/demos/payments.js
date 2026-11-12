import { createDemo, esc, fmt, svg, ICON, addDays, todayISO } from './core.js';

/*
 * Lättare hantering av betalningar: välj fakturor som ska betalas, skapa
 * betaluppdrag och signera dem med BankID. Allt är påhittat och inget skickas.
 */

const ICONS = {
    upload: 'M12 16V4m-5 5 5-5 5 5M5 20h14',
    robot: 'M12 3v3M5 8h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2zM9 13v1M15 13v1',
    close: 'M18 6 6 18M6 6l12 12',
    sign: 'M3 20h18M5 16l9.5-9.5a2.1 2.1 0 0 1 3 3L8 19l-4 1z',
};

const BANK = { name: 'Företagskonto', iban: 'SE45 5000 0000 0583 9825 7466', available: 418250 };

// De två sista stegen går att prova. Övriga flikar visas som i appen men är avstängda.
const TABS = [
    ['inbox', 'Inkorg', false],
    ['send', 'Att skicka på attest', false],
    ['attest', 'Under attest', false],
    ['book', 'Att bokföra', false],
    ['pay', 'Att betala', true],
    ['paying', 'Under betalning', true],
    ['paid', 'Betalda', false],
    ['all', 'Alla', false],
];

// Antal i flikarna som inte påverkas av demot.
const STATIC_COUNTS = { inbox: 1, send: 0, attest: 2, book: 0 };

const today = todayISO();

const INVOICES = [
    { id: 1, no: 41, supplier: 'Kontorsbolaget AB', ref: '20481', ocr: '1840 2290 55', amount: 4250, exec: addDays(today, 1) },
    { id: 2, no: 42, supplier: 'Nordisk El AB', ref: '77301', ocr: '9302 1184 07', amount: 1675, exec: addDays(today, 6) },
    { id: 3, no: 43, supplier: 'Lokalvård Syd AB', ref: '5512', ocr: '3321 7740 18', amount: 8900, exec: addDays(today, 14) },
];

// Fakturor som redan är under betalning, för att listan inte ska vara tom.
const PAYING = [
    { id: 101, no: 38, supplier: 'Webbhotell Norr AB', ref: '90112', ocr: '4410 8832 61', amount: 1190, state: 'Betalas' },
    { id: 102, no: 39, supplier: 'Städ & Service AB', ref: '3304', ocr: '2276 0915 43', amount: 3480, state: 'Betalas' },
];

const kr = (n) => `${fmt(n)} SEK`;

export default function mount(root) {

    const s = {
        tab: 'pay',
        selected: {},
        created: {},   // faktura-id -> true när betaluppdraget skapats
        signed: {},    // faktura-id -> true när det signerats
        exec: Object.fromEntries(INVOICES.map((i) => [i.id, i.exec])),
        modal: null,   // 'create' | 'sign'
        signing: false,
        toast: null,
        animated: false,
    };

    let demo;
    let toastTimer;
    let signTimer;

    const showToast = (title, text) => {
        s.toast = { title, text };
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { s.toast = null; demo.render(); }, 3200);
    };

    const toPay = () => INVOICES.filter((i) => !s.signed[i.id]);
    const moved = () => INVOICES.filter((i) => s.signed[i.id]);
    const selectedIds = () => toPay().filter((i) => s.selected[i.id]).map((i) => i.id);
    const selectedInvoices = () => INVOICES.filter((i) => selectedIds().includes(i.id));

    const count = (key) => {
        if (key === 'pay') return toPay().length;
        if (key === 'paying') return PAYING.length + moved().length;

        return STATIC_COUNTS[key];
    };

    /* ---------- Vyer ---------- */

    const pill = (label, tone) => `<span class="dm-pay-pill is-${tone}">${svg(ICON.check, 12, 2.6)}${esc(label)}</span>`;

    const orderCell = (inv) => {
        if (s.signed[inv.id]) return '<span class="dm-pay-order is-signed">Signerad</span>';
        if (s.created[inv.id]) return '<span class="dm-pay-order">Ej signerad</span>';

        return '';
    };

    const row = (inv, selectable) => `
        <tr class="${s.selected[inv.id] ? 'is-selected' : ''}">
            <td class="dm-pay-c-check">${selectable ? `<button type="button" class="dm-pay-check${s.selected[inv.id] ? ' is-on' : ''}" data-act="toggle" data-arg="${inv.id}" role="checkbox" aria-checked="${s.selected[inv.id] ? 'true' : 'false'}" aria-label="Välj faktura ${inv.no}">${s.selected[inv.id] ? svg(ICON.check, 12, 3.2) : ''}</button>` : ''}</td>
            <td class="dm-pay-c-no">${inv.no}</td>
            <td class="dm-pay-c-name">${esc(inv.supplier)}</td>
            <td class="dm-pay-c-ref">${esc(inv.ref)}</td>
            <td class="dm-pay-c-ocr">${esc(inv.ocr)}</td>
            <td>${pill('Bokförd', 'blue')}</td>
            <td class="dm-pay-c-attest">${pill('Attesterad', 'blue')}</td>
            <td>${selectable ? orderCell(inv) : `<span class="dm-pay-order is-signed">${esc(inv.state || 'Signerad')}</span>`}</td>
            <td class="dm-pay-c-sum">${fmt(inv.amount)}</td>
        </tr>`;

    const table = (rows, selectable) => `
        <div class="dm-pay-tablewrap" data-scroll="table">
            <table class="dm-pay-table">
                <thead>
                    <tr>
                        <th class="dm-pay-c-check"></th>
                        <th>Faktura #</th>
                        <th>Leverantör</th>
                        <th class="dm-pay-c-ref">Fakturanr</th>
                        <th class="dm-pay-c-ocr">OCR</th>
                        <th>Status</th>
                        <th class="dm-pay-c-attest">Attest</th>
                        <th>Betaluppdrag</th>
                        <th class="dm-pay-c-sum">Summa</th>
                    </tr>
                </thead>
                <tbody>${rows.map((inv) => row(inv, selectable)).join('')}</tbody>
                <tfoot><tr><td></td><td colspan="8">${rows.length} st</td></tr></tfoot>
            </table>
        </div>`;

    const list = () => {
        if (s.tab === 'pay') {
            const rows = toPay();

            return rows.length
                ? table(rows, true)
                : `<div class="dm-pay-empty"><strong>Allt är betalat</strong><span>Inga fakturor väntar på betalning.</span><button type="button" class="dm-pay-btn" data-act="reset">Börja om demot</button></div>`;
        }

        const rows = [...moved().map((i) => ({ ...i, state: 'Signerad' })), ...PAYING];

        return table(rows, false);
    };

    const stages = () => `
        <div class="dm-pay-stages" role="tablist">
            ${TABS.map(([key, label, enabled], i) => {
                const n = count(key);

                return `${i ? `<span class="dm-pay-arrow" aria-hidden="true">${svg(ICON.chevronRight, 14, 2)}</span>` : ''}
                    <button type="button" role="tab" class="dm-pay-stage${s.tab === key ? ' is-on' : ''}" ${enabled ? `data-act="tab" data-arg="${key}"` : 'disabled'} aria-selected="${s.tab === key ? 'true' : 'false'}">
                        ${esc(label)}${n !== undefined ? `<span class="dm-pay-count">${n}</span>` : ''}
                    </button>`;
            }).join('')}
        </div>`;

    const selectionBar = () => {
        const ids = selectedIds();

        if (!ids.length || s.modal) return '';

        const needsCreate = ids.some((id) => !s.created[id]);

        return `
            <div class="dm-pay-bar" role="toolbar" aria-label="Valda fakturor">
                <span class="dm-pay-bar-count">${ids.length}</span>
                <span class="dm-pay-bar-text">valda</span>
                <button type="button" class="dm-pay-bar-btn" data-act="zip">Ladda ner ZIP</button>
                <button type="button" class="dm-pay-bar-btn is-primary" data-act="${needsCreate ? 'open-create' : 'open-sign'}">${needsCreate ? 'Skapa betaluppdrag' : 'Signera betaluppdrag'}</button>
                <button type="button" class="dm-pay-bar-x" data-act="clear" aria-label="Avmarkera">${svg(ICONS.close, 16, 2)}</button>
            </div>`;
    };

    const dateField = (inv) => `<input type="date" class="dm-pay-date" value="${esc(s.exec[inv.id])}" min="${today}" data-key="date-${inv.id}" data-change="date:${inv.id}" aria-label="Utförandedatum för faktura ${inv.no}">`;

    const createModal = () => {
        const rows = selectedInvoices().filter((i) => !s.created[i.id]);

        return `
            <div class="dm-pay-scrim">
                <div class="dm-pay-modal" role="dialog" aria-modal="true" aria-label="Skapa betaluppdrag">
                    <button type="button" class="dm-pay-modal-x" data-act="close" aria-label="Stäng">${svg(ICONS.close, 18, 2)}</button>
                    <h3>Skapa betaluppdrag</h3>
                    <p class="dm-pay-modal-sub">Välj när varje betalning ska utföras.</p>
                    <div class="dm-pay-modal-table">
                        <div class="dm-pay-mrow is-head"><span>Mottagare</span><span>Faktura</span><span>Utförandedatum</span><span class="is-right">Summa</span></div>
                        ${rows.map((inv) => `
                            <div class="dm-pay-mrow">
                                <span class="dm-pay-strong">${esc(inv.supplier)}</span>
                                <span><span class="dm-pay-chip">Faktura ${inv.no}</span></span>
                                <span>${dateField(inv)}</span>
                                <span class="is-right">${kr(inv.amount)}</span>
                            </div>`).join('')}
                    </div>
                    <div class="dm-pay-modal-foot">
                        <button type="button" class="dm-pay-btn" data-act="create">Skapa betaluppdrag</button>
                    </div>
                </div>
            </div>`;
    };

    const signModal = () => {
        const rows = selectedInvoices();
        const total = rows.reduce((sum, i) => sum + i.amount, 0);

        return `
            <div class="dm-pay-scrim">
                <div class="dm-pay-modal" role="dialog" aria-modal="true" aria-label="Signera betaluppdrag">
                    <button type="button" class="dm-pay-modal-x" data-act="close" aria-label="Stäng"${s.signing ? ' disabled' : ''}>${svg(ICONS.close, 18, 2)}</button>
                    <h3>Signera betaluppdrag</h3>
                    <p class="dm-pay-modal-sub">Godkänn betalningen genom att signera med BankID.</p>
                    <div class="dm-pay-bank">
                        <span class="dm-pay-bank-logo">${svg(ICON.bank, 18, 2)}</span>
                        <span class="dm-pay-bank-text"><strong>${esc(BANK.name)}</strong><span>${esc(BANK.iban)}</span></span>
                        <span class="dm-pay-bank-avail"><span>Tillgängligt</span><strong>${kr(BANK.available)}</strong></span>
                    </div>
                    <div class="dm-pay-modal-table">
                        <div class="dm-pay-mrow is-sign is-head"><span>Mottagare</span><span>Status</span><span>Utförandedatum</span><span class="is-right">Summa</span></div>
                        ${rows.map((inv) => `
                            <div class="dm-pay-mrow is-sign">
                                <span class="dm-pay-strong">${esc(inv.supplier)}</span>
                                <span><span class="dm-pay-order">${s.signing ? 'Signeras…' : 'Ej signerad'}</span></span>
                                <span>${esc(s.exec[inv.id])}</span>
                                <span class="is-right">${kr(inv.amount)}</span>
                            </div>`).join('')}
                        <div class="dm-pay-mrow is-sign is-total"><span>${rows.length} st</span><span></span><span></span><span class="is-right">${kr(total)}</span></div>
                    </div>
                    <div class="dm-pay-modal-foot">
                        <button type="button" class="dm-pay-btn is-plain" data-act="close"${s.signing ? ' disabled' : ''}>Avbryt</button>
                        <button type="button" class="dm-pay-btn is-primary${s.signing ? ' is-busy' : ''}" data-act="sign"${s.signing ? ' disabled' : ''}>
                            ${s.signing ? '<span class="dm-pay-spin" aria-hidden="true"></span>Väntar på BankID…' : `${svg(ICONS.sign, 16, 2)}Signera`}
                        </button>
                    </div>
                </div>
            </div>`;
    };

    const toast = () => s.toast
        ? `<div class="dm-pay-toast" role="status"><span class="dm-pay-toast-icon">${svg(ICON.check, 16, 3)}</span><span><strong>${esc(s.toast.title)}</strong><span>${esc(s.toast.text)}</span></span></div>`
        : '';

    const windowView = () => `
        <div class="dm-window is-pay">
            <div class="dm-topbar">
                <strong>Leverantörsfakturor</strong>
                <span class="dm-grow"></span>
                <span class="dm-pill-mono">DEMO</span>
            </div>

            <div class="dm-pay-actions">
                <span class="dm-pay-ghost"><span class="dm-pay-switch" aria-hidden="true"></span>${svg(ICONS.robot, 17, 1.8)}Autopilot</span>
                <span class="dm-pay-ghost">${svg(ICONS.upload, 17, 1.8)}Ladda upp</span>
                <span class="dm-pay-new">${svg(ICON.plus, 16, 2.2)}Skapa ny</span>
            </div>

            ${stages()}

            <div class="dm-pay-filter">
                <span class="dm-pay-filterbtn">Filter</span>
                <span class="dm-pay-searchfake">${svg(ICON.search, 15, 2)}Sök fakturor…</span>
            </div>

            ${list()}
            ${selectionBar()}
            ${s.modal === 'create' ? createModal() : ''}
            ${s.modal === 'sign' ? signModal() : ''}
            ${toast()}
        </div>`;

    // På mobil visas flödet som en animation av desktopvyn, skalad så att den får plats.
    const view = () => s.animated
        ? `<div class="dm-pay-box"><div class="dm-pay-scaled" aria-hidden="true">${windowView()}</div></div>
           <p class="dm-pay-anim-note">Så här ser flödet ut på dator.</p>`
        : windowView();

    /* ---------- Åtgärder ---------- */

    demo = createDemo(root, s, view, {
        tab: (key) => { s.tab = key; },
        reset: () => {
            clearTimeout(signTimer);
            s.selected = {};
            s.created = {};
            s.signed = {};
            s.modal = null;
            s.signing = false;
            s.tab = 'pay';
        },
        toggle: (id) => { s.selected[id] = !s.selected[id]; },
        clear: () => { s.selected = {}; },
        zip: () => showToast('Ladda ner ZIP', 'I demot laddas ingenting ner.'),
        date: (value, event, id) => { if (value) s.exec[id] = value; },
        'open-create': () => { s.modal = 'create'; },
        'open-sign': () => { s.modal = 'sign'; },
        close: () => { if (!s.signing) s.modal = null; },
        create: () => {
            const made = selectedInvoices().filter((i) => !s.created[i.id]);

            made.forEach((i) => { s.created[i.id] = true; });
            s.modal = 'sign';
            showToast('Skapa betaluppdrag', `${made.length} betaluppdrag skapades`);
        },
        sign: () => {
            if (s.signing) return;

            s.signing = true;

            signTimer = setTimeout(() => {
                const ids = selectedIds();

                ids.forEach((id) => { s.signed[id] = true; delete s.selected[id]; });
                s.signing = false;
                s.modal = null;
                s.tab = 'paying';
                showToast('Betalningarna är signerade', `${ids.length} betalningar utförs på angivna datum.`);
                demo.render();
            }, 1600);
        },
    });

    /* ---------- Animation (mobil) ---------- */

    const mq = window.matchMedia('(max-width: 767px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    let timers = [];
    let visible = true;

    const clearTimers = () => { timers.forEach(clearTimeout); timers = []; };

    const resetState = () => {
        clearTimeout(signTimer);
        clearTimeout(toastTimer);
        s.selected = {};
        s.created = {};
        s.signed = {};
        s.modal = null;
        s.signing = false;
        s.toast = null;
        s.tab = 'pay';
    };

    const finalState = () => {
        resetState();
        INVOICES.forEach((i) => { s.signed[i.id] = true; });
        s.tab = 'paying';
    };

    const SCRIPT = [
        [700, () => { s.selected[1] = true; }],
        [1400, () => { s.selected[2] = true; }],
        [2100, () => { s.selected[3] = true; }],
        [3200, () => { s.modal = 'create'; }],
        [5400, () => { INVOICES.forEach((i) => { s.created[i.id] = true; }); s.modal = 'sign'; showToast('Skapa betaluppdrag', '3 betaluppdrag skapades'); }],
        [7600, () => { s.signing = true; }],
        [9400, () => {
            INVOICES.forEach((i) => { s.signed[i.id] = true; delete s.selected[i.id]; });
            s.signing = false;
            s.modal = null;
            s.tab = 'paying';
            showToast('Betalningarna är signerade', '3 betalningar utförs på angivna datum.');
        }],
    ];

    const LOOP_AT = 13500;

    const play = () => {
        clearTimers();

        if (!s.animated || !visible) return;

        resetState();
        demo.render();

        SCRIPT.forEach(([at, step]) => {
            timers.push(setTimeout(() => { step(); demo.render(); }, at));
        });

        timers.push(setTimeout(play, LOOP_AT));
    };

    const applyMode = () => {
        s.animated = mq.matches;
        clearTimers();

        if (!s.animated) {
            resetState();
            demo.render();

            return;
        }

        if (reduced.matches) {
            finalState();
            demo.render();

            return;
        }

        play();
    };

    // Skalan följer bredden så att hela desktopvyn (1000 px) syns på en smal skärm.
    const setScale = () => root.style.setProperty('--pay-scale', String(Math.min(1, root.clientWidth / 1000)));

    if ('ResizeObserver' in window) new ResizeObserver(setScale).observe(root);

    setScale();

    // Animationen körs bara medan demot syns.
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            const was = visible;

            visible = entry.isIntersecting;

            if (visible && !was) play();
            if (!visible) clearTimers();
        }).observe(root);
    }

    mq.addEventListener('change', applyMode);

    applyMode();
}
