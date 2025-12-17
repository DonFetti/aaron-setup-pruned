const closebutton = document.getElementById('closepop');
const popup = document.getElementById('popup');
const contactTriggers = document.querySelectorAll('.contact-trigger');

closebutton.onclick = function() {
    popup.style.display = 'none';
}

contactTriggers.forEach(trigger => {
    trigger.onclick = function(e) {
        e.preventDefault();
        popup.style.display = 'block';
    }
});

// Close popup when clicking outside
popup.onclick = function(e) {
    if (e.target === popup) {
        popup.style.display = 'none';
    }
}
