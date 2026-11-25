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

/*
 * Svenska telefonnummer skrivs läsbart ("073-683 49 64", "08-123 45 67", "0480-123 45") i stället för som
 * en lång rad siffror. Numret kan komma som +46736834964, 0736 83 49 64 eller 0046 73 ...; det visas på
 * svenskt vis och ringlänken får det internationella formatet (+46...). Utländska och oväntade nummer
 * visas som de är.
 *
 * @returns {{ text: string, dial: string }}
 */
export function formatPhone(raw) {

    const clean = String(raw).trim();
    let digits = clean.replace(/[^\d+]/g, '');

    // +46 (0)73... / 0046 73... / +46 73... -> 073...
    digits = digits.replace(/^(\+|00)46(0)?/, '0');

    const dial = digits.startsWith('0') && digits !== clean.replace(/[^\d+]/g, '')
        ? `+46${digits.slice(1)}`
        : clean.replace(/[^\d+]/g, '');

    if (!/^0\d{6,10}$/.test(digits)) return { text: clean, dial };

    // Områdeskoder med två siffror efter nollan (0xx); övriga landsändar-koder har tre (0xxx). 08 är Stockholm.
    const THREE = ['011', '013', '016', '018', '019', '021', '023', '026', '031', '033', '035', '036', '040', '042', '044', '046', '054', '060', '063', '090'];

    const group = (rest) => {
        switch (rest.length) {
            case 4: return `${rest.slice(0, 2)} ${rest.slice(2)}`;
            case 5: return `${rest.slice(0, 3)} ${rest.slice(3)}`;
            case 6: return `${rest.slice(0, 2)} ${rest.slice(2, 4)} ${rest.slice(4)}`;
            case 7: return `${rest.slice(0, 3)} ${rest.slice(3, 5)} ${rest.slice(5)}`;
            case 8: return `${rest.slice(0, 3)} ${rest.slice(3, 6)} ${rest.slice(6)}`;
            default: return rest;
        }
    };

    let text;

    if (/^07\d{8}$/.test(digits)) {
        text = `${digits.slice(0, 3)}-${digits.slice(3, 6)} ${digits.slice(6, 8)} ${digits.slice(8)}`;
    } else if (digits.startsWith('08')) {
        text = `08-${group(digits.slice(2))}`;
    } else if (THREE.includes(digits.slice(0, 3))) {
        text = `${digits.slice(0, 3)}-${group(digits.slice(3))}`;
    } else if (/^0[1-9]\d{2}/.test(digits) && digits.length >= 8) {
        text = `${digits.slice(0, 4)}-${group(digits.slice(4))}`;
    } else {
        return { text: clean, dial };
    }

    return { text, dial: `+46${digits.slice(1)}` };
}

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
                const formatted = phone ? formatPhone(value) : { text: value, dial: value.replace(/\s/g, '') };

                shown.className = 'byra-contact-btn is-revealed';
                // Bara den inledande ikonen följer med (inte ögat som visar att uppgiften är dold).
                shown.append(button.querySelector('svg').cloneNode(true));
                shown.append(Object.assign(document.createElement('span'), { textContent: formatted.text }));

                if (valid) shown.href = `${phone ? 'tel:' : 'mailto:'}${formatted.dial}`;

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
