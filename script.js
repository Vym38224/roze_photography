function toggleGallery(button) {
    const header = button.parentElement;
    const category = header.parentElement;
    const gallery = category.querySelector('.portfolio__gallery');

    if (gallery.style.display === 'none') {
        gallery.style.display = 'block';
        button.classList.add('open');
    } else {
        gallery.style.display = 'none';
        button.classList.remove('open');
    }
}

const siteHeader = document.querySelector('header');
const headerScrollThreshold = 20;

function updateHeaderTransparency() {
    if (!siteHeader) {
        return;
    }

    siteHeader.classList.toggle('header--scrolled', window.scrollY > headerScrollThreshold);
}

updateHeaderTransparency();
window.addEventListener('scroll', updateHeaderTransparency, { passive: true });

const animatedElements = document.querySelectorAll('.section__title, .faq__description, .faq__item, .contact-form-panel, .package:not(.package--middle), .o_mne, .portfolio__selection .portfolio__item');

if ('IntersectionObserver' in window) {
    const animatedElementsObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.25
    });

    animatedElements.forEach((element) => animatedElementsObserver.observe(element));
}

const filterButtons = document.querySelectorAll('.portfolio-filter button');
const portfolioItems = document.querySelectorAll('.portfolio-gallery-grid .portfolio__item');

function applyPortfolioFilter(filter) {
    portfolioItems.forEach((item) => {
        const category = item.dataset.category;
        item.style.display = filter === 'all' || category === filter ? '' : 'none';
    });
}

if (filterButtons.length && portfolioItems.length) {
    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;
            filterButtons.forEach((btn) => btn.classList.toggle('active', btn === button));
            applyPortfolioFilter(filter);
        });
    });

    filterButtons[0].classList.add('active');
    applyPortfolioFilter('all');
}

const lightbox = document.querySelector('.photo-lightbox');
const lightboxImage = document.querySelector('.photo-lightbox__image');
const lightboxCaption = document.querySelector('.photo-lightbox__caption');
const lightboxCloseButton = document.querySelector('.photo-lightbox__close');
const lightboxPrevButton = document.querySelector('.photo-lightbox__nav--prev');
const lightboxNextButton = document.querySelector('.photo-lightbox__nav--next');
const zoomableImages = Array.from(document.querySelectorAll('#portfolio img, #portfolio-page img, #omne img, .portfolio__item img'));
let currentLightboxIndex = -1;

// Masonry-like grid packing while preserving DOM (row-major) order.
function updateMasonryGrid(gridSelector, itemSelector) {
    const grids = document.querySelectorAll(gridSelector);
    grids.forEach((grid) => {
        const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows')) || 8;
        const rowGap = parseInt(window.getComputedStyle(grid).getPropertyValue('gap')) || 0;
        const items = grid.querySelectorAll(itemSelector);
        items.forEach((item) => {
            const img = item.querySelector('img');
            if (!img) return;
            const height = img.getBoundingClientRect().height;
            const rowSpan = Math.max(1, Math.ceil((height + rowGap) / (rowHeight + rowGap)));
            item.style.gridRowEnd = 'span ' + rowSpan;
        });
    });
}

function debounce(fn, ms) {
    let t;
    return function (...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), ms);
    };
}

const runMasonry = () => updateMasonryGrid('.portfolio__selection, .portfolio-gallery-grid, .portfolio__gallery.flex', '.portfolio__item');

window.addEventListener('load', () => {
    runMasonry();
    // Also update after a short delay to account for late-loading images
    setTimeout(runMasonry, 500);
});

window.addEventListener('resize', debounce(runMasonry, 150));

function openLightboxAt(index) {
    const image = zoomableImages[index];

    if (!image) {
        return;
    }

    currentLightboxIndex = index;
    lightboxImage.src = image.src;
    lightboxImage.alt = image.alt || '';
    lightboxCaption.textContent = image.alt || '';
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function openLightbox(image) {
    openLightboxAt(zoomableImages.indexOf(image));
}

function closeLightbox() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
    lightboxImage.alt = '';
    lightboxCaption.textContent = '';
    document.body.classList.remove('modal-open');
}

function showPreviousImage() {
    if (zoomableImages.length === 0) {
        return;
    }

    const previousIndex = (currentLightboxIndex - 1 + zoomableImages.length) % zoomableImages.length;
    openLightboxAt(previousIndex);
}

function showNextImage() {
    if (zoomableImages.length === 0) {
        return;
    }

    const nextIndex = (currentLightboxIndex + 1) % zoomableImages.length;
    openLightboxAt(nextIndex);
}

zoomableImages.forEach((image) => {
    image.addEventListener('click', () => openLightbox(image));
});

lightbox.addEventListener('click', (event) => {
    if (event.target === lightbox) {
        closeLightbox();
    }
});

lightboxCloseButton.addEventListener('click', closeLightbox);
lightboxPrevButton.addEventListener('click', (event) => {
    event.stopPropagation();
    showPreviousImage();
});
lightboxNextButton.addEventListener('click', (event) => {
    event.stopPropagation();
    showNextImage();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && lightbox.classList.contains('open')) {
        closeLightbox();
    }

    if (event.key === 'ArrowLeft' && lightbox.classList.contains('open')) {
        showPreviousImage();
    }

    if (event.key === 'ArrowRight' && lightbox.classList.contains('open')) {
        showNextImage();
    }
});

const scrollToTopButton = document.querySelector('.scroll-to-top');

function toggleScrollToTopButton() {
    if (!scrollToTopButton) {
        return;
    }

    const shouldShow = window.scrollY > 400;
    scrollToTopButton.classList.toggle('is-visible', shouldShow);
}

function scrollUp() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

toggleScrollToTopButton();
window.addEventListener('scroll', toggleScrollToTopButton, { passive: true });

if (scrollToTopButton) {
    scrollToTopButton.addEventListener('click', scrollUp);
}
