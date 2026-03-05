//menu de navigation (accueil, galerie, studio, profil)
//le menu de navigation doit être responsive (adapté aux mobiles)

<footer style="padding:10px; border-top:1px solid #ccc; margin-top:20px;">
    <nav>
    <?php 
    $routes = require __DIR__ . '/../../config/routes.php';
    foreach ($routes as $name => $config) {
        // On n'affiche le lien que si la page est publique OU si l'user est connecté
        if ($config['public'] || isset($_SESSION['user_id'])) {
            // On peut exclure 'login' si on est déjà connecté, etc.
            echo '<a href="#" data-page="' . $name . '">' . ucfirst($name) . '</a>';
        }
    }
    ?>
</nav>
</footer>