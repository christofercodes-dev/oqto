/*
 * Flytta till Oqto (support/components/move-chooser): ett val i tre steg. Alla
 * vyer finns redan i sidan; skriptet visar en i taget och håller adressen
 * (#fortnox, #fortnox-integration) i takt, så att bakåtknappen och länkar
 * fungerar. Utan JavaScript visas allt under varandra.
 */

export default function initMove(root) {

    const views = [...root.querySelectorAll('[data-move-view]')];
    const initial = root.dataset.moveInitial || 'system';

    const find = (name) => views.find((view) => view.dataset.moveView === name);

    const show = (name, { focus = false } = {}) => {

        const target = find(name) ? name : initial;

        views.forEach((view) => { view.hidden = view.dataset.moveView !== target; });

        if (focus) {
            const heading = find(target)?.querySelector('.move-heading');

            heading?.focus({ preventScroll: true });
            root.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    const fromHash = () => decodeURIComponent(location.hash.replace(/^#/, ''));

    root.addEventListener('click', (event) => {
        const go = event.target.closest('[data-move-go]');

        if (!go || !root.contains(go)) return;

        const name = go.dataset.moveGo;

        try {
            history.pushState(null, '', name === initial ? location.pathname + location.search : `#${name}`);
        } catch (e) { /* ingen historik */ }

        show(name, { focus: true });
    });

    window.addEventListener('popstate', () => show(fromHash() || initial));
    window.addEventListener('hashchange', () => show(fromHash() || initial));

    show(fromHash() || initial);
}
