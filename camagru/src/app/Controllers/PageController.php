<?php
class PageController {
    public function __construct($pdo) { /* Pas besoin de PDO ici pour l'instant */ }

    public function home() { return "home.php"; }
    public function gallery() { return "gallery.php"; }
}