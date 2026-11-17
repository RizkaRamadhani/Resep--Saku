<?php
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'resep--saku');
define('DB_USER', 'root');
define('DB_PASS', '');

$jwtSecret = getenv('RESEP_SAKU_JWT_SECRET');
define(
    'JWT_SECRET',
    is_string($jwtSecret) && $jwtSecret !== ''
        ? $jwtSecret
        : 'GANTI_DENGAN_SECRET_ACAK_MINIMAL_32_KARAKTER'
);

define('JWT_TTL_SECONDS', 86400);
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/recipes');
define('UPLOAD_URL_PREFIX', '/Resep--Saku/uploads/recipes');
