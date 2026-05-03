<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

verifierSession('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $id ? "Modifier" : "Ajouter";
$erreur = "";
$message = "";

// Initialisation des champs
$nom = "";
$email = "";
$telephone = "";
$adresse = "";
$role = isset($_GET['role']) && in_array($_GET['role'], ['client', 'pharmacien']) ? $_GET['role'] : "client";

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    $stmt->closeCursor();

    if (!$user) {
        $_SESSION['erreur_admin'] = "Utilisateur introuvable.";
        header('Location: dashboard_admin.php');
        exit();
    }
    
    $nom = $user['nom'];
    $email = $user['email'];
    $telephone = $user['telephone'] ?? "";
    $adresse = $user['adresse'] ?? "";
    $role = $user['role'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $role = $_POST['role'];
    $mdp = $_POST['mot_de_passe'] ?? "";

    if (empty($nom) || empty($email) || empty($role)) {
        $erreur = "Veuillez remplir les champs obligatoires (nom, email, rôle).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "L'adresse email n'est pas valide.";
    } else {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $erreur = "Cette adresse email est déjà utilisée.";
        } else {
            if ($id) {
                // Modification
                if (!empty($mdp)) {
                    // Update avec mot de passe
                    if (strlen($mdp) < 6) {
                        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
                    } else {
                        $hash = password_hash($mdp, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET nom=?, email=?, mot_de_passe=?, telephone=?, adresse=?, role=? WHERE id=?");
                        $stmt->execute([$nom, $email, $hash, $telephone, $adresse, $role, $id]);
                        $_SESSION['message_admin'] = "Utilisateur modifié avec succès.";
                        header('Location: dashboard_admin.php');
                        exit();
                    }
                } else {
                    // Update sans mot de passe
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom=?, email=?, telephone=?, adresse=?, role=? WHERE id=?");
                    $stmt->execute([$nom, $email, $telephone, $adresse, $role, $id]);
                    $_SESSION['message_admin'] = "Utilisateur modifié avec succès.";
                    header('Location: dashboard_admin.php');
                    exit();
                }
            } else {
                // Ajout
                if (empty($mdp) || strlen($mdp) < 6) {
                    $erreur = "Le mot de passe est obligatoire et doit contenir au moins 6 caractères.";
                } else {
                    $hash = password_hash($mdp, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, telephone, adresse, role) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$nom, $email, $hash, $telephone, $adresse, $role]);
                    $_SESSION['message_admin'] = "Utilisateur ajouté avec succès.";
                    header('Location: dashboard_admin.php');
                    exit();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action; ?> un utilisateur — Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="page-wrapper">

<!-- TOP BAR -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar-item">
            <i class="bi bi-truck"></i>
            <span>Livraison rapide à domicile</span>
        </div>
        <div class="top-bar-item">
            <i class="bi bi-clock-history"></i>
            <span>Devis en moins de 2h</span>
        </div>
        <div class="top-bar-item">
            <i class="bi bi-headset"></i>
            <span>Service client : 24/7</span>
        </div>
    </div>
</div>

<!-- Navbar rapide pour revenir -->
<nav class="navbar" style="border-bottom: 2px solid var(--rouge);">
    <div class="container">
        <a href="index.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
            <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
            </div>
            <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
        </a>
        <div class="navbar-right">
            <a href="dashboard_admin.php" class="btn btn-ghost btn-sm">← Retour au Dashboard</a>
        </div>
    </div>
</nav>

<div class="auth-wrapper" style="margin-top:20px;">
    <div class="auth-box" style="max-width: 500px;">

        <div class="auth-logo">
            
            <?php echo $action; ?> un utilisateur
        </div>

        <?php if ($erreur): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($erreur); ?></div>
        <?php endif; ?>

        <form method="POST">
            
            <div class="form-group">
                <label class="form-label" for="role">
                    Rôle de l'utilisateur <span class="required">*</span>
                </label>
                <select id="role" name="role" class="form-control" required>
                    <option value="client" <?php echo $role === 'client' ? 'selected' : ''; ?>>Client</option>
                    <option value="pharmacien" <?php echo $role === 'pharmacien' ? 'selected' : ''; ?>>Pharmacien</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="nom">
                    Nom complet <span class="required">*</span>
                </label>
                <input type="text" id="nom" name="nom" class="form-control" required
                       value="<?php echo htmlspecialchars($nom); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">
                    Adresse email <span class="required">*</span>
                </label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="mot_de_passe">
                    Mot de passe <?php if (!$id) echo '<span class="required">*</span>'; ?>
                </label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control"
                       placeholder="<?php echo $id ? 'Laissez vide pour conserver le mot de passe actuel' : '••••••••'; ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="telephone">Téléphone</label>
                <input type="text" id="telephone" name="telephone" class="form-control"
                       value="<?php echo htmlspecialchars($telephone); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="adresse">Adresse (Clients uniquement)</label>
                <textarea id="adresse" name="adresse" class="form-control" rows="2" placeholder="Facultatif pour les pharmaciens"><?php echo htmlspecialchars($adresse); ?></textarea>
            </div>

            <button type="submit" class="btn btn-danger btn-block">
                <i class="bi bi-check-circle-fill"></i> Enregistrer
            </button>
        </form>

    </div>
</div>

<footer class="footer-simple">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
    </div>
</footer>
</div>
</body>
</html>
