const REVIEW_CHAR_LIMIT = 180;

document.addEventListener('DOMContentLoaded', loadReviews);

async function loadReviews() {
    const rotator = document.getElementById('review-rotator');
    const summary = document.getElementById('review-summary');
    if (!rotator) return;

    try {
        const response = await fetch('reviews/index.json');
        if (!response.ok) throw new Error(response.status);
        const data = await response.json();
        const loaded = await Promise.all((data.reviews || []).map(loadReview));
        const reviews = loaded.filter(review => review && review.text);

        if (summary && data.google) {
            summary.replaceChildren(...renderGoogleSummary(data.google));
        }

        rotator.replaceChildren(...reviews.map(renderReview));
        playReviews(rotator);
    } catch (error) {
        console.error('Error loading reviews:', error);
    }
}

function renderGoogleSummary(google) {
    const rating = Number(google.rating) || 0;
    const count = Number(google.count) || 0;
    const block = document.createElement(google.link ? 'a' : 'div');
    block.className = 'review-summary-link';
    if (google.link) {
        block.href = google.link;
        block.target = '_blank';
        block.rel = 'noopener noreferrer';
    }

    const eyebrow = document.createElement('p');
    eyebrow.className = 'review-summary-eyebrow';
    eyebrow.textContent = 'Google rating';
    block.appendChild(eyebrow);

    const score = document.createElement('p');
    score.className = 'review-summary-score';

    const value = document.createElement('span');
    value.className = 'review-summary-value';
    value.textContent = rating.toFixed(1);
    score.appendChild(value);

    const stars = document.createElement('span');
    stars.className = 'review-stars';
    stars.textContent = starDisplay(rating);
    stars.setAttribute('aria-label', `${rating.toFixed(1)} out of 5 stars`);
    score.appendChild(stars);
    block.appendChild(score);

    const meta = document.createElement('p');
    meta.className = 'review-summary-meta';
    meta.textContent = count === 1 ? '1 Google review' : `${count} Google reviews`;
    block.appendChild(meta);

    const cta = document.createElement('span');
    cta.className = 'review-summary-cta';
    cta.textContent = 'See all reviews on Google';
    block.appendChild(cta);

    return [block];
}

function starDisplay(rating) {
    const filled = Math.round(Math.max(0, Math.min(5, Number(rating) || 0)));
    return '★'.repeat(filled) + '☆'.repeat(5 - filled);
}

async function loadReview(folder) {
    const base = `reviews/${encodeURIComponent(folder)}`;
    const response = await fetch(`${base}/review.json`);
    if (!response.ok) return null;

    const review = await response.json();
    review.photoUrl = review.photo ? `${base}/${encodeURIComponent(review.photo)}` : '';
    return review;
}

function renderReview(review) {
    const slide = document.createElement('article');
    slide.className = 'review-slide';

    if (review.photoUrl) {
        const photo = document.createElement('img');
        photo.className = 'review-photo';
        photo.src = review.photoUrl;
        photo.alt = '';
        slide.appendChild(photo);
    }

    const rating = Math.max(0, Math.min(5, Number(review.rating) || 0));
    const stars = document.createElement('p');
    stars.className = 'review-stars';
    stars.textContent = starDisplay(rating);
    stars.setAttribute('aria-label', `${rating} out of 5 stars`);
    slide.appendChild(stars);

    const shortened = shortenReview(review.text);
    const quote = document.createElement('blockquote');
    quote.className = 'review-text';
    quote.textContent = shortened.text;
    slide.appendChild(quote);

    if (shortened.trimmed && review.link) {
        slide.appendChild(reviewLink(review.link, 'Read more', 'review-more'));
    }

    const name = document.createElement('p');
    name.className = 'review-name';
    if (review.link) {
        name.appendChild(reviewLink(review.link, review.name || 'Review'));
    } else {
        name.textContent = review.name || '';
    }
    if (review.date) {
        const date = document.createElement('span');
        date.textContent = ` ${formatReviewDate(review.date)}`;
        name.appendChild(date);
    }
    slide.appendChild(name);

    return slide;
}

function shortenReview(text) {
    const clean = text.replace(/\s+/g, ' ').trim();
    if (clean.length <= REVIEW_CHAR_LIMIT) return { text: clean, trimmed: false };

    const cut = clean.slice(0, REVIEW_CHAR_LIMIT);
    const lastSpace = cut.lastIndexOf(' ');
    const shortened = (lastSpace > 80 ? cut.slice(0, lastSpace) : cut).trim();
    return { text: `${shortened}…`, trimmed: true };
}

function reviewLink(href, label, className) {
    const link = document.createElement('a');
    link.href = href;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = label;
    if (className) link.className = className;
    return link;
}

function formatReviewDate(value) {
    const [year, month] = String(value).split('-');
    const parsed = new Date(Number(year), Number(month) - 1, 1);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('en-CA', { month: 'long', year: 'numeric' });
}

function playReviews(rotator) {
    const slides = [...rotator.children];
    if (!slides.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion || slides.length === 1) {
        slides.forEach(slide => slide.classList.add('is-active'));
        return;
    }

    const secondsEach = 8;
    const style = document.createElement('style');
    const fade = 3;
    const slot = 100 / slides.length;
    style.textContent = `@keyframes review-cycle {
        0% { opacity: 0; pointer-events: none; }
        ${fade}% { opacity: 1; pointer-events: auto; }
        ${Math.max(fade, slot - fade)}% { opacity: 1; pointer-events: auto; }
        ${slot}% { opacity: 0; pointer-events: none; }
        100% { opacity: 0; pointer-events: none; }
    }`;
    document.head.appendChild(style);

    slides.forEach((slide, index) => {
        slide.style.animation = `review-cycle ${slides.length * secondsEach}s infinite`;
        slide.style.animationDelay = `${index * secondsEach}s`;
    });
}
