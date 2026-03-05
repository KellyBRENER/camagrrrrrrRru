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

// Fonction pour vérifier l'authentification de l'utilisateur
export function router() {
    // 1. On récupère le paramètre 'page' dans l'URL (ex: ?page=studio)
    const urlParams = new URLSearchParams(window.location.search);
    let page = urlParams.get('page');

    // 2. Logique de décision
    if (!page) {
        page = 'home'; // Si aucune page demandée -> Home
    } else if (!allowedPages.includes(page)) {
        page = '404';  // Si page non autorisée -> 404
    }

    // 3. On charge la page décidée
    loadPage(page);
}

export function checkAuth() {
    return fetch('/api/check-auth.php') // Un petit script PHP qui renvoie du JSON
        .then(res => res.json())
        .then(data => {
            // data ressemblera à { loggedIn: true, username: 'Kelly' }
            updateUI(data);
        });
}

export function updateUI(auth) {
    const nav = document.querySelector('nav');
    if (auth.loggedIn) {
        nav.innerHTML = `<a data-page="home">Home</a> 
                        <a data-page="studio">Studio</a> 
                        <span>Hello ${auth.username}</span>
                        <a href="/logout">Logout</a>`;
    } else {
        nav.innerHTML = `<a data-page="home">Home</a> 
                        <a data-page="login">Login</a>`;
    }
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