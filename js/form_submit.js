function submitForm(event) {
    event.preventDefault();

    const form = document.getElementById('contact-form');
    const formMessage = document.getElementById('form-message');
    const formData = new FormData(form);

    fetch('process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            formMessage.style.display = 'block';
            formMessage.style.backgroundColor = data.status === 'success' ? '#d4edda' : '#f8d7da';
            formMessage.style.color = data.status === 'success' ? '#155724' : '#721c24';
            formMessage.textContent = data.message;

            if (data.status === 'success') {
                form.reset();
                setTimeout(() => {
                    formMessage.style.display = 'none';
                }, 3000);
            }
        })
        .catch(error => {
            formMessage.style.display = 'block';
            formMessage.style.backgroundColor = '#f8d7da';
            formMessage.style.color = '#721c24';
            formMessage.textContent = 'An error occurred. Please try again later.';
        });
}