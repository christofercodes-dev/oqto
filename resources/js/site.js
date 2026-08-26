document.addEventListener('DOMContentLoaded', () => {

    /* ==================================================
       REVIEWS
       ================================================== */

    const reviewsSection = document.querySelector('.reviews-section');

    if (reviewsSection) {

        const slides = reviewsSection.querySelectorAll('.review-slide');
        const reviewsCard = reviewsSection.querySelector('.reviews-card');
        const prevButton = reviewsSection.querySelector('.prev-btn');
        const nextButton = reviewsSection.querySelector('.next-btn');

        if (slides.length && reviewsCard && prevButton && nextButton) {

            let currentIndex = 0;

            function showSlide(index) {

                slides.forEach((slide, i) => {
                    slide.classList.toggle('active', i === index);
                });

                reviewsCard.classList.remove(
                    'color-1',
                    'color-2',
                    'color-3',
                    'color-4'
                );

                const colorIndex = (index % 4) + 1;

                reviewsCard.classList.add(`color-${colorIndex}`);
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

    const featuresSection = document.querySelector('.features-section');

    if (featuresSection) {

        const observer = new IntersectionObserver(
            (entries, observer) => {

                entries.forEach((entry) => {

                    if (entry.isIntersecting) {

                        featuresSection.classList.add('is-visible');

                        observer.unobserve(entry.target);

                    }

                });

            },
            {
                threshold: 0.2
            }
        );

        observer.observe(featuresSection);
    }

});