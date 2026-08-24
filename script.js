// --- PREPÍNÁNÍ GALERIE ---
function toggleGallery(button) {
    const header = button.parentElement;
    const category = header.parentElement;
    const gallery = category.querySelector('.portfolio__gallery');

    if (!gallery) return;

    if (gallery.style.display === 'none') {
        gallery.style.display = 'block';
        button.classList.add('open');
    } else {
        gallery.style.display = 'none';
        button.classList.remove('open');
    }
}

// --- HEADER TRANSPARENCY ---
const siteHeader = document.querySelector('header');
const headerScrollThreshold = 20;

function updateHeaderTransparency() {
    if (!siteHeader) return;
    siteHeader.classList.toggle('header--scrolled', window.scrollY > headerScrollThreshold);
}

updateHeaderTransparency();
window.addEventListener('scroll', updateHeaderTransparency, { passive: true });

// --- INTERSECTION OBSERVER ---
const animatedElements = document.querySelectorAll('.section__title, .faq__description, .faq__item, .contact-form-panel, .package:not(.package--middle), .o_mne, .portfolio__selection .portfolio__item');

if ('IntersectionObserver' in window && animatedElements.length > 0) {
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

// --- MASONRY LAYOUT ---
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
    setTimeout(runMasonry, 500);
});

window.addEventListener('resize', debounce(runMasonry, 150));

// --- FILTRACE A MÍCHÁNÍ PORTFOLIA ---
const filterButtons = document.querySelectorAll('.portfolio-filter button');
const galleryGrid = document.querySelector('.portfolio-gallery-grid');
let portfolioItems = Array.from(document.querySelectorAll('.portfolio-gallery-grid .portfolio__item'));

document.querySelectorAll('.portfolio-filter button').forEach(button => {
    button.addEventListener('click', () => {
        // 1. Odstraní třídu is-active ze všech tlačítek
        document.querySelectorAll('.portfolio-filter button').forEach(btn => {
            btn.classList.remove('is-active');
        });

        // 2. Přidá is-active kliknutému tlačítku
        button.classList.add('is-active');

        // 3. Zavolá tvou filtrační funkci s filtrem z HTML (data-filter)
        const filterValue = button.getAttribute('data-filter');
        applyPortfolioFilter(filterValue);
    });
});

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

function applyPortfolioFilter(filter) {
    if (filter === 'all') {
        shuffleArray(portfolioItems);
        portfolioItems.forEach((item) => {
            item.style.display = '';
            if (galleryGrid) galleryGrid.appendChild(item);
        });
    } else {
        portfolioItems.forEach((item) => {
            const category = item.dataset.category;
            item.style.display = category === filter ? '' : 'none';
        });
    }

    setTimeout(runMasonry, 50);
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

// --- LIGHTBOX ---
const lightbox = document.querySelector('.photo-lightbox');
const lightboxImage = document.querySelector('.photo-lightbox__image');
const lightboxCaption = document.querySelector('.photo-lightbox__caption');
const lightboxCloseButton = document.querySelector('.photo-lightbox__close');
const lightboxPrevButton = document.querySelector('.photo-lightbox__nav--prev');
const lightboxNextButton = document.querySelector('.photo-lightbox__nav--next');
const zoomableImages = Array.from(document.querySelectorAll('#portfolio img, #portfolio-page img, #omne img, .portfolio__item img'));
let currentLightboxIndex = -1;

function openLightboxAt(index) {
    const image = zoomableImages[index];
    if (!image || !lightbox || !lightboxImage) return;

    currentLightboxIndex = index;
    lightboxImage.src = image.src;
    lightboxImage.alt = image.alt || '';
    if (lightboxCaption) {
        lightboxCaption.textContent = image.dataset.caption || image.alt || '';
    }
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function openLightbox(image) {
    openLightboxAt(zoomableImages.indexOf(image));
}

function closeLightbox() {
    if (!lightbox || !lightboxImage) return;
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
    lightboxImage.alt = '';
    if (lightboxCaption) lightboxCaption.textContent = '';
    document.body.classList.remove('modal-open');
}

function showPreviousImage() {
    if (zoomableImages.length === 0) return;
    const previousIndex = (currentLightboxIndex - 1 + zoomableImages.length) % zoomableImages.length;
    openLightboxAt(previousIndex);
}

function showNextImage() {
    if (zoomableImages.length === 0) return;
    const nextIndex = (currentLightboxIndex + 1) % zoomableImages.length;
    openLightboxAt(nextIndex);
}

if (zoomableImages.length > 0) {
    zoomableImages.forEach((image) => {
        image.addEventListener('click', () => openLightbox(image));
    });
}

if (lightbox) {
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) closeLightbox();
    });
}

if (lightboxCloseButton) {
    lightboxCloseButton.addEventListener('click', closeLightbox);
}

if (lightboxPrevButton) {
    lightboxPrevButton.addEventListener('click', (event) => {
        event.stopPropagation();
        showPreviousImage();
    });
}

if (lightboxNextButton) {
    lightboxNextButton.addEventListener('click', (event) => {
        event.stopPropagation();
        showNextImage();
    });
}

document.addEventListener('keydown', (event) => {
    if (!lightbox || !lightbox.classList.contains('open')) return;

    if (event.key === 'Escape') closeLightbox();
    if (event.key === 'ArrowLeft') showPreviousImage();
    if (event.key === 'ArrowRight') showNextImage();
});

// --- TLAČÍTKO SCROLL NAHORU ---
const scrollToTopButton = document.querySelector('.scroll-to-top');

function toggleScrollToTopButton() {
    if (!scrollToTopButton) return;
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

// --- INICIALIZACE PO NAČTENÍ DOM ---
document.addEventListener('DOMContentLoaded', () => {
    // Hamburger menu a zavírání po kliknutí na odkaz
    const menuToggle = document.querySelector('.menu-toggle');
    const menu = document.querySelector('.menu');

    if (menuToggle && menu) {
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('is-active');
            menu.classList.toggle('is-active');
        });

        const menuLinks = menu.querySelectorAll('a');
        menuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                menuToggle.classList.remove('is-active');
                menu.classList.remove('is-active');
            });
        });
    }
});