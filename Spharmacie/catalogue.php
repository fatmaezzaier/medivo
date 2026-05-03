<?php
session_start();
require_once 'config/database.php';
require_once 'includes/fonctions.php';

// Optionnel: vérifier si l'utilisateur est connecté pour voir le catalogue, 
// On peut le laisser public, mais s'il faut commander, on demande connexion.
$client_connecte = isset($_SESSION['utilisateur_id']) && $_SESSION['utilisateur_role'] === 'client';

// Récupérer toutes les catégories uniques des produits disponibles
$stmt = $pdo->query("SELECT DISTINCT categorie FROM produits WHERE disponible = 1 AND archive = 0 ORDER BY categorie ASC");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
$stmt->closeCursor();

// Récupérer tous les produits disponibles
$stmt = $pdo->query("SELECT * FROM produits WHERE disponible = 1 AND archive = 0 ORDER BY date_ajout DESC");
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$role = isset($_SESSION['utilisateur_role']) ? $_SESSION['utilisateur_role'] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/catalogue.css">
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

        <!-- Navbar -->
        <nav class="navbar">
            <div class="container">
                <a href="index.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
                    <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                        <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
                    </div>
                    <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
                </a>
                <div class="navbar-right">
                    <a href="index.php" class="btn btn-ghost btn-sm">Accueil</a>
                    <a href="catalogue.php" class="btn btn-ghost btn-sm active">Catalogue</a>
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
                        <a href="<?php echo $dashboard; ?>" class="btn btn-primary btn-sm">Mon Espace</a>
                    <?php else: ?>
                        <a href="connexion.php" class="btn btn-ghost btn-sm">Se connecter</a>
                        <a href="connexion.php?action=signup" class="btn btn-primary btn-sm">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main class="content-area">
            <div class="catalogue-header">
                <h1 class="catalogue-title">Produits de parapharmacie</h1>
                
                <div class="catalogue-filters">
                    <button class="btn btn-primary btn-sm" onclick="filtrerCatalogue('tous', this)">Tous</button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="btn btn-outline btn-sm" onclick="filtrerCatalogue(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>, this)">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($produits)): ?>
                <div class="empty-state">
                    <div class="empty-icon material-symbols-outlined">inventory_2</div>
                    <h3>Catalogue vide</h3>
                    <p>Aucun produit n'est disponible pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produits as $p): ?>
                        <div class="product-card anim-fade-up" data-categorie="<?= htmlspecialchars($p['categorie'], ENT_QUOTES) ?>">
                            <?php if ($p['image']): ?>
                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom'], ENT_QUOTES) ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image-fallback">
                                    <span class="material-symbols-outlined">medication</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-content">
                                <div class="product-category"><?= htmlspecialchars($p['categorie']) ?></div>
                                <h3 class="product-name"><?= htmlspecialchars($p['nom']) ?></h3>
                                <div class="product-desc"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
                                
                                <div style="margin-bottom: 12px;">
                                    <?php if ($p['stock'] > 0): ?>
                                        <span class="product-stock stock-in"><span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">check_circle</span> En stock</span>
                                    <?php else: ?>
                                        <span class="product-stock stock-out"><span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">cancel</span> Rupture de stock</span>
                                    <?php endif; ?>
                                </div>

                                <div class="product-footer">
                                    <div class="product-price"><?= number_format($p['prix'], 3, '.', '') ?> DT</div>
                                    <?php 
                                    $role = $_SESSION['utilisateur_role'] ?? 'invite';
                                    if ($role === 'client'): 
                                    ?>
                                    <button class="btn <?= $p['stock'] > 0 ? 'btn-primary' : 'btn-ghost' ?> btn-sm" 
                                            <?= $p['stock'] == 0 ? 'disabled' : '' ?>
                                            onclick="ajouterAuPanier(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['nom']), ENT_QUOTES) ?>, <?= $p['prix'] ?>, <?= $p['stock'] ?>)">
                                        <span class="material-symbols-outlined">add_shopping_cart</span>
                                    </button>
                                    <?php elseif ($role === 'invite'): ?>
                                    <a href="connexion.php" class="btn btn-outline btn-sm" title="Se connecter pour acheter">
                                        <span class="material-symbols-outlined">login</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        
        <!-- Panier flottant supprimé au profit de la barre de navigation -->

        <!-- Footer -->
        <footer class="footer-simple">
            <div class="container">
                <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
</body>
</html>