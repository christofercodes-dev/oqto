import { createDemo, esc, num, fmt, fmtQ, addDays, longDate, todayISO, initials, svg, ICON } from './core.js';

const CUSTS = [
    { id: 'ac', name: 'Ackolad AB', org: '556901-2231', city: 'Kristianstad', terms: 30, addr: 'Västra Storgatan 11', zip: '29132 Kristianstad', ref: 'Anna Ek', email: 'anna@ackolad.se', einv: true },
    { id: 'sb', name: 'Steambaker AB', org: '559122-4410', city: 'Lund', terms: 15, addr: 'Stora Södergatan 8', zip: '22223 Lund', ref: 'Johan Berg', email: 'ekonomi@steambaker.se', einv: false },
    { id: 'ng', name: 'Nordic Golf Group', org: '556788-0192', city: 'Malmö', terms: 30, addr: 'Bergsgatan 20', zip: '21154 Malmö', ref: 'Sara Lind', email: 'faktura@nordicgolf.se', einv: true },
    { id: 'bn', name: 'Back Nine AB', org: '559301-7765', city: 'Stockholm', terms: 10, addr: 'Hornsgatan 54', zip: '11821 Stockholm', ref: 'Max Holm', email: 'max@backnine.se', einv: false },
];

const ARTS = [
    { no: '1001', name: 'Rådgivning', unit: 'tim', price: 1500, vat: 25 },
    { no: 'KON', name: 'Konsultarvode', unit: 'st', price: 6550, vat: 25 },
    { no: '2001', name: 'Utlägg resa', unit: 'st', price: 0, vat: 6 },
    { no: '3001', name: 'Licens', unit: 'mån', price: 990, vat: 25 },
];

const HISTORY = [
    { no: 2040, name: 'Steambaker AB', date: '21 okt', sum: '8 750,00', status: 'Betald' },
    { no: 2039, name: 'Nordic Golf Group', date: '14 okt', sum: '31 250,00', status: 'Skickad' },
    { no: 2038, name: 'Ackolad AB', date: '2 okt', sum: '18 000,00', status: 'Betald' },
];

const PILLS = { Betald: 'is-paid', Skickad: 'is-sent', Bokförd: 'is-booked', Utkast: 'is-draft' };

const ICONS = {
    doc: 'M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 8h6M9 12h6M9 16h3',
    quote: 'M5 4h14v16H5zM9 9h6M9 13h4',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
    receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
    inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z',
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
    card: 'M2 5h20v14H2zM2 10h20',
    menu: 'M4 6h16M4 12h16M4 18h16',
    bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
};

export default function mount(root) {

    const today = todayISO();
    const now = new Date();
    const clock = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

    const blank = () => ({
        step: 1, maxStep: 1, sheet: null, custId: null, q: '', nc: null, rows: [], edit: null, artOpen: false,
        terms: null, theirRef: null, text: '', dist: null, discount: '', fee: '', attached: false, action: 'send',
    });

    const s = { ...blank(), screen: 'home', extra: [], invDate: today, delDate: today, ourRef: 'Elsa Nilsson', tmp: '', tmpDisc: '', tmpFee: '', sent: [] };

    const allCusts = () => [...CUSTS, ...s.extra];
    const cust = () => allCusts().find((c) => c.id === s.custId);
    const newEdit = () => ({ idx: -1, art: null, name: '', qty: '1', unit: 'st', price: '', vat: 25 });
    const editValid = () => !!s.edit && !!s.edit.name.trim() && num(s.edit.qty) > 0 && num(s.edit.price) > 0;
    const canNext = () => (s.step === 1 ? !!s.custId : s.step === 2 ? s.rows.length > 0 : true);

    const go = (n) => {
        const open = n === 2 && s.rows.length === 0;

        s.step = n;
        s.maxStep = Math.max(s.maxStep, n);
        s.sheet = open ? 'row' : null;
        s.edit = open ? newEdit() : s.edit;
        s.artOpen = false;
    };

    const next = () => { if (canNext() && s.step < 4) go(s.step + 1); };
    const startWizard = () => { Object.assign(s, blank(), { screen: 'wiz' }); };
    const cancel = () => { Object.assign(s, blank(), { screen: 'home' }); };
    const back = () => { if (s.step === 1) cancel(); else go(s.step - 1); };

    const commitRow = () => {
        if (!editValid()) return;

        const e = s.edit;
        const row = { art: e.art, name: e.name.trim(), qty: num(e.qty), unit: e.unit, price: num(e.price), vat: e.vat };

        if (e.idx >= 0) s.rows[e.idx] = row; else s.rows.push(row);

        s.edit = null;
        s.sheet = null;
    };

    const totals = () => {
        const disc = Math.min(100, num(s.discount)) / 100;
        const fee = num(s.fee);
        let ex = 0;
        let vat = 0;

        s.rows.forEach((r) => { const net = r.qty * r.price * (1 - disc); ex += net; vat += net * r.vat / 100; });

        if (fee) { ex += fee; vat += fee * 0.25; }

        return { ex, vat, tot: ex + vat, disc, fee };
    };

    const finish = (action) => {
        const a = action || s.action;
        const c = cust();
        const no = 2041 + s.sent.length;

        s.action = a;
        s.sheet = null;
        s.screen = 'done';
        s.sent.unshift({ no, name: c?.name ?? '', date: 'Idag', sum: fmt(totals().tot), status: a === 'send' ? 'Skickad' : a === 'book' ? 'Bokförd' : 'Utkast' });
    };

    const calc = () => {
        const c = cust();
        const terms = s.terms ?? c?.terms ?? 30;

        return {
            c,
            terms,
            due: addDays(s.invDate, terms),
            dist: s.dist ?? (c?.einv ? 'E-faktura' : 'E-post'),
            theirRef: s.theirRef ?? c?.ref ?? '',
            ...totals(),
        };
    };

    const actionMap = (dist) => ({
        send: ['Skapa, bokför & skicka', `Skickas som ${dist.toLowerCase()} direkt`],
        book: ['Skapa & bokför', 'Bokförs nu, du skickar senare'],
        draft: ['Spara som utkast', 'Varken bokförs eller skickas'],
    });

    /* ------------------------------------------------------------ vyer */

    const home = () => `
        <div class="dm-m-screen has-bar">
            <div class="dm-m-scroll"><div class="dm-m-home">
                <div class="dm-m-company">
                    <span class="dm-m-co-avatar">NH</span>
                    <span class="dm-m-co-name">Nightshift Holding AB</span>
                    <span class="dm-m-bell">${svg(ICONS.bell, 18, 2, '#111')}</span>
                </div>
                <div class="dm-m-stack">
                    <span class="dm-m-label">KUNDFAKTUROR</span>
                    <div class="dm-m-card">
                        ${[...s.sent, ...HISTORY].slice(0, 6).map((h, i) => `
                            <div class="dm-m-inv${i === 0 && s.sent.length ? ' is-new' : ''}">
                                <span class="dm-m-inv-text"><span class="dm-m-inv-name">${esc(h.name)}</span><span class="dm-m-inv-sub">Faktura ${h.no} · ${esc(h.date)}</span></span>
                                <span class="dm-m-inv-right"><span class="dm-m-inv-sum">${esc(h.sum)} kr</span><span class="dm-m-pill ${PILLS[h.status]}">${h.status}</span></span>
                            </div>`).join('')}
                    </div>
                </div>
            </div></div>
            <div class="dm-m-tabbar">
                <span class="is-on">${svg(ICONS.home, 22)}Hem</span>
                <span>${svg(ICONS.card, 22)}Betalningar</span>
                <span class="dm-m-fab-cell"><button type="button" class="dm-m-fab" data-act="openPlus" aria-label="Skapa">${svg(ICON.plus, 24, 2.4)}</button></span>
                <span>${svg(ICON.bank, 22)}Bank</span>
                <span>${svg(ICONS.menu, 22)}Meny</span>
            </div>
        </div>`;

    const step1 = () => {
        const q = s.q.trim().toLowerCase();
        const list = allCusts().filter((x) => !q || [x.name, x.org, x.city].some((v) => v.toLowerCase().includes(q)));

        return `
            <div class="dm-m-head"><h3>Vem ska faktureras?</h3><span>Steg 1 av 4</span></div>
            <label class="dm-m-search">${svg(ICON.search, 16, 2.2, '#888')}<input data-key="q" data-input="q" value="${esc(s.q)}" placeholder="Sök kund eller org.nr" autocomplete="off"></label>
            <div class="dm-m-stack">
                <span class="dm-m-label">${q ? 'SÖKRESULTAT' : 'SENASTE'}</span>
                <div class="dm-m-card">
                    ${list.map((x) => {
                        const on = x.id === s.custId;

                        return `<button type="button" class="dm-m-cust${on ? ' is-on' : ''}" data-act="pickCust" data-arg="${x.id}">
                            <span class="dm-m-avatar${on ? ' is-on' : ''}">${esc(initials(x.name))}</span>
                            <span class="dm-m-cust-text"><span class="dm-m-cust-name">${esc(x.name)}</span><span class="dm-m-cust-sub">${esc(x.city)} · ${x.terms} dagar</span></span>
                            <span class="dm-m-check${on ? ' is-on' : ''}">${svg(ICON.check, 13, 3)}</span>
                        </button>`;
                    }).join('')}
                    ${list.length === 0 ? `<div class="dm-m-empty">Ingen kund matchar ”${esc(s.q)}”.</div>` : ''}
                </div>
            </div>
            <button type="button" class="dm-m-outline" data-act="openNc"><span>+</span>Ny kund</button>`;
    };

    const step2 = (k) => `
        <div class="dm-m-head"><h3>Vad ska du fakturera?</h3><span>Steg 2 av 4</span></div>
        <div class="dm-m-stack">
            ${s.rows.map((r, i) => `
                <button type="button" class="dm-m-rowcard" data-act="editRow" data-arg="${i}">
                    <span class="dm-m-rowcard-name">${esc(r.name)}</span><span class="dm-m-rowcard-sum">${fmt(r.qty * r.price)}</span>
                    <span class="dm-m-rowcard-sub">${fmtQ(r.qty)} ${esc(r.unit)} × ${fmt(r.price)} kr</span><span class="dm-m-tag">${r.vat} % moms</span>
                </button>`).join('')}
        </div>
        <button type="button" class="dm-m-dashed" data-act="addRow"><span>+</span>Lägg till rad</button>
        <div class="dm-m-grow"></div>
        <div class="dm-m-totals">
            <div><span>Exkl. moms</span><span>${fmt(k.ex)} kr</span></div>
            <div><span>Moms</span><span>${fmt(k.vat)} kr</span></div>
            <div class="is-total"><span>Att betala</span><span>${fmt(k.tot)} kr</span></div>
        </div>`;

    const listRow = (label, value, empty, act, arg = '') => `
        <button type="button" class="dm-m-line" data-act="${act}"${arg ? ` data-arg="${arg}"` : ''}><span class="dm-m-line-k">${label}</span><span class="dm-m-line-v${value ? '' : ' is-muted'}">${esc(value || empty)}</span>${svg(ICON.chevronRight, 14, 2.4, value ? '#111' : '#999')}</button>`;

    const step3 = (k) => {
        const feeValue = [k.disc ? `Rabatt ${fmtQ(k.disc * 100)} %` : '', k.fee ? `Avgift ${fmtQ(k.fee)} kr` : ''].filter(Boolean).join(' · ');

        return `
            <div class="dm-m-head"><h3>Datum och villkor</h3><span>Steg 3 av 4 · förifyllt från kunden</span></div>
            <div class="dm-m-card">
                <label class="dm-m-line"><span class="dm-m-line-k">Fakturadatum</span><input type="date" value="${esc(s.invDate)}" data-change="invDate"></label>
                <button type="button" class="dm-m-line" data-act="openSheet" data-arg="terms"><span class="dm-m-line-k">Betalvillkor</span><span class="dm-m-line-v">${k.terms} dagar</span>${svg(ICON.chevronRight, 14, 2.4, '#111')}</button>
                <div class="dm-m-line"><span class="dm-m-line-k">Förfallodatum</span><span class="dm-m-line-v is-muted">${esc(k.due)}</span></div>
                <label class="dm-m-line"><span class="dm-m-line-k">Leveransdatum</span><input type="date" value="${esc(s.delDate)}" data-change="delDate"></label>
            </div>
            <div class="dm-m-card">
                ${listRow('Er referens', k.theirRef, 'Lägg till', 'openText', 'theirRef')}
                ${listRow('Vår referens', s.ourRef, 'Lägg till', 'openText', 'ourRef')}
                ${listRow('Fakturatext', s.text.trim(), 'Lägg till', 'openText', 'text')}
            </div>
            <div class="dm-m-card">
                ${listRow('Distribution', k.dist, '', 'openSheet', 'dist')}
                ${listRow('Avgifter och rabatt', feeValue, 'Inga', 'openFees')}
            </div>`;
    };

    const step4 = (k) => {
        const rows = s.rows.map((r) => ({ name: `${r.name} · ${fmtQ(r.qty)} ${r.unit} × ${fmt(r.price)}`, sum: fmt(r.qty * r.price) }));

        if (k.disc) rows.push({ name: `Rabatt ${fmtQ(k.disc * 100)} %`, sum: '−' + fmt(s.rows.reduce((a, r) => a + r.qty * r.price, 0) * k.disc) });
        if (k.fee) rows.push({ name: 'Fakturaavgift', sum: fmt(k.fee) });

        return `
            <div class="dm-m-invoice">
                <div class="dm-m-invoice-top"><span>Nightshift Holding AB</span><span>Faktura</span></div>
                <div class="dm-m-invoice-grid">
                    <span class="dm-m-invoice-dates"><span>Fakturadatum</span><span>${esc(s.invDate)}</span><span>Förfallodatum</span><span>${esc(k.due)}</span><span>Leveransdatum</span><span>${esc(s.delDate)}</span><span>Valuta</span><span>SEK</span></span>
                    <span class="dm-m-invoice-cust"><strong>${esc(k.c?.name ?? '')}</strong><span>${esc(k.c?.addr ?? '')}</span><span>${esc(k.c?.zip ?? '')}</span>${k.theirRef ? `<span>Er referens: ${esc(k.theirRef)}</span>` : ''}</span>
                </div>
                ${s.text.trim() ? `<div class="dm-m-invoice-text">${esc(s.text)}</div>` : ''}
                <div class="dm-m-invoice-rows"><div class="is-head"><span>Benämning</span><span>Summa</span></div>${rows.map((r) => `<div><span>${esc(r.name)}</span><span>${r.sum}</span></div>`).join('')}</div>
                <div class="dm-m-invoice-sums"><div><span>Totalt exkl. moms</span><span>${fmt(k.ex)}</span></div><div><span>Moms</span><span>${fmt(k.vat)}</span></div></div>
                <div class="dm-m-invoice-total"><span>Att betala</span><strong>${fmt(k.tot)} SEK</strong></div>
                <div class="dm-m-invoice-foot"><span>Bankgiro 5966-7816</span><span>Godkänd för F-skatt</span></div>
            </div>
            <button type="button" class="dm-m-attach" data-act="toggleAttach">
                <span class="dm-m-attach-icon">${s.attached ? '✓' : '+'}</span>
                <span class="dm-m-attach-text"><strong>Bilagor</strong><span>${s.attached ? 'tidrapport-oktober.pdf · 84 kB' : 'Inga tillagda'}</span></span>
                ${svg(ICON.chevronRight, 14, 2.4, '#aaa')}
            </button>`;
    };

    const wizard = (k) => {
        const map = actionMap(k.dist);

        return `
            <div class="dm-m-screen is-wiz">
                <div class="dm-m-nav">
                    <div class="dm-m-nav-row">
                        <button type="button" class="dm-m-nav-back" data-act="back" aria-label="Tillbaka">${svg(ICON.chevronLeft, 22, 2.4, '#111')}</button>
                        <span class="dm-m-nav-title">${s.step === 1 ? 'Ny faktura' : s.step === 4 ? 'Granska' : esc(k.c?.name ?? '')}</span>
                        <button type="button" class="dm-m-nav-cancel" data-act="cancel">Avbryt</button>
                    </div>
                    <div class="dm-m-segs">${[1, 2, 3, 4].map((n) => {
                        const reach = n <= s.maxStep && n !== s.step;

                        return `<button type="button" class="dm-m-seg ${n <= s.step ? 'is-active' : n <= s.maxStep ? 'is-seen' : ''}" ${reach ? `data-act="step" data-arg="${n}"` : 'tabindex="-1"'} aria-label="Steg ${n}"><span></span></button>`;
                    }).join('')}</div>
                </div>
                <div class="dm-m-scroll" data-scroll="wiz"><div class="dm-m-body">${s.step === 1 ? step1() : s.step === 2 ? step2(k) : s.step === 3 ? step3(k) : step4(k)}</div></div>
                <div class="dm-m-foot">
                    ${s.step !== 4
                        ? `<button type="button" class="dm-m-cta${canNext() ? '' : ' is-dim'}" data-act="next">${s.step === 3 ? 'Granska' : 'Fortsätt'}</button>`
                        : `<div class="dm-m-split">
                            <button type="button" class="dm-m-split-main" data-act="finish">${map[s.action][0]}</button>
                            <button type="button" class="dm-m-split-toggle" data-act="openSheet" data-arg="action" aria-label="Fler val">${svg(ICON.chevronUp, 16, 2.4)}</button>
                        </div>
                        ${s.action !== 'draft' ? '<button type="button" class="dm-m-text-btn" data-act="saveDraft">Spara som utkast</button>' : ''}`}
                </div>
            </div>`;
    };

    const done = (k) => {
        const name = k.c?.name ?? '';
        const no = s.sent[0]?.no ?? 2041;
        const title = s.action === 'send' ? `Faktura ${no} skickad` : s.action === 'book' ? `Faktura ${no} skapad` : 'Utkast sparat';
        const body = s.action === 'draft'
            ? `${fmt(k.tot)} kr till ${name}. Du hittar utkastet under Kundfakturor.`
            : `${fmt(k.tot)} kr till ${name}. Bokförd på verifikation K-112. Förfaller ${longDate(k.due)}.`;
        const ocr = String(no * 10 + 7);
        const rows = s.action === 'send'
            ? [['Skickad till', k.dist === 'E-post' ? (k.c?.email ?? '') : k.dist === 'E-faktura' ? `${name} · Peppol` : 'Brev via Oqto'], ['OCR', ocr]]
            : s.action === 'book' ? [['Status', 'Bokförd, ej skickad'], ['OCR', ocr]] : [['Status', 'Utkast']];

        return `
            <div class="dm-m-screen is-done">
                <div class="dm-m-done">
                    <span class="dm-m-done-icon">${svg(ICON.check, 36, 2.2)}</span>
                    <h3>${title}</h3>
                    <p>${esc(body)}</p>
                    <div class="dm-m-done-rows">${rows.map(([a, b]) => `<div><span>${a}</span><strong>${esc(b)}</strong></div>`).join('')}</div>
                </div>
                <div class="dm-m-done-actions">
                    <button type="button" class="dm-m-cta" data-act="toHome">Klar</button>
                    <button type="button" class="dm-m-text-btn" data-act="startWizard">Skapa en till faktura</button>
                </div>
            </div>`;
    };

    /* ------------------------------------------------------------ ark */

    const sheet = (k) => {
        const sh = s.sheet;

        if (!sh) return '';

        const e = s.edit;
        const art = e?.art ? ARTS.find((a) => a.no === e.art) : null;
        const titles = { nc: 'Ny kund', row: e?.idx >= 0 ? 'Ändra rad' : 'Ny rad', theirRef: 'Er referens', ourRef: 'Vår referens', text: 'Fakturatext', fees: 'Avgifter och rabatt' };
        const canSave = sh === 'nc' ? !!s.nc?.name?.trim() : sh === 'row' ? editValid() : true;
        const map = actionMap(k.dist);
        const distSub = { 'E-faktura': k.c?.einv ? 'Via Peppol · kunden tar emot e-faktura' : 'Ingen registrerad e-fakturaadress', 'E-post': `PDF till ${k.c?.email ?? '—'}`, Brev: 'Skrivs ut och skickas av Oqto · 12 kr' };

        const pickRow = (label, sub, on, arg, act) => `
            <button type="button" class="dm-m-pick" data-act="${act}" data-arg="${esc(arg)}"><span class="dm-m-pick-text"><span>${esc(label)}</span><small>${esc(sub)}</small></span><span class="dm-m-check${on ? ' is-on' : ''}">${svg(ICON.check, 13, 3)}</span></button>`;

        let content = '';

        if (sh === 'plus') {
            const item = (label, sub, icon, act, primary) => `
                <button type="button" class="dm-m-plus-item${act ? '' : ' is-off'}" ${act ? `data-act="${act}"` : 'tabindex="-1"'}>
                    <span class="dm-m-plus-icon${primary ? ' is-primary' : ''}">${svg(icon, 18)}</span>
                    <span class="dm-m-plus-text"><span>${label}</span><small>${sub}</small></span>
                    ${svg(ICON.chevronRight, 14, 2.4, '#aaa')}
                </button>`;

            content = `
                <div class="dm-m-plus-group"><span class="dm-m-label">SKAPA</span><div class="dm-m-card">${item('Kundfaktura', 'Fakturera en kund', ICONS.doc, 'startWizard', true)}${item('Offert', 'Blir faktura vid accept', ICONS.quote)}${item('Ny kund', 'Lägg upp i kundregistret', ICONS.user)}</div></div>
                <div class="dm-m-plus-group"><span class="dm-m-label">LADDA UPP</span><div class="dm-m-card">${item('Kvitto', 'Fotografera eller välj fil', ICONS.receipt)}${item('Leverantörsfaktura', 'PDF eller bild', ICONS.inbox)}</div></div>`;
        }

        if (sh === 'nc') {
            content = `
                <label class="dm-m-field"><span class="dm-m-label">FÖRETAGSNAMN</span><input data-key="ncName" data-input="ncName" value="${esc(s.nc?.name ?? '')}" placeholder="t.ex. Solberg Design AB"></label>
                <label class="dm-m-field"><span class="dm-m-label">ORG.NR</span><input data-key="ncOrg" data-input="ncOrg" value="${esc(s.nc?.org ?? '')}" inputmode="numeric" placeholder="556677-8899"></label>
                <label class="dm-m-field"><span class="dm-m-label">E-POST FÖR FAKTUROR</span><input data-key="ncEmail" data-input="ncEmail" value="${esc(s.nc?.email ?? '')}" inputmode="email" placeholder="faktura@foretag.se"></label>
                <span class="dm-m-note">Adress och betalvillkor hämtas från org.nr när kunden sparas.</span>`;
        }

        if (sh === 'row') {
            content = `
                <button type="button" class="dm-m-art" data-act="toggleArt"><span class="dm-m-art-text"><span>${art ? `${esc(art.no)} · ${esc(art.name)}` : 'Ingen artikel'}</span><small>${art ? 'Fyller i pris, enhet och moms' : 'Valfritt · välj för att fylla i automatiskt'}</small></span><span class="dm-m-art-action">${s.artOpen ? 'Stäng' : art ? 'Byt artikel ›' : 'Välj ›'}</span></button>
                ${s.artOpen ? `<div class="dm-m-card is-attached">${[{ no: '—', name: 'Ingen artikel', unit: null }, ...ARTS].map((a) => {
                    const on = a.unit ? e?.art === a.no : !e?.art;

                    return `<button type="button" class="dm-m-artrow" data-act="pickArt" data-arg="${esc(a.no)}"><span class="dm-m-artrow-no">${esc(a.no)}</span><span class="dm-m-artrow-name">${esc(a.name)}</span><span class="dm-m-artrow-meta">${a.unit ? (a.price ? `${fmtQ(a.price)} kr/${a.unit}` : a.unit) : ''}</span><span class="dm-m-check is-small${on ? ' is-on' : ''}">${svg(ICON.check, 11, 3.4)}</span></button>`;
                }).join('')}</div>` : ''}
                <label class="dm-m-field"><span class="dm-m-label">BENÄMNING</span><input data-key="eName" data-input="eName" value="${esc(e?.name ?? '')}" placeholder="Vad har du sålt?"></label>
                <div class="dm-m-grid-2">
                    <label class="dm-m-field"><span class="dm-m-label">ANTAL</span><input data-key="eQty" data-input="eQty" value="${esc(e?.qty ?? '')}" inputmode="decimal"></label>
                    <label class="dm-m-field"><span class="dm-m-label">ENHET</span><select data-change="eUnit">${['st', 'tim', 'dagar', 'mån', 'km'].map((u) => `<option value="${u}"${e?.unit === u ? ' selected' : ''}>${u}</option>`).join('')}</select></label>
                    <label class="dm-m-field"><span class="dm-m-label">À PRIS</span><input data-key="ePrice" data-input="ePrice" value="${esc(e?.price ?? '')}" inputmode="decimal" placeholder="0"></label>
                    <label class="dm-m-field"><span class="dm-m-label">MOMS</span><select data-change="eVat">${[25, 12, 6, 0].map((v) => `<option value="${v}"${e?.vat === v ? ' selected' : ''}>${v} %</option>`).join('')}</select></label>
                </div>
                <div class="dm-m-rowsum"><span>Radsumma</span><strong>${fmt(num(e?.qty) * num(e?.price))} kr</strong></div>
                ${e?.idx >= 0 ? '<button type="button" class="dm-m-danger" data-act="delRow">Ta bort rad</button>' : ''}`;
        }

        if (sh === 'terms') {
            content = `<span class="dm-m-pick-title">Betalvillkor</span><div class="dm-m-card">${[10, 15, 30, 60].map((t) => pickRow(`${t} dagar`, `Förfaller ${longDate(addDays(s.invDate, t))}${t === k.c?.terms ? ' · kundens standard' : ''}`, t === k.terms, t, 'setTerms')).join('')}</div>`;
        }

        if (sh === 'dist') {
            content = `<span class="dm-m-pick-title">Distribution</span><div class="dm-m-card">${['E-faktura', 'E-post', 'Brev'].map((d) => pickRow(d, distSub[d], d === k.dist, d, 'setDist')).join('')}</div>`;
        }

        if (sh === 'action') {
            content = `<span class="dm-m-pick-title">Hur vill du skapa fakturan?</span><div class="dm-m-card">${Object.keys(map).map((key) => pickRow(map[key][0], map[key][1], key === s.action, key, 'setAction')).join('')}</div>`;
        }

        if (sh === 'theirRef' || sh === 'ourRef') {
            content = `<input class="dm-m-bigfield" data-key="tmp" data-input="tmp" value="${esc(s.tmp)}" placeholder="Namn">`;
        }

        if (sh === 'text') {
            content = `<textarea class="dm-m-bigfield" data-key="tmp" data-input="tmp" rows="5" placeholder="Visas ovanför raderna på fakturan">${esc(s.tmp)}</textarea>`;
        }

        if (sh === 'fees') {
            content = `
                <label class="dm-m-field"><span class="dm-m-label">RABATT %</span><input data-key="tmpDisc" data-input="tmpDisc" value="${esc(s.tmpDisc)}" inputmode="decimal" placeholder="0"></label>
                <label class="dm-m-field"><span class="dm-m-label">FAKTURAAVGIFT, KR</span><input data-key="tmpFee" data-input="tmpFee" value="${esc(s.tmpFee)}" inputmode="decimal" placeholder="0"></label>
                <span class="dm-m-note">Rabatten gäller alla rader. Avgiften läggs till som egen rad med 25 % moms.</span>`;
        }

        return `
            <div class="dm-m-sheet-wrap">
                <div class="dm-m-scrim" data-act="closeSheet"></div>
                <div class="dm-m-sheet">
                    <div class="dm-m-grab"><span></span></div>
                    ${titles[sh] ? `<div class="dm-m-sheet-head"><button type="button" class="dm-m-sheet-cancel" data-act="closeSheet">Avbryt</button><span>${titles[sh]}</span><button type="button" class="dm-m-sheet-save${canSave ? '' : ' is-dim'}" data-act="sheetSave">Spara</button></div>` : ''}
                    <div class="dm-m-sheet-scroll" data-scroll="sheet"><div class="dm-m-sheet-body">${content}</div></div>
                </div>
            </div>`;
    };

    const captions = {
        home: '+ öppnar ett ark grupperat i Skapa och Ladda upp. Kundfaktura startar flödet.',
        1: 'Kunden styr valuta, betalvillkor och adress, därför först. Ny kund öppnas som ark ovanpå.',
        2: 'Webbens tabell blir kort. Summan ligger alltid synlig längst ned.',
        row: 'Artikeln är valfri. Väljer man en fylls pris, enhet och moms i. Annars räcker benämning och belopp.',
        3: 'Allt förifyllt från kunden. Steget går att trycka förbi. Distribution föreslås utifrån vad kunden kan ta emot.',
        4: 'Åtgärden ligger i knappen. Pilen byter till Skapa & bokför eller utkast.',
        done: 'Kvitto med fakturanummer och verifikation, det man annars går till webben för att kontrollera.',
    };

    const view = () => {
        const k = calc();
        const caption = s.screen === 'home' ? captions.home : s.screen === 'done' ? captions.done : s.sheet === 'row' ? captions.row : captions[s.step];

        return `
            <div class="dm-m-stage">
                <div class="dm-phone">
                    <div class="dm-phone-status"><span>${clock}</span>
                        <span class="dm-phone-icons">
                            <svg width="17" height="11" viewBox="0 0 17 11" fill="#111"><rect x="0" y="7" width="3" height="4" rx="1"/><rect x="4.5" y="5" width="3" height="6" rx="1"/><rect x="9" y="2.5" width="3" height="8.5" rx="1"/><rect x="13.5" y="0" width="3" height="11" rx="1"/></svg>
                            <svg width="25" height="12" viewBox="0 0 25 12" fill="none"><rect x=".5" y=".5" width="21" height="11" rx="3.5" stroke="#111" opacity=".4"/><rect x="2" y="2" width="16" height="8" rx="2" fill="#111"/><rect x="23" y="4" width="1.6" height="4" rx=".8" fill="#111" opacity=".4"/></svg>
                        </span>
                    </div>
                    ${s.screen === 'home' ? home() : s.screen === 'wiz' ? wizard(k) : done(k)}
                    ${sheet(k)}
                    <div class="dm-phone-home"></div>
                </div>
            </div>
            <p class="dm-m-caption">${caption}</p>`;
    };

    const actions = {
        openPlus: () => { s.sheet = 'plus'; },
        closeSheet: () => { s.sheet = null; s.artOpen = false; },
        startWizard,
        cancel,
        back,
        next,
        step: (n) => { n = Number(n); if (n <= s.maxStep && n !== s.step) go(n); },
        finish: () => finish(),
        saveDraft: () => finish('draft'),
        toHome: cancel,
        toggleAttach: () => { s.attached = !s.attached; },
        pickCust: (id) => { s.custId = id; s.terms = null; s.theirRef = null; s.dist = null; },
        q: (v) => { s.q = v; },
        openNc: () => { s.sheet = 'nc'; s.nc = { name: s.q, org: '', email: '' }; },
        ncName: (v) => { s.nc.name = v; },
        ncOrg: (v) => { s.nc.org = v; },
        ncEmail: (v) => { s.nc.email = v; },
        editRow: (i) => {
            const r = s.rows[Number(i)];

            s.sheet = 'row';
            s.artOpen = false;
            s.edit = { idx: Number(i), art: r.art, name: r.name, qty: String(r.qty).replace('.', ','), unit: r.unit, price: String(r.price).replace('.', ','), vat: r.vat };
        },
        addRow: () => { s.sheet = 'row'; s.edit = newEdit(); s.artOpen = false; },
        toggleArt: () => { s.artOpen = !s.artOpen; },
        pickArt: (no) => {
            const a = ARTS.find((x) => x.no === no);

            if (a) Object.assign(s.edit, { art: a.no, name: s.edit.name.trim() ? s.edit.name : a.name, unit: a.unit, price: a.price ? String(a.price) : '', vat: a.vat });
            else s.edit.art = null;

            s.artOpen = false;
        },
        eName: (v) => { s.edit.name = v; },
        eQty: (v) => { s.edit.qty = v; },
        eUnit: (v) => { s.edit.unit = v; },
        ePrice: (v) => { s.edit.price = v; },
        eVat: (v) => { s.edit.vat = Number(v); },
        delRow: () => { s.rows.splice(s.edit.idx, 1); s.edit = null; s.sheet = null; },
        invDate: (v) => { if (v) s.invDate = v; },
        delDate: (v) => { if (v) s.delDate = v; },
        openSheet: (name) => { s.sheet = name; },
        setTerms: (t) => { s.terms = Number(t); s.sheet = null; },
        setDist: (d) => { s.dist = d; s.sheet = null; },
        setAction: (a) => { s.action = a; s.sheet = null; },
        openText: (key) => {
            const k = calc();
            const value = key === 'theirRef' ? k.theirRef : key === 'ourRef' ? s.ourRef : s.text;

            s.sheet = key;
            s.tmp = value ?? '';
        },
        openFees: () => { s.sheet = 'fees'; s.tmpDisc = s.discount; s.tmpFee = s.fee; },
        tmp: (v) => { s.tmp = v; },
        tmpDisc: (v) => { s.tmpDisc = v; },
        tmpFee: (v) => { s.tmpFee = v; },
        sheetSave: () => {
            const sh = s.sheet;

            if (sh === 'nc') {
                if (!s.nc?.name?.trim()) return;

                const id = 'n' + Date.now();

                s.extra.push({ id, name: s.nc.name.trim(), org: s.nc.org || '—', city: 'Ny kund', terms: 30, addr: '', zip: '', ref: '', email: s.nc.email || '—', einv: false });
                s.nc = null;
                s.q = '';
                s.sheet = null;
                actions.pickCust(id);
            } else if (sh === 'row') {
                commitRow();
            } else if (sh === 'fees') {
                s.discount = s.tmpDisc;
                s.fee = s.tmpFee;
                s.sheet = null;
            } else {
                s[sh] = s.tmp;
                s.sheet = null;
            }
        },
    };

    // Telefonramen är 390 px bred; på smalare ytor skalas den ner.
    // I helskärm räknas också höjden in, så att hela telefonen ryms på skärmen.
    const rescale = () => {
        let scale = Math.min(1, (root.clientWidth - 24) / 412);

        if (root.closest('.is-fullscreen')) scale = Math.min(scale, (window.innerHeight - 300) / 868);

        root.style.setProperty('--dm-scale', String(Math.max(0.45, scale)));
    };

    window.addEventListener('resize', rescale);
    window.addEventListener('dm:rescale', () => setTimeout(rescale, 60));

    createDemo(root, s, view, actions);

    rescale();

    if ('ResizeObserver' in window) new ResizeObserver(rescale).observe(root);
}
