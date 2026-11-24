/*
 * Byrasökningen på /byrakartan. Formuläret fungerar utan JavaScript (Sök-knappen);
 * här läggs bara det som kräver webbläsaren till:
 *  - "Nära mig": hämtar webbläsarens position (efter att besökaren godkänt det),
 *    avrundar den till två decimaler (ca 1 km) och skickar formuläret med ?nara=lat,lng.
 *    Positionen används bara för att sortera listan och sparas inte.
 *  - Filtren (typ av byrå, tjänster, sortering) uppdaterar listan direkt vid ändring.
 *  - Sorteringen får en egen dropdown i sajtens stil. Den vanliga <select> ligger kvar
 *    (dold) och är det som skickas, så formuläret fungerar likadant utan JavaScript.
 */

/** Ersätter en <select> med en tillgänglig dropdown (knapp + listbox) i sajtens stil. */
function enhanceSelect(select) {

    const wrap = select.closest('[data-select]');

    if (!wrap) return;

    const label = document.getElementById(`${select.id}-label`);
    const options = [...select.options];

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'byraer-select-button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    if (label) button.setAttribute('aria-labelledby', `${label.id} ${select.id}-button`);
    button.id = `${select.id}-button`;

    const text = document.createElement('span');
    button.append(text);

    const list = document.createElement('ul');
    list.className = 'byraer-select-list';
    list.setAttribute('role', 'listbox');
    list.tabIndex = -1;
    list.hidden = true;

    const items = options.map((option) => {
        const item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.tabIndex = -1;
        item.textContent = option.textContent.trim();
        list.append(item);
        return item;
    });

    const sync = () => {
        const current = items.find((item) => item.dataset.value === select.value) || items[0];

        text.textContent = current?.textContent || '';
        items.forEach((item) => item.setAttribute('aria-selected', String(item === current)));

        return current;
    };

    const close = (refocus = false) => {
        list.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        if (refocus) button.focus();
    };

    const open = () => {
        list.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        sync()?.focus();
    };

    const choose = (item) => {
        close(true);

        if (select.value === item.dataset.value) return;

        select.value = item.dataset.value;
        sync();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    button.addEventListener('click', () => (list.hidden ? open() : close()));

    button.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });

    list.addEventListener('click', (event) => {
        const item = event.target.closest('[role="option"]');

        if (item) choose(item);
    });

    list.addEventListener('keydown', (event) => {
        const index = items.indexOf(document.activeElement);

        if (event.key === 'ArrowDown') items[Math.min(items.length - 1, index + 1)].focus();
        else if (event.key === 'ArrowUp') items[Math.max(0, index - 1)].focus();
        else if (event.key === 'Home') items[0].focus();
        else if (event.key === 'End') items[items.length - 1].focus();
        else if (event.key === 'Enter' || event.key === ' ') { if (index >= 0) choose(items[index]); }
        else if (event.key === 'Escape') close(true);
        else if (event.key === 'Tab') close();
        else return;

        event.preventDefault();
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) close();
    });

    label?.addEventListener('click', (event) => {
        event.preventDefault();
        button.focus();
    });

    wrap.classList.add('is-enhanced');
    wrap.append(button, list);
    sync();
}

export default function initAgencySearch(form) {

    const nearMe = form.querySelector('[data-near-me]');

    if (nearMe && 'geolocation' in navigator) {
        nearMe.hidden = false;

        nearMe.addEventListener('click', () => {

            const current = form.querySelector('input[name="nara"]');

            // Redan aktiv: slå av platsen igen.
            if (nearMe.getAttribute('aria-pressed') === 'true') {
                current?.remove();
                form.requestSubmit();
                return;
            }

            nearMe.disabled = true;

            navigator.geolocation.getCurrentPosition(
                ({ coords }) => {
                    const input = current || Object.assign(document.createElement('input'), { type: 'hidden', name: 'nara' });

                    input.value = `${coords.latitude.toFixed(2)},${coords.longitude.toFixed(2)}`;

                    if (!current) form.append(input);

                    // Den skrivna orten/postnumret ersätts av positionen, och listan sorteras på avstånd.
                    form.elements.q.value = '';
                    if (form.elements.sort) form.elements.sort.value = 'narmast';

                    form.requestSubmit();
                },
                () => { nearMe.disabled = false; },
                { timeout: 10000, maximumAge: 600000 },
            );
        });
    }

    form.querySelectorAll('[data-select] select').forEach(enhanceSelect);

    form.addEventListener('change', (event) => {
        if (event.target.matches('input[type="checkbox"], select')) form.requestSubmit();
    });
}
