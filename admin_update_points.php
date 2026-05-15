<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number'] ?? '');
    $points    = intval($_POST['points'] ?? 0);
    if ($id_number && $points !== 0) {
        $pdo->prepare("UPDATE students SET points = GREATEST(0, COALESCE(points,0) + ?) WHERE id_number = ?")
            ->execute([$points, $id_number]);
    }
}
header("Location: admin_leaderboard.php?updated=1");
exit;