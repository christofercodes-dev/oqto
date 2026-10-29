import { createDemo, esc, svg, ICON, segmented } from './core.js';

/*
 * Förfallna fakturor och påminnelseflödet. Förfallodatumen räknas från idag
 * så att demot alltid ser aktuellt ut. Partnernamnen visas som text.
 */

const PARTNERS = [
    { id: 'kravia', name: 'Kravia', connected: true },
    { id: 'finansfabriken', name: 'Finansfabriken', connected: false },
    { id: 'waya', name: 'Waya', connected: false },
];

const FEES = [['none', 'Ingen avgift'], ['60', '60 kr'], ['custom', 'Eget belopp']];
const INTS = [['none', 'Ingen ränta'], ['ref8', 'Referensränta + 8 %'], ['custom', 'Egen ränta']];
const REF = 2.0;
const MAX = 3;
const MON = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

const DAY = 864e5;
const TODAY = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), d.getDate()); })();
const daysAgo = (n) => new Date(TODAY.getFullYear(), TODAY.getMonth(), TODAY.getDate() - n);
const addD = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const dateLabel = (d) => `${d.getDate()} ${MON[d.getMonth()]}`;
const kr = (n) => n.toLocaleString('sv-SE').replace(/,/g, ' ') + ' kr';

const INVOICES = [
    { id: '1017', cust: 'Byggbolaget Norr AB', amt: 12400, due: daysAgo(31), sent: 2 },
    { id: '1029', cust: 'Lindqvist Arkitekter', amt: 8750, due: daysAgo(14), sent: 1, custManual: true },
    { id: '1031', cust: 'Kafé Sjöboden', amt: 2340, due: daysAgo(10), sent: 0 },
    { id: '1036', cust: 'Ekholm Konsult AB', amt: 5000, due: daysAgo(5), sent: 0 },
    { id: '1038', cust: 'Ström & Dotter', amt: 19900, due: daysAgo(3), sent: 0 },
    { id: '1040', cust: 'Nordic Padel Club', amt: 3600, due: daysAgo(2), sent: 0, disputed: true },
];

const DEFAULTS = {
    mode: 'manual',
    include: 'all',
    usage: 'default',
    steps: [
        { id: 1, type: 'reminder', days: 7, fee: '60', int: 'ref8' },
        { id: 2, type: 'reminder', days: 14, fee: '60', int: 'ref8' },
        { id: 3, type: 'inkasso', days: 10, partner: 'kravia', mode: 'suggest' },
    ],
};

const clone = (o) => JSON.parse(JSON.stringify(o));

export default function mount(root) {

    let uid = 10;

    const s = { ...clone(DEFAULTS), saved: clone(DEFAULTS), dirty: false, view: 'list', extra: {}, paused: {}, resumed: {}, handed: {}, wait: {} };

    const upd = (fn) => { fn(); s.dirty = true; };
    const setStep = (id, patch) => upd(() => { s.steps = s.steps.map((x) => (x.id === id ? { ...x, ...patch } : x)); });

    const stepName = (steps, i) => {
        let r = 0;

        for (let k = 0; k <= i; k++) if (steps[k].type === 'reminder') r++;

        return steps[i].type === 'reminder' ? `Påminnelse ${r}` : 'Inkasso';
    };

    const radioCard = (on, act, arg, title, desc, extraHtml = '') => `
        <button type="button" class="dm-r-radio${on ? ' is-on' : ''}" data-act="${act}"${arg !== null ? ` data-arg="${arg}"` : ''}>
            <span class="dm-r-dot"></span>
            <span class="dm-r-radio-text"><span class="dm-r-radio-title">${title}</span>${desc ? `<span class="dm-r-radio-desc">${desc}</span>` : ''}${extraHtml}</span>
        </button>`;

    /* ------------------------------------------------------- förfallna fakturor */

    const buildRows = () => {
        const auto = s.mode === 'auto';
        const cum = [];

        s.steps.reduce((a, x) => { cum.push(a + x.days); return a + x.days; }, 0);

        const inkStep = s.steps.find((x) => x.type === 'inkasso');
        const partner = inkStep ? PARTNERS.find((p) => p.id === inkStep.partner) : null;
        let needs = 0;

        const rows = INVOICES.map((inv) => {
            const late = Math.round((TODAY - inv.due) / DAY);
            const sent = Math.min(s.steps.length, inv.sent + (s.extra[inv.id] || 0));
            const handed = !!s.handed[inv.id];
            const paused = s.paused[inv.id] ?? (inv.disputed && !s.resumed[inv.id]);
            const managed = auto && !inv.custManual && s.include === 'all';
            const badge = !auto ? '' : inv.custManual ? 'Manuell · kundinställning' : !managed ? 'Manuell · förföll före bytet' : '';

            const dots = s.steps.map((x, i) => (i < sent || (handed && x.type === 'inkasso') ? (x.type === 'inkasso' ? 'is-ink' : 'is-on') : ''));
            const stage = handed ? `Hos ${partner ? partner.name : 'inkasso'}` : sent === 0 ? 'Ingen påminnelse skickad' : `${stepName(s.steps, sent - 1)} skickad`;

            const btn = (label, primary, act, arg = inv.id) => `<button type="button" class="dm-r-btn${primary ? ' is-dark' : ''}" data-act="${act}" data-arg="${arg}">${label}</button>`;

            let next;
            let nextSub;
            let nextDot = '';
            let actions = '';

            if (handed) {
                next = 'Inkassoärende pågår';
                nextSub = 'Status synkas från ' + (partner ? partner.name : 'partnern');
                nextDot = 'is-ink';
                actions = '<button type="button" class="dm-r-btn" tabindex="-1">Visa ärende</button>';
            } else if (paused) {
                next = inv.disputed ? 'Pausad · bestriden' : 'Pausad';
                nextSub = 'Inga påminnelser skickas';
                nextDot = 'is-warn';
                actions = btn('Återuppta', false, 'resume');
            } else if (sent >= s.steps.length) {
                next = 'Flödet är slut';
                nextSub = 'Hantera manuellt';
                actions = btn('Skicka till inkasso', false, 'hand');
            } else {
                const st = s.steps[sent];
                const name = stepName(s.steps, sent);
                const nd = cum[sent];
                const date = addD(inv.due, nd);
                const isDue = late >= nd;
                const isInk = st.type === 'inkasso';

                if (managed) {
                    if (isInk && st.mode === 'suggest' && isDue && !s.wait[inv.id]) {
                        next = `Förslag: inkasso via ${partner.name}`;
                        nextSub = 'Väntar på ditt godkännande';
                        nextDot = 'is-ink';
                        needs++;
                        actions = btn('Vänta', false, 'wait') + btn('Skicka', true, 'hand');
                    } else if (s.wait[inv.id]) {
                        next = 'Förslag uppskjutet';
                        nextSub = `Föreslås igen ${dateLabel(addD(TODAY, 7))}`;
                        nextDot = 'is-warn';
                        actions = btn('Skicka nu', false, 'hand');
                    } else {
                        next = isInk ? `Lämnas till ${partner.name} automatiskt` : `${name} skickas automatiskt`;
                        nextSub = isDue ? 'I morgon kl. 08' : `${dateLabel(date)} kl. 08`;
                        nextDot = 'is-ok';
                        actions = btn('Pausa', false, 'pause');
                    }
                } else if (isDue) {
                    next = `Redo för ${name.toLowerCase()}`;
                    nextSub = `Sedan ${dateLabel(date)}`;
                    nextDot = 'is-ink';
                    needs++;
                    actions = btn(isInk ? 'Skicka till inkasso' : 'Skicka påminnelse', true, isInk ? 'hand' : 'bump');
                } else {
                    next = name;
                    nextSub = `Blir aktuell ${dateLabel(date)}`;
                    actions = btn('Skicka nu', false, isInk ? 'hand' : 'bump');
                }
            }

            return `
                <div class="dm-r-row">
                    <span class="dm-r-id">${inv.id}</span>
                    <span class="dm-r-cust"><strong>${esc(inv.cust)}</strong>${badge ? `<span class="dm-r-badge">${badge}</span>` : ''}</span>
                    <span class="dm-r-amt">${kr(inv.amt)}</span>
                    <span class="dm-r-due"><span>${dateLabel(inv.due)}</span><small>${late} dagar sen</small></span>
                    <span class="dm-r-flow"><span class="dm-r-dots">${dots.map((d) => `<i class="${d}"></i>`).join('')}</span><span>${esc(stage)}</span></span>
                    <span class="dm-r-next">
                        <span class="dm-r-next-text"><span class="dm-r-next-title"><i class="dm-r-state ${nextDot}"></i>${esc(next)}</span><small>${esc(nextSub)}</small></span>
                        <span class="dm-r-actions">${actions}</span>
                    </span>
                </div>`;
        });

        return { rows, needs };
    };

    const listView = () => {
        const auto = s.mode === 'auto';
        const { rows, needs } = buildRows();
        const sum = INVOICES.reduce((a, x) => a + x.amt, 0);

        return `
            <div class="dm-r-page">
                <div class="dm-r-pagehead">
                    <div class="dm-r-titles"><h3>Förfallna fakturor</h3><span>${INVOICES.length} fakturor · ${kr(sum)} · ${needs} kräver åtgärd</span></div>
                    <div class="dm-r-mode">
                        <span class="dm-r-state ${auto ? 'is-ok' : ''}"></span>
                        <span class="dm-r-mode-text"><strong>${auto ? 'Oqto fakturaservice' : 'Du skickar själv'}</strong><small>${auto ? 'Påminnelser skickas automatiskt' : 'Manuella påminnelser'}</small></span>
                        <button type="button" class="dm-r-btn" data-act="view" data-arg="settings">Ändra</button>
                    </div>
                </div>
                ${!auto ? `
                    <div class="dm-r-upsell">
                        <span class="dm-r-upsell-icon">${svg(ICON.bolt, 18)}</span>
                        <span class="dm-r-upsell-text"><strong>Låt Oqto skicka påminnelserna åt dig</strong><small>Samma flöde som idag, men det sker automatiskt och stoppas när kunden betalar.</small></span>
                        <button type="button" class="dm-r-btn is-dark" data-act="view" data-arg="settings">Slå på fakturaservice</button>
                    </div>` : ''}
                <div class="dm-r-table">
                    <div class="dm-r-row is-head"><span>NR</span><span>KUND</span><span class="r">BELOPP</span><span>FÖRFÖLL</span><span>I FLÖDET</span><span>NÄSTA STEG</span></div>
                    ${rows.join('')}
                </div>
            </div>`;
    };

    /* ------------------------------------------------------------ inställningar */

    const settingsView = () => {
        const auto = s.mode === 'auto';
        const hasInk = s.steps.some((x) => x.type === 'inkasso');
        const canAdd = s.steps.length < MAX && !hasInk;
        let total = 0;
        let rem = 0;

        const steps = s.steps.map((x, i) => {
            total += x.days;

            const isReminder = x.type === 'reminder';

            if (isReminder) rem++;

            const partner = PARTNERS.find((p) => p.id === x.partner);
            const prev = i === 0 ? 'förfallodatum' : stepName(s.steps, i - 1).toLowerCase();
            const feeAmt = x.feeAmt ?? 60;
            const intRate = x.intRate ?? 8;
            const curMode = x.mode || 'suggest';

            const body = isReminder ? `
                <div class="dm-r-setting">
                    <span class="dm-r-setting-k is-pad">Påminnelseavgift</span>
                    <div class="dm-r-setting-v">
                        ${segmented(FEES, x.fee, `fee:${x.id}`, 'is-wrap')}
                        ${x.fee === 'custom' ? `<div class="dm-r-inline"><label class="dm-r-numfield${feeAmt > 60 ? ' is-warn' : ''}"><input type="number" min="0" value="${feeAmt}" data-change="feeAmt:${x.id}"><span>kr</span></label></div>` : ''}
                        ${x.fee === 'custom' && feeAmt > 60 ? '<span class="dm-r-warn">Över 60 kr får inte tas ut av privatpersoner. Gäller då bara företagskunder.</span>' : ''}
                        <span class="dm-r-hint">Max 60 kr och bara om det avtalats i förväg.</span>
                    </div>
                </div>
                <div class="dm-r-setting is-last">
                    <span class="dm-r-setting-k is-pad">Dröjsmålsränta</span>
                    <div class="dm-r-setting-v">
                        ${segmented(INTS, x.int, `int:${x.id}`, 'is-wrap')}
                        ${x.int === 'custom' ? `<div class="dm-r-inline"><span>Referensränta +</span><label class="dm-r-numfield"><input type="number" min="0" step="0.5" value="${intRate}" data-change="intRate:${x.id}"><span>%</span></label><small>= ${(REF + intRate).toLocaleString('sv-SE')} % per år just nu</small></div>` : ''}
                        <span class="dm-r-hint">Räknas från förfallodatum på utestående belopp.</span>
                    </div>
                </div>` : `
                <div class="dm-r-setting">
                    <span class="dm-r-setting-k">Överlämning</span>
                    ${auto
                        ? `<div class="dm-r-modegrid">${[
                            ['suggest', 'Föreslå först', 'Förslaget visas under Förfallna fakturor. Du godkänner varje ärende.'],
                            ['auto', 'Automatiskt', 'Obetalda fakturor lämnas över direkt, utan att du behöver göra något.'],
                        ].map(([id, title, desc]) => radioCard(curMode === id, `inkMode:${x.id}`, id, title, desc)).join('')}</div>`
                        : '<span class="dm-r-plain">Du skickar själv till inkasso från Förfallna fakturor. Med fakturaservice kan det ske automatiskt.</span>'}
                </div>
                <div class="dm-r-setting is-last">
                    <span class="dm-r-setting-k"><strong>Inkassobolag</strong><small>Integrerade med Oqto</small></span>
                    <div class="dm-r-setting-v">
                        <div class="dm-r-partners">${PARTNERS.map((p) => `
                            <button type="button" class="dm-r-partner${p.id === x.partner ? ' is-on' : ''}" data-act="partner:${x.id}" data-arg="${p.id}">
                                <span class="dm-r-partner-name">${p.name}</span>
                                <span class="dm-r-partner-status"><i class="dm-r-state ${p.connected ? 'is-ok' : 'is-warn'}"></i>${p.connected ? 'Kopplad' : 'Kräver avtal'}</span>
                            </button>`).join('')}</div>
                        ${partner && !partner.connected ? `<div class="dm-r-agree"><span>För att använda ${partner.name} behöver du godkänna avtal och kundkännedom (KYC).</span><button type="button" class="dm-r-btn is-dark" tabindex="-1">Godkänn med BankID</button></div>` : ''}
                        <span class="dm-r-hint">Se fler inkassobolag och jämför villkor och avgifter</span>
                    </div>
                </div>`;

            return `
                <div class="dm-r-tl">
                    <div class="dm-r-tl-rail"><span class="dm-r-tl-num${isReminder ? '' : ' is-ink'}">${i + 1}</span><span class="dm-r-tl-line"></span></div>
                    <div class="dm-r-tl-body">
                        <div class="dm-r-stepcard">
                            <div class="dm-r-stephead">
                                <span class="dm-r-mono">DAG ${total}</span>
                                <strong>${isReminder ? `Påminnelse ${rem}` : 'Inkasso'}</strong>
                                <button type="button" class="dm-r-icon" data-act="remove" data-arg="${x.id}" aria-label="Ta bort steg">${svg(ICON.trash, 16)}</button>
                            </div>
                            <div class="dm-r-setting">
                                <span class="dm-r-setting-k">Tidpunkt</span>
                                <div class="dm-r-time">
                                    <div class="dm-r-stepper"><button type="button" data-act="dec" data-arg="${x.id}" aria-label="Minska">−</button><span>${x.days}</span><button type="button" data-act="inc" data-arg="${x.id}" aria-label="Öka">+</button></div>
                                    <span>dagar efter ${prev}</span>
                                </div>
                            </div>
                            ${body}
                        </div>
                    </div>
                </div>`;
        }).join('');

        return `
            <div class="dm-r-page is-settings">
                <div class="dm-r-titles"><h3>Påminnelser</h3><p>Ett flöde för alla förfallna kundfakturor. Välj om du skickar själv eller låter Oqto göra det.</p></div>

                <div class="dm-r-section">
                    <div class="dm-r-section-head"><span class="dm-r-mono">01</span><strong>Vem skickar påminnelserna?</strong></div>
                    <div class="dm-r-twocol">
                        ${radioCard(!auto, 'setMode', 'manual', 'Jag skickar själv', 'Fakturor som är redo för nästa steg samlas under Förfallna fakturor. Du skickar med ett klick.')}
                        ${radioCard(auto, 'setMode', 'auto', 'Oqto fakturaservice <span class="dm-r-chip">Kräver bankkoppling</span>', 'Oqto bevakar betalningen mot banken och skickar enligt ditt flöde. Påminnelser skickas i ditt namn, inkasso lämnas över till partner.', `
                            <span class="dm-r-bullets"><span>${svg(ICON.check, 12, 2.6)}Du bestämmer flödet</span><span>${svg(ICON.check, 12, 2.6)}Kopplad mot ditt bankkonto</span><span>${svg(ICON.check, 12, 2.6)}Sker automatiskt</span></span>`)}
                    </div>
                    ${auto ? `
                        <div class="dm-r-auto">
                            <div class="dm-r-auto-bank"><i class="dm-r-state is-ok"></i><strong>Bankkonto kopplat</strong><span>Företagskonto ••4521</span></div>
                            <div class="dm-r-auto-include">
                                <strong>Du har ${INVOICES.length} förfallna fakturor. Ska de också tas med?</strong>
                                ${segmented([['all', 'Ja, ta med dem'], ['new', 'Nej, bara nya fakturor']], s.include, 'include')}
                                <span class="dm-r-hint">${s.include === 'all' ? 'De fortsätter i flödet där de är. Steg som redan har passerats skickas i morgon kl. 08.' : 'De ligger kvar under Förfallna fakturor och hanteras manuellt.'}</span>
                            </div>
                        </div>` : ''}
                </div>

                <div class="dm-r-section is-flow">
                    <div class="dm-r-section-head"><span class="dm-r-mono">02</span><span class="dm-r-section-title"><strong>Ditt påminnelseflöde</strong><small>Gäller både när du skickar själv och med fakturaservice. Upp till tre steg. Inkasso är alltid sista steget.</small></span></div>
                    <div class="dm-r-timeline">
                        <div class="dm-r-tl"><div class="dm-r-tl-rail"><span class="dm-r-tl-start"></span><span class="dm-r-tl-line"></span></div><div class="dm-r-tl-body"><div class="dm-r-tl-first"><span class="dm-r-mono">DAG 0</span><strong>Förfallodatum</strong></div></div></div>
                        ${steps}
                        <div class="dm-r-tl is-end"><div class="dm-r-tl-rail"><span class="dm-r-tl-open"></span></div><div class="dm-r-tl-body">
                            ${canAdd
                                ? `<div class="dm-r-addrow"><button type="button" class="dm-r-add" data-act="addReminder">${svg(ICON.plus, 15, 2.2)}Påminnelse ${rem + 1}</button><button type="button" class="dm-r-add" data-act="addInkasso">${svg(ICON.plus, 15, 2.2)}Inkasso</button></div>`
                                : `<div class="dm-r-endhint">${hasInk ? 'Flödet slutar med inkasso.' : 'Max tre steg. Obetalda fakturor markeras sedan för manuell hantering.'}</div>`}
                        </div></div>
                    </div>
                </div>

                ${auto ? `
                    <div class="dm-r-section">
                        <div class="dm-r-section-head"><span class="dm-r-mono">03</span><span class="dm-r-section-title"><strong>Hur ska fakturaservice användas?</strong><small>Gäller nya kundfakturor. Du kan alltid ändra per kund eller faktura.</small></span></div>
                        <div class="dm-r-twocol is-indent">
                            ${radioCard(s.usage === 'choice', 'usage', 'choice', 'Som ett val', 'Du väljer Oqto fakturaservice när du skickar en faktura.')}
                            ${radioCard(s.usage === 'default', 'usage', 'default', 'Förvalt', 'Alla nya kundfakturor skickas med Oqto fakturaservice. Du kan välja bort det per kund eller faktura.')}
                        </div>
                    </div>` : ''}
            </div>`;
    };

    const view = () => {
        const isList = s.view === 'list';

        return `
            <div class="dm-window dm-rem">
                <div class="dm-topbar">
                    <span class="dm-crumb">${isList ? 'Försäljning' : 'Inställningar'}</span>
                    ${svg(ICON.chevronRight, 12, 2, '#aaa')}
                    <strong>${isList ? 'Förfallna fakturor' : 'Påminnelser'}</strong>
                    <span class="dm-grow"></span>
                    <div class="dm-tabs" role="tablist">
                        <button type="button" role="tab" class="dm-tab${isList ? ' is-on' : ''}" data-act="view" data-arg="list">Förfallna fakturor <span class="dm-tab-count">${INVOICES.length}</span></button>
                        <button type="button" role="tab" class="dm-tab${isList ? '' : ' is-on'}" data-act="view" data-arg="settings">Påminnelser</button>
                    </div>
                </div>
                <div class="dm-r-scroll" data-scroll="rem">${isList ? listView() : settingsView()}</div>
                ${isList ? '' : `
                    <div class="dm-r-savebar">
                        ${s.dirty ? '<span class="dm-r-unsaved"><i></i>Osparade ändringar</span>' : '<span class="dm-r-saved">Alla ändringar sparade</span>'}
                        <span class="dm-grow"></span>
                        <button type="button" class="dm-btn-lg" data-act="undo">Ångra</button>
                        <button type="button" class="dm-btn-lg is-dark" data-act="save">Spara</button>
                    </div>`}
            </div>`;
    };

    const actions = {
        view: (v) => { s.view = v; },
        setMode: (m) => upd(() => { s.mode = m; }),
        include: (v) => upd(() => { s.include = v; }),
        usage: (v) => upd(() => { s.usage = v; }),
        addReminder: () => upd(() => { s.steps = [...s.steps, { id: uid++, type: 'reminder', days: s.steps.length === 0 ? 7 : 14, fee: '60', int: 'ref8' }]; }),
        addInkasso: () => upd(() => { s.steps = [...s.steps, { id: uid++, type: 'inkasso', days: 10, partner: 'kravia', mode: 'suggest' }]; }),
        remove: (id) => upd(() => { s.steps = s.steps.filter((y) => y.id !== Number(id)); }),
        dec: (id) => { const x = s.steps.find((y) => y.id === Number(id)); setStep(x.id, { days: Math.max(1, x.days - 1) }); },
        inc: (id) => { const x = s.steps.find((y) => y.id === Number(id)); setStep(x.id, { days: Math.min(60, x.days + 1) }); },
        save: () => { s.saved = clone({ mode: s.mode, include: s.include, usage: s.usage, steps: s.steps }); s.dirty = false; },
        undo: () => { Object.assign(s, clone(s.saved)); s.dirty = false; },
        bump: (id) => { s.extra[id] = (s.extra[id] || 0) + 1; },
        hand: (id) => { s.handed[id] = true; },
        pause: (id) => { s.paused[id] = true; },
        resume: (id) => { s.paused[id] = false; s.resumed[id] = true; },
        wait: (id) => { s.wait[id] = true; },
    };

    // Åtgärder per steg anropas som "fee:3" med värdet som första och steg-id som tredje argument.
    Object.assign(actions, {
        fee: (v, e, id) => setStep(Number(id), { fee: v }),
        int: (v, e, id) => setStep(Number(id), { int: v }),
        feeAmt: (v, e, id) => setStep(Number(id), { feeAmt: Math.max(0, Number(v) || 0) }),
        intRate: (v, e, id) => setStep(Number(id), { intRate: Math.max(0, Number(v) || 0) }),
        inkMode: (v, e, id) => setStep(Number(id), { mode: v }),
        partner: (v, e, id) => setStep(Number(id), { partner: v }),
    });

    createDemo(root, s, view, actions);
}
