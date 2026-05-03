<?php
// voir_ordonnance.php
// Sert le fichier ordonnance de façon sécurisée :
// - Vérifie que l'utilisateur est connecté
// - Vérifie que le fichier lui appartient (client) ou qu'il est pharmacien
// - Ne révèle jamais le chemin réel du fichier

session_start();
require_once 'config/database.php';

// Doit être connecté
if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: connexion.php');
    exit();
}

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['utilisateur_role'];
$uid  = $_SESSION['utilisateur_id'];

if ($id === 0) {
    http_response_code(400);
    die("Identifiant manquant.");
}

// Récupérer l'ordonnance
$stmt = $pdo->prepare("SELECT * FROM ordonnances WHERE id = ?");
$stmt->execute([$id]);
$ordo = $stmt->fetch();

if (!$ordo) {
    http_response_code(404);
    die("Ordonnance introuvable.");
}

// Vérification des droits
// - Un client ne peut voir QUE ses propres ordonnances
// - Un pharmacien peut voir toutes les ordonnances
if ($role === 'client' && $ordo['client_id'] !== $uid) {
    http_response_code(403);
    die("Accès refusé.");
}

$chemin = $ordo['fichier_ordonnance'];

if (!file_exists($chemin)) {
    http_response_code(404);
    die("Fichier introuvable sur le serveur.");
}

// Détecter le type MIME du fichier
$mime = mime_content_type($chemin);
$mimes_ok = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];

if (!in_array($mime, $mimes_ok)) {
    http_response_code(403);
    die("Type de fichier non autorisé.");
}

// Envoyer le fichier au navigateur
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header('Content-Disposition: inline; filename="ordonnance_' . $id . '"');
readfile($chemin);
exit();