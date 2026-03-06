<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camagru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" href="/favicon.png">
    <script>
        // Configuration transmise de PHP à JS
        window.userConfig = {
            isLoggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>,
            username: "<?php echo $_SESSION['username'] ?? ''; ?>"
        };
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/header.php'; ?>

    <main id="content" class="container flex-grow-1 my-4">
        <?php 
            if (isset($viewPath) && file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<p>Page en cours de chargement...</p>";
            }
        ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script type="module" src="/js/app.js"></script>
</body>

</html>