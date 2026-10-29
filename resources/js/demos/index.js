/*
 * Startar de interaktiva demona (se resources/views/foretagare/demos). Varje
 * demo har sin egen modul som laddas först när ett element med
 * data-demo="namn" finns på sidan.
 */

const modules = {
    'invoice-desktop': () => import('./invoice-desktop.js'),
    'invoice-mobile': () => import('./invoice-mobile.js'),
    reminders: () => import('./reminders.js'),
};

document.querySelectorAll('[data-demo]').forEach((root) => {
    const load = modules[root.dataset.demo];

    if (!load || root.dataset.demoReady) return;

    root.dataset.demoReady = '1';

    load().then((module) => module.default(root));
});

document.querySelectorAll('[data-demo-wizard]').forEach((root) => {
    import('./wizard.js').then((module) => module.default(root));
});
