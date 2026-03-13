<?php
require_once "db.php";

$newPassword = password_hash("admin123", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admins SET `password` = 'admin123' WHERE username = 'admin'");
$stmt->execute([$newPassword]);

echo "Done! Password is now: admin123";
?>
