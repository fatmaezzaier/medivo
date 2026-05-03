<?php
require_once __DIR__ . '/config/database.php';
$email = 'admin@smartpharmacie.com';
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
$stmt->closeCursor();

if ($user) {
    echo "User found: id={$user['id']}, role={$user['role']}, mdp={$user['mot_de_passe']}\n";
    if (password_verify('admin123', $user['mot_de_passe'])) {
        echo "Password verification successful.\n";
    } else {
        echo "Password verification FAILED.\n";
    }
} else {
    echo "User NOT found.\n";
}
?>
