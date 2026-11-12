import { createDemo, esc, svg, ICON, addDays } from './core.js';
import { ICONS, taxPage, topbar, toastHtml, scaled, animateOnMobile, money, today } from './tax-shared.js';

/*
 * Skattekonto: saldo och transaktioner, med två saker att prova.
 *  - Fyll på kontot: ett betaluppdrag till skattekontot, och beräknat saldo ökar.
 *  - Autopilot: "Ja, skapa automation" öppnar automationen och Spara slår på den.
 * Allt är påhittat och inget skickas. Knapparna som går att klicka pulserar
 * tills de har använts, så att man ser var man ska börja.
 */

const PLUS = 'M12 5v14M5 12h14';

const MONTH_NAMES = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
const WEEKDAYS = ['Må', 'Ti', 'On', 'To', 'Fr', 'Lö', 'Sö'];

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const monthOf = (isoDate) => ({ y: Number(isoDate.slice(0, 4)), m: Number(isoDate.slice(5, 7)) - 1 });

// Sex veckor som börjar på måndag, med dagarna från grannmånaderna i grått.
const calendarDays = ({ y, m }) => {
    const first = new Date(y, m, 1, 12);
    const start = new Date(first);

    start.setDate(1 - ((first.getDay() + 6) % 7));

    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start);

        d.setDate(start.getDate() + i);

        return { iso: iso(d), day: d.getDate(), other: d.getMonth() !== m };
    });
};

export default function mount(root) {

    const s = {
        screen: 'page',   // 'page' | 'editor'
        modal: null,      // 'fill' | 'ask' | 'linked' | 'settings'
        seenFill: false,
        seenAuto: false,
        amount: '200 000,00',
        date: null,        // vald dag (ÅÅÅÅ-MM-DD), annars "Snarast"
        popup: null,       // 'calendar' | 'reference'
        cal: monthOf(today),
        refType: 'ocr',    // 'ocr' | 'msg'
        refMsg: '',
        extra: 0,
        flash: false,
        press: null,
        autopilot: false,
        minBalance: 0,
        minDraft: '0',
        toast: null,
        animated: false,
    };

    let demo;
    let toastTimer;

    const showToast = (title, text) => {
        s.toast = { title, text };
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { s.toast = null; s.flash = false; demo.render(); }, 3400);
    };

    const pressed = (key) => (s.press === key ? ' is-pressed' : '');

    const amountValue = () => parseFloat(s.amount.replace(/\s/g, '').replace(',', '.')) || 0;

    const calendar = () => {
        const days = calendarDays(s.cal);

        return `
            <div class="dm-tax-cal" role="dialog" aria-label="Välj utförandedatum">
                <div class="dm-tax-cal-head">
                    <button type="button" data-act="calPrev" aria-label="Föregående månad">${svg(ICON.chevronLeft, 18, 2.4)}</button>
                    <strong>${MONTH_NAMES[s.cal.m]} ${s.cal.y}</strong>
                    <button type="button" data-act="calNext" aria-label="Nästa månad">${svg(ICON.chevronRight, 18, 2.4)}</button>
                </div>
                <div class="dm-tax-cal-quick">
                    <button type="button" data-act="calPick" data-arg="${today}">Idag</button>
                    <button type="button" data-act="calPick" data-arg="${iso(new Date(s.cal.y, s.cal.m, 1, 12))}">Första</button>
                    <button type="button" data-act="calPick" data-arg="${iso(new Date(s.cal.y, s.cal.m + 1, 0, 12))}">Sista</button>
                </div>
                <div class="dm-tax-cal-weekdays">${WEEKDAYS.map((d) => `<span>${d}</span>`).join('')}</div>
                <div class="dm-tax-cal-grid">
                    ${days.map((d) => `<button type="button" class="${d.other ? 'is-other' : ''}${d.iso === today ? ' is-today' : ''}${d.iso === s.date ? ' is-selected' : ''}${pressed(`day-${d.iso}`)}" data-act="calPick" data-arg="${d.iso}">${d.day}</button>`).join('')}
                </div>
            </div>`;
    };

    const fillModal = () => `
        <div class="dm-pay-scrim${s.popup ? ' has-popup' : ''}">
            <div class="dm-pay-modal dm-tax-modal" role="dialog" aria-modal="true" aria-label="Betaluppdrag">
                ${s.popup ? '<button type="button" class="dm-tax-backdrop" data-act="popClose" tabindex="-1" aria-hidden="true"></button>' : ''}
                <button type="button" class="dm-pay-modal-x" data-act="close" aria-label="Stäng">${svg(ICONS.close, 18, 2)}</button>
                <h3>Betaluppdrag</h3>
                <div class="dm-tax-form">
                    <div class="dm-tax-field">
                        <span class="dm-tax-field-label">Mottagare</span>
                        <span class="dm-tax-input is-select"><span>Skattekonto</span><span class="dm-tax-tag">BG</span>${svg(ICON.chevronDown, 16, 2.4)}</span>
                    </div>
                    <div class="dm-tax-field is-anchor${s.popup === 'calendar' ? ' is-open' : ''}">
                        <span class="dm-tax-field-label">Utförandedatum<span class="dm-tax-info" aria-hidden="true">i</span></span>
                        <button type="button" class="dm-tax-input is-select is-button${s.popup === 'calendar' ? ' is-focus' : ''}${pressed('date')}" data-act="toggleCal" aria-haspopup="dialog" aria-expanded="${s.popup === 'calendar' ? 'true' : 'false'}"><span class="${s.date ? '' : 'dm-tax-placeholder'}">${esc(s.date || 'Snarast')}</span>${svg(ICONS.calendar, 18, 1.8)}</button>
                        ${s.popup === 'calendar' ? calendar() : ''}
                    </div>
                    <div class="dm-tax-field">
                        <span class="dm-tax-field-label">Belopp</span>
                        <span class="dm-tax-input is-amount"><span class="dm-tax-currency">SEK</span><input type="text" inputmode="decimal" value="${esc(s.amount)}" placeholder="0,00" data-key="amount" data-input="amount" aria-label="Belopp i SEK"></span>
                    </div>
                    <div class="dm-tax-field is-anchor${s.popup === 'reference' ? ' is-open' : ''}">
                        <span class="dm-tax-field-label">Referens</span>
                        <span class="dm-tax-input is-ref">
                            <button type="button" class="dm-tax-ref-type${s.popup === 'reference' ? ' is-focus' : ''}" data-act="toggleRef" aria-haspopup="listbox" aria-expanded="${s.popup === 'reference' ? 'true' : 'false'}">${s.refType === 'ocr' ? 'OCR' : 'Meddelande'}${svg(ICON.chevronDown, 16, 2.4)}</button>
                            ${s.refType === 'ocr'
                                ? '<span class="dm-tax-ref-value">556677889926</span>'
                                : `<input class="dm-tax-ref-input" type="text" value="${esc(s.refMsg)}" placeholder="Skriv ett meddelande" data-key="refmsg" data-input="refMsg" aria-label="Meddelande">`}
                        </span>
                        ${s.popup === 'reference' ? `
                            <div class="dm-tax-menu" role="listbox">
                                <button type="button" role="option" aria-selected="${s.refType === 'ocr'}" data-act="setRef" data-arg="ocr"><span>${s.refType === 'ocr' ? svg(ICON.check, 15, 2.6) : ''}</span>OCR</button>
                                <button type="button" role="option" aria-selected="${s.refType === 'msg'}" data-act="setRef" data-arg="msg"><span>${s.refType === 'msg' ? svg(ICON.check, 15, 2.6) : ''}</span>Meddelande</button>
                            </div>` : ''}
                    </div>
                </div>
                <div class="dm-pay-modal-foot">
                    <button type="button" class="dm-pay-btn is-dark${pressed('fillsave')}${amountValue() > 0 ? '' : ' is-dim'}" data-act="fillSave"${amountValue() > 0 ? '' : ' disabled'}>Spara</button>
                </div>
            </div>
        </div>`;

    const ask = () => `
        <div class="dm-pay-scrim">
            <div class="dm-pay-modal dm-ae-modal" role="dialog" aria-modal="true" aria-label="Fyll på skattekonto automatiskt">
                <button type="button" class="dm-pay-modal-x" data-act="close" aria-label="Stäng">${svg(ICONS.close, 18, 2)}</button>
                <h3>Fyll på skattekonto automatiskt</h3>
                <div class="dm-ae-ask">
                    <span class="dm-ae-robot"><span>${svg(ICONS.robot, 30, 1.8)}</span></span>
                    <p>Vi kan automatiskt skapa betaluppdrag för att fylla på skattekontot när saldot riskerar att snart bli för lågt. Vill du att vi skapar en automation åt dig?</p>
                </div>
                <div class="dm-pay-modal-foot">
                    <button type="button" class="dm-pay-btn is-dark${pressed('yes')}" data-act="yes">Ja, skapa automation</button>
                </div>
            </div>
        </div>`;

    const linked = () => `
        <div class="dm-pay-scrim">
            <div class="dm-pay-modal dm-ae-modal" role="dialog" aria-modal="true" aria-label="Fyll på skattekonto automatiskt">
                <button type="button" class="dm-pay-modal-x" data-act="close" aria-label="Stäng">${svg(ICONS.close, 18, 2)}</button>
                <h3>Fyll på skattekonto automatiskt</h3>
                <div class="dm-ae-ask">
                    <span class="dm-ae-robot"><span>${svg(ICONS.robot, 30, 1.8)}</span></span>
                    <div class="dm-ae-linked"><span>Följande automation är kopplad:</span><strong>Skattekonto - Fyll på</strong></div>
                </div>
                <div class="dm-pay-modal-foot">
                    <button type="button" class="dm-pay-btn" data-act="goto">Gå till automation</button>
                </div>
            </div>
        </div>`;

    const settings = () => `
        <div class="dm-pay-scrim">
            <div class="dm-pay-modal dm-ae-modal is-small" role="dialog" aria-modal="true" aria-label="Fyll på skattekonto">
                <button type="button" class="dm-pay-modal-x" data-act="settingsClose" aria-label="Stäng">${svg(ICONS.close, 18, 2)}</button>
                <h3>Fyll på skattekonto</h3>
                <div class="dm-ae-setting">
                    <div class="dm-ae-setting-text"><strong>Minsta saldo</strong><span>Ange i kronor om du vill ha en buffert</span></div>
                    <input class="dm-ae-setting-input" type="text" inputmode="numeric" value="${esc(s.minDraft)}" data-key="min" data-input="min" aria-label="Minsta saldo i kronor">
                </div>
                <div class="dm-pay-modal-foot">
                    <button type="button" class="dm-pay-btn" data-act="settingsClose">Avbryt</button>
                    <button type="button" class="dm-pay-btn is-dark${pressed('ok')}" data-act="settingsOk">OK</button>
                </div>
            </div>
        </div>`;

    const editor = () => `
        <div class="dm-ae">
            <div class="dm-ae-canvas">
                <span class="dm-ae-pill">${svg(ICONS.bell, 14, 1.8)}NÄR</span>
                <div class="dm-ae-node dm-ae-trigger">
                    <span class="dm-ae-select">${svg(ICONS.bolt, 16, 1.8)}<span>Skattekonto - Saldo uppdaterat</span>${svg(ICON.chevronDown, 15, 2)}</span>
                    <span class="dm-ae-hint">Körs efter saldo har uppdaterats från Skatteverket</span>
                </div>
                <span class="dm-ae-pill">${svg(ICONS.wand, 14, 1.8)}DÅ</span>
                <div class="dm-ae-actions">
                    <div class="dm-ae-action">
                        <div class="dm-ae-action-head">
                            <strong>Fyll på skattekonto</strong>
                            ${s.minBalance > 0 ? `<span class="dm-ae-chip">Minsta saldo ${money(s.minBalance)}</span>` : ''}
                            <span class="dm-grow"></span>
                            <button type="button" class="dm-ae-iconbtn${pressed('gear')}" data-act="gear" aria-label="Inställningar för åtgärden">${svg(ICONS.gear, 18, 1.8)}</button>
                            <span class="dm-ae-iconbtn" aria-hidden="true">${svg(ICONS.close, 18, 2)}</span>
                        </div>
                        <span class="dm-ae-hint">Kräver modul Bank &amp; Skatt<br>De inställningar som används kan ändras under skattekonto-&gt;inställningar.</span>
                    </div>
                    <span class="dm-ae-add">${svg(PLUS, 16, 2)}Sen</span>
                </div>
            </div>

            <div class="dm-ae-save">
                <button type="button" class="dm-pay-btn is-dark dm-pulse is-pulsing${pressed('save')}" data-act="save">Spara</button>
            </div>
        </div>`;

    const windowView = () => `
        <div class="dm-window is-pay is-tax${s.screen === 'editor' ? ' is-editor' : ''}">
            ${topbar(s.screen === 'editor' ? 'Automation' : 'Skattekonto')}
            ${s.screen === 'editor'
                ? editor()
                : taxPage({
                    fill: { pulsing: !s.seenFill, pressed: s.press === 'fill' },
                    auto: { on: s.autopilot, interactive: true, pulsing: !s.seenAuto && !s.autopilot, pressed: s.press === 'auto' },
                    extra: s.extra,
                    flashEstimate: s.flash,
                })}
            ${s.modal === 'fill' ? fillModal() : ''}
            ${s.modal === 'ask' ? ask() : ''}
            ${s.modal === 'linked' ? linked() : ''}
            ${s.modal === 'settings' ? settings() : ''}
            ${toastHtml(s.toast)}
        </div>`;

    const applyMin = () => {
        const value = parseFloat(String(s.minDraft).replace(/\s/g, '').replace(',', '.'));

        s.minBalance = Number.isFinite(value) && value > 0 ? value : 0;
    };

    const saveFill = () => {
        s.extra += amountValue();
        s.modal = null;
        s.popup = null;
        s.press = null;
        s.flash = true;
        showToast('Betaluppdrag', 'Betaluppdrag skapades');
    };

    const save = () => {
        s.autopilot = true;
        s.screen = 'page';
        s.press = null;
        showToast('Automationen sparades', 'Autopiloten fyller på skattekontot när saldot riskerar att bli för lågt.');
    };

    demo = createDemo(root, s, () => scaled(s.animated, windowView()), {
        fill: () => { s.seenFill = true; s.modal = 'fill'; s.popup = null; },
        amount: (value) => { s.amount = value; },
        refMsg: (value) => { s.refMsg = value; },
        popClose: () => { s.popup = null; },
        toggleCal: () => {
            s.popup = s.popup === 'calendar' ? null : 'calendar';
            s.cal = monthOf(s.date || today);
        },
        toggleRef: () => { s.popup = s.popup === 'reference' ? null : 'reference'; },
        setRef: (type) => { s.refType = type === 'msg' ? 'msg' : 'ocr'; s.popup = null; },
        calPrev: () => { s.cal = s.cal.m === 0 ? { y: s.cal.y - 1, m: 11 } : { y: s.cal.y, m: s.cal.m - 1 }; },
        calNext: () => { s.cal = s.cal.m === 11 ? { y: s.cal.y + 1, m: 0 } : { y: s.cal.y, m: s.cal.m + 1 }; },
        calPick: (value) => { s.date = value; s.cal = monthOf(value); s.popup = null; },
        fillSave: () => { if (amountValue() > 0) saveFill(); },
        // Är autopiloten redan på stängs den inte av här: man får gå till automationen.
        autopilot: () => {
            s.seenAuto = true;
            s.modal = s.autopilot ? 'linked' : 'ask';
        },
        goto: () => { s.modal = null; s.screen = 'editor'; },
        close: () => { s.modal = null; s.popup = null; },
        yes: () => { s.modal = null; s.screen = 'editor'; },
        gear: () => { s.minDraft = s.minBalance > 0 ? String(s.minBalance) : '0'; s.modal = 'settings'; },
        settingsClose: () => { s.modal = null; },
        settingsOk: () => { applyMin(); s.modal = null; },
        min: (value) => { s.minDraft = value; },
        save,
    });

    animateOnMobile(root, s, demo, {
        reset: () => {
            clearTimeout(toastTimer);
            Object.assign(s, { screen: 'page', modal: null, seenFill: false, seenAuto: false, press: null, autopilot: false, minBalance: 0, minDraft: '0', amount: '200 000,00', date: null, popup: null, cal: monthOf(today), refType: 'ocr', refMsg: '', extra: 0, flash: false, toast: null });
        },
        script: [
            [1800, () => { s.press = 'fill'; }],
            [2300, () => { s.press = null; s.seenFill = true; s.modal = 'fill'; }],
            [3300, () => { s.press = 'date'; }],
            [3700, () => { s.press = null; s.popup = 'calendar'; s.cal = monthOf(today); }],
            [4900, () => { s.press = `day-${addDays(today, 5)}`; }],
            [5400, () => { s.press = null; s.date = addDays(today, 5); s.cal = monthOf(s.date); s.popup = null; }],
            [6600, () => { s.press = 'fillsave'; }],
            [7100, () => saveFill()],
            [9000, () => { s.press = 'auto'; }],
            [9500, () => { s.press = null; s.seenAuto = true; s.modal = 'ask'; }],
            [11800, () => { s.press = 'yes'; }],
            [12300, () => { s.press = null; s.modal = null; s.screen = 'editor'; }],
            [13600, () => { s.press = 'gear'; }],
            [14100, () => { s.press = null; s.modal = 'settings'; }],
            [14800, () => { s.minDraft = '2000'; }],
            [16000, () => { s.press = 'ok'; }],
            [16500, () => { s.press = null; applyMin(); s.modal = null; }],
            [18000, () => { s.press = 'save'; }],
            [18500, () => save()],
        ],
        loopAt: 22600,
    });
}
