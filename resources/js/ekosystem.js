/*
 * Det interaktiva ekosystemet (se resources/views/ekosystem). Läser JSON från
 * elementet [data-ecosystem-data] och ritar flödet som en tidslinje med en
 * bana per system.
 *
 * Skalet byggs en gång per flöde. Ett byte av steg ändrar bara klasser, så att
 * övergångarna i CSS får animera.
 */

const PLAY_INTERVAL = 3200;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

export default function ecosystem(root) {
    const dataEl = root.querySelector('[data-ecosystem-data]');
    let data;

    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        return;
    }

    const flows = data.flows || [];

    if (!flows.length) return;

    const systems = (data.systems || []).filter((s) => s.used);
    const byKey = Object.fromEntries(systems.map((s) => [s.key, s]));

    const state = { flow: 0, step: 0, timer: null };

    const flow = () => flows[state.flow];
    const step = () => flow().steps[state.step];

    root.classList.add('is-ready');
    root.innerHTML = `
        <div class="eco-app">
            <div class="eco-bar">
                <div class="eco-seg" role="tablist" aria-label="Flöde" data-flows>
                    ${flows.map((f, i) => `<button type="button" role="tab" class="eco-seg-btn" data-flow="${i}">${esc(f.title)}</button>`).join('')}
                </div>
            </div>

            <div class="eco-main">
                <ol class="eco-steps" data-steps></ol>
                <div class="eco-canvas" data-canvas></div>
            </div>

            <div class="eco-controls">
                <button type="button" class="eco-ctl" data-act="prev" aria-label="Föregående steg">←</button>
                <span class="eco-count" data-count></span>
                <button type="button" class="eco-ctl" data-act="next" aria-label="Nästa steg">→</button>
                <button type="button" class="integration-activate-button eco-play" data-act="play" aria-pressed="false">
                    <span data-play-label>Spela upp</span>
                    <span class="integration-activate-arrow" data-play-icon aria-hidden="true">▶</span>
                </button>
            </div>

            <p class="eco-sr" aria-live="polite" data-live></p>
        </div>`;

    const canvas = root.querySelector('[data-canvas]');
    const stepsEl = root.querySelector('[data-steps]');

    /* ---------- Vyer ---------- */

    // Märket för ett system: logga, ikon eller (som sista utväg) bokstaven.
    function badge(system) {
        const tone = `eco-tone-${system.tone}`;

        if (system.logo) {
            return `<span class="eco-badge has-logo fit-${system.logo_fit === 'contain' ? 'contain' : 'cover'}" data-letter="${esc(system.letter)}" data-tone="${esc(system.tone)}"><img src="${esc(system.logo)}" alt="" loading="lazy"></span>`;
        }

        if (system.icon_svg) {
            // SVG:erna kommer från en fast lista på servern (EcosystemCatalog::ICONS).
            return `<span class="eco-badge has-icon ${tone}">${system.icon_svg}</span>`;
        }

        return `<span class="eco-badge ${tone}">${esc(system.letter)}</span>`;
    }

    // Systemen som flödet använder, i den ordning de är definierade.
    function lanes() {
        const used = new Set(flows.flatMap((f) => f.steps.map((s) => s.system)));

        return systems.filter((s) => used.has(s.key));
    }

    function timelineView() {
        const laneList = lanes();
        const cols = flow().steps.length;

        const header = flow().steps.map((s, i) => `
            <div class="eco-tl-cell">
                <div class="eco-tl-head" data-step="${i}">
                    <span class="eco-tl-num">${s.n}</span>
                    ${i < cols - 1 ? '<span class="eco-tl-line"></span>' : ''}
                </div>
            </div>`).join('');

        const rows = laneList.map((lane) => {
            const cells = flow().steps.map((s, i) => `
                <div class="eco-tl-cell">
                    ${s.system === lane.key ? `
                        <button type="button" class="eco-tl-card" data-step="${i}" data-goto="${i}">
                            <span class="eco-tl-card-title">${esc(s.title)}</span>
                            <span class="eco-tl-card-text">${esc(s.short)}</span>
                        </button>` : ''}
                </div>`).join('');

            return `
                <div class="eco-tl-row">
                    <div class="eco-tl-label">
                        ${badge(lane)}
                        <span class="eco-tl-label-text">
                            <span class="eco-tl-label-name">${esc(lane.name)}</span>
                            ${lane.role ? `<span class="eco-tl-label-role">${esc(lane.role)}</span>` : ''}
                        </span>
                    </div>
                    ${cells}
                </div>`;
        }).join('');

        return `
            <div class="eco-timeline">
                <div class="eco-tl" style="--cols:${cols}">
                    <div class="eco-tl-row eco-tl-row-head"><div class="eco-tl-label"></div>${header}</div>
                    ${rows}
                </div>
            </div>`;
    }

    function renderCanvas() {
        canvas.innerHTML = timelineView();
    }

    function renderSteps() {
        stepsEl.innerHTML = flow().steps.map((s, i) => {
            const system = byKey[s.system];

            return `
                <li class="eco-step" data-step="${i}">
                    <button type="button" class="eco-step-btn" data-goto="${i}">
                        <span class="eco-step-num">${s.n}</span>
                        <span class="eco-step-body">
                            <span class="eco-step-title">${esc(s.title)}</span>
                            <span class="eco-step-detail">
                                <span class="eco-step-sys">${esc(system.name)}${system.role ? ` · ${esc(system.role)}` : ''}</span>
                                ${s.text ? `<span class="eco-step-text">${esc(s.text)}</span>` : ''}
                            </span>
                        </span>
                    </button>
                </li>`;
        }).join('');
    }

    /* ---------- Uppdatering vid stegbyte ---------- */

    function mark(selector) {
        root.querySelectorAll(selector).forEach((el) => {
            const i = Number(el.dataset.step);

            el.classList.toggle('is-done', i < state.step);
            el.classList.toggle('is-active', i === state.step);
            el.classList.toggle('is-todo', i > state.step);

            if (el.matches('button')) {
                if (i === state.step) el.setAttribute('aria-current', 'step');
                else el.removeAttribute('aria-current');
            }
        });
    }

    function update() {
        const s = step();
        const total = flow().steps.length;

        mark('[data-step]');

        root.querySelector('[data-count]').textContent = `Steg ${state.step + 1} av ${total}`;
        root.querySelector('[data-act="prev"]').disabled = state.step === 0;
        root.querySelector('[data-act="next"]').disabled = state.step === total - 1;
        root.querySelector('[data-live]').textContent = `Steg ${state.step + 1} av ${total}: ${s.title}. ${s.text}`;

        // Tidslinjen kan vara bredare än ytan på smala skärmar: håll aktivt steg i bild.
        const scroller = canvas.querySelector('.eco-timeline');
        const head = canvas.querySelector('.eco-tl-head.is-active');

        if (scroller && head && scroller.scrollWidth > scroller.clientWidth) {
            // Banornas namn ligger fast till vänster, så steget ska landa precis efter dem.
            const label = canvas.querySelector('.eco-tl-label');
            const offset = head.getBoundingClientRect().left - scroller.getBoundingClientRect().left;
            const left = scroller.scrollLeft + offset - (label ? label.offsetWidth : 0) - 12;

            scroller.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
        }
    }

    function syncTabs() {
        root.querySelectorAll('[data-flow]').forEach((btn) => {
            const on = Number(btn.dataset.flow) === state.flow;

            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function render() {
        syncTabs();
        renderSteps();
        renderCanvas();
        update();
    }

    /* ---------- Uppspelning ---------- */

    function setPlaying(on) {
        clearInterval(state.timer);
        state.timer = null;

        if (on) {
            state.timer = setInterval(() => {
                if (state.step >= flow().steps.length - 1) {
                    setPlaying(false);

                    return;
                }

                state.step += 1;
                update();
            }, PLAY_INTERVAL);
        }

        const btn = root.querySelector('[data-act="play"]');

        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.querySelector('[data-play-icon]').textContent = on ? '❚❚' : '▶';
        btn.querySelector('[data-play-label]').textContent = on ? 'Pausa' : 'Spela upp';
    }

    function go(index) {
        const max = flow().steps.length - 1;

        state.step = Math.max(0, Math.min(max, index));
        update();
    }

    /* ---------- Händelser ---------- */

    root.addEventListener('click', (e) => {
        const target = e.target.closest('[data-flow],[data-goto],[data-act]');

        if (!target) return;

        if (target.dataset.flow !== undefined) {
            setPlaying(false);
            state.flow = Number(target.dataset.flow);
            state.step = 0;
            render();
        } else if (target.dataset.goto !== undefined) {
            setPlaying(false);
            go(Number(target.dataset.goto));
        } else if (target.dataset.act === 'prev') {
            setPlaying(false);
            go(state.step - 1);
        } else if (target.dataset.act === 'next') {
            setPlaying(false);
            go(state.step + 1);
        } else if (target.dataset.act === 'play') {
            if (!state.timer && state.step >= flow().steps.length - 1) go(0);

            setPlaying(!state.timer);
        }
    });

    // En logga som inte går att hämta ersätts av bokstaven.
    root.addEventListener('error', (e) => {
        const img = e.target;

        if (!(img instanceof HTMLImageElement) || !img.closest('.eco-badge')) return;

        const holder = img.parentElement;

        holder.classList.remove('has-logo', 'fit-contain', 'fit-cover');
        holder.classList.add(`eco-tone-${holder.dataset.tone || 'gray'}`);
        holder.textContent = holder.dataset.letter || '';
    }, true);

    root.addEventListener('keydown', (e) => {
        if (e.target.closest('[role="tablist"]') || e.altKey || e.ctrlKey || e.metaKey) return;

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            setPlaying(false);
            go(state.step + 1);
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            setPlaying(false);
            go(state.step - 1);
        }
    });

    render();
}
