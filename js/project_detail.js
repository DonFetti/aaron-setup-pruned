/**
 * Load and display project details based on slug from URL parameter
 */

document.addEventListener('DOMContentLoaded', function() {
    loadProjectDetail();
});

/**
 * Get slug from URL query parameter and load project
 */
function loadProjectDetail() {
    const urlParams = new URLSearchParams(window.location.search);
    const slug = urlParams.get('slug');
    
    if (!slug) {
        showError('No project specified');
        return;
    }
    
    loadProjectBySlug(slug);
}

/**
 * Load project data by slug
 * @param {string} slug - Project slug
 */
async function loadProjectBySlug(slug) {
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const projectContent = document.getElementById('project-content');
    
    try {
        // Try direct path: gallery/{slug}/project.json
        const projectPath = `gallery/${slug}/project.json`;
        const response = await fetch(projectPath);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const project = await response.json();
        
        // Verify slug matches
        if (project.slug !== slug) {
            throw new Error('Slug mismatch');
        }
        
        // Hide loading, show content
        loadingState.classList.add('d-none');
        errorState.classList.add('d-none');
        projectContent.classList.remove('d-none');
        
        // Populate project data
        populateProjectData(project);
        
        // Update page title
        document.title = `${project.title} | Project Details`;
        
    } catch (error) {
        console.error('Error loading project:', error);
        loadingState.classList.add('d-none');
        errorState.classList.remove('d-none');
        projectContent.classList.add('d-none');
    }
}

/**
 * Populate all project data into the page
 * @param {Object} project - Project data object
 */
function populateProjectData(project) {
    // Cover Image
    const coverImage = document.getElementById('project-cover-image');
    if (coverImage && project.coverImage) {
        coverImage.src = project.coverImage;
        coverImage.alt = project.title || 'Project cover image';
    }
    
    // Project Type Badges
    const typeBadgesContainer = document.getElementById('project-type-badges');
    if (typeBadgesContainer && project.projectType && Array.isArray(project.projectType)) {
        typeBadgesContainer.innerHTML = '';
        project.projectType.forEach(type => {
            if (type) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark opacity-75 me-1';
                badge.textContent = type;
                typeBadgesContainer.appendChild(badge);
            }
        });
    }
    
    // Title
    const title = document.getElementById('project-title');
    if (title) {
        title.textContent = project.title || 'Untitled Project';
    }
    
    // Project Meta (Status, Location, Dates)
    const projectMeta = document.getElementById('project-meta');
    if (projectMeta) {
        projectMeta.innerHTML = '';
        
        // Status
        if (project.status) {
            const statusBadge = document.createElement('span');
            // Use bg-success if status is 'Completed' (case-insensitive), else bg-warning
            const isCompleted = typeof project.status === 'string' && project.status.trim().toLowerCase() === 'completed';
            statusBadge.className = 'badge ' + (isCompleted ? 'bg-success' : 'bg-warning');
            statusBadge.textContent = project.status;
            projectMeta.appendChild(statusBadge);
        }
        
        // Location
        if (project.location && (project.location.city || project.location.province)) {
            const locationSpan = document.createElement('span');
            locationSpan.className = 'text-muted';
            const locationParts = [];
            if (project.location.city) locationParts.push(project.location.city);
            if (project.location.province) locationParts.push(project.location.province);
            locationSpan.textContent = locationParts.join(', ');
            projectMeta.appendChild(locationSpan);
        }
        
        // Dates
        if (project.dates && (project.dates.start || project.dates.end)) {
            const dateSpan = document.createElement('span');
            dateSpan.className = 'text-muted';
            const dateParts = [];
            if (project.dates.start) dateParts.push(formatDate(project.dates.start));
            if (project.dates.end) dateParts.push(formatDate(project.dates.end));
            dateSpan.textContent = dateParts.join(' - ');
            projectMeta.appendChild(dateSpan);
        }
    }
    
    // Description
    const description = document.getElementById('project-description');
    if (description) {
        description.textContent = project.description || 'No description available.';
    }
    
    // Gallery
    const galleryContainer = document.getElementById('project-gallery');
    const gallerySection = document.getElementById('project-gallery-section');
    if (galleryContainer && project.gallery && Array.isArray(project.gallery)) {
        const validImages = project.gallery.filter(img => img.url);
        
        if (validImages.length > 0) {
            // Check if mobile device (using window width, Bootstrap breakpoint is 768px)
            const isMobile = window.innerWidth < 768;
            // On mobile, only show first 3 images in gallery, but all images available in modal
            const imagesToDisplay = isMobile ? validImages.slice(0, 3) : validImages;
            
            galleryContainer.innerHTML = '';
            imagesToDisplay.forEach((image, displayIndex) => {
                // Find the original index in the full validImages array
                const originalIndex = validImages.findIndex(img => img.url === image.url);
                
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4';
                
                const card = document.createElement('div');
                card.className = 'card shadow-sm';
                
                const img = document.createElement('img');
                img.src = image.url;
                img.alt = image.caption || `Gallery image ${originalIndex + 1}`;
                img.className = 'card-img-top';
                img.style.cssText = 'height: 250px; object-fit: cover; cursor: pointer;';
                
                // Add click to open in modal viewer with all images, starting at clicked image
                img.addEventListener('click', () => {
                    openImageViewer(validImages, originalIndex);
                });
                
                if (image.caption) {
                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body';
                    const caption = document.createElement('p');
                    caption.className = 'card-text small text-muted mb-0';
                    caption.textContent = image.caption;
                    cardBody.appendChild(caption);
                    card.appendChild(cardBody);
                }
                
                card.insertBefore(img, card.firstChild);
                col.appendChild(card);
                galleryContainer.appendChild(col);
            });
            
            // Add "See More" button on mobile if there are more than 3 images
            if (isMobile && validImages.length > 3) {
                const seeMoreCol = document.createElement('div');
                seeMoreCol.className = 'col-12 mt-3';
                
                const seeMoreBtn = document.createElement('button');
                seeMoreBtn.className = 'btn btn-outline-dark w-100';
                seeMoreBtn.textContent = `See More (${validImages.length - 3} more)`;
                seeMoreBtn.style.cssText = 'padding: 12px; font-size: 1.1rem;';
                
                // Open modal starting at the 4th image (index 3)
                seeMoreBtn.addEventListener('click', () => {
                    openImageViewer(validImages, 3);
                });
                
                seeMoreCol.appendChild(seeMoreBtn);
                galleryContainer.appendChild(seeMoreCol);
            }
        } else {
            gallerySection.classList.add('d-none');
        }
    } else {
        if (gallerySection) gallerySection.classList.add('d-none');
    }
    
    // Status (in sidebar)
    const statusSection = document.getElementById('project-status-section');
    const statusBadge = document.getElementById('project-status');
    if (statusSection && statusBadge) {
        if (project.status) {
            statusBadge.textContent = project.status;
            statusBadge.className = project.status.toLowerCase() === 'completed' 
                ? 'badge bg-success ms-2' 
                : 'badge bg-warning ms-2';
        } else {
            statusSection.classList.add('d-none');
        }
    }
    
    // Location (in sidebar)
    const locationSection = document.getElementById('project-location-section');
    const locationText = document.getElementById('project-location');
    if (locationSection && locationText) {
        if (project.location && (project.location.city || project.location.province)) {
            const locationParts = [];
            if (project.location.city) locationParts.push(project.location.city);
            if (project.location.province) locationParts.push(project.location.province);
            locationText.textContent = locationParts.join(', ');
        } else {
            locationSection.classList.add('d-none');
        }
    }
    
    // Dates (in sidebar)
    const datesSection = document.getElementById('project-dates-section');
    const datesText = document.getElementById('project-dates');
    if (datesSection && datesText) {
        if (project.dates && (project.dates.start || project.dates.end)) {
            const dateParts = [];
            if (project.dates.start) dateParts.push(`Start: ${formatDate(project.dates.start)}`);
            if (project.dates.end) dateParts.push(`End: ${formatDate(project.dates.end)}`);
            datesText.textContent = dateParts.join('\n');
            datesText.style.whiteSpace = 'pre-line';
        } else {
            datesSection.classList.add('d-none');
        }
    }
    
    // Client (in sidebar)
    const clientSection = document.getElementById('project-client-section');
    const clientText = document.getElementById('project-client');
    if (clientSection && clientText) {
        if (project.client && project.client.name) {
            clientText.textContent = project.client.name;
        } else {
            clientSection.classList.add('d-none');
        }
    }
    
    // Scope of Work
    const scopeSection = document.getElementById('project-scope-section');
    const scopeList = document.getElementById('project-scope');
    if (scopeSection && scopeList) {
        if (project.scopeOfWork && Array.isArray(project.scopeOfWork) && project.scopeOfWork.length > 0) {
            scopeList.innerHTML = '';
            project.scopeOfWork.forEach(item => {
                if (item) {
                    const li = document.createElement('li');
                    li.className = 'mb-2';
                    li.innerHTML = `<span class="text-success me-2">✓</span>${item}`;
                    scopeList.appendChild(li);
                }
            });
        } else {
            scopeSection.classList.add('d-none');
        }
    }
    
    // Materials
    const materialsSection = document.getElementById('project-materials-section');
    const materialsList = document.getElementById('project-materials');
    if (materialsSection && materialsList) {
        if (project.materials && Array.isArray(project.materials) && project.materials.length > 0) {
            materialsList.innerHTML = '';
            project.materials.forEach(material => {
                if (material) {
                    const li = document.createElement('li');
                    li.className = 'mb-2';
                    li.innerHTML = `<span class="text-primary me-2">•</span>${material}`;
                    materialsList.appendChild(li);
                }
            });
        } else {
            materialsSection.classList.add('d-none');
        }
    }
    
    // Team
    const teamSection = document.getElementById('project-team-section');
    const teamList = document.getElementById('project-team');
    if (teamSection && teamList) {
        if (project.team && Array.isArray(project.team) && project.team.length > 0) {
            const validTeam = project.team.filter(member => member.name);
            if (validTeam.length > 0) {
                teamList.innerHTML = '';
                validTeam.forEach(member => {
                    const li = document.createElement('li');
                    li.className = 'mb-2';
                    li.innerHTML = `<strong>${member.name}</strong>${member.role ? ` - ${member.role}` : ''}`;
                    teamList.appendChild(li);
                });
            } else {
                teamSection.classList.add('d-none');
            }
        } else {
            teamSection.classList.add('d-none');
        }
    }
    
    // Highlights
    const highlightsSection = document.getElementById('project-highlights-section');
    const highlightsList = document.getElementById('project-highlights');
    if (highlightsSection && highlightsList) {
        if (project.highlights && Array.isArray(project.highlights) && project.highlights.length > 0) {
            highlightsList.innerHTML = '';
            project.highlights.forEach(highlight => {
                if (highlight) {
                    const li = document.createElement('li');
                    li.className = 'mb-2';
                    li.innerHTML = `<span class="text-warning me-2">★</span>${highlight}`;
                    highlightsList.appendChild(li);
                }
            });
        } else {
            highlightsSection.classList.add('d-none');
        }
    }
}

/**
 * Format date string for display
 * @param {string} dateString - Date string to format
 * @returns {string} - Formatted date or empty string
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString; // Return original if invalid
        
        // Format as Month Day, Year (e.g., "May 1, 2024")
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    } catch (error) {
        return dateString; // Return original if parsing fails
    }
}

/**
 * Show error message
 * @param {string} message - Error message
 */
function showError(message) {
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const projectContent = document.getElementById('project-content');
    
    loadingState.classList.add('d-none');
    errorState.classList.remove('d-none');
    projectContent.classList.add('d-none');
}

/**
 * Open image viewer modal
 * @param {Array} images - Array of image objects
 * @param {number} currentIndex - Current image index
 */
function openImageViewer(images, currentIndex) {
    if (!images || images.length === 0) return;
    
    const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
    const modalImage = document.getElementById('modal-image');
    const modalCaption = document.getElementById('modal-caption');
    const modalTitle = document.getElementById('imageViewerModalLabel');
    const imageCounter = document.getElementById('image-counter');
    const prevBtn = document.getElementById('prev-image-btn');
    const nextBtn = document.getElementById('next-image-btn');
    
    let currentIdx = currentIndex;
    
    function updateImage() {
        const image = images[currentIdx];
        modalImage.src = image.url;
        modalImage.alt = image.caption || `Gallery image ${currentIdx + 1}`;
        modalCaption.textContent = image.caption || '';
        modalTitle.textContent = image.caption || `Image ${currentIdx + 1}`;
        imageCounter.textContent = `${currentIdx + 1} / ${images.length}`;
        
        // Show/hide navigation buttons
        prevBtn.style.display = images.length > 1 ? 'block' : 'none';
        nextBtn.style.display = images.length > 1 ? 'block' : 'none';
    }
    
    // Navigation handlers
    prevBtn.onclick = () => {
        currentIdx = (currentIdx - 1 + images.length) % images.length;
        updateImage();
    };
    
    nextBtn.onclick = () => {
        currentIdx = (currentIdx + 1) % images.length;
        updateImage();
    };
    
    // Keyboard navigation
    const handleKeyDown = (e) => {
        if (e.key === 'ArrowLeft') {
            currentIdx = (currentIdx - 1 + images.length) % images.length;
            updateImage();
        } else if (e.key === 'ArrowRight') {
            currentIdx = (currentIdx + 1) % images.length;
            updateImage();
        }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    
    // Remove event listener when modal is hidden
    document.getElementById('imageViewerModal').addEventListener('hidden.bs.modal', () => {
        document.removeEventListener('keydown', handleKeyDown);
    }, { once: true });
    
    updateImage();
    modal.show();
}

