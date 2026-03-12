<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camagru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <script>
        // Configuration transmise de PHP à JS
        window.userConfig = {
            isLoggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>,
            username: "<?php echo $_SESSION['username'] ?? ''; ?>"
        };
    </script>
</head>
<body class="app-shell">
    <?php include __DIR__ . '/header.php'; ?>

    <main id="content" class="container app-content">
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