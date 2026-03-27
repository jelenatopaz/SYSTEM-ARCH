<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_current_sitin.php");
    exit;
}

$record_id = intval($_POST['record_id'] ?? 0);

if (!$record_id) {
    header("Location: admin_current_sitin.php?error=invalid");
    exit;
}

// Verify the record exists and is still active (no time_out yet)
$stmt = $pdo->prepare("
    SELECT r.id, r.student_id FROM sit_in_records r
    WHERE r.id = ? AND r.time_out IS NULL
");
$stmt->execute([$record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    // Already timed out or doesn't exist
    header("Location: admin_current_sitin.php?error=already_done");
    exit;
}

// Set time_out to NOW()
$pdo->prepare("UPDATE sit_in_records SET time_out = NOW() WHERE id = ?")
    ->execute([$record_id]);

header("Location: admin_current_sitin.php?success=timeout");
exit;