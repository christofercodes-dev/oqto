import { createDemo, esc, num, fmt, fmtQ, addDays, todayISO, svg, ICON } from './core.js';
import { topbar, toastHtml, scaled, animateOnMobile } from './tax-shared.js';

/*
 * Enklare fakturering: formuläret för en kundfaktura med en förhandsvisning
 * som uppdateras medan man fyller i. Välj kund, välj artikel, ändra antal och
 * pris och se fakturan växa fram. Allt är påhittat och inget skickas.
 * Nästa steg att göra pulserar, så att man ser var man ska börja.
 */

const ICONS = {
    search: ICON.search,
    calendar: 'M8 3v4M16 3v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z',
    trash: ICON.trash,
    plus: ICON.plus,
    arrow: 'M19 12H5M12 19l-7-7 7-7',
    sort: 'M8 9l4-4 4 4M8 15l4 4 4-4',
    next: 'm9 18 6-6-6-6',
    house: 'M3 11l9-8 9 8M5 10v10h14V10',
};

const CUSTOMERS = [
    { id: 1, name: 'Nordbygg Entreprenad AB', org: '5560731234', terms: 30, addr: 'Byggvägen 4', zip: '602 21 Norrköping', ourRef: 'Anton', yourRef: 'Karin Holm' },
    { id: 2, name: 'Lindqvist Konsult AB', org: '5569085678', terms: 30, addr: 'Kungsgatan 18', zip: '411 19 Göteborg', ourRef: 'Anton', yourRef: 'Maria Lindqvist' },
    { id: 3, name: 'Café Sundet HB', org: '9695012345', terms: 20, addr: 'Hamngatan 2', zip: '262 31 Ängelholm', ourRef: 'Anton', yourRef: 'Olof Sund' },
    { id: 4, name: 'Helsinki Design Oy', org: '', terms: 30, addr: 'Esplanadi 12', zip: '00130 Helsingfors', ourRef: 'Anton', yourRef: 'Aino Virtanen' },
    { id: 5, name: 'Erik Svensson', org: '', terms: 10, addr: 'Björkvägen 3', zip: '186 30 Vallentuna', ourRef: 'Anton', yourRef: 'Erik Svensson' },
];

const ARTICLES = [
    { no: 'KONS-T', name: 'Konsulttimme', unit: 'st', price: 1250 },
    { no: 'SUPP-M', name: 'Supportavtal, månad', unit: 'mån', price: 2900 },
    { no: 'PROJ-D', name: 'Projektledning, dag', unit: 'dag', price: 9500 },
    { no: 'LIC-A', name: 'Licens, år', unit: 'år', price: 4800 },
];

const UNITS = ['st', 'h', 'dag', 'mån', 'år'];
const VAT = 0.25;
const COMPANY = 'Exempelbolaget AB';

const emptyRow = () => ({ art: '', name: '', qty: '', unit: '', price: '', disc: '' });

const rowFilled = (r) => !!(r.art || r.name || r.qty || r.price);

const lineSum = (r) => num(r.qty) * num(r.price) * (1 - num(r.disc) / 100);

export default function mount(root) {

    const today = todayISO();

    const fresh = (invoiceNo) => ({
        invoiceNo,
        customer: null,
        type: 'invoice',
        date: today,
        due: addDays(today, 30),
        dueTouched: false,
        delivery: '',
        ourRef: '',
        yourRef: '',
        order: '',
        rows: [emptyRow()],
        popup: null,     // { kind: 'customer' | 'article' | 'create', row?: number }
        search: '',
        press: null,
    });

    const s = { ...fresh(35), toast: null, animated: false };

    let demo;
    let toastTimer;

    const showToast = (title, text) => {
        s.toast = { title, text };
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { s.toast = null; demo.render(); }, 3400);
    };

    const customer = () => CUSTOMERS.find((c) => c.id === s.customer) || null;

    const pressed = (key) => (s.press === key ? ' is-pressed' : '');

    // Håll alltid en tom rad sist, som i appen.
    const ensureTrailingRow = () => {
        if (rowFilled(s.rows[s.rows.length - 1])) s.rows.push(emptyRow());
    };

    const totals = () => {
        const sign = s.type === 'credit' ? -1 : 1;
        const net = sign * s.rows.reduce((sum, r) => sum + lineSum(r), 0);
        const vat = net * VAT;
        const gross = net + vat;
        const rounded = Math.round(gross);

        return { net, vat, round: rounded - gross, pay: rounded };
    };

    const filledRows = () => s.rows.filter(rowFilled);

    // Vad som är nästa att göra, för pulsen.
    const hint = () => {
        if (!s.customer) return 'customer';
        if (!filledRows().length) return 'article';

        return 'create';
    };

    const pulse = (key) => (hint() === key ? ' dm-pulse is-pulsing' : '');

    /* ---------- Formuläret ---------- */

    const dateInput = (field, label) => `
        <label class="dm-inv-field">
            <span>${label}</span>
            <span class="dm-inv-input has-icon"><input type="date" value="${esc(s[field])}" data-key="${field}" data-change="${field}" aria-label="${label}">${svg(ICONS.calendar, 18, 1.8)}</span>
        </label>`;

    const textInput = (field, label) => `
        <label class="dm-inv-field">
            <span>${label}</span>
            <span class="dm-inv-input"><input type="text" value="${esc(s[field])}" data-key="${field}" data-input="${field}" aria-label="${label}"></span>
        </label>`;

    const staticSelect = (label) => `
        <div class="dm-inv-field">
            <span>${label}</span>
            <span class="dm-inv-input is-static">${svg(ICON.chevronDown, 16, 2.2)}</span>
        </div>`;

    const customerMenu = () => {
        const q = s.search.trim().toLowerCase();
        const list = CUSTOMERS.filter((c) => !q || c.name.toLowerCase().includes(q) || c.org.includes(q));

        return `
            <div class="dm-inv-menu" role="listbox" aria-label="Välj kund">
                <label class="dm-inv-menu-search">${svg(ICONS.search, 16, 2)}<input type="text" value="${esc(s.search)}" placeholder="Sök…" data-key="search" data-input="search"></label>
                <button type="button" role="option" class="dm-inv-opt is-muted" data-act="pickCustomer" data-arg="0"><span>- Inget val -</span>${s.customer ? '' : svg(ICON.check, 16, 2.4)}</button>
                ${list.map((c) => `<button type="button" role="option" class="dm-inv-opt${pressed(`cust-${c.id}`)}" data-act="pickCustomer" data-arg="${c.id}"><span>${esc(c.name)}</span><em>#${c.id}</em>${c.org ? `<small>${esc(c.org)}</small>` : ''}${s.customer === c.id ? svg(ICON.check, 16, 2.4) : ''}</button>`).join('')}
                <button type="button" class="dm-inv-opt is-create" data-act="createNew" data-arg="kund">${svg(ICONS.plus, 16, 2)}Skapa ny</button>
            </div>`;
    };

    const articleMenu = (row) => {
        const q = s.search.trim().toLowerCase();
        const list = ARTICLES.filter((a) => !q || a.name.toLowerCase().includes(q) || a.no.includes(q));

        return `
            <div class="dm-inv-menu is-article" role="listbox" aria-label="Välj artikel">
                <label class="dm-inv-menu-search">${svg(ICONS.search, 16, 2)}<input type="text" value="${esc(s.search)}" placeholder="Sök…" data-key="search" data-input="search"></label>
                <button type="button" role="option" class="dm-inv-opt is-muted" data-act="pickArticle:${row}" data-arg="">- Inget val -${s.rows[row].art ? '' : svg(ICON.check, 16, 2.4)}</button>
                ${list.map((a) => `<button type="button" role="option" class="dm-inv-opt${pressed(`art-${a.no}`)}" data-act="pickArticle:${row}" data-arg="${a.no}"><b>${esc(a.no)}</b><span>${esc(a.name)}</span>${s.rows[row].art === a.no ? svg(ICON.check, 16, 2.4) : ''}</button>`).join('')}
                <button type="button" class="dm-inv-opt is-create" data-act="createNew" data-arg="artikel">${svg(ICONS.plus, 16, 2)}Skapa nytt</button>
            </div>`;
    };

    const rowHtml = (r, i) => {
        const open = s.popup && s.popup.kind === 'article' && s.popup.row === i;

        return `
            <tr>
                <td class="dm-inv-c-art">
                    <div class="dm-inv-anchor${open ? ' is-open' : ''}">
                        <button type="button" class="dm-inv-cell is-select${i === 0 ? pulse('article') : ''}${pressed(`art-open-${i}`)}" data-act="openArticle:${i}" aria-haspopup="listbox" aria-expanded="${open ? 'true' : 'false'}"><span>${esc(r.art)}</span>${svg(ICON.chevronDown, 16, 2.2)}</button>
                        ${open ? articleMenu(i) : ''}
                    </div>
                </td>
                <td><input class="dm-inv-cell" type="text" value="${esc(r.name)}" data-key="name-${i}" data-input="rowName:${i}" aria-label="Benämning rad ${i + 1}"></td>
                <td><input class="dm-inv-cell is-num${pressed(`qty-${i}`)}" type="text" inputmode="decimal" value="${esc(r.qty)}" data-key="qty-${i}" data-input="rowQty:${i}" aria-label="Antal rad ${i + 1}"></td>
                <td>
                    <select class="dm-inv-cell" data-key="unit-${i}" data-change="rowUnit:${i}" aria-label="Enhet rad ${i + 1}">
                        <option value=""></option>
                        ${UNITS.map((u) => `<option value="${u}"${r.unit === u ? ' selected' : ''}>${u}</option>`).join('')}
                    </select>
                </td>
                <td><input class="dm-inv-cell is-num" type="text" inputmode="decimal" value="${esc(r.price)}" data-key="price-${i}" data-input="rowPrice:${i}" aria-label="À-pris rad ${i + 1}"></td>
                <td><input class="dm-inv-cell is-num" type="text" inputmode="decimal" value="${esc(r.disc)}" data-key="disc-${i}" data-input="rowDisc:${i}" aria-label="Rabatt rad ${i + 1}"></td>
                <td class="dm-inv-c-sum">${rowFilled(r) ? fmt(lineSum(r)) : ''}</td>
                <td class="dm-inv-c-sort"><span aria-hidden="true">${svg(ICONS.sort, 18, 1.8)}</span></td>
                <td class="dm-inv-c-trash"><button type="button" class="dm-inv-trash" data-act="removeRow:${i}" aria-label="Ta bort rad ${i + 1}">${svg(ICONS.trash, 20, 1.6)}</button></td>
            </tr>`;
    };

    const createMenu = () => `
        <div class="dm-inv-create-menu" role="menu">
            <button type="button" role="menuitem" data-act="create" data-arg="utkast">Skapa som utkast</button>
            <button type="button" role="menuitem" data-act="create" data-arg="bokfor">Skapa och bokför</button>
            <button type="button" role="menuitem" data-act="create" data-arg="skicka">Skapa, bokför och skicka</button>
        </div>`;

    const form = () => {
        const t = totals();
        const custOpen = s.popup && s.popup.kind === 'customer';
        const createOpen = s.popup && s.popup.kind === 'create';
        const c = customer();

        return `
            <div class="dm-inv-form" data-scroll="invform">
                <h3 class="dm-inv-title">Skapa kundfaktura ${s.invoiceNo}<span>*</span></h3>

                <div class="dm-inv-customer">
                    <div class="dm-inv-field is-wide">
                        <span>Kund</span>
                        <div class="dm-inv-anchor${custOpen ? ' is-open' : ''}">
                            <button type="button" class="dm-inv-input is-select${pulse('customer')}${pressed('cust-open')}" data-act="openCustomer" aria-haspopup="listbox" aria-expanded="${custOpen ? 'true' : 'false'}"><span>${c ? `${esc(c.name)}  <em>#${c.id}</em>` : '- Välj kund -'}</span>${svg(custOpen ? ICON.chevronUp : ICON.chevronDown, 16, 2.4)}</button>
                            ${custOpen ? customerMenu() : ''}
                        </div>
                    </div>
                    <span class="dm-inv-ghostbtn">Ändra kunduppgifter</span>
                </div>

                <div class="dm-inv-grid">
                    <div class="dm-inv-field">
                        <span>Fakturatyp</span>
                        <div class="dm-seg dm-inv-seg">
                            <button type="button" class="dm-seg-btn${s.type === 'invoice' ? ' is-on' : ''}" data-act="setType" data-arg="invoice">Faktura</button>
                            <button type="button" class="dm-seg-btn${s.type === 'credit' ? ' is-on' : ''}" data-act="setType" data-arg="credit">Kredit</button>
                        </div>
                    </div>
                    ${dateInput('date', 'Fakturadatum')}
                    ${dateInput('due', 'Förfallodatum')}
                    ${dateInput('delivery', 'Leveransdatum')}
                    ${textInput('ourRef', 'Vår referens')}
                    ${textInput('yourRef', 'Er referens')}
                    ${textInput('order', 'Ert ordernummer')}
                    ${staticSelect('Projekt')}
                    ${staticSelect('Kostnadsställe')}
                    <div class="dm-inv-field"><span>Taggar</span><span class="dm-inv-tag">${svg(ICONS.plus, 13, 2.2)}Lägg till tagg</span></div>
                </div>

                <div class="dm-inv-section">
                    <div><strong>Husarbete</strong><span>Skattereduktion för rot, rut och grön teknik.</span></div>
                    <span class="dm-inv-ghostbtn">${svg(ICONS.plus, 15, 2)}Lägg till husarbete</span>
                </div>

                <div class="dm-inv-tabs"><span class="is-on">Artikelregistrering</span><span>Bokföringsuppgifter</span><span>Periodisering</span></div>

                <div class="dm-inv-tablewrap">
                    <table class="dm-inv-table">
                        <thead><tr><th>Artikelnummer</th><th>Benämning</th><th class="is-num">Antal</th><th>Enhet</th><th class="is-num">á pris</th><th class="is-num">Rabatt %</th><th class="is-num dm-inv-c-sum">Summa</th><th class="dm-inv-c-sort"></th><th class="dm-inv-c-trash"></th></tr></thead>
                        <tbody>${s.rows.map(rowHtml).join('')}</tbody>
                    </table>
                </div>
                <button type="button" class="dm-inv-ghostbtn is-left" data-act="addRow">${svg(ICONS.plus, 15, 2)}Lägg till rad</button>

                <div class="dm-inv-section is-other">
                    <div><strong>Övrigt</strong><span>Rabatter, avgifter och övrig information</span></div>
                    <span class="dm-inv-ghostbtn">Visa övrigt</span>
                </div>

                <div class="dm-inv-totals">
                    <div><span>Exkl moms</span><strong>${fmt(t.net)}</strong></div>
                    <div><span>Moms</span><strong>${fmt(t.vat)}</strong></div>
                    <div><span>Avrundning</span><strong>${fmt(t.round)}</strong></div>
                    <div class="is-pay"><span>Att betala</span><strong>${fmt(t.pay)} SEK</strong></div>
                </div>
            </div>

            <div class="dm-inv-bar">
                <div class="dm-inv-anchor is-up${createOpen ? ' is-open' : ''}">
                    ${createOpen ? createMenu() : ''}
                    <span class="dm-inv-split${pulse('create')}${pressed('create-open')}">
                        <button type="button" class="dm-inv-split-main" data-act="create" data-arg="bokfor">Skapa och bokför</button>
                        <button type="button" class="dm-inv-split-arrow" data-act="openCreate" aria-haspopup="menu" aria-expanded="${createOpen ? 'true' : 'false'}" aria-label="Fler sätt att skapa">${svg(createOpen ? ICON.chevronUp : ICON.chevronDown, 16, 2.4)}</button>
                    </span>
                </div>
            </div>`;
    };

    /* ---------- Förhandsvisningen ---------- */

    const preview = () => {
        const c = customer();
        const t = totals();
        const rows = filledRows();
        const credit = s.type === 'credit';

        const meta = [
            c ? ['Kundnummer:', c.id] : null,
            ['Fakturadatum:', s.date],
            s.due ? ['Förfallodatum:', s.due] : null,
            s.delivery ? ['Leveransdatum:', s.delivery] : null,
            ['Valuta:', 'SEK'],
        ].filter(Boolean);

        const refs = [
            s.ourRef ? ['Vår referens:', s.ourRef] : null,
            s.yourRef ? ['Er referens:', s.yourRef] : null,
            s.order ? ['Ert ordernr:', s.order] : null,
        ].filter(Boolean);

        const metaRow = ([k, v]) => `<div><span>${esc(k)}</span><span>${esc(v)}</span></div>`;

        return `
            <aside class="dm-inv-side" aria-label="Förhandsvisning av fakturan">
                <div class="dm-inv-side-tabs"><span class="is-on">Förhandsvisning</span><span>Bilagor</span></div>
                <div class="dm-inv-stage">
                    <div class="dm-inv-paper">
                        <div class="dm-inv-paper-top"><strong>${COMPANY}</strong><b>${credit ? 'Kreditfaktura' : 'Faktura'}</b><small>Sida 1/1</small></div>
                        <div class="dm-inv-paper-info">
                            <div class="dm-inv-meta">${meta.map(metaRow).join('')}${refs.length ? '<i></i>' : ''}${refs.map(metaRow).join('')}</div>
                            <div class="dm-inv-addr"><strong>Fakturaadress:</strong>${c ? `<span>${esc(c.name)}</span><span>${esc(c.addr)}</span><span>${esc(c.zip)}</span>` : ''}</div>
                        </div>
                        <table class="dm-inv-ptable">
                            <thead><tr><th>Artikelnr</th><th>Benämning</th><th class="is-num">Antal Enh</th><th class="is-num">Pris</th><th class="is-num">Summa</th></tr></thead>
                            <tbody>${rows.map((r) => `
                                <tr>
                                    <td>${esc(r.art)}</td>
                                    <td>${esc(r.name)}</td>
                                    <td class="is-num">${r.qty ? `${fmtQ(num(r.qty))} ${esc(r.unit)}` : ''}</td>
                                    <td class="is-num">${r.price ? fmt(num(r.price)) : ''}</td>
                                    <td class="is-num">${fmt((credit ? -1 : 1) * lineSum(r))}</td>
                                </tr>`).join('')}</tbody>
                        </table>
                        <div class="dm-inv-paper-foot">
                            ${t.net !== 0 ? `<small>Moms: 25 % ${fmt(t.vat)} (${fmt(t.net)})</small>` : ''}
                            <div class="dm-inv-paper-sum">
                                <div><span>Totalt exkl. moms</span><b>${fmt(t.net)}</b></div>
                                <div><span>Moms</span><b>${fmt(t.vat)}</b></div>
                                <div class="is-pay"><span>Att betala</span><b>${fmt(t.pay)} SEK</b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>`;
    };

    const windowView = () => `
        <div class="dm-window is-pay is-inv">
            <div class="dm-topbar">
                <span class="dm-inv-back" aria-hidden="true">${svg(ICONS.arrow, 18, 2)}</span>
                <strong>Kundfakturor</strong>
                <span class="dm-grow"></span>
                <span class="dm-pill-mono">DEMO</span>
            </div>
            <div class="dm-inv">
                <div class="dm-inv-left">
                    ${s.popup ? '<button type="button" class="dm-tax-backdrop" data-act="popClose" tabindex="-1" aria-hidden="true"></button>' : ''}
                    ${form()}
                </div>
                ${preview()}
            </div>
            ${toastHtml(s.toast)}
        </div>`;

    /* ---------- Åtgärder ---------- */

    const open = (popup) => {
        s.popup = s.popup && s.popup.kind === popup.kind && s.popup.row === popup.row ? null : popup;
        s.search = '';
    };

    const setRow = (i, patch) => {
        Object.assign(s.rows[i], patch);
        ensureTrailingRow();
    };

    const applyCustomer = (c) => {
        s.customer = c ? c.id : null;
        s.ourRef = c ? c.ourRef : '';
        s.yourRef = c ? c.yourRef : '';
        if (!s.dueTouched) s.due = addDays(s.date, c ? c.terms : 30);
    };

    const pickArticle = (i, no) => {
        const a = ARTICLES.find((x) => x.no === no);

        if (!a) {
            s.rows[i] = emptyRow();
        } else {
            setRow(i, { art: a.no, name: a.name, unit: a.unit, price: fmt(a.price).replace(/\s/g, ' '), qty: s.rows[i].qty || '1,00' });
        }

        s.popup = null;
        ensureTrailingRow();
    };

    const create = (how) => {
        if (!s.customer || !filledRows().length) {
            showToast('Fakturan är inte klar', s.customer ? 'Lägg till minst en rad.' : 'Välj en kund först.');
            s.popup = null;

            return;
        }

        const text = { utkast: 'sparades som utkast', bokfor: 'skapades och bokfördes', skicka: 'skapades, bokfördes och skickades' }[how] || 'skapades';
        const no = s.invoiceNo;

        Object.assign(s, fresh(no + 1));
        showToast(`Faktura ${no}`, `Fakturan ${text}. I demot skickas ingenting.`);
    };

    demo = createDemo(root, s, () => scaled(s.animated, windowView()), {
        openCustomer: () => open({ kind: 'customer' }),
        openCreate: () => open({ kind: 'create' }),
        openArticle: (arg, e, i) => open({ kind: 'article', row: Number(i) }),
        popClose: () => { s.popup = null; },
        search: (value) => { s.search = value; },
        pickCustomer: (id) => { applyCustomer(CUSTOMERS.find((c) => c.id === Number(id))); s.popup = null; },
        pickArticle: (no, e, i) => pickArticle(Number(i), no),
        createNew: (what) => { s.popup = null; showToast('Skapa ny', `I demot går det inte att skapa ${what === 'kund' ? 'nya kunder' : 'nya artiklar'}.`); },
        setType: (type) => { s.type = type === 'credit' ? 'credit' : 'invoice'; },
        date: (value) => { if (value) { s.date = value; if (!s.dueTouched) s.due = addDays(value, customer() ? customer().terms : 30); } },
        due: (value) => { s.due = value; s.dueTouched = true; },
        delivery: (value) => { s.delivery = value; },
        ourRef: (value) => { s.ourRef = value; },
        yourRef: (value) => { s.yourRef = value; },
        order: (value) => { s.order = value; },
        rowName: (value, e, i) => setRow(Number(i), { name: value }),
        rowQty: (value, e, i) => setRow(Number(i), { qty: value }),
        rowUnit: (value, e, i) => setRow(Number(i), { unit: value }),
        rowPrice: (value, e, i) => setRow(Number(i), { price: value }),
        rowDisc: (value, e, i) => setRow(Number(i), { disc: value }),
        addRow: () => { s.rows.push(emptyRow()); },
        removeRow: (arg, e, i) => {
            s.rows.splice(Number(i), 1);
            if (!s.rows.length) s.rows.push(emptyRow());
            ensureTrailingRow();
        },
        create,
    });

    animateOnMobile(root, s, demo, {
        reset: () => {
            clearTimeout(toastTimer);
            Object.assign(s, fresh(35), { toast: null });
        },
        script: [
            [1500, () => { s.press = 'cust-open'; }],
            [2000, () => { s.press = null; s.popup = { kind: 'customer' }; }],
            [3200, () => { s.press = 'cust-1'; }],
            [3700, () => { s.press = null; applyCustomer(CUSTOMERS[0]); s.popup = null; }],
            [5000, () => { s.press = 'art-open-0'; }],
            [5500, () => { s.press = null; s.popup = { kind: 'article', row: 0 }; }],
            [6700, () => { s.press = 'art-KONS-T'; }],
            [7200, () => { s.press = null; pickArticle(0, 'KONS-T'); }],
            [8300, () => { s.press = 'qty-0'; }],
            [8700, () => { s.press = null; setRow(0, { qty: '4' }); }],
            [9100, () => { setRow(0, { qty: '40' }); }],
            [11000, () => { s.press = 'create-open'; }],
            [11500, () => { s.press = null; s.popup = { kind: 'create' }; }],
        ],
        loopAt: 14500,
    });
}
