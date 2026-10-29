import { createDemo, esc, svg, ICON } from './core.js';

/*
 * Efter flytten: jämför rapporterna mellan det tidigare systemet och Oqto och
 * sätt upp det som inte följer med. Siffrorna är påhittade.
 */

const FIGURES = [
    ['Intäkter', '4 218 600 kr'],
    ['Kostnader', '3 764 150 kr'],
    ['Årets resultat', '454 450 kr'],
    ['Balansomslutning', '2 981 300 kr'],
];

const SETTINGS = [
    ['Räkenskapsår', '2025-01-01 – 2025-12-31'],
    ['Låst period', 'Till och med 2025-09-30'],
    ['Bokföringsmetod', 'Kontantmetoden'],
];

const TODO = [
    ['logo', 'Ladda upp logga', 'Inställningar → Företaget'],
    ['layout', 'Välj fakturalayout', 'Fakturalayout och logga följer inte med'],
    ['company', 'Kontrollera företagsuppgifterna', 'Adress, bankuppgifter och e-post under Inställningar'],
    ['vat', 'Kontrollera momsperioden', 'Månad, kvartal eller år, under Inställningar'],
];

export default function mount(root) {

    const s = { compared: false, done: {} };

    const left = (sys) => `
        <div class="dm-mc-col is-left">
            <div class="dm-mc-head"><strong>${sys}</strong></div>
            ${FIGURES.map(([name, value]) => `<div class="dm-mc-line"><span>${esc(name)}</span><strong>${esc(value)}</strong></div>`).join('')}
            ${SETTINGS.map(([name, value]) => `<div class="dm-mc-line is-sub"><span>${esc(name)}</span><span>${esc(value)}</span></div>`).join('')}
        </div>`;

    const right = () => `
        <div class="dm-mc-col is-right">
            <div class="dm-mc-head"><strong>Oqto</strong></div>
            ${FIGURES.map(([name, value]) => `
                <div class="dm-mc-line${s.compared ? ' is-match' : ''}">
                    <span>${esc(name)}</span>
                    <strong>${s.compared ? `${esc(value)}<span class="dm-mc-ok">${svg(ICON.check, 13, 3)}</span>` : '<span class="dm-mc-dash">–</span>'}</strong>
                </div>`).join('')}
            ${SETTINGS.map(([name, value]) => `
                <div class="dm-mc-line is-sub${s.compared ? ' is-match' : ''}">
                    <span>${esc(name)}</span>
                    <span>${s.compared ? `${esc(value)}<span class="dm-mc-ok">${svg(ICON.check, 13, 3)}</span>` : '<span class="dm-mc-dash">–</span>'}</span>
                </div>`).join('')}
        </div>`;

    const doneCount = () => TODO.filter(([key]) => s.done[key]).length;

    const view = () => `
        <div class="dm-window">
            <div class="dm-topbar">
                <span class="dm-crumb">Klienter</span><span class="dm-crumb">/</span><strong>Kontrollera efter flytten</strong>
                <span class="dm-grow"></span>
                <span class="dm-pill-mono">DEMO</span>
            </div>

            <div class="dm-mg-scroll" data-scroll="mc">
                <div class="dm-mg-page">

                    <div class="dm-mc-top">
                        <p class="dm-mg-note">Jämför balans- och resultatrapporten med ditt tidigare system. De ska stämma exakt.</p>
                        <button type="button" class="dm-mg-go${s.compared ? ' is-ghost' : ''}" data-act="compare">${s.compared ? 'Dölj jämförelsen' : 'Jämför rapporterna'}${s.compared ? '' : `<span class="dm-mg-go-arrow">${svg(ICON.chevronRight, 16, 2.6)}</span>`}</button>
                    </div>

                    <div class="dm-mc-board">
                        ${left('Ditt befintliga system')}
                        <div class="dm-mc-mid" aria-hidden="true">${svg(ICON.chevronRight, 20, 2.4)}</div>
                        ${right()}
                    </div>

                    ${s.compared ? `<div class="dm-mg-banner">${svg(ICON.check, 18, 3)}<div><strong>Det stämmer.</strong> Siffrorna är identiska i båda systemen.</div></div>` : ''}

                    <div class="dm-mc-todo">
                        <div class="dm-between">
                            <span class="dm-label">SÄTT UPP DET SOM INTE FÖLJER MED</span>
                            <span class="dm-mono-sub">${doneCount()} av ${TODO.length}</span>
                        </div>
                        ${TODO.map(([key, title, text]) => `
                            <button type="button" class="dm-mc-task${s.done[key] ? ' is-done' : ''}" data-act="task" data-arg="${key}" role="checkbox" aria-checked="${s.done[key] ? 'true' : 'false'}">
                                <span class="dm-mg-check${s.done[key] ? ' is-on' : ''}" aria-hidden="true">${s.done[key] ? svg(ICON.check, 13, 3) : ''}</span>
                                <span class="dm-mg-text"><span class="dm-mg-name">${esc(title)}</span><span class="dm-mg-hint">${esc(text)}</span></span>
                            </button>`).join('')}
                    </div>

                </div>
            </div>
        </div>`;

    createDemo(root, s, view, {
        compare: () => { s.compared = !s.compared; },
        task: (key) => { s.done[key] = !s.done[key]; },
    });
}
