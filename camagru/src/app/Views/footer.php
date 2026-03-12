<?php
//menu de navigation (accueil, galerie, studio, profil)
//le menu de navigation doit être responsive (adapté aux mobiles)

// On récupère les routes pour savoir quoi afficher
$routes = require __DIR__ . '/../../config/routes.php';
?>
<footer class="leopard-bar">
    <nav class="d-flex justify-content-around w-100 bg-transparent"> 
        <a href="#" data-page="home" class="nav-link-leopard p-3" title="Accueil">
            <i class="bi bi-house-fill fs-4"></i>
        </a>
        <a href="#" data-page="gallery" class="nav-link-leopard p-3" title="Galerie">
            <i class="bi bi-images fs-4"></i>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="#" data-page="studio" class="nav-link-leopard p-3" title="Studio">
                <i class="bi bi-camera-fill fs-4"></i>
            </a>
            <a href="#" data-page="profil" class="nav-link-leopard p-3" title="Profil">
                <i class="bi bi-person-circle fs-4"></i>
            </a>
        <?php else: ?>
            <a href="#" data-page="login" class="nav-link-leopard p-3" title="Connexion">
                <i class="bi bi-key-fill fs-4"></i>
            </a>
        <?php endif; ?>
    </nav>
</footer>