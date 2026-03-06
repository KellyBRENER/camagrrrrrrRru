<?php
session_start();
session_unset();    // Supprime les variables de session
session_destroy();  // Détruit la session
header("Location: /?page=home"); // Redirection vers l'accueil
exit;