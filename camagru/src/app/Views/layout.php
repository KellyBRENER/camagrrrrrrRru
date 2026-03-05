<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camagru</title>
    <link rel="stylesheet" href="/css/style.css">
    <script>
        // Configuration transmise de PHP à JS
        window.userConfig = {
            isLoggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>,
            username: "<?php echo $_SESSION['username'] ?? ''; ?>"
        };
    </script>
</head>
<body>
    <?php require __DIR__ . '/header.php'; // On suppose que tout est dans le même dossier Views ?>

    <main id="content" style="padding:20px;">
        <?php 
            // C'est ici que la magie opère : 
            // On affiche la vue demandée dès le chargement de la page
            if (isset($viewPath) && file_exists($viewPath)) {
                require $viewPath;
            }
        ?>
    </main>

    <?php require __DIR__ . '/footer.php'; ?>

    <script type="module" src="/js/app.js"></script>
</body>
</html>