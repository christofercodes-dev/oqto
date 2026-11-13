import mountApp from './app-shared.js';

/* Signera betalning i appen: markera fakturor och signera med BankID. */
export default function mount(root) {
    mountApp(root, 'sign');
}
