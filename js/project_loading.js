/**
 * Load and display projects from gallery/index.json
 * Each project is loaded from gallery/{slug}/project.json
 */

document.addEventListener('DOMContentLoaded', function() {
    loadProjects().then(() => {
        setupProjectClickListeners();
    });
});

/**
 * Fetch project slugs from index.json and load each project
 */
async function loadProjects() {
    try {
        const response = await fetch('index.json');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (!data.projects || !Array.isArray(data.projects)) {
            console.error('Invalid JSON structure: projects array not found');
            return;
        }
        
        // Load each project by slug
        const projectPromises = data.projects.map(slug => loadProjectBySlug(slug));
        const projects = await Promise.all(projectPromises);
        
        // Filter out any failed loads (null values)
        const validProjects = projects.filter(project => project !== null);
        
        displayProjects(validProjects);
    } catch (error) {
        console.error('Error loading projects:', error);
        displayErrorMessage('Failed to load projects. Please try again later.');
    }
}

/**
 * Load a single project by its slug
 * Searches through gallery subdirectories to find the project.json file
 * @param {string} slug - Project slug
 * @returns {Promise<Object|null>} - Project data or null if not found
 */
async function loadProjectBySlug(slug) {
    if (!slug) {
        console.warn('Empty slug provided');
        return null;
    }
    
    // Try the direct path first: {slug}/project.json (relative to gallery directory)
    const directPath = `${slug}/project.json`;
    
    try {
        const response = await fetch(directPath);
        if (response.ok) {
            const project = await response.json();
            // Verify the slug matches
            if (project.slug === slug) {
                return project;
            }
        }
    } catch (error) {
        // Direct path failed, continue to search
    }
    
    
    console.warn(`Project with slug "${slug}" not found`);
    return null;
}

/**
 * Display all projects in the gallery
 * @param {Array} projects - Array of project objects
 */
function displayProjects(projects) {
    const galleryRow = document.getElementById('gallery-row');
    const template = document.getElementById('project-card-template');
    
    if (!galleryRow || !template) {
        console.error('Gallery row or template not found');
        return;
    }
    
    // Clear any existing content (except template)
    galleryRow.innerHTML = '';
    
    if (projects.length === 0) {
        displayEmptyMessage(galleryRow);
        return;
    }
    
    // Create a card for each project
    projects.forEach(project => {
        const card = createProjectCard(project, template);
        if (card) {
            galleryRow.appendChild(card);
        }
    });
}

/**
 * Create a project card from template and project data
 * @param {Object} project - Project data object
 * @param {HTMLElement} template - Template element to clone
 * @returns {HTMLElement|null} - Cloned and populated card element
 */
function createProjectCard(project, template) {
    // Clone the template
    const card = template.content.cloneNode(true);
    const cardElement = card.querySelector('.card');
    
    if (!cardElement) {
        console.error('Card element not found in template');
        return null;
    }
    
    // Set slug data attribute
    if (project.slug) {
        cardElement.setAttribute('data-slug', project.slug);
    }
    
    // Set cover image
    const coverImage = card.querySelector('.project-cover-image');
    if (coverImage) {
        // Fix image path for gallery subdirectory - remove 'gallery/' prefix if present
        let imagePath = project.coverImage || '';
        if (imagePath.startsWith('gallery/')) {
            imagePath = imagePath.substring(8); // Remove 'gallery/' prefix
        }
        coverImage.src = imagePath;
        coverImage.alt = project.title || 'Project image';
    }
    
    // Set project types (array of badges)
    const typeBadgesContainer = card.querySelector('.project-type-badges');
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
    
    // Set title
    const title = card.querySelector('.project-title');
    if (title) {
        title.textContent = project.title || 'Untitled Project';
    }
    
    // Set location
    const locationCity = card.querySelector('.project-location-city');
    const locationProvince = card.querySelector('.project-location-province');
    const locationSeparator = card.querySelector('.project-location-separator');
    
    if (locationCity && project.location) {
        locationCity.textContent = project.location.city || '';
    }
    if (locationProvince && project.location) {
        locationProvince.textContent = project.location.province || '';
    }
    
    // Hide separator if no location data
    if (locationSeparator) {
        const hasCity = project.location && project.location.city;
        const hasProvince = project.location && project.location.province;
        if (!hasCity || !hasProvince) {
            locationSeparator.style.display = 'none';
        }
    }
    
    // Hide entire location if no data
    const locationContainer = card.querySelector('.project-location');
    if (locationContainer && project.location) {
        const hasLocation = (project.location.city || project.location.province);
        if (!hasLocation) {
            locationContainer.closest('.text-muted').style.display = 'none';
        }
    }
    
    // Set description
    const description = card.querySelector('.project-description');
    if (description) {
        description.textContent = project.description || '';
        // Hide if empty
        if (!project.description) {
            description.style.display = 'none';
        }
    }
    
    // Set status
    const status = card.querySelector('.project-status');
    if (status) {
        
        status.textContent = project.status || '';
        // Hide if empty
        if (!project.status) {
            status.style.display = 'none';
        }
    }
    
    // Set dates
    const dateStart = card.querySelector('.project-date-start');
    const dateEnd = card.querySelector('.project-date-end');
    const dateSeparator = card.querySelector('.project-date-separator');
    
    if (dateStart && project.dates) {
        dateStart.textContent = project.dates.start || '';
    }
    if (dateEnd && project.dates) {
        dateEnd.textContent = project.dates.end || '';
    }
    
    // Hide date separator if only one date
    if (dateSeparator && project.dates) {
        const hasStart = project.dates.start;
        const hasEnd = project.dates.end;
        if (!hasStart || !hasEnd) {
            dateSeparator.style.display = 'none';
        }
    }
    
    // Hide entire date range if no dates
    const dateRange = card.querySelector('.project-date-range');
    if (dateRange && project.dates) {
        const hasDates = (project.dates.start || project.dates.end);
        if (!hasDates) {
            dateRange.style.display = 'none';
        }
    }
    
    // Set project link
    const projectLink = card.querySelector('.project-link');
    if (projectLink && project.slug) {
        projectLink.href = `../project?slug=${encodeURIComponent(project.slug)}`;
        projectLink.setAttribute('data-project-slug', project.slug);
    } else if (projectLink) {
        // Disable link if no slug
        projectLink.href = '#';
        projectLink.addEventListener('click', (e) => e.preventDefault());
    }
    
    return card;
}


/**
 * Display empty message when no projects found
 * @param {HTMLElement} container - Container element
 */
function displayEmptyMessage(container) {
    const emptyMessage = document.createElement('div');
    emptyMessage.className = 'col-12 text-center py-5';
    emptyMessage.innerHTML = '<p class="text-muted">No projects available at this time.</p>';
    container.appendChild(emptyMessage);
}

/**
 * Display error message
 * @param {string} message - Error message to display
 */
function displayErrorMessage(message) {
    const galleryRow = document.getElementById('gallery-row');
    if (galleryRow) {
        galleryRow.innerHTML = `
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    ${message}
                </div>
            </div>
        `;
    }
}

/**
 * Set up click event listener using event delegation on gallery container
 * This handles clicks on all project cards, even dynamically added ones
 */
function setupProjectClickListeners() {
    document.querySelectorAll('[data-project-slug]').forEach((card)=> {
        card.addEventListener('click', function() {
            if (window.pushProject) {
                const projectSlug = card.getAttribute('data-project-slug');
                window.pushProject({
                slug : projectSlug
                })
            }
        });
        
    })
}