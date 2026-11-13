import mountApp from './app-shared.js';

/* Attestera faktura i appen: markera fakturor och attestera dem. */
export default function mount(root) {
    mountApp(root, 'attest');
}
