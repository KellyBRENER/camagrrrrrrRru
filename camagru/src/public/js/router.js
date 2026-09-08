export function setActiveFooterTab(page) {
    const currentPage = page;
    const footerLinks = document.querySelectorAll('footer nav a[data-page]');

    footerLinks.forEach((link) => {
        const linkPage = link.dataset.page || '';
        const isCurrent = linkPage === currentPage;
        link.classList.toggle('is-current', isCurrent);
        link.setAttribute('aria-current', isCurrent ? 'page' : 'false');
    });
}

function loadPageScript(page) {
    const pageScripts = {
        gallery: 'gallery',
        home: 'home',
        login: 'login',
        profil: 'profil',
        register: 'register',
        studio: 'studio'
    };
    const scriptName = Object.prototype.hasOwnProperty.call(pageScripts, page) ? pageScripts[page] : null;
    if (!scriptName) {
        return;
    }

    console.info('[ROUTER] loading page script', { page, scriptName });

    import(`/js/pages/${scriptName}.js?v=${encodeURIComponent(window.userConfig.scriptVersion)}`)
        .then(module => {
            if (module.init) {
                console.info('[ROUTER] init page script', { page, scriptName });
                module.init();
            }
        })
        .catch(err => console.error("impossible de charger le script de la page", err));
}

// utils.js - fonctions utilitaires pour l'application
export function loadPage(page, queryParams = new URLSearchParams()) {
    const params = new URLSearchParams(queryParams);
    params.set('page', page);

    const pageUrl = `index.php?${params.toString()}`;
    const nextUrl = `?${params.toString()}`;

    console.info('[ROUTER] loadPage called', { page, url: pageUrl });
    document.getElementById('content').innerHTML = '<p>Chargement...</p>';

    // On utilise l'index.php avec l'en-tête X-Requested-With
    fetch(pageUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (response.status === 403) {
            window.location.href = '/?page=login';
            return null;
        }
        if (!response.ok) throw new Error("Erreur serveur");
        // Only server-rendered HTML views may reach the HTML sink below.
        // JSON can contain raw user comments and must never be parsed as HTML.
        const contentType = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
        if (contentType !== 'text/html') throw new Error('Réponse HTML attendue');
        return response.text();
    })
    .then(html => {
        if (html === null) return;
        // Nettoyage Webcam si on change de page
        if (window.currentStream) {
            window.currentStream.getTracks().forEach(track => track.stop());
            window.currentStream = null;
        }

        document.getElementById('content').innerHTML = html;
        setActiveFooterTab(page);

        // On met à jour l'URL sans recharger
        const sameUrl = window.location.search === nextUrl;
        if (!sameUrl) {
            if (window.history.length <= 1) {
                window.history.replaceState({ page: page }, '', nextUrl);
            } else {
                window.history.pushState({ page: page }, '', nextUrl);
            }
        }

        loadPageScript(page);
    })
    .catch(error => {
        document.getElementById('content').innerHTML = '<p>Erreur lors du chargement.</p>';
    });
}

// app.js ou utils.js
export function router(shouldFetch = true) {
    // 1. On récupère la page dans l'URL actuelle
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page') || 'home';
    console.info('[ROUTER] router resolved page', { page, shouldFetch });

    if (shouldFetch) {
        loadPage(page, urlParams);
        return;
    }

    setActiveFooterTab(page);
    loadPageScript(page);
}

export function updateNavigation() {
    // Le header et ses formulaires sécurisés sont rendus par le serveur.
    window.location.reload();
}
