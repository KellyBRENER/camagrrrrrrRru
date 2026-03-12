<div class="welcome-savane">
    <h1><span class="paw-print">🐾</span> Bienvenue sur Camagru <span class="paw-print">🐾</span></h1>
    <p>Votre plateforme de partage de photos personnalisées avec un style félin unique !</p>
    <p>Connectez-vous pour accéder à votre studio de création, partager vos images dans la galerie et rejoindre notre communauté sauvage.</p>
    
    <div class="mt-4">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="#" data-page="register" class="btn-savane me-3">Rejoindre la meute</a>
            <a href="#" data-page="login" class="btn-savane-secondary">Se connecter</a>
        <?php else: ?>
            <a href="#" data-page="studio" class="btn-savane me-3">📸 Studio</a>
            <a href="#" data-page="gallery" class="btn-savane-secondary">🖼️ Galerie</a>
        <?php endif; ?>
    </div>
</div>

<div class="savane-card mt-4">
    <h3>🦁 Fonctionnalités</h3>
    <ul>
        <li>📸 Créez des photos uniques avec nos filtres félins</li>
        <li>🖼️ Partagez vos créations dans la galerie publique</li>
        <li>💬 Commentez et aimez les photos de la communauté</li>
        <li>👤 Gérez votre profil et vos préférences</li>
    </ul>
</div>