// utils.js - fonctions utilitaires pour l'application
export function loadPage(page) {
    document.getElementById('content').innerHTML = '<p>Chargement...</p>';

    // On utilise l'index.php avec l'en-tête X-Requested-With
    fetch(`index.php?page=${page}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (response.status === 403) return loadPage('login'); // Sécurité
        if (!response.ok) throw new Error("Erreur serveur");
        return response.text();
    })
    .then(html => {
        // Nettoyage Webcam si on change de page
        if (window.currentStream) {
            window.currentStream.getTracks().forEach(track => track.stop());
            window.currentStream = null;
        }

        document.getElementById('content').innerHTML = html;
        
        // On met à jour l'URL sans recharger
        window.history.pushState({page: page}, "", `?page=${page}`);

        // Chargement dynamique du JS spécifique à la page
        // On vérifie si le fichier existe avant d'importer
        import(`/js/pages/${page}.js`).catch(err => console.log("Pas de JS spécifique pour cette page"));
    })
    .catch(error => {
        document.getElementById('content').innerHTML = '<p>Erreur lors du chargement.</p>';
    });
}

// app.js ou utils.js
export function router() {
    // 1. On récupère la page dans l'URL actuelle
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page') || 'home';

    // 2. On charge la page (loadPage s'occupe de l'AJAX et du JS spécifique)
    loadPage(page);
}

export function updateNavigation() {
    const nav = document.querySelector('nav');
    
    if (window.userConfig.isLoggedIn) {
        // Menu pour connectés
        nav.innerHTML = `
            <a data-page="home">Accueil</a>
            <a data-page="studio">Studio</a>
            <a href="/logout">Déconnexion (${window.userConfig.username})</a>
        `;
    } else {
        // Menu pour visiteurs
        nav.innerHTML = `
            <a data-page="home">Accueil</a>
            <a data-page="galerie">Galerie</a>
            <a data-page="login">Connexion</a>
        `;
    }
}