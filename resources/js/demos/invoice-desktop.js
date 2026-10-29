import { createDemo, esc, num, fmt, fmtQ, addDays, longDate, todayISO, initials, svg, ICON, segmented } from './core.js';

const CUSTS = [
    { id: 'ac', no: 2, name: 'Ackolad AB', org: '556901-2231', city: 'Kristianstad', terms: 30, addr: 'Västra Storgatan 11', zip: '29132 KRISTIANSTAD', ref: 'Anna Ek', email: 'anna@ackolad.se', einv: true },
    { id: 'sb', no: 7, name: 'Steambaker AB', org: '559122-4410', city: 'Lund', terms: 15, addr: 'Stora Södergatan 8', zip: '22223 LUND', ref: 'Johan Berg', email: 'ekonomi@steambaker.se', einv: false },
    { id: 'ng', no: 12, name: 'Nordic Golf Group', org: '556788-0192', city: 'Malmö', terms: 30, addr: 'Bergsgatan 20', zip: '21154 MALMÖ', ref: 'Sara Lind', email: 'faktura@nordicgolf.se', einv: true },
    { id: 'bn', no: 15, name: 'Back Nine AB', org: '559301-7765', city: 'Stockholm', terms: 10, addr: 'Hornsgatan 54', zip: '11821 STOCKHOLM', ref: 'Max Holm', email: 'max@backnine.se', einv: false },
];

const ARTS = [
    { no: '1001', name: 'Rådgivning', unit: 'tim', price: 1500, vat: 25 },
    { no: 'KON', name: 'Konsultarvode', unit: 'st', price: 6550, vat: 25 },
    { no: '2001', name: 'Utlägg resa', unit: 'st', price: 0, vat: 6 },
    { no: '3001', name: 'Licens', unit: 'mån', price: 990, vat: 25 },
];

const SEGS = ['KUND', 'RADER', 'DETALJER', 'GRANSKA'];

export default function mount(root) {

    const today = todayISO();

    const blank = () => ({
        step: 1, maxStep: 1, done: false, custId: null, q: '', nc: null, rows: [], edit: null,
        terms: null, theirRef: null, text: '', showText: false, dist: null, showFees: false,
        discount: '', fee: '', attached: false, menu: false, action: 'send',
    });

    const s = { ...blank(), extra: [], invDate: today, delDate: today, ourRef: 'Elsa Nilsson' };

    const allCusts = () => [...CUSTS, ...s.extra];
    const cust = () => allCusts().find((c) => c.id === s.custId);
    const newEdit = () => ({ idx: -1, art: null, name: '', qty: '1', unit: 'st', price: '', vat: 25 });
    const editValid = () => !!s.edit && !!s.edit.name.trim() && num(s.edit.qty) > 0 && num(s.edit.price) > 0;
    const editRow = () => ({ art: s.edit.art, name: s.edit.name.trim(), qty: num(s.edit.qty), unit: s.edit.unit, price: num(s.edit.price), vat: s.edit.vat });
    const canNext = () => (s.step === 1 ? !!s.custId : s.step === 2 ? s.rows.length > 0 || editValid() : true);

    const go = (n) => {
        s.step = n;
        s.maxStep = Math.max(s.maxStep, n);
        s.menu = false;

        if (n === 2 && s.rows.length === 0 && !s.edit) s.edit = newEdit();
    };

    const commitRow = () => {
        const row = editRow();

        if (s.edit.idx >= 0) s.rows[s.edit.idx] = row; else s.rows.push(row);

        s.edit = null;
    };

    const next = () => {
        if (!canNext()) return;

        if (s.step === 2 && s.edit) {
            if (editValid()) commitRow(); else s.edit = null;
        }

        if (s.step < 4) go(s.step + 1);
    };

    const reset = () => Object.assign(s, blank());

    const calc = () => {
        const c = cust();
        const terms = s.terms ?? c?.terms ?? 30;
        const due = addDays(s.invDate, terms);
        const dist = s.dist ?? (c?.einv ? 'E-faktura' : 'E-post');
        const theirRef = s.theirRef ?? c?.ref ?? '';
        const disc = Math.min(100, num(s.discount)) / 100;
        const fee = num(s.fee);

        const liveRows = [...s.rows];
        let liveIdx = -1;

        if (s.edit && editValid()) {
            const row = editRow();

            if (s.edit.idx >= 0) { liveRows[s.edit.idx] = row; liveIdx = s.edit.idx; } else { liveRows.push(row); liveIdx = liveRows.length - 1; }
        }

        let ex = 0;
        let vat = 0;
        const vatBy = {};

        liveRows.forEach((r) => {
            const net = r.qty * r.price * (1 - disc);

            ex += net;
            vat += net * r.vat / 100;
            vatBy[r.vat] = (vatBy[r.vat] || 0) + net;
        });

        if (fee) { ex += fee; vat += fee * 0.25; vatBy[25] = (vatBy[25] || 0) + fee; }

        return { c, terms, due, dist, theirRef, disc, fee, liveRows, liveIdx, ex, vat, tot: ex + vat, vatBy };
    };

    const actionMap = (dist) => ({
        send: ['Skapa, bokför & skicka', `Skickas som ${dist.toLowerCase()} direkt`],
        book: ['Skapa & bokför', 'Bokförs nu, du skickar senare'],
        draft: ['Spara som utkast', 'Varken bokförs eller skickas'],
    });

    /* ------------------------------------------------------------ steg */

    const step1 = () => {
        const q = s.q.trim().toLowerCase();
        const list = allCusts().filter((x) => !q || [x.name, x.org, x.city].some((v) => v.toLowerCase().includes(q) || v.toLowerCase().includes(q.replace(/-/g, ''))));

        return `
            <div class="dm-head"><h3 class="dm-h">Vem ska faktureras?</h3><span class="dm-mono-sub">STEG 1 AV 4</span></div>
            <label class="dm-search">${svg(ICON.search, 16, 2, '#888')}<input data-key="q" data-input="q" value="${esc(s.q)}" placeholder="Sök kund eller org.nr" autocomplete="off"></label>
            <div class="dm-stack-sm">
                <span class="dm-label">${q ? 'SÖKRESULTAT' : 'SENASTE'}</span>
                <div class="dm-list">
                    ${list.map((x) => {
                        const on = x.id === s.custId;

                        return `<button type="button" class="dm-cust${on ? ' is-on' : ''}" data-act="pickCust" data-arg="${x.id}">
                            <span class="dm-avatar${on ? ' is-on' : ''}">${esc(initials(x.name))}</span>
                            <span class="dm-cust-text"><span class="dm-cust-name">${esc(x.name)}</span><span class="dm-cust-sub">${esc(x.city)} · ${x.terms} dagar</span></span>
                            <span class="dm-mono-sm">${esc(x.org)}</span>
                            <span class="dm-check${on ? ' is-on' : ''}">${svg(ICON.check, 13, 3)}</span>
                        </button>`;
                    }).join('')}
                    ${list.length === 0 ? `<div class="dm-empty">Ingen kund matchar ”${esc(s.q)}”.</div>` : ''}
                </div>
            </div>
            ${s.nc ? `
                <div class="dm-card-edit">
                    <span class="dm-card-edit-title">Ny kund</span>
                    <label class="dm-field"><span class="dm-label">FÖRETAGSNAMN</span><input class="dm-input" data-key="ncName" data-input="ncName" value="${esc(s.nc.name)}" placeholder="t.ex. Solberg Design AB"></label>
                    <div class="dm-grid-2">
                        <label class="dm-field"><span class="dm-label">ORG.NR</span><input class="dm-input" data-key="ncOrg" data-input="ncOrg" value="${esc(s.nc.org)}" placeholder="556677-8899"></label>
                        <label class="dm-field"><span class="dm-label">E-POST FÖR FAKTUROR</span><input class="dm-input" data-key="ncEmail" data-input="ncEmail" value="${esc(s.nc.email)}" placeholder="faktura@foretag.se"></label>
                    </div>
                    <span class="dm-note">Adress och betalvillkor hämtas från org.nr när kunden sparas.</span>
                    <div class="dm-row-end">
                        <button type="button" class="dm-btn-sm" data-act="closeNc">Avbryt</button>
                        <button type="button" class="dm-btn-sm is-dark${s.nc.name.trim() ? '' : ' is-dim'}" data-act="saveNc">Spara och välj</button>
                    </div>
                </div>` : `
                <button type="button" class="dm-btn-outline" data-act="openNc"><span class="dm-plus">+</span>Ny kund</button>`}
        `;
    };

    const step2 = (k) => `
        <div class="dm-head"><h3 class="dm-h">Vad ska du fakturera?</h3><span class="dm-mono-sub">STEG 2 AV 4 · ${esc((k.c?.name ?? '').toUpperCase())}</span></div>
        <div class="dm-stack-sm">
            ${s.rows.map((r, i) => `
                <button type="button" class="dm-rowcard" data-act="editRow" data-arg="${i}">
                    <span class="dm-rowcard-name">${esc(r.name)}</span>
                    <span class="dm-rowcard-sum">${fmt(r.qty * r.price)}</span>
                    <span class="dm-rowcard-sub">${fmtQ(r.qty)} ${esc(r.unit)} × ${fmt(r.price)} kr</span>
                    <span class="dm-tag">${r.vat} % moms</span>
                </button>`).join('')}
        </div>
        ${s.edit ? `
            <div class="dm-card-edit is-strong">
                <div class="dm-between"><span class="dm-card-edit-title">${s.edit.idx >= 0 ? 'Ändra rad' : 'Ny rad'}</span><span class="dm-mono-sm">${s.edit.art ? `Artikel ${esc(s.edit.art)}` : 'Ingen artikel'}</span></div>
                <div class="dm-stack-xs">
                    <span class="dm-label">VÄLJ ARTIKEL · VALFRITT</span>
                    <div class="dm-chips">
                        ${ARTS.map((a) => `<button type="button" class="dm-chip-btn${s.edit.art === a.no ? ' is-on' : ''}" data-act="art" data-arg="${esc(a.no)}"><span class="dm-chip-no">${esc(a.no)}</span>${esc(a.name)}</button>`).join('')}
                    </div>
                </div>
                <label class="dm-field"><span class="dm-label">BENÄMNING</span><input class="dm-input is-lg" data-key="eName" data-input="eName" value="${esc(s.edit.name)}" placeholder="Vad har du sålt?"></label>
                <div class="dm-grid-3">
                    <label class="dm-field"><span class="dm-label">ANTAL</span><input class="dm-input is-lg" data-key="eQty" data-input="eQty" value="${esc(s.edit.qty)}" inputmode="decimal"></label>
                    <label class="dm-field"><span class="dm-label">ENHET</span>
                        <select class="dm-input is-lg" data-change="eUnit">${['st', 'tim', 'dagar', 'mån', 'km'].map((u) => `<option value="${u}"${s.edit.unit === u ? ' selected' : ''}>${u}</option>`).join('')}</select>
                    </label>
                    <label class="dm-field"><span class="dm-label">À PRIS, KR</span><input class="dm-input is-lg is-right" data-key="ePrice" data-input="ePrice" value="${esc(s.edit.price)}" inputmode="decimal" placeholder="0"></label>
                </div>
                <div class="dm-edit-total">
                    <span class="dm-label">MOMS</span>
                    ${segmented([25, 12, 6, 0].map((v) => [v, `${v} %`]), s.edit.vat, 'vat')}
                    <span class="dm-grow"></span>
                    <span class="dm-muted-sm">Radsumma</span>
                    <span class="dm-strong-lg">${fmt(num(s.edit.qty) * num(s.edit.price))} kr</span>
                </div>
                <div class="dm-edit-actions">
                    ${s.edit.idx >= 0 ? '<button type="button" class="dm-link-danger" data-act="delRow">Ta bort rad</button>' : ''}
                    <span class="dm-grow"></span>
                    <button type="button" class="dm-btn-sm" data-act="cancelEdit">Avbryt</button>
                    <button type="button" class="dm-btn-sm is-dark${editValid() ? '' : ' is-dim'}" data-act="saveRow">Spara rad</button>
                </div>
            </div>` : `
            <button type="button" class="dm-btn-dashed" data-act="addRow"><span class="dm-plus">+</span>Lägg till rad</button>`}
        <div class="dm-totals">
            <div><span>Exkl. moms</span><span>${fmt(k.ex)} kr</span></div>
            <div><span>Moms</span><span>${fmt(k.vat)} kr</span></div>
            <div class="is-total"><span>Att betala</span><span>${fmt(k.tot)} kr</span></div>
        </div>
    `;

    const step3 = (k) => {
        const name = (k.c?.name ?? '').toUpperCase();
        const feeValue = [k.disc ? `Rabatt ${fmtQ(k.disc * 100)} %` : '', k.fee ? `Avgift ${fmtQ(k.fee)} kr` : ''].filter(Boolean).join(' · ');
        const textValue = s.text.trim() ? s.text.trim().slice(0, 24) + (s.text.trim().length > 24 ? '…' : '') : '';
        const distHint = k.dist === 'E-faktura'
            ? (k.c?.einv ? `${k.c.name} tar emot e-faktura, föreslaget.` : `${k.c?.name} har ingen registrerad e-fakturaadress.`)
            : k.dist === 'E-post' ? `PDF skickas till ${k.c?.email ?? '—'}` : 'Skrivs ut och skickas via Oqto · 12 kr';

        return `
            <div class="dm-head"><h3 class="dm-h">Datum och villkor</h3><span class="dm-mono-sub">STEG 3 AV 4 · FÖRIFYLLT FRÅN ${esc(name)}</span></div>
            <p class="dm-lede">Allt här är redan ifyllt. Ändra bara det som avviker, annars är det bara att gå vidare.</p>
            <div class="dm-group">
                <div class="dm-line"><span class="dm-line-k">Fakturadatum</span><input class="dm-line-input" type="date" value="${esc(s.invDate)}" data-change="invDate"></div>
                <div class="dm-line"><span class="dm-line-k">Betalvillkor</span>${segmented([10, 15, 30, 60].map((t) => [t, `${t} d`]), k.terms, 'terms')}</div>
                <div class="dm-line"><span class="dm-line-k">Förfallodatum</span><span class="dm-line-v is-muted">${esc(k.due)}</span></div>
                <div class="dm-line"><span class="dm-line-k">Leveransdatum</span><input class="dm-line-input" type="date" value="${esc(s.delDate)}" data-change="delDate"></div>
            </div>
            <div class="dm-group">
                <div class="dm-line"><span class="dm-line-k">Er referens</span><input class="dm-line-input is-text" data-key="theirRef" data-input="theirRef" value="${esc(k.theirRef)}" placeholder="Lägg till"></div>
                <div class="dm-line"><span class="dm-line-k">Vår referens</span><input class="dm-line-input is-text" data-key="ourRef" data-input="ourRef" value="${esc(s.ourRef)}" placeholder="Lägg till"></div>
                <button type="button" class="dm-line is-button" data-act="toggleText"><span class="dm-line-k">Fakturatext</span><span class="dm-line-v${textValue ? '' : ' is-muted'}">${textValue ? esc(textValue) : 'Lägg till'}</span>${svg(s.showText ? ICON.chevronUp : ICON.chevronRight, 14, 2, '#999')}</button>
                ${s.showText ? `<div class="dm-line-extra"><textarea class="dm-input" data-key="text" data-input="text" rows="3" placeholder="Visas ovanför raderna på fakturan">${esc(s.text)}</textarea></div>` : ''}
            </div>
            <div class="dm-group">
                <div class="dm-line is-tall">
                    <div class="dm-line-top"><span class="dm-line-k">Distribution</span>${segmented(['E-faktura', 'E-post', 'Brev'].map((d) => [d, d]), k.dist, 'dist')}</div>
                    <span class="dm-line-hint">${esc(distHint)}</span>
                </div>
                <button type="button" class="dm-line is-button" data-act="toggleFees"><span class="dm-line-k">Avgifter och rabatt</span><span class="dm-line-v${feeValue ? '' : ' is-muted'}">${esc(feeValue || 'Inga')}</span>${svg(s.showFees ? ICON.chevronUp : ICON.chevronRight, 14, 2, '#999')}</button>
                ${s.showFees ? `<div class="dm-line-extra dm-grid-2">
                    <label class="dm-field"><span class="dm-label">RABATT %</span><input class="dm-input" data-key="discount" data-input="discount" value="${esc(s.discount)}" inputmode="decimal" placeholder="0"></label>
                    <label class="dm-field"><span class="dm-label">FAKTURAAVGIFT, KR</span><input class="dm-input" data-key="fee" data-input="fee" value="${esc(s.fee)}" inputmode="decimal" placeholder="0"></label>
                </div>` : ''}
            </div>
        `;
    };

    const step4 = (k) => {
        const review = [
            { k: 'KUND', v: k.c?.name ?? '', sub: k.theirRef ? `Er referens: ${k.theirRef}` : (k.c?.city ?? ''), go: 1 },
            { k: 'RADER', v: `${s.rows.length} ${s.rows.length === 1 ? 'rad' : 'rader'} · ${fmt(k.ex)} kr exkl. moms`, sub: s.rows.map((r) => r.name).join(', '), go: 2 },
            { k: 'VILLKOR', v: `${k.terms} dagar · förfaller ${longDate(k.due)}`, sub: `Fakturadatum ${s.invDate}`, go: 3 },
            { k: 'SKICKAS', v: k.dist, sub: k.dist === 'E-post' ? (k.c?.email ?? '') : k.dist === 'E-faktura' ? 'Via Peppol' : `${k.c?.addr ?? ''}, ${k.c?.zip ?? ''}`, go: 3 },
        ];

        return `
            <div class="dm-head"><h3 class="dm-h">Granska och skicka</h3><span class="dm-mono-sub">STEG 4 AV 4</span></div>
            <div class="dm-group">
                ${review.map((r) => `
                    <div class="dm-review">
                        <span class="dm-review-k">${r.k}</span>
                        <span class="dm-review-text"><span class="dm-review-v">${esc(r.v)}</span><span class="dm-review-sub">${esc(r.sub)}</span></span>
                        <button type="button" class="dm-link" data-act="step" data-arg="${r.go}" data-force="1">Ändra</button>
                    </div>`).join('')}
            </div>
            <button type="button" class="dm-attach" data-act="toggleAttach">
                <span class="dm-attach-icon">${s.attached ? '✓' : '+'}</span>
                <span class="dm-attach-text"><span class="dm-attach-title">Bilagor</span><span class="dm-attach-sub">${s.attached ? 'tidrapport-oktober.pdf · 84 kB' : 'Inga tillagda'}</span></span>
                <span class="dm-attach-action">${s.attached ? 'Ta bort' : 'Lägg till'}</span>
            </button>
            <div class="dm-grand"><span>Att betala, förfaller ${esc(longDate(k.due))}</span><strong>${fmt(k.tot)} SEK</strong></div>
        `;
    };

    /* ------------------------------------------------------ förhandsvisning */

    const preview = (k) => {
        const hl = (n) => (s.step === n ? ' is-hl' : '');
        const c = k.c;
        const rows = k.liveRows.map((r, i) => ({ name: r.name, qty: fmtQ(r.qty), unit: r.unit, price: fmt(r.price), sum: fmt(r.qty * r.price), live: i === k.liveIdx }));

        if (k.disc) rows.push({ name: `Rabatt ${fmtQ(k.disc * 100)} %`, qty: '', unit: '', price: '', sum: '−' + fmt(k.liveRows.reduce((a, r) => a + r.qty * r.price, 0) * k.disc) });
        if (k.fee) rows.push({ name: 'Fakturaavgift', qty: '1', unit: 'st', price: fmt(k.fee), sum: fmt(k.fee) });

        const meta = [
            ['Fakturanummer:', '2041'], ['Kundnummer:', c ? String(c.no) : '—'], ['Fakturadatum:', s.invDate], ['Förfallodatum:', k.due],
            ['Leveransdatum:', s.delDate], ['OCR:', '20417'], ['Valuta:', 'SEK'], ['Er referens:', k.theirRef || '—'], ['Vår referens:', s.ourRef || '—'],
        ];

        const vatLine = Object.keys(k.vatBy).length
            ? 'Moms: ' + Object.keys(k.vatBy).sort((a, b) => b - a).map((r) => `${r} % ${fmt(k.vatBy[r] * r / 100)} (${fmt(k.vatBy[r])})`).join(' · ')
            : 'Moms: —';

        return `
            <aside class="dm-preview">
                <div class="dm-preview-head"><span class="dm-label">FÖRHANDSVISNING</span><span class="dm-muted-xs">Uppdateras medan du fyller i</span></div>
                <div class="dm-preview-scroll" data-scroll="preview">
                    <div class="dm-paper">
                        <div class="dm-paper-top"><span class="dm-paper-co">Nightshift Holding AB</span><span class="dm-paper-title"><strong>Faktura</strong><span>Sida 1/1</span></span></div>
                        <div class="dm-paper-grid">
                            <div class="dm-paper-meta${hl(3)}">${meta.map(([a, b]) => `<span>${a}</span><span>${esc(b)}</span>`).join('')}</div>
                            <div class="dm-paper-addr${hl(1)}">
                                <strong>Fakturaadress:</strong>
                                ${c ? `<span>${esc(c.name)}</span><span>${esc(c.addr)}</span><span>${esc(c.zip)}</span>` : '<span class="is-faint">Välj kund</span>'}
                            </div>
                        </div>
                        ${s.text.trim() ? `<div class="dm-paper-text">${esc(s.text)}</div>` : ''}
                        <div class="dm-paper-rows${hl(2)}">
                            <div class="dm-paper-row is-head"><span>Benämning</span><span class="r">Antal</span><span>Enh</span><span class="r">Pris</span><span class="r">Summa</span></div>
                            ${rows.map((r) => `<div class="dm-paper-row${r.live ? ' is-live' : ''}"><span>${esc(r.name)}</span><span class="r">${r.qty}</span><span>${esc(r.unit)}</span><span class="r">${r.price}</span><span class="r">${r.sum}</span></div>`).join('')}
                            ${rows.length === 0 ? '<span class="dm-paper-empty">Raderna visas här</span>' : ''}
                        </div>
                        <div class="dm-grow"></div>
                        <div class="dm-paper-sums">
                            <span class="dm-paper-vat">${vatLine}</span>
                            <div class="dm-paper-box">
                                <span><em>Totalt exkl. moms</em>${fmt(k.ex)}</span>
                                <span><em>Moms</em>${fmt(k.vat)}</span>
                                <span class="dm-grow"></span>
                                <span class="r"><em>Att betala</em><b>${fmt(k.tot)} SEK</b></span>
                            </div>
                        </div>
                        <div class="dm-paper-foot">
                            <span><strong>Nightshift Holding AB</strong><span>Döbelnsgatan 10</span><span>29130 KRISTIANSTAD</span></span>
                            <span class="dm-paper-foot-grid"><span>Bankgiro:</span><span>5966-7816</span><span>Org. nummer:</span><span>559415-2240</span><span>VAT-nr:</span><span>SE559415224001</span><span></span><span>Godkänd för F-skatt</span></span>
                        </div>
                    </div>
                </div>
            </aside>`;
    };

    /* ------------------------------------------------------------ vyer */

    const wizard = (k) => {
        const map = actionMap(k.dist);

        return `
            <div class="dm-wiz">
                <section class="dm-wiz-main">
                    <div class="dm-segs-wrap">
                        <div class="dm-segs">
                            ${SEGS.map((label, i) => {
                                const n = i + 1;
                                const reach = n <= s.maxStep && n !== s.step;
                                const bar = n <= s.step ? 'is-active' : n <= s.maxStep ? 'is-seen' : '';

                                return `<button type="button" class="dm-seg-step ${bar}${n === s.step ? ' is-current' : ''}" ${reach ? `data-act="step" data-arg="${n}"` : 'tabindex="-1"'}><span class="dm-seg-bar"></span><span class="dm-seg-label">${label}</span></button>`;
                            }).join('')}
                        </div>
                    </div>
                    <div class="dm-wiz-scroll" data-scroll="main"><div class="dm-wiz-body">${s.step === 1 ? step1() : s.step === 2 ? step2(k) : s.step === 3 ? step3(k) : step4(k)}</div></div>
                    <div class="dm-wiz-foot">
                        <div class="dm-foot-total"><span class="dm-label">ATT BETALA</span><strong>${fmt(k.tot)} kr</strong></div>
                        <button type="button" class="dm-btn-lg" data-act="back">${s.step === 1 ? 'Avbryt' : 'Tillbaka'}</button>
                        ${s.step !== 4
                            ? `<button type="button" class="dm-btn-lg is-dark${canNext() ? '' : ' is-dim'}" data-act="next">${s.step === 3 ? 'Granska' : 'Fortsätt'}<span class="dm-kbd">⌘↵</span></button>`
                            : `<div class="dm-split">
                                <button type="button" class="dm-split-main" data-act="finish">${map[s.action][0]}</button>
                                <button type="button" class="dm-split-toggle" data-act="toggleMenu" aria-label="Fler val">${svg(ICON.chevronUp, 14, 2.4)}</button>
                                ${s.menu ? `<div class="dm-menu">${Object.keys(map).map((key) => `
                                    <button type="button" class="dm-menu-item${key === s.action ? ' is-on' : ''}" data-act="action" data-arg="${key}"><span>${map[key][0]}</span><small>${esc(map[key][1])}</small></button>`).join('')}</div>` : ''}
                            </div>`}
                    </div>
                </section>
                ${preview(k)}
            </div>`;
    };

    const done = (k) => {
        const c = k.c;
        const name = c?.name ?? '';
        const title = s.action === 'send' ? 'Faktura 2041 skickad' : s.action === 'book' ? 'Faktura 2041 skapad' : 'Utkast sparat';
        const body = s.action === 'draft'
            ? `${fmt(k.tot)} kr till ${name}. Du hittar utkastet under Kundfakturor.`
            : `${fmt(k.tot)} kr till ${name}. Bokförd på verifikation K-112. Förfaller ${longDate(k.due)}.`;
        const rows = s.action === 'send'
            ? [['Skickad till', k.dist === 'E-post' ? (c?.email ?? '') : k.dist === 'E-faktura' ? `${name} · Peppol` : 'Brev via Oqto'], ['OCR', '20417']]
            : s.action === 'book' ? [['Status', 'Bokförd, ej skickad'], ['OCR', '20417']] : [['Status', 'Utkast']];

        return `
            <div class="dm-done-wrap">
                <div class="dm-done">
                    <span class="dm-done-icon">${svg(ICON.check, 34, 2.2)}</span>
                    <h3>${title}</h3>
                    <p>${esc(body)}</p>
                    <div class="dm-done-rows">${rows.map(([a, b]) => `<div><span>${a}</span><strong>${esc(b)}</strong></div>`).join('')}</div>
                    <button type="button" class="dm-btn-block" data-act="reset">Klar</button>
                    <button type="button" class="dm-link-plain" data-act="reset">Skapa en till faktura</button>
                </div>
            </div>`;
    };

    const view = () => {
        const k = calc();

        return `
            <div class="dm-window">
                <div class="dm-topbar">
                    <span class="dm-crumb">Kundfakturor</span>
                    ${svg(ICON.chevronRight, 12, 2, '#aaa')}
                    <strong>Ny faktura</strong>
                    <span class="dm-pill-mono">${s.done ? 'KLAR' : 'UTKAST · SPARAS AUTOMATISKT'}</span>
                </div>
                ${s.done ? done(k) : wizard(k)}
            </div>`;
    };

    const actions = {
        step: (n) => { n = Number(n); if (n <= s.maxStep && n !== s.step) go(n); },
        next,
        back: () => { if (s.step === 1) reset(); else go(s.step - 1); },
        finish: () => { s.done = true; s.menu = false; },
        reset,
        shortcut: () => { if (s.done) return; if (s.step === 4) { s.done = true; s.menu = false; } else next(); },
        pickCust: (id) => { s.custId = id; s.terms = null; s.theirRef = null; s.dist = null; },
        q: (v) => { s.q = v; },
        openNc: () => { s.nc = { name: s.q, org: '', email: '' }; },
        closeNc: () => { s.nc = null; },
        ncName: (v) => { s.nc.name = v; },
        ncOrg: (v) => { s.nc.org = v; },
        ncEmail: (v) => { s.nc.email = v; },
        saveNc: () => {
            if (!s.nc.name.trim()) return;

            const id = 'n' + Date.now();

            s.extra.push({ id, no: 20 + s.extra.length, name: s.nc.name.trim(), org: s.nc.org || '—', city: 'Ny kund', terms: 30, addr: '', zip: '', ref: '', email: s.nc.email || '—', einv: false });
            s.nc = null;
            s.q = '';
            actions.pickCust(id);
        },
        editRow: (i) => {
            const r = s.rows[Number(i)];

            s.edit = { idx: Number(i), art: r.art, name: r.name, qty: String(r.qty).replace('.', ','), unit: r.unit, price: String(r.price).replace('.', ','), vat: r.vat };
        },
        addRow: () => { s.edit = newEdit(); },
        art: (no) => {
            if (s.edit.art === no) { s.edit.art = null; return; }

            const a = ARTS.find((x) => x.no === no);

            Object.assign(s.edit, { art: a.no, name: a.name, unit: a.unit, price: a.price ? String(a.price) : '', vat: a.vat });
        },
        eName: (v) => { s.edit.name = v; },
        eQty: (v) => { s.edit.qty = v; },
        eUnit: (v) => { s.edit.unit = v; },
        ePrice: (v) => { s.edit.price = v; },
        vat: (v) => { s.edit.vat = Number(v); },
        saveRow: () => { if (editValid()) commitRow(); },
        cancelEdit: () => { s.edit = null; },
        delRow: () => { s.rows.splice(s.edit.idx, 1); s.edit = null; },
        invDate: (v) => { if (v) s.invDate = v; },
        delDate: (v) => { if (v) s.delDate = v; },
        terms: (v) => { s.terms = Number(v); },
        theirRef: (v) => { s.theirRef = v; },
        ourRef: (v) => { s.ourRef = v; },
        toggleText: () => { s.showText = !s.showText; },
        text: (v) => { s.text = v; },
        dist: (v) => { s.dist = v; },
        toggleFees: () => { s.showFees = !s.showFees; },
        discount: (v) => { s.discount = v; },
        fee: (v) => { s.fee = v; },
        toggleAttach: () => { s.attached = !s.attached; },
        toggleMenu: () => { s.menu = !s.menu; },
        action: (k) => { s.action = k; s.menu = false; },
    };

    createDemo(root, s, view, actions);
}
