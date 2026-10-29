import { createDemo, esc, svg, ICON } from './core.js';

/*
 * Flytta till Oqto: två kolumner, "Ditt befintliga ekonomisystem" och "Oqto".
 * Besökaren väljer vilka delar som ska med och ser dem landa i Oqto. Demot
 * pratar medvetet generellt om "ditt befintliga system" (de exakta systemen
 * och vad som följer med respektive finns i supportguiderna under
 * /support/flytta-till-oqto). All data är påhittad.
 */

const MODULES = [
    { key: 'foretag', label: 'Företagsuppgifter', short: 'Företagsuppgifter', hint: 'Namn, adress, kontakt- och bankuppgifter', count: 1, unit: 'bolag', always: true },
    { key: 'bokf', label: 'Bokföring från valt startår', short: 'Bokföring', hint: 'Kontoplan, verifikat och ingående balans', count: 9320, unit: 'verifikat', always: true },
    { key: 'proj', label: 'Projekt och kostnadsställen', short: 'Projekt och kostnadsställen', hint: 'Med kopplingen på verifikaten', count: 14, unit: 'st', always: true },
    { key: 'reg', label: 'Kunder, leverantörer och artiklar', short: 'Kunder, leverantörer och artiklar', hint: 'Registren med adresser, betalmottagare och priser', count: 556, unit: 'st' },
    { key: 'fakt', label: 'Kund- och leverantörsfakturor med betalningar', short: 'Fakturor', hint: 'Öppna och betalda, med betalningar och krediter', count: 4334, unit: 'st' },
    { key: 'avtal', label: 'Avtal (kräver licens för fakturering)', short: 'Avtal', hint: 'Återkommande fakturering', count: 24, unit: 'st' },
    { key: 'husarbete', label: 'Husarbete (ROT/RUT)', short: 'Husarbete', hint: 'Med köpare, begärt och beviljat belopp', count: 11, unit: 'st' },
    { key: 'anl', label: 'Anläggningsregister med historik', short: 'Anläggningsregister', hint: 'Tillgångar och avskrivningsplaner', count: 18, unit: 'st' },
    { key: 'bilagor', label: 'Bilagor och låst period', short: 'Bilagor och låst period', hint: 'Underlag på verifikat och fakturor', count: 6540, unit: 'st', last: true },
];

const NOT_INCLUDED = [
    'Räkenskapsår före startåret (bara som ingående balans)',
    'Logga och fakturalayout',
    'Offerter och order',
    'Periodiseringar',
    'Anställda och löner',
    'Bankkonton',
    'Automatisk fakturering av avtal',
];

const DURATION = 5600;
const TICK = 70;

const nf = (n) => n.toLocaleString('sv-SE').replace(/,/g, ' ');
const clamp = (n) => Math.max(0, Math.min(1, n));

export default function mount(root) {

    const s = {
        open: false,
        picked: Object.fromEntries(MODULES.map((m) => [m.key, true])),
        phase: 'select',
        t: 0,
        timer: null,
    };

    const included = (m) => m.always || s.picked[m.key];

    // Delarna startar förskjutet; bilagorna kommer sist, som i verkligheten.
    const progressOf = (m, index, total) => {
        const start = m.last ? 0.4 : (index / Math.max(total, 1)) * 0.38;
        const length = m.last ? 0.6 : 0.34;

        return clamp((s.t - start) / length);
    };

    const stop = () => {
        clearInterval(s.timer);
        s.timer = null;
    };

    let demo;

    // Guiden runt demot (wizard.js) låser nästa steg tills flytten är gjord.
    const setGate = (value) => {
        root.dataset.gate = value;
        root.dispatchEvent(new CustomEvent('dm:gate', { bubbles: true }));
    };

    setGate('pending');

    // Under flytten uppdateras bara de delar som ändras. Ritas hela demot om
    // varje tick byts knapparna ut mellan musnedtryck och släpp, och ett klick
    // (t.ex. på utfällningen) går då aldrig fram.
    const softRender = () => {
        const next = document.createElement('div');

        next.innerHTML = view();

        ['.dm-mg-sumr', '.dm-mg-overall', '.dm-mg-go'].forEach((selector) => {
            const live = root.querySelector(selector);
            const fresh = next.querySelector(selector);

            if (live && fresh) live.innerHTML = fresh.innerHTML;
        });

        const fresh = [...next.querySelectorAll('.dm-mg-row:not(.is-summary)')];

        root.querySelectorAll('.dm-mg-row:not(.is-summary)').forEach((el, i) => {
            if (fresh[i]) el.replaceWith(fresh[i]);
        });
    };

    const start = () => {
        s.phase = 'running';
        s.t = 0;

        stop();

        const began = performance.now();

        s.timer = setInterval(() => {
            s.t = clamp((performance.now() - began) / DURATION);

            if (s.t >= 1) {
                stop();
                s.phase = 'done';
                setGate('done');
                demo.render();

                return;
            }

            softRender();
        }, TICK);
    };

    const reset = () => {
        stop();
        s.phase = 'select';
        s.t = 0;
        setGate('pending');
    };

    /* ------------------------------------------------------------------ vy */

    const row = (m, rows) => {
        const on = included(m);
        const idx = rows.indexOf(m);
        const p = s.phase === 'select' ? 0 : (on ? progressOf(m, idx, rows.length) : 0);
        const done = p >= 1;
        const running = s.phase !== 'select' && on && !done;
        const locked = s.phase !== 'select';

        const chip = m.always ? '<span class="dm-mg-chip">Alltid med</span>' : '';

        const check = `<span class="dm-mg-check${m.always ? ' is-fixed' : ''}${on ? ' is-on' : ''}" aria-hidden="true">${on ? svg(ICON.check, 13, 3) : ''}</span>`;

        const left = (m.always || locked)
            ? `<div class="dm-mg-cell is-left">${check}${label(m)}${chip}</div>`
            : `<button type="button" class="dm-mg-cell is-left is-button" data-act="toggle" data-arg="${m.key}" role="checkbox" aria-checked="${s.picked[m.key] ? 'true' : 'false'}">${check}${label(m)}${chip}</button>`;

        let state;

        if (!on) {
            state = '<span class="dm-mg-wait">Flyttas inte</span>';
        } else if (s.phase === 'select') {
            state = '<span class="dm-mg-wait">Väntar</span>';
        } else if (done) {
            state = `<span class="dm-mg-done">${svg(ICON.check, 14, 3)}<span>${nf(m.count)} ${m.unit}</span></span>`;
        } else {
            state = `<span class="dm-mg-bar"><span class="dm-mg-bar-fill" style="width:${Math.round(p * 100)}%"></span></span><span class="dm-mg-count">${nf(Math.round(m.count * p))} / ${nf(m.count)}</span>`;
        }

        return `
            <div class="dm-mg-row${on ? '' : ' is-skipped'}${running ? ' is-running' : ''}${done ? ' is-done' : ''}">
                ${left}
                <div class="dm-mg-arrow" aria-hidden="true">${svg(ICON.chevronRight, 18, 2.4)}</div>
                <div class="dm-mg-cell is-right"><span class="dm-mg-rlabel">${esc(m.label)}</span><span class="dm-mg-state">${state}</span></div>
            </div>`;
    };

    const label = (m) => `<span class="dm-mg-text"><span class="dm-mg-name">${esc(m.label)}</span><span class="dm-mg-hint">${esc(m.hint)}</span></span>`;

    const view = () => {
        const rows = MODULES.filter(included);
        const total = rows.length;
        const overall = s.phase === 'select' ? 0 : s.phase === 'done' ? 1 : s.t;

        const note = 'Välj vad som ska flyttas över från ditt befintliga ekonomisystem. Oqto hämtar uppgifterna med en integration eller från filer, beroende på system.';

        let button;

        if (s.phase === 'select') {
            button = `<button type="button" class="dm-mg-go" data-act="start">Flytta över<span class="dm-mg-go-arrow">${svg(ICON.chevronRight, 16, 2.6)}</span></button>`;
        } else if (s.phase === 'running') {
            button = `<button type="button" class="dm-mg-go is-busy" disabled>Flyttar… ${Math.round(overall * 100)} %</button>`;
        } else {
            button = '<button type="button" class="dm-mg-go is-ghost" data-act="reset">Börja om</button>';
        }

        // När flytten är klar blir bekräftelsen knappen till nästa steg.
        const banner = s.phase === 'done'
            ? `<button type="button" class="dm-mg-next" data-act="next">
                    <span class="dm-mg-next-check">${svg(ICON.check, 18, 3)}</span>
                    <span class="dm-mg-next-text"><strong>Klart!</strong> Allt är på plats i Oqto. Gå vidare och jämför rapporterna.</span>
                    <span class="dm-mg-next-arrow">${svg(ICON.chevronRight, 18, 2.6)}</span>
                </button>`
            : '';

        // Överblicken: allt som flyttas i en rad, så att demot inte behöver vara högt.
        const records = rows.reduce((n, m) => n + m.count, 0);
        const active = rows
            .map((m, i) => ({ m, p: progressOf(m, i, total) }))
            .filter((x) => x.p > 0 && x.p < 1)
            .pop();

        let sumState;

        if (s.phase === 'select') {
            sumState = `<span class="dm-mg-to">Flyttas hit till Oqto</span><span class="dm-mg-sub">${nf(records)} poster väntar på att flyttas</span>`;
        } else if (s.phase === 'running') {
            sumState = `<span class="dm-mg-sumline"><span class="dm-mg-bar"><span class="dm-mg-bar-fill" style="width:${Math.round(overall * 100)}%"></span></span><span class="dm-mg-count">${Math.round(overall * 100)} %</span></span><span class="dm-mg-sub">${active ? `Flyttar ${esc(active.m.short.toLowerCase())}… ${nf(Math.round(active.m.count * active.p))} / ${nf(active.m.count)}` : 'Förbereder…'}</span>`;
        } else {
            sumState = `<span class="dm-mg-done">${svg(ICON.check, 14, 3)}<span>${nf(records)} poster flyttade</span></span><span class="dm-mg-sub">${total} ${total === 1 ? 'del' : 'delar'} på plats i Oqto</span>`;
        }

        const summary = `
            <div class="dm-mg-row is-summary${s.phase === 'running' ? ' is-running' : ''}${s.phase === 'done' ? ' is-done' : ''}">
                <button type="button" class="dm-mg-cell is-left is-button dm-mg-sum" data-act="fold" aria-expanded="${s.open ? 'true' : 'false'}">
                    <span class="dm-mg-text"><span class="dm-mg-name">Allt som kan flyttas</span><span class="dm-mg-hint">${rows.map((m) => esc(m.short)).join(', ')}</span></span>
                    <span class="dm-mg-pick">
                        <span class="dm-mg-pick-label">${s.open ? 'Dölj delarna' : (s.phase === 'select' ? 'Välj vilka delar som ska med' : 'Visa alla delar')}</span>
                        <span class="dm-mg-fold-count">${total} av ${MODULES.length}</span>
                        <span class="dm-mg-fold-chev${s.open ? ' is-open' : ''}">${svg(ICON.chevronDown, 18, 2.4)}</span>
                    </span>
                </button>
                <div class="dm-mg-arrow" aria-hidden="true"><span class="dm-mg-flow">${svg(ICON.chevronRight, 18, 2.8)}</span></div>
                <div class="dm-mg-cell is-right dm-mg-sumr">${sumState}</div>
            </div>`;

        return `
            <div class="dm-window is-fit">
                <div class="dm-topbar">
                    <span class="dm-crumb">Klienter</span><span class="dm-crumb">/</span><strong>Flytta till Oqto</strong>
                    <span class="dm-grow"></span>
                    <span class="dm-pill-mono">DEMO</span>
                </div>

                <div class="dm-mg-scroll" data-scroll="mg">
                    <div class="dm-mg-page">

                        <p class="dm-mg-note">${note}</p>

                        <div class="dm-mg-board">
                            <div class="dm-mg-heads is-${s.phase}">
                                <div class="dm-mg-head is-left">
                                    <span class="dm-mg-logo is-generic">${svg('M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6', 20, 1.8)}</span>
                                    <strong>Ditt befintliga ekonomisystem</strong>
                                </div>
                                <div class="dm-mg-head-arrow" aria-hidden="true"><span class="dm-mg-flow">${svg(ICON.chevronRight, 18, 2.8)}</span></div>
                                <div class="dm-mg-head is-right">
                                    <span class="dm-mg-logo is-oqto"><img src="/favicon.svg" alt=""></span>
                                    <strong>Oqto</strong>
                                </div>
                            </div>

                        </div>

                        <div class="dm-mg-board is-list">
                            ${summary}
                            ${s.open ? MODULES.map((m) => row(m, rows)).join('') : ''}
                        </div>

                        ${s.phase === 'done' ? `
                            <div class="dm-mg-extra">
                                <span class="dm-label">FÖLJER INTE MED</span>
                                <ul>${NOT_INCLUDED.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>
                            </div>` : ''}

                        <div class="dm-mg-foot">
                            <div class="dm-mg-overall">
                                <span class="dm-mg-overall-text">${total} ${total === 1 ? 'del' : 'delar'} flyttas${s.phase === 'select' ? ' · Flytta över för att gå vidare' : ` · ${Math.round(overall * 100)} %`}</span>
                                <span class="dm-mg-bar is-wide"><span class="dm-mg-bar-fill" style="width:${Math.round(overall * 100)}%"></span></span>
                            </div>
                            ${button}
                        </div>

                        ${banner}

                    </div>
                </div>
            </div>`;
    };

    /* ------------------------------------------------------------- åtgärder */

    demo = createDemo(root, s, view, {
        toggle: (key) => {
            if (s.phase !== 'select') return;
            s.picked[key] = !s.picked[key];
        },
        fold: () => { s.open = !s.open; },
        start,
        reset,
        next: () => root.dispatchEvent(new CustomEvent('dm:next', { bubbles: true })),
    });
}
