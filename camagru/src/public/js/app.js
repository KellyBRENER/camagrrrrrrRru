import { loadPage, router, setActiveFooterTab } from './utils.js';

// Liste des pages autorisées
const allowedPages = ['home', 'studio', 'gallery', 'setup'];

function applyResponsiveShellSizes() {
    const root = document.documentElement;
    const width = window.innerWidth;
    const height = window.innerHeight;
    const isMobile = width <= 767;
    const isPortrait = height >= width;

    let values;

    if (isMobile && isPortrait) {
        values = {
            contentPadY: '0.7rem',
            barBorder: '2px',
            headerPadY: '0.35rem',
            headerPadX: '0.7rem',
            titleSize: '0.95rem',
            titleSpacing: '1px',
            headerBtnPadY: '0.2rem',
            headerBtnPadX: '0.45rem',
            headerBtnSize: '0.75rem',
            footerLinkPadY: '9px',
            footerIconSize: '1.15rem',
            footerActiveScale: '1.2'
        };
    } else if (isMobile) {
        values = {
            contentPadY: '0.6rem',
            barBorder: '2px',
            headerPadY: '0.25rem',
            headerPadX: '0.65rem',
            titleSize: '0.9rem',
            titleSpacing: '0.8px',
            headerBtnPadY: '0.16rem',
            headerBtnPadX: '0.4rem',
            headerBtnSize: '0.72rem',
            footerLinkPadY: '7px',
            footerIconSize: '1.05rem',
            footerActiveScale: '1.15'
        };
    } else if (width <= 1024) {
        values = {
            contentPadY: '0.95rem',
            barBorder: '3px',
            headerPadY: '0.45rem',
            headerPadX: '1rem',
            titleSize: '1.2rem',
            titleSpacing: '1.4px',
            headerBtnPadY: '0.23rem',
            headerBtnPadX: '0.55rem',
            headerBtnSize: '0.8rem',
            footerLinkPadY: '12px',
            footerIconSize: '1.3rem',
            footerActiveScale: '1.25'
        };
    } else {
        values = {
            contentPadY: '1rem',
            barBorder: '3px',
            headerPadY: '0.5rem',
            headerPadX: '1.5rem',
            titleSize: '1.45rem',
            titleSpacing: '2px',
            headerBtnPadY: '0.25rem',
            headerBtnPadX: '0.6rem',
            headerBtnSize: '0.85rem',
            footerLinkPadY: '15px',
            footerIconSize: '1.5rem',
            footerActiveScale: '1.3'
        };
    }

    root.style.setProperty('--shell-content-pad-y', values.contentPadY);
    root.style.setProperty('--shell-bar-border-width', values.barBorder);
    root.style.setProperty('--shell-header-pad-y', values.headerPadY);
    root.style.setProperty('--shell-header-pad-x', values.headerPadX);
    root.style.setProperty('--shell-title-size', values.titleSize);
    root.style.setProperty('--shell-title-spacing', values.titleSpacing);
    root.style.setProperty('--shell-header-btn-pad-y', values.headerBtnPadY);
    root.style.setProperty('--shell-header-btn-pad-x', values.headerBtnPadX);
    root.style.setProperty('--shell-header-btn-font-size', values.headerBtnSize);
    root.style.setProperty('--shell-footer-link-pad-y', values.footerLinkPadY);
    root.style.setProperty('--shell-footer-icon-size', values.footerIconSize);
    root.style.setProperty('--shell-footer-active-scale', values.footerActiveScale);
}

function activateTabImmediately(page) {
    if (!page) {
        return;
    }

    setActiveFooterTab(page);
}

document.addEventListener('pointerdown', (e) => {
    const link = e.target.closest('footer nav a[data-page]');
    if (!link) {
        return;
    }

    activateTabImmediately(link.dataset.page);
});

document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-page]'); // Cherche si on a cliqué sur un lien data-page
    if (link) {
        e.preventDefault();
        const page = link.dataset.page;
        activateTabImmediately(page);
        loadPage(page);
    }
});

window.addEventListener('DOMContentLoaded', () => {
    applyResponsiveShellSizes();
    router();
});
window.addEventListener('resize', applyResponsiveShellSizes);
window.addEventListener('orientationchange', applyResponsiveShellSizes);
window.addEventListener('popstate', router);