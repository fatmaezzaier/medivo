<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
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

<!-- MAIN HEADER -->
<header class="main-header">
    <div class="container">
        <!-- Logo -->
        <a href="index.php" class="header-logo" style="gap: 0; margin-left: -8px;">
            <div class="logo-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
            </div>
            <span>Medi<span class="green">vo</span></span>
        </a>

        <!-- Navigation -->
        <nav class="nav-menu">
            <a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">Accueil</a>
            <a href="dashboard_client.php" class="nav-link <?= ($current_page == 'dashboard_client.php') ? 'active' : '' ?>">Ordonnance</a>
            <a href="catalogue.php" class="nav-link <?= ($current_page == 'catalogue.php') ? 'active' : '' ?>">Parapharmacie</a>
            
            <?php if (isset($_SESSION['utilisateur_id'])): ?>
                <a href="deconnexion.php" class="nav-link" style="color: #ca3e32;"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <?php endif; ?>
        </nav>

        <!-- Actions -->
        <div class="header-actions">
            <div class="action-icon">
                <i class="bi bi-search"></i>
            </div>
            
            <?php if (isset($_SESSION['utilisateur_role']) && $_SESSION['utilisateur_role'] === 'client'): ?>
            <a href="panier.php" class="action-icon">
                <i class="bi bi-cart3"></i>
                <span class="cart-badge cart-count-nav">0</span>
            </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['utilisateur_id'])): ?>
                <?php 
                $dash = 'dashboard_client.php';
                if ($_SESSION['utilisateur_role'] === 'pharmacien') $dash = 'dashboard_pharmacien.php';
                elseif ($_SESSION['utilisateur_role'] === 'admin') $dash = 'dashboard_admin.php';
                ?>
                <a href="<?= $dash ?>" class="btn-connect">
                    <i class="bi bi-person-circle"></i>
                    <span>Mon espace</span>
                </a>
            <?php else: ?>
                <a href="connexion.php" class="btn-connect">
                    <i class="bi bi-person"></i>
                    <span>Se connecter</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
