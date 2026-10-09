document.addEventListener('DOMContentLoaded', () => {

    /* ==================================================
       KONVERTERINGSSPÅRNING (GTM dataLayer)
       ================================================== */

    const sha256 = async (value) => {

        if (!value || !window.crypto?.subtle) {
            return undefined;
        }

        const digest = await window.crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(value)
        );

        return [...new Uint8Array(digest)]
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    };

    // E.164 (t.ex. +46701234567) - annars matchar inte hasharna hos
    // annonsplattformarna.
    const normalizePhone = (value) => {

        const cleaned = String(value || '').replace(/[^\d+]/g, '');

        if (!cleaned) {
            return '';
        }

        if (cleaned.startsWith('+')) {
            return `+${cleaned.slice(1).replace(/\D/g, '')}`;
        }

        if (cleaned.startsWith('00')) {
            return `+${cleaned.slice(2)}`;
        }

        if (cleaned.startsWith('0')) {
            return `+46${cleaned.slice(1)}`;
        }

        return cleaned.startsWith('46') ? `+${cleaned}` : `+46${cleaned}`;
    };

    const hasMarketingConsent = () => {

        try {
            const stored = JSON.parse(localStorage.getItem('cookie_consent'));

            return Array.isArray(stored?.groups)
                && stored.groups.includes('marketing');
        } catch (e) {
            return false;
        }
    };

    /* ==================================================
       KAMPANJ-ATTRIBUTION (UTM)
       ================================================== */

    const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    const ATTRIBUTION_KEY = 'oqto_attribution';

    const readAttribution = () => {
        try {
            return JSON.parse(sessionStorage.getItem(ATTRIBUTION_KEY)) || {};
        } catch (e) {
            return {};
        }
    };

    // Sparas i fliken (sessionStorage) från första sidan besökaren landar
    // på, så kampanjen följer med till formulär och loggar även efter att
    // hen klickat vidare. Nya UTM-parametrar ersätter de gamla (senaste
    // klick vinner). Googles klick-id (gclid) är en annonsidentifierare och
    // sparas därför bara om besökaren godkänt marknadsföringscookies.
    const captureAttribution = () => {

        try {
            const params = new URLSearchParams(window.location.search);

            // En felbyggd annonslänk kan lägga parametrarna efter ett "#"
            // (t.ex. "…&gclid=X#?utm_source=google"). Då hamnar de i
            // fragmentet och inte i frågesträngen, så de läses härifrån.
            const fragment = window.location.hash.replace(/^#\??/, '');

            if (fragment.includes('=')) {
                new URLSearchParams(fragment).forEach((value, key) => {
                    if (!params.has(key)) {
                        params.set(key, value);
                    }
                });
            }

            const stored = readAttribution();
            let changed = false;

            if (UTM_KEYS.some((key) => params.get(key))) {
                UTM_KEYS.forEach((key) => delete stored[key]);

                UTM_KEYS.forEach((key) => {
                    if (params.get(key)) {
                        stored[key] = params.get(key).slice(0, 100);
                    }
                });

                changed = true;
            }

            if (params.get('gclid') && hasMarketingConsent() && stored.gclid !== params.get('gclid')) {
                stored.gclid = params.get('gclid').slice(0, 200);
                changed = true;
            }

            if (!stored.landing_page) {
                stored.landing_page = window.location.pathname;
                changed = true;
            }

            if (changed) {
                sessionStorage.setItem(ATTRIBUTION_KEY, JSON.stringify(stored));
            }
        } catch (e) {
            // sessionStorage kan vara blockerat - då följer inget med.
        }
    };

    // Lägger de sparade värdena som dolda fält i alla Statamic-formulär, så
    // de följer med inskicket (och vidare till Zapier).
    const fillAttributionFields = () => {

        const stored = readAttribution();

        document.querySelectorAll('form[action*="/!/forms/"]').forEach((form) => {

            Object.entries(stored).forEach(([name, value]) => {

                let input = form.querySelector(`input[type="hidden"][name="${name}"]`);

                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    form.appendChild(input);
                }

                input.value = value;
            });
        });
    };

    captureAttribution();
    fillAttributionFields();

    // Godkänner besökaren marknadsföring efter att ha landat via en
    // annonslänk kan gclid först då sparas.
    window.addEventListener('cookieconsent:change', () => {
        captureAttribution();
        fillAttributionFields();
    });

    // Skickar ett event till GTM. Personuppgifter (hashade med SHA-256)
    // följer bara med om besökaren godkänt marknadsföringscookies.
    const trackFormSuccess = async (eventName, formName, fields = null) => {

        const payload = { event: eventName, form_name: formName };

        if (fields && hasMarketingConsent()) {

            let { firstName, lastName } = fields;

            if (!firstName && fields.name) {
                const [first, ...rest] = fields.name.trim().split(/\s+/);

                firstName = first;
                lastName = rest.join(' ');
            }

            const entries = {
                email: (fields.email || '').trim().toLowerCase(),
                phone: normalizePhone(fields.phone),
                firstName: (firstName || '').trim().toLowerCase(),
                lastName: (lastName || '').trim().toLowerCase(),
            };

            const userData = {};

            for (const [key, value] of Object.entries(entries)) {
                const hashed = await sha256(value);

                if (hashed) {
                    userData[key] = hashed;
                }
            }

            if (Object.keys(userData).length) {
                payload.user_data = userData;
            }
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(payload);
    };

    // Formulär som laddar om sidan vid inskick renderar en dold markör när
    // Statamic rapporterar "success" - eventet skickas när sidan laddats.
    // Väntar in cookie-addonet så samtyckesläget hunnit uppdateras först.
    const trackingMarkers = document.querySelectorAll('[data-track-form]');
    const FIELDS_KEY = 'oqto_track_fields';

    // Kontaktformulären laddar om sidan, så fälten sparas tillfälligt i
    // fliken (sessionStorage, inte i HTML) och läses av markören efteråt.
    document
        .querySelectorAll('form[action*="/!/forms/contact"], form[action*="/!/forms/developers_contact"]')
        .forEach((form) => {
            form.addEventListener('submit', () => {
                try {
                    sessionStorage.setItem(FIELDS_KEY, JSON.stringify({
                        name: form.elements.name?.value || '',
                        email: form.elements.email?.value || '',
                    }));
                } catch (e) {
                    // sessionStorage kan vara blockerat - eventet skickas då utan användardata.
                }
            });
        });

    if (trackingMarkers.length) {

        let waited = 0;

        const sendMarkerEvents = () => {

            if (!window.CookieConsent && waited < 3000) {
                waited += 100;
                setTimeout(sendMarkerEvents, 100);

                return;
            }

            let storedFields = null;

            try {
                storedFields = JSON.parse(sessionStorage.getItem(FIELDS_KEY));
                sessionStorage.removeItem(FIELDS_KEY);
            } catch (e) {
                storedFields = null;
            }

            trackingMarkers.forEach((marker) => {

                const isContactForm =
                    marker.dataset.trackForm === 'contact_submission';

                trackFormSuccess(
                    marker.dataset.trackForm,
                    marker.dataset.formName,
                    isContactForm && storedFields?.email ? storedFields : null
                );
            });
        };

        sendMarkerEvents();
    }


    /* ==================================================
       REVIEWS
       ================================================== */

    const reviewsSection = document.querySelector('.reviews-section');

    if (reviewsSection) {

        const slides =
            reviewsSection.querySelectorAll('.review-slide');

        const reviewsSlides =
            reviewsSection.querySelector('.reviews-slides');

        const reviewsCard =
            reviewsSection.querySelector('.reviews-card');

        const prevButton =
            reviewsSection.querySelector('.prev-btn');

        const nextButton =
            reviewsSection.querySelector('.next-btn');

        if (
            slides.length &&
            reviewsSlides &&
            reviewsCard &&
            prevButton &&
            nextButton
        ) {

            let currentIndex = 0;

            /*
             * Alla slides ligger absolut positionerade på varandra (för
             * mjuk övertoning), vilket gör att containern annars kollapsar
             * till 0 höjd. Vi mäter därför upp det längsta citatet och
             * låser containerns höjd till det, så korten aldrig blir
             * trängre än det behöver för den längsta texten.
             */
            function setSlidesHeight() {

                let maxHeight = 0;

                slides.forEach((slide) => {

                    const content =
                        slide.querySelector('.reviews-card-content');

                    if (content) {

                        const slideStyle = getComputedStyle(slide);

                        const paddingY =
                            parseFloat(slideStyle.paddingTop) +
                            parseFloat(slideStyle.paddingBottom);

                        maxHeight = Math.max(
                            maxHeight,
                            content.offsetHeight + paddingY
                        );
                    }
                });

                reviewsSlides.style.height = `${maxHeight}px`;
            }

            let isAnimating = false;

            /*
             * "Push"-övergång: det inkommande kortet placeras direkt
             * utanför synligt område (höger vid nästa, vänster vid
             * föregående) och de båda korten skjuts sedan samtidigt åt
             * samma håll, som en riktig slider.
             */
            function goToSlide(targetIndex, direction) {

                if (isAnimating || targetIndex === currentIndex) {
                    return;
                }

                isAnimating = true;

                const currentSlide = slides[currentIndex];
                const nextSlide = slides[targetIndex];

                const outTransform =
                    direction > 0 ? 'translateX(-100%)' : 'translateX(100%)';

                const inStartTransform =
                    direction > 0 ? 'translateX(100%)' : 'translateX(-100%)';

                nextSlide.classList.add('no-transition');
                nextSlide.style.transform = inStartTransform;
                nextSlide.style.opacity = '1';
                nextSlide.style.visibility = 'visible';
                nextSlide.style.zIndex = '2';

                // Tvinga fram en reflow så startläget hinner registreras
                // innan övergången animeras.
                void nextSlide.offsetWidth;

                nextSlide.classList.remove('no-transition');

                requestAnimationFrame(() => {
                    currentSlide.style.transform = outTransform;
                    nextSlide.style.transform = 'translateX(0)';
                });

                const handleTransitionEnd = (event) => {

                    if (event.target !== nextSlide || event.propertyName !== 'transform') {
                        return;
                    }

                    nextSlide.removeEventListener('transitionend', handleTransitionEnd);

                    currentSlide.classList.remove('active');
                    currentSlide.style.opacity = '0';
                    currentSlide.style.visibility = 'hidden';
                    currentSlide.style.zIndex = '';
                    currentSlide.classList.add('no-transition');
                    currentSlide.style.transform = 'translateX(0)';
                    void currentSlide.offsetWidth;
                    currentSlide.classList.remove('no-transition');

                    nextSlide.classList.add('active');
                    nextSlide.style.zIndex = '';

                    currentIndex = targetIndex;
                    isAnimating = false;
                };

                nextSlide.addEventListener('transitionend', handleTransitionEnd);
            }

            nextButton.addEventListener('click', () => {

                let targetIndex = currentIndex + 1;

                if (targetIndex >= slides.length) {
                    targetIndex = 0;
                }

                goToSlide(targetIndex, 1);
            });

            prevButton.addEventListener('click', () => {

                let targetIndex = currentIndex - 1;

                if (targetIndex < 0) {
                    targetIndex = slides.length - 1;
                }

                goToSlide(targetIndex, -1);
            });

            setSlidesHeight();
            slides[currentIndex].classList.add('active');

            let resizeTimeout;

            window.addEventListener('resize', () => {

                clearTimeout(resizeTimeout);

                resizeTimeout = setTimeout(setSlidesHeight, 150);
            });
        }
    }


    /* ==================================================
       FEATURES — SCROLL REVEAL
       ================================================== */

    const featuresSection =
        document.querySelector('.features-section');

    if (featuresSection) {

        const observer =
            new IntersectionObserver(
                (entries, observer) => {

                    entries.forEach((entry) => {

                        if (entry.isIntersecting) {

                            entry.target.classList.add(
                                'is-visible'
                            );

                            observer.unobserve(
                                entry.target
                            );
                        }

                    });

                },
                {
                    threshold: 0.2
                }
            );

        observer.observe(featuresSection);
    }


    /* ==================================================
       BOKA DEMO — ORG.NUMMER → ALTERNATIV 1/2
       ================================================== */

    const bokaDemoSection =
        document.querySelector('.boka-demo-section');

    if (bokaDemoSection) {

        const lookupUrl =
            bokaDemoSection.dataset.lookupUrl;

        const lookupForm =
            bokaDemoSection.querySelector('.boka-demo-lookup-form');

        const lookupInput =
            bokaDemoSection.querySelector('#org_number');

        const lookupError =
            bokaDemoSection.querySelector('[data-role="lookup-error"]');

        const lookupEmailInput =
            bokaDemoSection.querySelector('#lookup_email');

        const isValidEmail = (value) =>
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

        const lookupButtonText =
            bokaDemoSection.querySelector('[data-role="lookup-button-text"]');

        const logUrl = bokaDemoSection.dataset.logUrl;

        // Kampanjparametrarna samlas in på varje sida (se KAMPANJ-ATTRIBUTION
        // ovan), så de finns kvar även om besökaren landat på en annan sida.
        const getUtm = () => {
            const stored = readAttribution();

            return Object.fromEntries(
                [...UTM_KEYS, 'gclid', 'landing_page']
                    .filter((key) => stored[key])
                    .map((key) => [key, stored[key]])
            );
        };

        // Bolagsnamn som innehåller ett av dessa ord (t.ex. "redovis")
        // behandlas som en byrå och får Cal.com-bokningen, på samma sätt
        // som en matchande SNI-kod. Orden ligger i config/bolagsverket.php.
        const nameKeywords = (bokaDemoSection.dataset.nameKeywords || '')
            .split(',')
            .map((word) => word.trim())
            .filter(Boolean);

        const normalizeName = (value) =>
            value
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();

        const nameLooksLikeAccountingFirm = (name) => {
            const normalized = normalizeName(name);

            return nameKeywords.some((word) => normalized.includes(word));
        };

        const looksLikeOrgNumberInput = (value) =>
            /^[\d\s-]*$/.test(value);

        const isCompleteOrgNumber = (value) =>
            /^\d{6}-?\d{4}$/.test(value.replace(/\s+/g, ''));

        if (lookupInput) {

            lookupInput.addEventListener('input', () => {

                // Bara maska som org.nummer om användaren bara har
                // skrivit siffror/mellanslag/bindestreck hittills -
                // annars skriver de förmodligen ett bolagsnamn.
                if (!looksLikeOrgNumberInput(lookupInput.value)) {
                    return;
                }

                const digits =
                    lookupInput.value.replace(/\D/g, '').slice(0, 10);

                lookupInput.value = digits.length > 6
                    ? `${digits.slice(0, 6)}-${digits.slice(6)}`
                    : digits;

            });

        }

        const steps = {
            lookup: bokaDemoSection.querySelector('[data-step="lookup"]'),
            1: bokaDemoSection.querySelector('[data-step="alternative-1"]'),
            2: bokaDemoSection.querySelector('[data-step="alternative-2"]'),
        };

        // Kontaktformulärets ursprungliga fält sparas så att steget kan
        // återställas helt när besökaren gör en ny sökning efter ett inskick.
        const contactBodyOriginal = (() => {
            const body = steps[2]
                ? steps[2].querySelector('[data-role="alt2-form-body"]')
                : null;

            return body ? body.innerHTML : null;
        })();

        function resetContactStep() {

            const card = steps[2];

            if (!card || !card.classList.contains('is-submitted')) {
                return;
            }

            const body = card.querySelector('[data-role="alt2-form-body"]');

            if (body && contactBodyOriginal !== null) {
                body.innerHTML = contactBodyOriginal;
            }

            card.classList.remove('is-submitted');
        }

        let calEmbedInitialized = false;

        function showStep(data) {

            const step = steps[data.alternative];

            if (!step) {
                return;
            }

            // Räknar hur många som kommer förbi första steget (utan namn).
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'book_demo_lookup',
                lookup_step: data.alternative === 1 ? 'cal' : 'form',
            });

            // En ny sökning startar om flödet - ett tidigare skickat
            // formulär ska inte ligga kvar som tack-ruta.
            resetContactStep();

            [steps[1], steps[2]].forEach((otherStep) => {
                if (otherStep && otherStep !== step) {
                    otherStep.hidden = true;
                }
            });

            step.hidden = false;

            const greeting =
                step.querySelector('[data-role="greeting"]');

            if (greeting) {

                if (data.company_name) {
                    greeting.textContent = `Hej ${data.company_name}!`;
                    greeting.hidden = false;
                } else {
                    greeting.hidden = true;
                }

            }

            if (data.alternative === 2) {

                const orgNumberField =
                    step.querySelector('[data-role="alt2-org-number"]');

                if (orgNumberField) {
                    orgNumberField.value = data.org_number || '';
                }

                const emailField = step.querySelector('#alt2_email');

                if (emailField && data.email) {
                    emailField.value = data.email;
                }

                const companyField =
                    step.querySelector('[data-role="alt2-company-name"]');

                if (companyField && data.company_name) {
                    companyField.value = data.company_name;
                }

            }

            if (data.alternative === 1 && !calEmbedInitialized) {
                initCalEmbed(step.querySelector('.boka-demo-cal-embed'), data.email);
                calEmbedInitialized = true;
            }

            scrollToStep(step);
        }

        // Scrollar ner till det nya steget (formulär eller bokning) så att
        // besökaren ser det direkt, särskilt på mobil där det annars hamnar
        // utanför bild. Hoppar över scrollen om steget redan syns bra.
        // scroll-margin-top i CSS tar hänsyn till den fasta menyn.
        function scrollToStep(step) {

            window.requestAnimationFrame(() => {

                const rect = step.getBoundingClientRect();

                const alreadyInView =
                    rect.top >= 0
                    && rect.top < window.innerHeight * 0.45
                    && rect.bottom <= window.innerHeight;

                if (alreadyInView) {
                    return;
                }

                const reduceMotion =
                    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                step.scrollIntoView({
                    behavior: reduceMotion ? 'auto' : 'smooth',
                    block: 'start',
                });
            });
        }

        function initCalEmbed(embedElement, email) {

            if (!embedElement || !window.Cal) {
                return;
            }

            window.Cal('init', 'demo', { origin: 'https://app.cal.com' });

            window.Cal.config = window.Cal.config || {};
            window.Cal.config.forwardQueryParams = true;

            window.Cal.ns.demo('inline', {
                elementOrSelector: embedElement,
                config: {
                    layout: 'month_view',
                    useSlotsViewOnSmallScreen: 'true',
                    // Förifyller e-posten i bokningsformuläret.
                    ...(email ? { email } : {}),
                },
                calLink: embedElement.dataset.calLink,
            });

            window.Cal.ns.demo('on', {
                action: 'bookingSuccessful',
                callback: () => {
                    trackFormSuccess('book_demo', 'cal_booking');
                },
            });

            window.Cal.ns.demo('ui', {
                hideEventTypeDetails: false,
                layout: 'month_view',
            });
        }

        if (lookupForm && lookupUrl) {

            lookupForm.addEventListener('submit', async (event) => {

                event.preventDefault();

                lookupError.hidden = true;

                const inputValue = lookupInput.value.trim();

                if (!inputValue) {
                    lookupError.textContent = 'Ange ett organisationsnummer eller bolagsnamn.';
                    lookupError.hidden = false;
                    return;
                }

                const email = lookupEmailInput.value.trim();

                if (!isValidEmail(email)) {
                    lookupError.textContent = 'Ange en giltig e-postadress.';
                    lookupError.hidden = false;
                    lookupEmailInput.focus();
                    return;
                }

                if (!isCompleteOrgNumber(inputValue)) {
                    // Ser inte ut som ett org.nummer - tolka som bolagsnamn.
                    // Namn som tyder på en byrå får bokningen, övriga det
                    // generella formuläret.
                    const nameAlternative = nameLooksLikeAccountingFirm(inputValue) ? 1 : 2;

                    showStep({
                        alternative: nameAlternative,
                        org_number: null,
                        company_name: inputValue,
                        email,
                    });

                    if (logUrl) {
                        fetch(logUrl, {
                            method: 'POST',
                            keepalive: true,
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({
                                name: inputValue,
                                alternative: nameAlternative,
                                email,
                                ...getUtm(),
                            }),
                        }).catch(() => {
                            // Loggningen får aldrig störa besökaren.
                        });
                    }

                    return;
                }

                const orgNumber = inputValue;

                lookupButtonText.textContent = 'Söker...';
                lookupForm.querySelector('button').disabled = true;

                try {

                    // POST så att e-postadressen inte hamnar i URL:en.
                    const response = await fetch(lookupUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            org_number: orgNumber,
                            email,
                            ...getUtm(),
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message || 'Kunde inte slå upp organisationsnumret.'
                        );
                    }

                    showStep({ ...data, email });

                } catch (err) {

                    lookupError.textContent =
                        err.message || 'Något gick fel. Försök igen.';

                    lookupError.hidden = false;

                } finally {

                    lookupButtonText.textContent = 'Fortsätt';
                    lookupForm.querySelector('button').disabled = false;

                }

            });

        }

        const contactForm =
            bokaDemoSection.querySelector('.boka-demo-contact-form');

        if (contactForm) {

            contactForm.addEventListener('submit', async (event) => {

                event.preventDefault();

                const submitButton =
                    contactForm.querySelector('.boka-demo-btn');

                const formBody =
                    contactForm.querySelector('[data-role="alt2-form-body"]');

                const existingErrors =
                    contactForm.querySelector('[data-role="alt2-errors"]');

                if (existingErrors) {
                    existingErrors.remove();
                }

                submitButton.disabled = true;
                submitButton.classList.add('is-loading');

                const showErrors = (messages) => {

                    const list = document.createElement('ul');
                    list.className = 'boka-demo-errors';
                    list.dataset.role = 'alt2-errors';

                    messages.forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        list.appendChild(item);
                    });

                    formBody.prepend(list);
                };

                try {

                    const formData = new FormData(contactForm);

                    const response = await fetch(contactForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { Accept: 'application/json' },
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        showErrors(
                            data.error
                                ? Object.values(data.error)
                                : ['Något gick fel. Försök igen.']
                        );

                        return;
                    }

                    trackFormSuccess('book_demo', 'boka_demo', {
                        firstName: formData.get('first_name'),
                        lastName: formData.get('last_name'),
                        email: formData.get('email'),
                        phone: formData.get('phone'),
                    });

                    // Byter ut fälten mot samma success-markup som
                    // Statamics form-tagg själv hade renderat vid en
                    // vanlig sidladdning - så wizard-steget (som JS
                    // redan navigerat till) inte nollställs av en
                    // full omladdning.
                    const thanks = steps[2].querySelector('[data-role="thanks-template"]');

                    if (thanks) {

                        formBody.replaceChildren(thanks.content.cloneNode(true));

                        const firstName = String(formData.get('first_name') || '').trim();
                        const thanksTitle = formBody.querySelector('[data-role="thanks-title"]');

                        if (thanksTitle && firstName) {
                            thanksTitle.textContent = `Tack, ${firstName}!`;
                        }

                        steps[2].classList.add('is-submitted');

                        startLottie(formBody.querySelectorAll('[data-lottie-src]'));

                    } else {
                        formBody.innerHTML = '<p class="boka-demo-success">Tack! Vi hör av oss inom kort.</p>';
                    }

                } catch (err) {

                    showErrors(['Något gick fel. Försök igen.']);

                } finally {

                    if (contactForm.contains(submitButton)) {
                        submitButton.disabled = false;
                        submitButton.classList.remove('is-loading');
                    }

                }

            });

        }
    }


    /* ==================================================
       JOBBANSÖKAN — LADDNINGSFEEDBACK
       ================================================== */

    const jobApplicationForm =
        document.querySelector('.job-application-form');

    if (jobApplicationForm) {

        jobApplicationForm.addEventListener('submit', () => {

            const submitButton =
                jobApplicationForm.querySelector('.job-form-submit');

            if (!submitButton) {
                return;
            }

            // Vanligt formulärskick (inte AJAX) - sidan navigerar bort
            // strax efter, men knappen ska ändå visa att den tryckts
            // in medan svaret väntas in.
            submitButton.disabled = true;
            submitButton.classList.add('is-loading');

        });

    }


    /* ==================================================
       LOTTIE-ANIMATIONER
       ================================================== */

    // Startar Lottie-animationer i givna element. data-lottie-loop="false"
    // spelar animationen en gång. Används även för element som skapas efter
    // sidladdningen (t.ex. tack-rutan på /boka-demo).
    function startLottie(elements) {

        const targets = Array.from(elements);

        if (!targets.length) {
            return;
        }

        import('lottie-web').then(({ default: lottie }) => {

            targets.forEach((el) => {

                el.lottie = lottie.loadAnimation({
                    container: el,
                    renderer: 'svg',
                    loop: el.dataset.lottieLoop !== 'false',
                    autoplay: true,
                    path: el.dataset.lottieSrc,
                    // data-lottie-fit="cover" fyller rutan och beskär kanterna
                    // (för 16:9-animationer i en mer kvadratisk ruta).
                    rendererSettings: el.dataset.lottieFit === 'cover'
                        ? { preserveAspectRatio: 'xMidYMid slice' }
                        : undefined,
                });

            });

        });
    }

    startLottie(document.querySelectorAll('[data-lottie-src]'));

});
