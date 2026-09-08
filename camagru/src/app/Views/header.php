<?php
//possibilité de se déconnecté à tout instant
//affiche le logo du site et le bouton déconnexion si le user est connecté
?>
<header class="navbar leopard-bar px-4 py-2">
    <a href="#" data-page="home" class="navbar-brand leopard-title">
        🐆 Camagrrrrrru
    </a>
    <div class="d-flex align-items-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="me-3 d-none d-sm-inline text-white">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </span>
            <form action="/logout.php" method="POST" class="m-0">
                <?php echo Security::csrfField(); ?>
                <button type="submit" class="btn btn-sm btn-danger shadow">
                <i class="bi bi-box-arrow-right"></i> Quitter
                </button>
            </form>
        <?php else: ?>
            <a href="#" data-page="login" class="btn btn-sm btn-dark shadow">
                <i class="bi bi-box-arrow-in-right"></i> Connexion
            </a>
        <?php endif; ?>
    </div>
</header>