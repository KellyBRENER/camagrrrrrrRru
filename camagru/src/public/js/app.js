import { loadPage, router } from './utils.js';

// Liste des pages autorisées
const allowedPages = ['home', 'studio', 'galerie', 'setup'];

// On attache les événements
document.querySelectorAll('a[data-page]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        loadPage(link.dataset.page);
    });
});

window.addEventListener('DOMContentLoaded', router);
window.addEventListener('popstate', router);