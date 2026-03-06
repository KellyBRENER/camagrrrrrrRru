<?php
//menu de navigation (accueil, galerie, studio, profil)
//le menu de navigation doit être responsive (adapté aux mobiles)

// On récupère les routes pour savoir quoi afficher
$routes = require __DIR__ . '/../../config/routes.php';
?>
<footer class="leopard-bar fixed-bottom">
    <nav class="d-flex justify-content-around">
        <a href="#" data-page="home" class="nav-link-leopard p-3">🏠</a>
        <a href="#" data-page="gallery" class="nav-link-leopard p-3">🖼️</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="#" data-page="studio" class="nav-link-leopard p-3">📸</a>
            <a href="#" data-page="profil" class="nav-link-leopard p-3">👤</a>
        <?php else: ?>
            <a href="#" data-page="login" class="nav-link-leopard p-3">🔑</a>
        <?php endif; ?>
    </nav>
</footer>