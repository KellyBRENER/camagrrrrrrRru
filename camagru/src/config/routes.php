<?php
return [
    'home'    => ['controller' => 'PageController', 'method' => 'home',    'public' => true],
    'gallery' => ['controller' => 'PageController', 'method' => 'gallery', 'public' => true],
    'registerinprogress' => ['controller' => 'PageController', 'method' => 'registerinprogress', 'public' => true],
    'studio'  => ['controller' => 'AuthController', 'method' => 'studio',  'public' => false],
    'photo_create' => ['controller' => 'PhotoController', 'method' => 'create', 'public' => false],
    'photo_mine' => ['controller' => 'PhotoController', 'method' => 'mine', 'public' => false],
    'photo_delete' => ['controller' => 'PhotoController', 'method' => 'delete', 'public' => false],
    'photo_public_list' => ['controller' => 'PhotoController', 'method' => 'publicList', 'public' => true],
    'photo_hashtag_list' => ['controller' => 'PhotoController', 'method' => 'hashtagList', 'public' => true],
    'photo_like_toggle' => ['controller' => 'PhotoController', 'method' => 'toggleLike', 'public' => false],
    'photo_comments' => ['controller' => 'PhotoController', 'method' => 'comments', 'public' => true],
    'photo_comment_add' => ['controller' => 'PhotoController', 'method' => 'addComment', 'public' => false],
    'login'   => ['controller' => 'AuthController', 'method' => 'login',   'public' => true],
    'register'=> ['controller' => 'AuthController', 'method' => 'register','public' => true],
    'resend_validation'=> ['controller' => 'AuthController', 'method' => 'resendValidation','public' => true],
    'verify'  => ['controller' => 'AuthController', 'method' => 'verify',  'public' => true],
];
