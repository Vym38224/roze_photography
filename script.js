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

const lightbox = document.querySelector('.photo-lightbox');
const lightboxImage = document.querySelector('.photo-lightbox__image');
const lightboxCaption = document.querySelector('.photo-lightbox__caption');
const lightboxCloseButton = document.querySelector('.photo-lightbox__close');
const lightboxPrevButton = document.querySelector('.photo-lightbox__nav--prev');
const lightboxNextButton = document.querySelector('.photo-lightbox__nav--next');
const zoomableImages = Array.from(document.querySelectorAll('#portfolio img, #omne img'));
let currentLightboxIndex = -1;

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

function scrollUp() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
