<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    $db->insert('logs', [
        'usuario_id' => $_SESSION['user_id'],
        'accion' => 'Logout',
        'descripcion' => 'Cierre de sesión',
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
}

session_destroy();
header('Location: login.php');
exit;