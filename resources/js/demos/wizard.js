/*
 * Steg-för-steg-guiden för demona (se foretagare/components/feature). Ett steg
 * visas i taget; stegindikatorn, "nästa"/"föregående" och adressfältet
 * (#steg-2) hålls i takt. Knappen "Helskärm" lägger guiden över hela skärmen. Alla demon laddas direkt, så att ett demo behåller
 * sitt läge när man går fram och tillbaka.
 *
 * Ett demo kan låsa nästa steg tills det är gjort: det sätter data-gate="pending"
 * på sitt element (och "done" när det är klart) och skickar händelsen dm:gate.
 * Med dm:next ber demot om att få gå vidare, så att t.ex. en "Klart!"-ruta kan
 * vara knappen till nästa steg.
 */

export default function initWizard(root) {

    const fullButton = root.querySelector('[data-wiz-full]');
    const fullLabel = root.querySelector('[data-wiz-full-label]');

    const steps = [...root.querySelectorAll('[data-wiz-step]')];
    const panels = [...root.querySelectorAll('[data-wiz-panel]')];

    if (!steps.length || steps.length !== panels.length) return;

    const titleOf = (n) => steps[n - 1]?.querySelector('.foretagare-wiz-label')?.textContent.trim() ?? '';

    // Saknas en egen knapptext blir den "Nästa: {nästa rubrik}".
    panels.forEach((panel, i) => {
        const label = panel.querySelector('[data-wiz-next-label]');

        if (label && !panel.dataset.next) label.textContent = `Nästa: ${titleOf(i + 2)}`;
    });

    let current = 1;

    const demoOf = (panel) => panel.querySelector('.dm-demo[data-gate]');

    // Det första steget som måste göras klart innan man kommer vidare (1-baserat), annars null.
    const firstBlocked = () => {
        const i = panels.findIndex((panel) => demoOf(panel)?.dataset.gate === 'pending');

        return i === -1 ? null : i + 1;
    };

    // Låsta steg, och knappar som bara fungerar när steget är gjort.
    const refresh = () => {
        const blocked = firstBlocked();

        steps.forEach((step, i) => {
            const locked = blocked !== null && i + 1 > blocked;

            step.classList.toggle('is-locked', locked);
            step.setAttribute('aria-disabled', locked ? 'true' : 'false');
            step.title = locked ? 'Gör klart föregående steg först' : '';
        });

        // På ett steg som låser nästa är demots egen knapp vägen vidare.
        panels.forEach((panel) => {
            const gated = !!demoOf(panel);
            const next = panel.querySelector('.foretagare-wiz-next');
            const foot = panel.querySelector('.foretagare-wiz-foot');

            if (next) next.hidden = gated;
            if (foot) foot.hidden = [...foot.children].every((el) => el.hidden);
        });
    };

    const show = (n, { scroll = false, hash = true } = {}) => {

        n = Math.min(Math.max(n, 1), steps.length);

        const blocked = firstBlocked();

        if (blocked !== null && n > blocked) n = blocked;

        current = n;

        steps.forEach((step, i) => {
            const on = i + 1 === n;

            step.classList.toggle('is-active', on);
            step.classList.toggle('is-done', i + 1 < n);
            step.setAttribute('aria-selected', String(on));
            step.tabIndex = on ? 0 : -1;
        });

        panels.forEach((panel, i) => { panel.hidden = i + 1 !== n; });

        if (hash) {
            try { history.replaceState(null, '', n === 1 ? location.pathname + location.search : `#steg-${n}`); } catch (e) { /* ingen historik */ }
        }

        if (scroll && root.classList.contains('is-fullscreen')) {
            root.scrollTo({ top: 0, behavior: 'smooth' });
        } else if (scroll) {
            const top = root.getBoundingClientRect().top;

            if (top < 80 || top > window.innerHeight * 0.4) {
                root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    };

    root.addEventListener('click', (event) => {
        const step = event.target.closest('[data-wiz-step]');
        const go = event.target.closest('[data-wiz-go]');

        if (step && root.contains(step)) {
            if (step.getAttribute('aria-disabled') !== 'true') show(Number(step.dataset.wizStep), { scroll: true });
        }
        else if (go && root.contains(go)) show(Number(go.dataset.wizGo), { scroll: true });
    });

    // Demon som låser eller släpper nästa steg, och som ber om att få gå vidare.
    root.addEventListener('dm:gate', refresh);
    root.addEventListener('dm:next', () => show(current + 1, { scroll: true }));

    // Pilknappar flyttar mellan stegen i stegindikatorn.
    root.addEventListener('keydown', (event) => {
        if (!event.target.closest('[data-wiz-step]')) return;

        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault();
            show(current + (event.key === 'ArrowRight' ? 1 : -1));
            steps[current - 1].focus();
        }
    });

    // Helskärm: guiden läggs över hela skärmen (och i webbläsarens egen helskärm där den finns).
    const setFullscreen = (on) => {
        root.classList.toggle('is-fullscreen', on);
        document.documentElement.classList.toggle('foretagare-lock', on);

        if (fullButton) fullButton.setAttribute('aria-pressed', String(on));
        if (fullLabel) fullLabel.textContent = on ? 'Stäng helskärm' : 'Helskärm';

        if (on && root.requestFullscreen && !document.fullscreenElement) {
            Promise.resolve(root.requestFullscreen()).catch(() => { /* faller tillbaka på att täcka sidan */ });
        }

        if (!on && document.fullscreenElement === root && document.exitFullscreen) {
            Promise.resolve(document.exitFullscreen()).catch(() => {});
        }

        root.scrollTop = 0;

        // Demona (t.ex. telefonramen) räknar om sin storlek.
        window.dispatchEvent(new Event('dm:rescale'));
    };

    if (fullButton) fullButton.addEventListener('click', () => setFullscreen(!root.classList.contains('is-fullscreen')));

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && root.classList.contains('is-fullscreen')) setFullscreen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-fullscreen')) setFullscreen(false);
    });

    const fromHash = /^#steg-(\d+)$/.exec(location.hash);

    refresh();

    show(fromHash ? Number(fromHash[1]) : 1, { hash: false });

    if (fromHash) root.scrollIntoView({ block: 'start' });
}
