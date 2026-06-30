<?php
require_once __DIR__ . '/../config.php';
if (!$isLoggedIn || !$isAdmin) {
    if (isset($_GET['ajax']) || (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Access denied']);
        exit;
    }
    if (!headers_sent()) {
        header('Location: ' . ($baseUrl !== '' ? $baseUrl : '/'));
    }
    exit('<script>window.location.href="' . ($baseUrl !== '' ? $baseUrl : '/') . '";</script>');
}
