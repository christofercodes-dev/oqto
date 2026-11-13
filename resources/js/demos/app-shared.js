import { createDemo, esc, fmt, fmtQ, addDays, todayISO, svg, ICON } from './core.js';

/*
 * Oqtoappen: en telefon med appens startsida, Betalningar och Attest. Samma app
 * används av två demon: "app-sign" (markera fakturor och signera betalningen med
 * BankID) och "app-attest" (markera fakturor och attestera dem). Allt är påhittat
 * och inget skickas. Det som är nästa steg pulserar, så att man ser var man ska trycka.
 */

const ICONS = {
    card: 'M3 6.5h18v11H3zM3 10.5h18',
    home: 'M3 11.5 12 4l9 7.5M5.5 10v10h13V10M10 20v-5.5h4V20',
    stamp: 'M5 22h14M19.27 13.73A2.5 2.5 0 0 0 17.5 13h-11A2.5 2.5 0 0 0 4 15.5V17a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1.5c0-.66-.26-1.3-.73-1.77zM14 13V8.5C14 7 15 7 15 5a3 3 0 0 0-3-3c-1.66 0-3 1-3 3s1 2 1 3.5V13',
    grid: 'M4 4h6.5v6.5H4zM13.5 4H20v6.5h-6.5zM4 13.5h6.5V20H4zM13.5 13.5H20V20h-6.5z',
    plus: 'M12 5v14M5 12h14',
    chevron: 'm9 18 6-6-6-6',
    updown: 'm8 9 4-4 4 4M8 15l4 4 4-4',
    info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v5M12 8h.01',
    bank: 'M3 21h18M5 21V10M19 21V10M10 21v-7h4v7M12 3 3 9h18z',
    close: 'M18 6 6 18M6 6l12 12',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    ok: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8.5 12.5l2.5 2.5 4.5-5',
};

const today = todayISO();

const SETS = {
    sign: [
        { id: 1, name: 'DNB Finans', date: addDays(today, -12), amount: 7535 },
        { id: 2, name: 'Nordisk El AB', date: addDays(today, -4), amount: 1675 },
    ],
    attest: [
        { id: 1, name: 'Kontorsbolaget AB', date: addDays(today, -3), amount: 4250 },
        { id: 2, name: 'Lokalvård Syd AB', date: addDays(today, -1), amount: 8900 },
    ],
};

const kr = (n) => `${fmtQ(n)} kr`;

const plural = (n, one, many) => (n === 1 ? one : many);

/**
 * @param {HTMLElement} root
 * @param {'sign' | 'attest' | 'balance'} mode  vad demot går ut på (styr data och vad som pulserar)
 */
export default function mountApp(root, mode) {

    const fresh = () => ({
        tab: 'home',
        sel: {},
        overlay: null,   // 'sign' medan BankID startas
        toast: null,
        visited: {},
        sign: mode === 'sign' ? SETS.sign.map((i) => ({ ...i })) : [],
        attest: mode === 'attest' ? SETS.attest.map((i) => ({ ...i })) : [],
        signed: 0,
    });

    const s = fresh();

    let demo;
    let toastTimer;
    let signTimer;

    const showToast = (text) => {
        s.toast = text;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { s.toast = null; demo.render(); }, 4000);
    };

    const open = (key) => s[key].filter((i) => !i.done);
    const listFor = (tab) => open(tab === 'attest' ? 'attest' : 'sign');
    const total = (list) => list.reduce((sum, i) => sum + i.amount, 0);
    const picked = () => listFor(s.tab).filter((i) => s.sel[i.id]);

    const pulse = (on) => (on ? ' dm-app-pulse' : '');

    // Nästa demo på sidan (om det finns): ett färdigt demo leder vidare dit.
    const nextTarget = () => {
        const selector = root.dataset.next;
        const el = selector ? document.querySelector(selector) : null;

        if (!el) return null;

        return { el, label: el.querySelector('.fo-stack-title')?.textContent.trim() || 'nästa' };
    };

    const nextButton = () => {
        const next = nextTarget();

        return next
            ? `<button type="button" class="dm-app-next dm-app-pulse" data-act="goNext">Nästa: ${esc(next.label)}${svg('M12 5v14M6 13l6 6 6-6', 18, 2.2)}</button>`
            : '';
    };

    /* ---------- Gemensamma delar ---------- */

    const header = (greeting, sub) => `
        <div class="dm-app-top">
            <span class="dm-app-co"><i>EB</i>Exempelbolaget AB${svg(ICONS.updown, 14, 2)}</span>
            ${greeting ? `<strong class="dm-app-hello">Ciao <em>ANNA</em>!</strong><span class="dm-app-sub">${sub}</span>` : ''}
        </div>`;

    const tabs = () => {
        const tab = (key, label, icon, enabled) => `
            <button type="button" class="dm-app-tab${s.tab === key ? ' is-on' : ''}${enabled && !s.visited[key] && s.tab !== key && hintTab() === key ? ' dm-app-pulse-tab' : ''}" ${enabled ? `data-act="goTab" data-arg="${key}"` : 'tabindex="-1" aria-disabled="true"'}>
                ${svg(icon, 22, 1.8)}<span>${label}</span>
            </button>`;

        return `
            <nav class="dm-app-tabs" aria-label="Appens flikar">
                ${tab('home', 'Hem', ICONS.home, true)}
                ${tab('sign', 'Betalningar', ICONS.card, true)}
                <span class="dm-app-upload"><i class="dm-app-plus">${svg(ICONS.plus, 24, 1.8)}</i><span>Ladda upp</span></span>
                ${tab('attest', 'Attest', ICONS.stamp, true)}
                ${tab('menu', 'Meny', ICONS.grid, false)}
            </nav>`;
    };

    // Vilken flik som pulserar först: den som demot handlar om.
    const hintTab = () => (mode === 'sign' ? 'sign' : mode === 'attest' ? 'attest' : null);

    /* ---------- Startsidan ---------- */

    const taskCard = (key, title, sub, amount) => `
        <button type="button" class="dm-app-task${pulse(!s.visited[key] && hintTab() === key)}" data-act="goTab" data-arg="${key}">
            <span class="dm-app-task-icon">${svg(key === 'sign' ? ICONS.card : ICONS.stamp, 18, 1.8)}</span>
            <span class="dm-app-task-text"><strong>${title}</strong><span>${sub}</span></span>
            <b>${amount}</b>
            ${svg(ICONS.chevron, 16, 2)}
        </button>`;

    const home = () => {
        const toSign = open('sign');
        const toAttest = open('attest');
        const tasks = (toSign.length ? 1 : 0) + (toAttest.length ? 1 : 0);
        const nothing = tasks === 0;

        return `
            ${header(true, nothing ? 'Här är din översikt.' : `Du har <b>${tasks}</b> ${plural(tasks, 'sak', 'saker')} att göra idag.`)}
            <div class="dm-app-sheet">
                <span class="dm-app-grab"></span>
                ${nothing ? `
                    <div class="dm-app-done">
                        <span class="dm-app-emoji" role="img" aria-label="Fest">🎉</span>
                        <strong>Allt klart!</strong>
                        <span>Inget att betala eller attestera just nu.</span>
                        ${mode === 'attest' && s.signed ? nextButton() : ''}
                        ${s.signed ? '<button type="button" class="dm-app-link" data-act="reset">Börja om demot</button>' : ''}
                    </div>` : `
                    <div class="dm-app-tasks">
                        ${toSign.length ? taskCard('sign', 'Att betala', `${toSign.length} ${plural(toSign.length, 'betalning', 'betalningar')} att signera`, kr(total(toSign))) : ''}
                        ${toAttest.length ? taskCard('attest', 'Att attestera', `${toAttest.length} ${plural(toAttest.length, 'faktura', 'fakturor')} att attestera`, kr(total(toAttest))) : ''}
                    </div>`}

                <span class="dm-app-label">Bankkonton</span>
                ${mode === 'balance' ? `
                <button type="button" class="dm-app-row is-button${pulse(!s.visited.account)}" data-act="account">
                    <span class="dm-app-bank">${svg(ICONS.bank, 18, 1.8)}</span>
                    <span class="dm-app-row-text"><span>Företagskonto</span><strong>118 450,00 SEK</strong></span>
                    ${svg(ICONS.chevron, 16, 2)}
                </button>` : `
                <div class="dm-app-row">
                    <span class="dm-app-bank">${svg(ICONS.bank, 18, 1.8)}</span>
                    <span class="dm-app-row-text"><span>Företagskonto</span><strong>118 450,00 SEK</strong></span>
                    ${svg(ICONS.chevron, 16, 2)}
                </div>`}

                ${mode === 'balance' && s.visited.account ? nextButton() : ''}

                <div class="dm-app-label-row"><span class="dm-app-label">Lön</span><span class="dm-app-pillbtn">Visa alla</span></div>
                <div class="dm-app-pay-cards">
                    <div class="dm-app-pay-card"><i>SEP</i><span><small>Utbetalas 25 sep</small><strong>34 347,00</strong></span></div>
                    <div class="dm-app-pay-card"><i>AUG</i><span><small>Utbetald 25 aug</small><strong>34 347,00</strong></span></div>
                </div>
            </div>`;
    };

    /* ---------- Betalningar och Attest ---------- */

    const listScreen = () => {
        const attest = s.tab === 'attest';
        const list = listFor(s.tab);
        const chosen = picked();
        const all = list.length > 0 && chosen.length === list.length;
        const sum = total(list);

        const noun = attest ? ['faktura', 'fakturor'] : ['faktura', 'fakturor'];
        const idle = attest ? 'Markera en faktura för att attestera' : 'Markera en faktura för att betala';
        const action = attest
            ? `Attestera ${chosen.length} ${plural(chosen.length, noun[0], noun[1])}`
            : `Betala ${chosen.length} ${plural(chosen.length, noun[0], noun[1])} - ${kr(total(chosen))}`;

        const nextPulse = (list.length > 0 && !chosen.length) ? 'select' : chosen.length ? 'action' : '';

        return `
            ${header(false)}
            <div class="dm-app-sheet is-list">
                <div class="dm-app-summary">
                    <span>${attest ? 'Att attestera' : 'Att betala'}</span>
                    <span class="dm-app-ok">${svg(ICONS.ok, 22, 1.6)}</span>
                    <strong>${kr(sum)}</strong>
                    <small>${attest ? (list.length ? `${list.length} ${plural(list.length, 'faktura', 'fakturor')} väntar` : 'Inget väntar') : 'Inga försenade betalningar'}</small>
                </div>

                <button type="button" class="dm-app-action${chosen.length ? ' is-ready' : ''}${pulse(nextPulse === 'action')}" data-act="${attest ? 'attest' : 'pay'}"${chosen.length ? '' : ' disabled'}>${chosen.length ? action : idle}</button>

                ${list.length ? `
                    <button type="button" class="dm-app-item is-all${pulse(nextPulse === 'select')}" data-act="toggleAll" role="checkbox" aria-checked="${all ? 'true' : 'false'}">
                        <span class="dm-app-check${all ? ' is-on' : ''}">${all ? svg(ICONS.check, 14, 3) : ''}</span>
                        <span class="dm-app-item-name">Markera alla</span>
                    </button>
                    ${list.map((i) => `
                        <button type="button" class="dm-app-item" data-act="toggle" data-arg="${i.id}" role="checkbox" aria-checked="${s.sel[i.id] ? 'true' : 'false'}">
                            <span class="dm-app-check${s.sel[i.id] ? ' is-on' : ''}">${s.sel[i.id] ? svg(ICONS.check, 14, 3) : ''}</span>
                            <span class="dm-app-item-name"><strong>${esc(i.name)}</strong><small>${esc(i.date)}</small></span>
                            <span class="dm-app-item-sum">${fmt(i.amount)} SEK</span>
                            <span class="dm-app-info" aria-hidden="true">${svg(ICONS.info, 20, 1.6)}</span>
                        </button>`).join('')}
                ` : `
                    <p class="dm-app-empty">${attest ? 'Inga fakturor att attestera' : 'Inga betalningar att signera'}</p>
                    ${attest && s.signed ? nextButton() : ''}
                    ${s.signed ? '<button type="button" class="dm-app-link" data-act="reset">Börja om demot</button>' : ''}
                `}
            </div>`;
    };

    const bankId = () => `
        <div class="dm-app-overlay" role="dialog" aria-modal="true" aria-label="Signera med BankID">
            <div class="dm-app-overlay-head">
                <span class="dm-app-bank is-lg">${svg(ICONS.bank, 20, 1.8)}</span>
                <span class="dm-app-overlay-title"><strong>Signera med BankID</strong><small>Betala ${picked().length} ${plural(picked().length, 'faktura', 'fakturor')} - ${kr(total(picked()))}</small></span>
                <button type="button" class="dm-app-close" data-act="cancel" aria-label="Avbryt">${svg(ICONS.close, 22, 2)}</button>
            </div>
            <div class="dm-app-overlay-card">
                <span class="dm-app-spin" aria-hidden="true"></span>
                <span>Startar signering hos banken</span>
            </div>
        </div>`;

    const toast = () => s.toast
        ? `<div class="dm-app-toast" role="status"><span>${esc(s.toast)}</span><button type="button" data-act="dismiss">OK</button></div>`
        : '';

    const screen = () => (s.tab === 'home' ? home() : listScreen());

    const view = () => `
        <div class="dm-app-stage">
            <div class="dm-app-phone">
                <div class="dm-app-scroll" data-scroll="app">${screen()}</div>
                ${tabs()}
                ${s.overlay ? bankId() : ''}
                ${toast()}
            </div>
        </div>`;

    /* ---------- Åtgärder ---------- */

    demo = createDemo(root, s, view, {
        goTab: (tab) => {
            s.tab = tab === 'sign' || tab === 'attest' ? tab : 'home';
            s.sel = {};
            s.visited[s.tab] = true;
        },
        toggle: (id) => { s.sel[id] = !s.sel[id]; },
        toggleAll: () => {
            const list = listFor(s.tab);
            const all = list.every((i) => s.sel[i.id]);

            list.forEach((i) => { s.sel[i.id] = !all; });
        },
        pay: () => {
            if (!picked().length) return;

            s.overlay = 'sign';

            signTimer = setTimeout(() => {
                const ids = picked().map((i) => i.id);

                s.sign.forEach((i) => { if (ids.includes(i.id)) i.done = true; });
                s.sel = {};
                s.overlay = null;
                s.signed += ids.length;
                showToast(ids.length === 1 ? 'Betalningen signerades' : 'Betalningarna signerades');
                demo.render();
            }, 2400);
        },
        cancel: () => { clearTimeout(signTimer); s.overlay = null; },
        attest: () => {
            const ids = picked().map((i) => i.id);

            if (!ids.length) return;

            s.attest.forEach((i) => { if (ids.includes(i.id)) i.done = true; });
            s.sel = {};
            s.signed += ids.length;
            showToast(ids.length === 1 ? 'Fakturan attesterades' : 'Fakturorna attesterades');
        },
        account: () => {
            s.visited.account = true;
            showToast('I appen öppnas kontot här. Det går inte att prova i demot.');
        },
        goNext: () => {
            const next = nextTarget();

            if (!next) return;

            const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            next.el.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'center' });
        },
        dismiss: () => { clearTimeout(toastTimer); s.toast = null; },
        reset: () => {
            clearTimeout(signTimer);
            clearTimeout(toastTimer);
            Object.assign(s, fresh());
        },
    });
}
