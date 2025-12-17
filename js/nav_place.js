fetch('/partials/navbar.html')
  .then(r => r.text())
  .then(html => {
    document.getElementById('navbar').innerHTML = html;

    const path = location.pathname;
    document.querySelectorAll('nav a').forEach(a => {
      const href = a.getAttribute('href');
      // Special case for home page
      if (
        (href === '/' && (path === '/' || path === '/index.html')) ||
        (href !== '/' && path.endsWith(href.replace(/^\//, "")))
      ) {
        a.classList.add('active');
      }
    });
  });
