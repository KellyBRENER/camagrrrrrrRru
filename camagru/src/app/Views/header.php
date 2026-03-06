<?php
//possibilité de se déconnecté à tout instant
//affiche le logo du site et le bouton déconnexion si le user est connecté
?>
<header class="navbar leopard-bar px-4 py-2">
    <a href="#" data-page="home" class="navbar-brand leopard-title">
        Camagrrrrrru
    </a>
    <div class="d-flex align-items-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="me-3 d-none d-sm-inline">🐆 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="/logout.php" class="btn btn-sm btn-danger shadow">Quitter</a>
        <?php else: ?>
            <a href="#" data-page="login" class="btn btn-sm btn-dark shadow">Connexion</a>
        <?php endif; ?>
    </div>
</header>