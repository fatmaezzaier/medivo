<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

verifierSession('admin');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id === $_SESSION['utilisateur_id']) {
        $_SESSION['erreur_admin'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header('Location: dashboard_admin.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Supprimer les commandes liées (en tant que client ou pharmacien)
        $pdo->prepare("DELETE FROM commandes WHERE client_id = ? OR pharmacien_id = ?")->execute([$id, $id]);

        // 2. Supprimer les devis liés (si l'utilisateur est un pharmacien)
        $pdo->prepare("DELETE FROM devis WHERE pharmacien_id = ?")->execute([$id]);

        // 3. Supprimer les devis liés aux ordonnances du client
        // Sous-requête pour MySQL
        $pdo->prepare("DELETE FROM devis WHERE ordonnance_id IN (SELECT id FROM ordonnances WHERE client_id = ?)")->execute([$id]);

        // 4. Supprimer les ordonnances du client
        $pdo->prepare("DELETE FROM ordonnances WHERE client_id = ?")->execute([$id]);

        // 5. Supprimer l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $_SESSION['message_admin'] = "L'utilisateur et toutes ses données liées ont été supprimés avec succès.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['erreur_admin'] = "Erreur lors de la suppression : " . $e->getMessage();
    }

} else {
    $_SESSION['erreur_admin'] = "Identifiant utilisateur manquant.";
}

header('Location: dashboard_admin.php');
exit();
?>