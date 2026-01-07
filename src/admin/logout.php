<?php
// src/admin/logout.php
require_once __DIR__ . '/../includes/security.php';

// Aktuelle Session sicher beenden
start_secure_session();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Neue Session starten, Flash setzen und auf Startseite umleiten
start_secure_session();
$_SESSION['flash_success'] = 'Erfolgreich abgemeldet.';
header("Location: /index.php");
exit();
