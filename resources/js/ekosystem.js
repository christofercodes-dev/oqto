/*
 * Det interaktiva ekosystemet (se resources/views/ekosystem). Läser JSON från
 * elementet [data-ecosystem-data] och ritar tre vyer av samma flöde som
 * delar steg: ett diagram över vilka system som pratar med varandra, en
 * tidslinje med en bana per system och ett fakturakort som fylls på.
 *
 * Skalet byggs en gång per flöde och vy. Ett byte av steg ändrar bara
 * klasser, så att övergångarna i CSS får animera.
 */

const VIEWS = [
    ['diagram', 'Diagram'],
    ['timeline', 'Tidslinje'],
    ['invoice', 'Följ en faktura'],
];

const PLAY_INTERVAL = 3200;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const edgeKey = (a, b) => [a, b].sort().join('|');

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

    const state = { flow: 0, step: 0, view: 'diagram', timer: null };

    const flow = () => flows[state.flow];
    const step = () => flow().steps[state.step];

    root.classList.add('is-ready');
    root.innerHTML = `
        <div class="eco-app">
            <div class="eco-bar">
                <div class="eco-seg" role="tablist" aria-label="Flöde" data-flows>
                    ${flows.map((f, i) => `<button type="button" role="tab" class="eco-seg-btn" data-flow="${i}">${esc(f.title)}</button>`).join('')}
                </div>
                <div class="eco-seg" role="tablist" aria-label="Visa som" data-views>
                    ${VIEWS.map(([id, label]) => `<button type="button" role="tab" class="eco-seg-btn" data-view="${id}">${label}</button>`).join('')}
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

    function badge(system) {
        return `<span class="eco-badge eco-tone-${system.tone}">${esc(system.letter)}</span>`;
    }

    // Systemen som flödet använder, i den ordning de är definierade.
    function lanes() {
        const used = new Set(flows.flatMap((f) => f.steps.map((s) => s.system)));

        return systems.filter((s) => used.has(s.key));
    }

    // Systemen utan eget läge fördelas jämnt på en ellips.
    function positions() {
        const free = systems.filter((s) => s.x === null || s.y === null);
        const out = {};

        systems.forEach((s) => {
            if (s.x !== null && s.y !== null) out[s.key] = [s.x, s.y];
        });

        free.forEach((s, i) => {
            const angle = (-135 + (360 / free.length) * i) * (Math.PI / 180);
            out[s.key] = [50 + 34 * Math.cos(angle), 50 + 34 * Math.sin(angle)];
        });

        return out;
    }

    function edges() {
        const seen = new Set();

        flows.forEach((f) => f.steps.forEach((s) => {
            if (s.with) seen.add(edgeKey(s.system, s.with));
        }));

        return [...seen];
    }

    function diagramView() {
        const pos = positions();

        const lines = edges().map((key) => {
            const [a, b] = key.split('|');

            if (!pos[a] || !pos[b]) return '';

            return `<line data-edge="${key}" x1="${pos[a][0]}" y1="${pos[a][1]}" x2="${pos[b][0]}" y2="${pos[b][1]}" vector-effect="non-scaling-stroke"></line>`;
        }).join('');

        const nodes = systems.map((s) => `
            <div class="eco-node" data-sys="${esc(s.key)}" style="left:${pos[s.key][0]}%;top:${pos[s.key][1]}%">
                ${badge(s)}
                <span class="eco-node-text">
                    <span class="eco-node-name">${esc(s.name)}</span>
                    ${s.role ? `<span class="eco-node-role">${esc(s.role)}</span>` : ''}
                </span>
            </div>`).join('');

        return `
            <div class="eco-diagram" data-pos='${JSON.stringify(pos)}'>
                <svg class="eco-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">${lines}</svg>
                ${nodes}
                <span class="eco-pulse" data-pulse hidden></span>
                <span class="eco-pill" data-pill></span>
            </div>`;
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

    function invoiceView() {
        const doc = data.document || {};

        const log = flow().steps.map((s, i) => {
            const system = byKey[s.system];

            return `
                <li class="eco-log-item" data-step="${i}">
                    <span class="eco-log-pill eco-tone-${system.tone}">${esc(system.name)}</span>
                    <span class="eco-log-text">${esc(s.log || s.title)}</span>
                </li>`;
        }).join('');

        const chips = lanes().map((s) => `
            <span class="eco-chip eco-tone-${s.tone}" data-chip="${esc(s.key)}">
                ${badge(s)}<span>${esc(s.name)}</span>
            </span>`).join('');

        return `
            <div class="eco-invoice">
                <div class="eco-doc">
                    <div class="eco-doc-head">
                        <div>
                            ${doc.label ? `<p class="eco-doc-label">${esc(doc.label)}</p>` : ''}
                            ${doc.title ? `<p class="eco-doc-title">${esc(doc.title)}</p>` : ''}
                            ${doc.subtitle ? `<p class="eco-doc-sub">${esc(doc.subtitle)}</p>` : ''}
                        </div>
                        <span class="eco-status" data-status></span>
                    </div>
                    ${doc.amount ? `
                        <div class="eco-doc-amount">
                            <span>${esc(doc.amount_label)}</span>
                            <strong>${esc(doc.amount)}</strong>
                        </div>` : ''}
                    <ol class="eco-log">${log}</ol>
                </div>
                <div class="eco-chips">${chips}</div>
            </div>`;
    }

    function renderCanvas() {
        canvas.innerHTML = state.view === 'timeline'
            ? timelineView()
            : state.view === 'invoice' ? invoiceView() : diagramView();
        canvas.dataset.view = state.view;
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

        // Diagrammet: ägaren fylls, motparten får en ring och linjen mellan dem lyser.
        if (state.view === 'diagram') {
            const pos = JSON.parse(canvas.querySelector('.eco-diagram').dataset.pos);

            canvas.querySelectorAll('.eco-node').forEach((node) => {
                node.classList.toggle('is-active', node.dataset.sys === s.system);
                node.classList.toggle('is-with', node.dataset.sys === s.with);
            });

            canvas.querySelectorAll('.eco-lines line').forEach((line) => {
                line.classList.toggle('is-active', !!s.with && line.dataset.edge === edgeKey(s.system, s.with));
            });

            const pulse = canvas.querySelector('[data-pulse]');

            if (s.with && pos[s.system] && pos[s.with]) {
                pulse.hidden = false;
                pulse.style.left = `${(pos[s.system][0] + pos[s.with][0]) / 2}%`;
                pulse.style.top = `${(pos[s.system][1] + pos[s.with][1]) / 2}%`;
            } else {
                pulse.hidden = true;
            }

            canvas.querySelector('[data-pill]').textContent = `Steg ${state.step + 1} av ${total} · ${s.title}`;
        }

        // Tidslinjen kan vara bredare än ytan på smala skärmar: håll aktivt steg i bild.
        if (state.view === 'timeline') {
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

        // Fakturakortet: etiketten följer steget och sista steget blir grönt.
        if (state.view === 'invoice') {
            const status = canvas.querySelector('[data-status]');

            status.textContent = s.status || '';
            status.hidden = !s.status;
            status.classList.toggle('is-final', state.step === total - 1);

            canvas.querySelectorAll('[data-chip]').forEach((chip) => {
                chip.classList.toggle('is-active', chip.dataset.chip === s.system);
            });
        }
    }

    function syncTabs() {
        root.querySelectorAll('[data-flow]').forEach((btn) => {
            const on = Number(btn.dataset.flow) === state.flow;

            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        root.querySelectorAll('[data-view]').forEach((btn) => {
            const on = btn.dataset.view === state.view;

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
        const target = e.target.closest('[data-flow],[data-view],[data-goto],[data-act]');

        if (!target) return;

        if (target.dataset.flow !== undefined) {
            setPlaying(false);
            state.flow = Number(target.dataset.flow);
            state.step = 0;
            render();
        } else if (target.dataset.view !== undefined) {
            state.view = target.dataset.view;
            syncTabs();
            renderCanvas();
            update();
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
