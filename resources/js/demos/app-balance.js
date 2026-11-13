import mountApp from './app-shared.js';

/* Banksaldo i appen: startsidan med saldot på bankkontot. */
export default function mount(root) {
    mountApp(root, 'balance');
}
