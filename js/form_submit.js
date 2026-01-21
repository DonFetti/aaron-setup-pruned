function submitForm(event) {
    event.preventDefault();

    const form = document.getElementById('contact-form');
    const formMessage = document.getElementById('form-message');
    const fileInput = document.getElementById('images');
    
    // Validate file count (max 5)
    if (fileInput.files.length > 5) {
        formMessage.classList.remove('d-none');
        formMessage.style.backgroundColor = '#f8d7da';
        formMessage.style.color = '#721c24';
        formMessage.textContent = 'Maximum 5 files allowed. Please select fewer files.';
        return;
    }
    
    // Validate file size (8MB = 8 * 1024 * 1024 bytes) and file types
    const maxFileSize = 8 * 1024 * 1024; // 8MB in bytes
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/heic', 'image/heif'];
    
    for (let i = 0; i < fileInput.files.length; i++) {
        const file = fileInput.files[i];
        
        // Check file size
        if (file.size > maxFileSize) {
            formMessage.classList.remove('d-none');
            formMessage.style.backgroundColor = '#f8d7da';
            formMessage.style.color = '#721c24';
            formMessage.textContent = `File "${file.name}" exceeds 8MB limit. Please choose a smaller file.`;
            return;
        }
        
        // Check file type (check both MIME type and extension)
        const fileExtension = file.name.split('.').pop().toLowerCase();
        const isValidType = allowedTypes.includes(file.type) || 
                           ['jpg', 'jpeg', 'png', 'heic'].includes(fileExtension);
        
        if (!isValidType) {
            formMessage.classList.remove('d-none');
            formMessage.style.backgroundColor = '#f8d7da';
            formMessage.style.color = '#721c24';
            formMessage.textContent = `File "${file.name}" is not a supported format. Please use JPG, JPEG, PNG, or HEIC files.`;
            return;
        }
    }

    const formData = new FormData(form);

    fetch('api/contact-run.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is ok, and try to parse JSON
            if (!response.ok) {
                // Try to get JSON error message, otherwise use status text
                return response.text().then(text => {
                    try {
                        const json = JSON.parse(text);
                        throw json;
                    } catch (e) {
                        if (e.status) {
                            // It's JSON with status
                            return e;
                        }
                        // Not JSON, throw with status text
                        throw { status: 'error', message: response.statusText || 'Server error occurred' };
                    }
                });
            }
            return response.json();
        })
        .then(data => {
            formMessage.classList.remove('d-none');
            formMessage.style.backgroundColor = data.status === 'success' ? '#d4edda' : '#f8d7da';
            formMessage.style.color = data.status === 'success' ? '#155724' : '#721c24';
            formMessage.textContent = data.message;

            if (data.status === 'success') {
                if (window.pushLead){
                    window.pushLead({
                        form:'contact-form'
                    })
                }
                form.reset();
                setTimeout(() => {
                    formMessage.classList.add('d-none');
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            formMessage.classList.remove('d-none');
            formMessage.style.backgroundColor = '#f8d7da';
            formMessage.style.color = '#721c24';
            formMessage.textContent = error.message || 'An error occurred. Please try again later.';
            if (window.pushFailedForm) {
                window.pushFailedForm({
                    form: 'contact-form',
                    failure: error.message
                })
            }
        });
}