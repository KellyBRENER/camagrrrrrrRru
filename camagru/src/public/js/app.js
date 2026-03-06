import { loadPage, router } from './utils.js';

// Liste des pages autorisées
const allowedPages = ['home', 'studio', 'galerie', 'setup'];

document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-page]'); // Cherche si on a cliqué sur un lien data-page
    if (link) {
        e.preventDefault();
        const page = link.dataset.page;
        loadPage(page);
    }
});

window.addEventListener('DOMContentLoaded', router);
window.addEventListener('popstate', router);