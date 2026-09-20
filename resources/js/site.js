document.addEventListener('DOMContentLoaded', () => {

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

        const lookupButtonText =
            bokaDemoSection.querySelector('[data-role="lookup-button-text"]');

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
                    orgNumberField.value = data.org_number || '';
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

                const inputValue = lookupInput.value.trim();

                if (!inputValue) {
                    lookupError.textContent = 'Ange ett organisationsnummer eller bolagsnamn.';
                    lookupError.hidden = false;
                    return;
                }

                if (!isCompleteOrgNumber(inputValue)) {
                    // Ser inte ut som ett org.nummer - tolka som bolagsnamn
                    // och gå direkt till det generella formuläret.
                    showStep({
                        alternative: 2,
                        org_number: null,
                        company_name: inputValue,
                    });

                    return;
                }

                const orgNumber = inputValue;

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