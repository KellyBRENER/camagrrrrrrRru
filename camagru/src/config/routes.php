<?php
return [
    'home'    => ['controller' => 'PageController', 'method' => 'home',    'public' => true],
    'gallery' => ['controller' => 'PageController', 'method' => 'gallery', 'public' => true],
    'registerinprogress' => ['controller' => 'PageController', 'method' => 'registerinprogress', 'public' => true],
    'studio'  => ['controller' => 'AuthController', 'method' => 'studio',  'public' => false],
    'login'   => ['controller' => 'AuthController', 'method' => 'login',   'public' => true],
    'register'=> ['controller' => 'AuthController', 'method' => 'register','public' => true],
    'verify'  => ['controller' => 'AuthController', 'method' => 'verify',  'public' => true],
];