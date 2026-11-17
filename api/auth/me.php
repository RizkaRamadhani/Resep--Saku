<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('GET');

$user = require_auth();
json_response([
    'success' => true,
    'data' => [
        'id_user' => (int)$user['id_user'],
        'username' => $user['username'],
        'role' => $user['role']
    ]
]);
