<?php
// Exercise the origin used by verification, reset and notification emails.
(function () {
    $savedServer = $_SERVER;
    $savedUrl = getenv('APP_URL');
    $savedOrigins = getenv('APP_ALLOWED_ORIGINS');
    $fallback = 'https://192.168.1.42:8443';
    try {
        putenv('APP_URL=' . $fallback);
        putenv('APP_ALLOWED_ORIGINS=http://localhost:8081,https://localhost:8443,http://192.168.1.42:8081,' . $fallback);
        foreach ([
            ['localhost:8081', false, 'http://localhost:8081'],
            ['localhost:8443', true, 'https://localhost:8443'],
            ['192.168.1.42:8081', false, 'http://192.168.1.42:8081'],
            ['192.168.1.42:8443', true, $fallback],
            ['LOCALHOST:8081', false, 'http://localhost:8081'],
            ['attacker.invalid', false, $fallback],
            ['localhost.attacker.invalid:8081', false, $fallback],
            ['localhost:8081@attacker.invalid', false, $fallback],
            ['localhost:8082', false, $fallback],
            ['localhost:8443', false, $fallback],
            ['localhost:8081/path', false, $fallback],
            ["localhost:8081\r\nX-Fake: value", false, $fallback],
            ['', false, $fallback],
        ] as [$host, $tls, $expected]) {
            $_SERVER = ['HTTP_HOST' => $host, 'HTTPS' => $tls ? 'on' : 'off'];
            check(Security::appUrl() === $expected, 'Origine email contrôlée : ' . json_encode([$host, $tls]));
        }
        $_SERVER = [
            'HTTP_HOST' => 'attacker.invalid',
            'HTTP_X_FORWARDED_HOST' => 'localhost:8443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_FORWARDED' => 'host=localhost:8443;proto=https',
            'HTTP_ORIGIN' => 'https://localhost:8443',
            'HTTP_REFERER' => 'https://localhost:8443/',
        ];
        check(Security::appUrl() === $fallback, 'En-têtes indirects ignorés pour les liens email');
        $_SERVER = ['HTTP_HOST' => 'localhost:8081'];
        putenv('APP_ALLOWED_ORIGINS');
        check(Security::appUrl() === $fallback, 'Liste absente : repli sur APP_URL');
        putenv('APP_ALLOWED_ORIGINS=http://localhost:8081/path,http://user@localhost:8081');
        check(Security::appUrl() === $fallback, 'Entrées non conformes à une origine ignorées');
    } finally {
        $_SERVER = $savedServer;
        putenv($savedUrl === false ? 'APP_URL' : 'APP_URL=' . $savedUrl);
        putenv($savedOrigins === false ? 'APP_ALLOWED_ORIGINS' : 'APP_ALLOWED_ORIGINS=' . $savedOrigins);
    }
})();
