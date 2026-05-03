<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Statistiques (nombre de patients)
$sql = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'client'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt->closeCursor();


$role = isset($_SESSION['utilisateur_role']) ? $_SESSION['utilisateur_role'] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medivo — Accueil</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
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

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
            <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
            </div>
            <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
        </a>
        <div class="navbar-right">
            <a href="index.php" class="btn btn-ghost btn-sm active">Accueil</a>
            <a href="catalogue.php" class="btn btn-ghost btn-sm">Catalogue</a>
            <?php if (isset($_SESSION['utilisateur_id'])): ?>
                <span class="navbar-username"><?php echo htmlspecialchars($_SESSION['utilisateur_nom']); ?></span>
                <span class="badge-role <?php echo $_SESSION['utilisateur_role']; ?>">
                    <?php 
                    if ($_SESSION['utilisateur_role'] === 'pharmacien') echo 'Pharmacien';
                    elseif ($_SESSION['utilisateur_role'] === 'admin') echo 'Administrateur';
                    else echo 'Client';
                    ?>
                </span>
                <?php 
                $dashboard = 'dashboard_client.php';
                if ($_SESSION['utilisateur_role'] === 'pharmacien') $dashboard = 'dashboard_pharmacien.php';
                elseif ($_SESSION['utilisateur_role'] === 'admin') $dashboard = 'dashboard_admin.php';
                ?>
                <?php if ($_SESSION['utilisateur_role'] === 'client'): ?>
                <a href="panier.php" class="btn btn-ghost btn-sm" title="Mon Panier">
                    <span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">shopping_cart</span>
                    <span class="cart-count-nav" style="background: var(--danger); color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 2px;">0</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo $dashboard; ?>" class="btn btn-primary btn-sm">Mon espace</a>
            <?php else: ?>
                <a href="connexion.php" class="btn btn-ghost btn-sm">Se connecter</a>
                <a href="connexion.php?action=signup" class="btn btn-primary btn-sm">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</nav>


<!-- HERO -->
<section class="hero" style="
    background-image: url('uploads/photo/photo-hero.jpeg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
">
    <!-- Overlay sombre -->
    <div style="
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 0;
    "></div>

    <div class="hero-content" style="position: relative; z-index: 1;">
        <h1 class="anim-fade-up" style="color: #fff;">
            Votre pharmacie,<br>
            <span>à portée de main.</span>
        </h1>
        <p class="anim-fade-up-2" style="color: #fff;">
            Déposez votre ordonnance en ligne ou choisissez parmi notre catalogue de parapharmacie.
            Recevez un devis sous 2h et faites-vous livrer à domicile — sans queue, sans stress.
        </p>
        <div class="hero-btns anim-fade-up-3">
            <?php if (!isset($_SESSION['utilisateur_id'])): ?>
                <a href="connexion.php?action=signup" class="btn btn-white">S'inscrire gratuitement</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CONTENU -->
<div class="content-area">

    <!-- Comment ça marche -->
    <div class="card anim-zoom-in">
        <div class="card-header">
            <div class="card-title">
               
                L'envoi d'ordonnance en 4 étapes
            </div>
        </div>
        <div class="steps-grid">
            <div class="step-card anim-fade-up">
                <div class="step-icon"><i class="bi bi-upload"></i></div>
                <div class="step-num">1</div>
                <div class="step-title">Déposez</div>
                <div class="step-desc">Uploadez votre ordonnance (JPEG ou PDF) depuis chez vous.</div>
            </div>
            <div class="step-card anim-fade-up-2">
                <div class="step-icon"><i class="bi bi-journal-text"></i></div>
                <div class="step-num">2</div>
                <div class="step-title">Recevez le devis</div>
                <div class="step-desc">Le pharmacien vous envoie prix et délai sous 2h.</div>
            </div>
            <div class="step-card anim-fade-up-3">
                <div class="step-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="step-num">3</div>
                <div class="step-title">Confirmez</div>
                <div class="step-desc">Acceptez ou refusez le devis en un clic.</div>
            </div>
            <div class="step-card anim-fade-up-4">
                <div class="step-icon"><i class="bi bi-truck"></i></div>
                <div class="step-num">4</div>
                <div class="step-title">Recevez</div>
                <div class="step-desc">Vos médicaments livrés directement à votre domicile.</div>
            </div>
        </div>
    </div>
</div>

<!-- Panier flottant supprimé au profit de la barre de navigation -->

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4 class="footer-col-title">Medivo</h4>
                <p>Votre pharmacie en ligne de confiance. Déposez votre ordonnance et recevez vos produits chez vous.</p>
            </div>
            <div class="footer-col">
                <h4 class="footer-col-title">Liens utiles</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="catalogue.php">Catalogue</a></li>
                    <li><a href="dashboard_client.php">Mon Espace</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-col-title">Nos services</h4>
                <ul class="footer-links">
                    <li>Ordonnance en ligne</li>
                    <li>Parapharmacie</li>
                    <li>Livraison Express</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-col-title">Contact</h4>
                <ul class="footer-links">
                    <li><i class="bi bi-geo-alt"></i> Tunis, Tunisie</li>
                    <li><i class="bi bi-telephone"></i> +216 50 433 207</li>
                    <li><i class="bi bi-envelope"></i> medivo@gmail.com </li>
                </ul>
            </div>
        </div>
        <div style="text-align: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
        </div>
    </div>
</footer>

</div>
<script src="assets/js/main.js"></script>
</body>
</html>