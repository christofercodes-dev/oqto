/*
 * Byråns egen sida (/byrakartan/{slug}): klickräkning hos Byråkartan, som tar emot anropen direkt från
 * webbläsaren.
 *
 *  - Webbplatsen är en vanlig länk (data-track="webbplats"); ett klick räknas med sendBeacon, som aldrig
 *    fördröjer länken.
 *  - Telefon och e-post ligger aldrig i sidan. Knapparna (data-reveal="telefon" | "epost") hämtar uppgiften
 *    först vid klick, så att varje visning räknas och ingen kan kopiera den utan att det syns.
 *
 * Utan data-api (klickräkningen avstängd, eller byrån saknar slug) görs ingenting, och telefon/e-post-knapparna
 * renderas inte alls. Allt som visas sätts med textContent, så inget i svaren tolkas som HTML.
 */

export default function initAgencyPage(root) {

    const api = (root.dataset.api || '').replace(/\/$/, '');
    const slug = root.dataset.slug || '';

    if (!api || !slug) return;

    const source = location.hostname;
    const endpoint = (path) => `${api}/byraer/${encodeURIComponent(slug)}/${path}`;

    const trackClick = (type) => {
        const body = new URLSearchParams({ typ: type, kalla: source });

        try {
            // Enkel begäran (formulärdata): ingen förfrågan i förväg, och den överlever att sidan lämnas.
            if (!navigator.sendBeacon(endpoint('klick'), body)) throw new Error('beacon');
        } catch (e) {
            fetch(endpoint('klick'), { method: 'POST', body, keepalive: true, mode: 'no-cors' }).catch(() => {});
        }
    };

    root.querySelectorAll('[data-track]').forEach((link) => {
        link.addEventListener('click', () => trackClick(link.dataset.track));
    });

    root.querySelectorAll('[data-reveal]').forEach((button) => {

        const kind = button.dataset.reveal;
        const phone = kind === 'telefon';
        const labelNode = button.querySelector('[data-label]');
        const label = labelNode ? labelNode.textContent : '';

        button.addEventListener('click', async () => {
            button.disabled = true;

            try {
                const response = await fetch(endpoint(`visa/${kind}`), {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: new URLSearchParams({ kalla: source }),
                });

                const value = response.ok ? String((await response.json()).varde || '').trim() : '';

                if (!value) throw new Error('empty');

                // Bara rimliga värden blir länkar (tel:/mailto:), annat visas som ren text.
                const valid = phone ? /^[0-9+()\-.\s]{5,30}$/.test(value) : /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(value);

                // Värdet tar knappens plats (med samma ikon), som en länk man kan klicka på för att ringa/skriva.
                const shown = document.createElement(valid ? 'a' : 'span');

                shown.className = 'byra-contact-btn is-revealed';
                shown.append(...[...button.querySelectorAll('svg')].map((icon) => icon.cloneNode(true)));
                shown.append(Object.assign(document.createElement('span'), { textContent: value }));

                if (valid) shown.href = `${phone ? 'tel:' : 'mailto:'}${value.replace(phone ? /[\s().-]/g : /\s/g, '')}`;

                button.replaceWith(shown);
            } catch (e) {
                button.disabled = false;

                if (labelNode) {
                    labelNode.textContent = 'Kunde inte visa just nu, försök igen';
                    setTimeout(() => { labelNode.textContent = label; }, 4000);
                }
            }
        });
    });
}
