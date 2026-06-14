function toggleGallery(button) {
    const header = button.parentElement;
    const category = header.parentElement;
    const gallery = category.querySelector('.portfolio__gallery');
    
    if (gallery.style.display === 'none') {
        gallery.style.display = 'flex';
        button.classList.add('open');
    } else {
        gallery.style.display = 'none';
        button.classList.remove('open');
    }
}

function scrollUp() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
