/*
 * Meddelandet till byrån på /for-foretagare: en färdig text som man kan ändra
 * direkt i rutan och kopiera. Vi vet inte vilken byrå det gäller, så inget
 * skickas härifrån.
 */

const LINK = 'https://oqto.se/for-foretagare';

const MESSAGE = [
    'Hej!',
    '',
    `Jag skulle gärna vilja börja använda Oqto. Oqto är ett ekonomisystem som redovisningsbyråer arbetar i, och jag har läst om vad det kan göra för oss som kunder: ${LINK}`,
    '',
    'Kan ni titta på om vi kan komma igång? Hör gärna av er så pratar vi om hur vi gör.',
    '',
    'Vänliga hälsningar',
    '',
].join('\n');

export default function foretagareMessage(root) {
    const text = root.querySelector('[data-message-text]');
    const copy = root.querySelector('[data-message-copy]');
    const label = root.querySelector('[data-message-copy-label]');
    const status = root.querySelector('[data-message-status]');

    if (!text || !copy) return;

    let timer;

    const say = (message) => {
        if (!status) return;

        status.textContent = message;
        clearTimeout(timer);
        timer = setTimeout(() => { status.textContent = ''; }, 3500);
    };

    text.value = MESSAGE;

    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(text.value);
        } catch (e) {
            // Äldre webbläsare: markera texten så att man kan kopiera själv.
            text.focus();
            text.select();

            try { document.execCommand('copy'); } catch (err) { say('Markera texten och kopiera den.'); return; }
        }

        if (label) {
            label.textContent = 'Kopierat';
            setTimeout(() => { label.textContent = 'Kopiera meddelandet'; }, 2200);
        }

        say('Meddelandet är kopierat.');
    });

    // Har du en byrå eller inte.
    root.querySelectorAll('[data-message-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.dataset.messageMode;

            root.querySelectorAll('[data-message-mode]').forEach((b) => {
                const on = b === button;

                b.classList.toggle('is-on', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            root.querySelectorAll('[data-message-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.messagePanel !== mode;
            });
        });
    });
}
