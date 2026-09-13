document.addEventListener('DOMContentLoaded', () => {

    /* ==================================================
       REVIEWS
       ================================================== */

    const reviewsSection = document.querySelector('.reviews-section');

    if (reviewsSection) {

        const slides =
            reviewsSection.querySelectorAll('.review-slide');

        const reviewsCard =
            reviewsSection.querySelector('.reviews-card');

        const prevButton =
            reviewsSection.querySelector('.prev-btn');

        const nextButton =
            reviewsSection.querySelector('.next-btn');

        if (
            slides.length &&
            reviewsCard &&
            prevButton &&
            nextButton
        ) {

            let currentIndex = 0;

            function showSlide(index) {

                slides.forEach((slide, i) => {
                    slide.classList.toggle(
                        'active',
                        i === index
                    );
                });

                reviewsCard.classList.remove(
                    'color-1',
                    'color-2',
                    'color-3',
                    'color-4'
                );

                const colorIndex =
                    (index % 4) + 1;

                reviewsCard.classList.add(
                    `color-${colorIndex}`
                );
            }

            nextButton.addEventListener('click', () => {

                currentIndex++;

                if (currentIndex >= slides.length) {
                    currentIndex = 0;
                }

                showSlide(currentIndex);
            });

            prevButton.addEventListener('click', () => {

                currentIndex--;

                if (currentIndex < 0) {
                    currentIndex = slides.length - 1;
                }

                showSlide(currentIndex);
            });

            showSlide(currentIndex);
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

        const lookupButtonText =
            bokaDemoSection.querySelector('[data-role="lookup-button-text"]');

        if (lookupInput) {

            lookupInput.addEventListener('input', () => {

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

        let calEmbedInitialized = false;

        function showStep(data) {

            const step = steps[data.alternative];

            if (!step) {
                return;
            }

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
                    orgNumberField.value = lookupInput.value.trim();
                }

                const companyField =
                    step.querySelector('[data-role="alt2-company-name"]');

                if (companyField && data.company_name) {
                    companyField.value = data.company_name;
                }

            }

            if (data.alternative === 1 && !calEmbedInitialized) {
                initCalEmbed(step.querySelector('.boka-demo-cal-embed'));
                calEmbedInitialized = true;
            }
        }

        function initCalEmbed(embedElement) {

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
                },
                calLink: embedElement.dataset.calLink,
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

                const orgNumber = lookupInput.value.trim();

                if (!orgNumber) {
                    lookupError.textContent = 'Ange ett organisationsnummer.';
                    lookupError.hidden = false;
                    return;
                }

                lookupButtonText.textContent = 'Söker...';
                lookupForm.querySelector('button').disabled = true;

                try {

                    const response = await fetch(
                        `${lookupUrl}?org_number=${encodeURIComponent(orgNumber)}`,
                        { headers: { Accept: 'application/json' } }
                    );

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message || 'Kunde inte slå upp organisationsnumret.'
                        );
                    }

                    showStep(data);

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
    }


    /* ==================================================
       LOTTIE-ANIMATIONER
       ================================================== */

    const lottieElements =
        document.querySelectorAll('[data-lottie-src]');

    if (lottieElements.length) {

        import('lottie-web').then(({ default: lottie }) => {

            lottieElements.forEach((el) => {

                lottie.loadAnimation({
                    container: el,
                    renderer: 'svg',
                    loop: true,
                    autoplay: true,
                    path: el.dataset.lottieSrc,
                });

            });

        });

    }

});