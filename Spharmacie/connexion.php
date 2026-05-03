<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Si déjà connecté, rediriger selon le rôle
if (isset($_SESSION['utilisateur_id'])) {
    if ($_SESSION['utilisateur_role'] === 'admin') {
        $dest = 'dashboard_admin.php';
    } elseif ($_SESSION['utilisateur_role'] === 'pharmacien') {
        $dest = 'dashboard_pharmacien.php';
    } elseif ($_SESSION['utilisateur_role'] === 'client') {
        $dest = 'dashboard_client.php';
    } else {
        session_destroy();
        header("Location: connexion.php");
        exit();
    }
    header("Location: $dest"); exit();
}

$erreur_connexion = "";
$erreur_inscription = "";
$succes_inscription = "";
$active_panel = (isset($_GET['action']) && $_GET['action'] === 'signup') ? "right-panel-active" : ""; // Pour garder le panel actif après un POST ou via URL

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_connexion'])) {
        // --- LOGIQUE CONNEXION ---
        $email = trim($_POST['email']);
        $mdp   = $_POST['mot_de_passe'];

        if (empty($email) || empty($mdp)) {
            $erreur_connexion = "Veuillez remplir tous les champs.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $stmt->closeCursor();

            if ($user && password_verify($mdp, $user['mot_de_passe'])) {
                $_SESSION['utilisateur_id']   = $user['id'];
                $_SESSION['utilisateur_nom']  = $user['nom'];
                $_SESSION['utilisateur_role'] = $user['role'];
                header('Location: ' . ($user['role'] === 'admin' ? 'dashboard_admin.php' : ($user['role'] === 'client' ? 'dashboard_client.php' : 'dashboard_pharmacien.php')));
                exit();
            } else {
                $erreur_connexion = "Email ou mot de passe incorrect.";
            }
        }
    } elseif (isset($_POST['action_inscription'])) {
        // --- LOGIQUE INSCRIPTION ---
        $active_panel = "right-panel-active";
        $nom       = trim($_POST['nom']);
        $email     = trim($_POST['email']);
        $mdp       = $_POST['mot_de_passe'];
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($nom) || empty($email) || empty($mdp)) {
            $erreur_inscription = "Tous les champs obligatoires doivent être remplis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur_inscription = "L'adresse email n'est pas valide.";
        } elseif (strlen($mdp) < 6) {
            $erreur_inscription = "Le mot de passe doit contenir au moins 6 caractères.";
        } else {
            $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $check->execute([$email]);
            $existe = $check->fetch();
            $check->closeCursor();

            if ($existe) {
                $erreur_inscription = "Cette adresse email est déjà utilisée.";
            } else {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, telephone, role) VALUES (?,?,?,?,'client')");
                if ($stmt->execute([$nom, $email, $hash, $telephone])) {
                    $succes_inscription = "Inscription réussie ! Vous pouvez vous connecter.";
                    $active_panel = ""; // Retour au panel de connexion
                } else {
                    $erreur_inscription = "Une erreur est survenue. Veuillez réessayer.";
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
    <title>Authentification — Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sliding-auth.css">
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-page-body">

<div class="auth-container-main <?php echo $active_panel; ?>" id="container">
    
    <!-- INSCRIPTION -->
    <div class="form-container sign-up-container">
        <form method="POST" action="connexion.php">
            <h1>Créer un compte</h1>
            <div style="margin-top: 20px;"></div>
            
            <?php if ($erreur_inscription): ?>
                <div class="alert alert-danger" style="background:#fdecea; color:#ca3e32; border:1px solid #f9cfcc;"><?php echo htmlspecialchars($erreur_inscription); ?></div>
            <?php endif; ?>

            <input type="text" name="nom" placeholder="Nom complet" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" />
            <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
            <input type="text" name="telephone" placeholder="Téléphone (Optionnel)" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" />
            <input type="password" name="mot_de_passe" placeholder="Mot de passe" required />
            <button type="submit" name="action_inscription">S'inscrire</button>
        </form>
    </div>

    <!-- CONNEXION -->
    <div class="form-container sign-in-container">
        <form method="POST" action="connexion.php">
            <h1>Se connecter</h1>
            <div style="margin-top: 20px;"></div>

            <?php if ($erreur_connexion): ?>
                <div class="alert alert-danger" style="background:#fdecea; color:#ca3e32; border:1px solid #f9cfcc;"><?php echo htmlspecialchars($erreur_connexion); ?></div>
            <?php endif; ?>
            
            <?php if ($succes_inscription): ?>
                <div class="alert alert-success" style="background:#e6f4ef; color:#0b7b5c; border:1px solid #c3e6db;"><?php echo htmlspecialchars($succes_inscription); ?></div>
            <?php endif; ?>

            <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
            <input type="password" name="mot_de_passe" placeholder="Mot de passe" required />
           
            <button type="submit" name="action_connexion">Se connecter</button>
        </form>
    </div>

    <!-- OVERLAY -->
    <div class="overlay-container">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <h1>Bon retour !</h1>
                <p>Pour rester connecté avec nous, veuillez vous connecter avec vos informations personnelles</p>
                <button class="ghost" id="signIn">Se connecter</button>
            </div>
            <div class="overlay-panel overlay-right">
                <h1>Salut, l'ami !</h1>
                <p>Entrez vos coordonnées personnelles et commencez votre voyage avec nous</p>
                <button class="ghost" id="signUp">S'inscrire</button>
            </div>
        </div>
    </div>
</div>

<div class="auth-footer" style="margin-top: 30px; text-align: center;">
    <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: color 0.3s ease;">
        <i class="bi bi-arrow-left"></i> Retour à l'accueil
    </a>
</div>

<script>
    const signUpButton = document.getElementById('signUp');
    const signInButton = document.getElementById('signIn');
    const container = document.getElementById('container');

    signUpButton.addEventListener('click', () => {
        container.classList.add("right-panel-active");
    });

    signInButton.addEventListener('click', () => {
        container.classList.remove("right-panel-active");
    });
</script>
</body>
</html>