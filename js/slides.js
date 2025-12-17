const slides = document.querySelectorAll('.slide');
let currentSlide = 0;

function showSlide() {
    slides.forEach((slide, index) => {
        slide.classList.remove('active');
        if (index === currentSlide) {
            slide.classList.add('active');
        }
    });
    currentSlide = (currentSlide + 1) % slides.length;
}

setInterval(showSlide, 5000); // Change slide every 5 seconds
showSlide(); // Show the first slide immediately