<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_sitin.php");
    exit;
}

$student_id = intval($_POST['student_id'] ?? 0);
$purpose    = trim($_POST['purpose'] ?? '');
$lab        = trim($_POST['lab'] ?? '');

if (!$student_id || !$purpose || !$lab) {
    header("Location: admin_sitin.php?error=missing_fields");
    exit;
}

// --- 1. Verify student exists ---
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: admin_sitin.php?error=not_found");
    exit;
}

// --- 2. Block if student already has an ACTIVE sit-in (time_out IS NULL) ---
$activeCheck = $pdo->prepare("
    SELECT id FROM sit_in_records
    WHERE student_id = ? AND time_out IS NULL
    LIMIT 1
");
$activeCheck->execute([$student_id]);

if ($activeCheck->fetch()) {
    // Student is already sitting in — reject and redirect with error
    header("Location: admin_sitin.php?error=already_active&query=" . urlencode($student['id_number']));
    exit;
}

// --- 3. Block if no remaining sessions ---
$sessions = intval($student['sessions'] ?? 0);
if ($sessions <= 0) {
    header("Location: admin_sitin.php?error=no_sessions&query=" . urlencode($student['id_number']));
    exit;
}

// --- 4. Insert sit-in record ---
$insert = $pdo->prepare("
    INSERT INTO sit_in_records (student_id, purpose, lab, time_in)
    VALUES (?, ?, ?, NOW())
");
$insert->execute([$student_id, $purpose, $lab]);

// --- 5. Deduct one session from student ---
$pdo->prepare("UPDATE students SET sessions = sessions - 1 WHERE id = ?")
    ->execute([$student_id]);

header("Location: admin_sitin.php?success=sitin&query=" . urlencode($student['id_number']));
exit;