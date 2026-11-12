import { esc, svg, ICON, addDays, todayISO } from './core.js';

/*
 * Delat av skattekontodemona: ikoner, påhittad data, själva skattekontosidan
 * och animationen som ersätter demot på mobil.
 */

export const ICONS = {
    robot: 'M12 3v3M5 8h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2zM9 13v1M15 13v1',
    close: 'M18 6 6 18M6 6l12 12',
    wallet: 'M3 7a2 2 0 0 1 2-2h13v4M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5a2 2 0 0 1-2-2zM16 14h2',
    gear: 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM19 12l2-1-1-3-2 .5-1.5-1.5L17 5l-3-1-1 2h-2l-1-2-3 1 .5 2L5 8.5 3 8l-1 3 2 1v2l-2 1 1 3 2-.5L6.5 19 7 21l3 1 1-2h2l1 2 3-1-.5-2 1.5-1.5 2 .5 1-3-2-1z',
    dots: 'M12 5h.01M12 12h.01M12 19h.01',
    landmark: 'M3 21h18M5 21V10M19 21V10M10 21v-7h4v7M12 3 3 9h18z',
    dotCircle: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11.5h.01',
    plusCircle: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 8v8M8 12h8',
    calendar: 'M8 3v4M16 3v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z',
    bell: 'M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8zM10 20a2 2 0 0 0 4 0',
    bolt: 'M13 2 4 14h7l-1 8 9-12h-7z',
    wand: 'M15 4l5 5M3 21l12-12 3 3L6 24M18 3v2M21 6h-2M5 3v2M8 4H6',
};

export const today = todayISO();

const pad = (n) => String(n).padStart(2, '0');

// "2026-11" för månaden som ligger `back` månader bakåt.
const ym = (back) => {
    const d = new Date();

    d.setDate(1);
    d.setMonth(d.getMonth() - back);

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
};

// De senaste månadernas tre transaktioner. De två senaste är "Säkert förslag", resten avstämda.
const monthRows = (back) => {
    const label = ym(back);
    const date = `${label}-12`;
    const match = back < 2 ? 'suggest' : 'ok';

    return [
        { text: `Arbetsgivaravgift ${label}`, match, date, amount: -7855, balance: back === 0 ? 18 : 0 },
        { text: `Avdragen skatt ${label}`, match, date, amount: -6500, balance: back === 0 ? 7873 : 7855 },
        { text: `Inbetalning ${label}`, match, date, amount: 14355, balance: back === 0 ? 14373 : 14355 },
    ];
};

export const ROWS = [
    ...monthRows(0),
    { text: 'Intäktsränta', match: 'suggest', icon: 'plus', date: addDays(today, -2), amount: 18, balance: 18 },
    ...monthRows(1),
    ...monthRows(2),
    ...monthRows(3),
];

export const BASE_ESTIMATE = 10020;

export const money = (n) => n.toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace('-', '−');

const matchPill = (row) => {
    if (row.match === 'ok') return `<span class="dm-tax-match is-ok">${svg(ICON.check, 14, 2.4)}Avstämd</span>`;

    return `<span class="dm-tax-match is-suggest">${svg(row.icon === 'plus' ? ICONS.plusCircle : ICONS.dotCircle, 14, 1.8)}Säkert förslag</span>`;
};

const rowHtml = (row) => `
    <tr>
        <td class="dm-tax-c-text">${esc(row.text)}</td>
        <td><span class="dm-pay-pill dm-tax-pill-done">Genomförd</span></td>
        <td class="dm-tax-c-match">${matchPill(row)}</td>
        <td class="dm-tax-c-date">${esc(row.date)}</td>
        <td class="dm-pay-c-sum${row.amount < 0 ? ' is-neg' : ''}">${money(row.amount)}</td>
        <td class="dm-pay-c-sum dm-tax-c-balance${row.balance < 0 ? ' is-neg' : ''}">${money(row.balance)}</td>
    </tr>`;

const stat = (label, value, sub, info = false, flash = false) => `
    <div class="dm-tax-stat${flash ? ' is-flash' : ''}">
        <span class="dm-tax-stat-label">${esc(label)}${info ? '<span class="dm-tax-info" aria-hidden="true">i</span>' : ''}</span>
        <span class="dm-tax-stat-value">${value}<small>SEK</small></span>
        <span class="dm-tax-stat-sub">${esc(sub)}</span>
    </div>`;

const cls = (base, o) => `${base}${o.pulsing ? ' is-pulsing' : ''}${o.pressed ? ' is-pressed' : ''}`;

/**
 * Skattekontosidan (rubrik med knappar, saldorutor och tabell).
 *   fill: { pulsing, pressed } och auto: { on, pulsing, pressed, interactive } styr de två knapparna.
 */
export function taxPage({ fill = {}, auto = {}, extra = 0, flashEstimate = false, fillInteractive = true }) {
    const autoSwitch = `<span class="dm-pay-switch${auto.on ? ' is-on' : ''}" aria-hidden="true"></span>${svg(ICONS.robot, 17, 1.8)}Autopilot`;

    const autoButton = auto.interactive
        ? `<button type="button" class="${cls('dm-pay-ghost dm-tax-autobtn dm-pulse', auto)}" data-act="autopilot" aria-pressed="${auto.on ? 'true' : 'false'}">${autoSwitch}</button>`
        : `<span class="dm-pay-ghost">${autoSwitch}</span>`;

    const fillButton = fillInteractive
        ? `<button type="button" class="${cls('dm-tax-fill', fill)}" data-act="fill">${svg(ICONS.wallet, 17, 1.8)}Fyll på kontot</button>`
        : `<span class="dm-tax-fill is-static">${svg(ICONS.wallet, 17, 1.8)}Fyll på kontot</span>`;

    return `
        <div class="dm-tax-head">
            <span class="dm-tax-title"><span class="dm-tax-logo">${svg(ICONS.landmark, 22, 1.8)}</span>Skattekonto</span>
            <span class="dm-grow"></span>
            ${autoButton}
            ${fillButton}
            <span class="dm-pay-ghost">${svg(ICONS.gear, 17, 1.8)}Inställningar</span>
            <span class="dm-tax-dots" aria-hidden="true">${svg(ICONS.dots, 18, 2.6)}</span>
        </div>

        <div class="dm-tax-stats">
            ${stat('Saldo', '18,00', `${today} 11:35`)}
            ${stat('Inga kommande transaktioner', '0', '-')}
            ${stat('Beräknat saldo', money(BASE_ESTIMATE + extra).replace('−', '-'), addDays(today, 14), true, flashEstimate)}
            ${stat('Saldo hos Kronofogden', '0,00', `${today} 11:35`)}
        </div>

        <div class="dm-tablewrap-tax dm-pay-tablewrap" data-scroll="tax">
            <table class="dm-pay-table dm-tax-table">
                <thead>
                    <tr>
                        <th>Beskrivning</th>
                        <th>Status</th>
                        <th class="dm-tax-c-match">Avstämd</th>
                        <th class="dm-tax-c-date">Transaktionsdag</th>
                        <th class="dm-pay-c-sum">Belopp</th>
                        <th class="dm-pay-c-sum dm-tax-c-balance">Saldo</th>
                    </tr>
                </thead>
                <tbody>${ROWS.map(rowHtml).join('')}</tbody>
            </table>
        </div>`;
}

export const topbar = (title) => `
    <div class="dm-topbar">
        <strong>${esc(title)}</strong>
        <span class="dm-grow"></span>
        <span class="dm-pill-mono">DEMO</span>
    </div>`;

export const toastHtml = (toast) => toast
    ? `<div class="dm-pay-toast" role="status"><span class="dm-pay-toast-icon">${svg(ICON.check, 16, 3)}</span><span><strong>${esc(toast.title)}</strong><span>${esc(toast.text)}</span></span></div>`
    : '';

/** På mobil visas desktopvyn som en skalad animation i stället för demot. */
export const scaled = (animated, windowHtml) => animated
    ? `<div class="dm-pay-box"><div class="dm-pay-scaled" aria-hidden="true">${windowHtml}</div></div>
       <p class="dm-pay-anim-note">Så här ser flödet ut på dator.</p>`
    : windowHtml;

/**
 * Kör `script` i en loop på mobil (och bara medan demot syns).
 * script: [[ms, () => ändra tillståndet], ...]. reset() nollställer tillståndet.
 */
export function animateOnMobile(root, s, demo, { reset, script, loopAt }) {
    const mq = window.matchMedia('(max-width: 767px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    let timers = [];
    let visible = true;

    const clearTimers = () => { timers.forEach(clearTimeout); timers = []; };

    const play = () => {
        clearTimers();

        if (!s.animated || !visible) return;

        reset();
        demo.render();

        script.forEach(([at, step]) => {
            timers.push(setTimeout(() => { step(); demo.render(); }, at));
        });

        timers.push(setTimeout(play, loopAt));
    };

    const applyMode = () => {
        s.animated = mq.matches;
        clearTimers();
        reset();

        if (s.animated && !reduced.matches) {
            play();

            return;
        }

        demo.render();
    };

    const setScale = () => root.style.setProperty('--pay-scale', String(Math.min(1, root.clientWidth / 1000)));

    if ('ResizeObserver' in window) new ResizeObserver(setScale).observe(root);

    setScale();

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
