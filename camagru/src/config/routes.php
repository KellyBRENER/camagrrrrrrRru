<?php
return [
    'home'    => ['controller' => 'PageController', 'method' => 'home',    'public' => true],
    'gallery' => ['controller' => 'PageController', 'method' => 'gallery', 'public' => true],
    'studio'  => ['controller' => 'AuthController', 'method' => 'studio',  'public' => false],
    'login'   => ['controller' => 'AuthController', 'method' => 'login',   'public' => true],
];