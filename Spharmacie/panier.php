<?php
session_start();
require_once 'config/database.php';
require_once 'includes/fonctions.php';

$client_connecte = isset($_SESSION['utilisateur_id']) && $_SESSION['utilisateur_role'] === 'client';

// Sécurisation stricte de l'accès à la page
if (!$client_connecte) {
    header('Location: connexion.php');
    exit();
}

$message = '';
$type_message = '';
$commande_reussie = false;

// Validation de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commander'])) {

            $client_id = $_SESSION['utilisateur_id'];
            $total_commande = (float)($_POST['total_commande'] ?? 0);
            $produits_commande = $_POST['produits'] ?? [];
            $adresse_livraison = $_POST['adresse_livraison'] ?? '';

            if ($total_commande > 0 && !empty($produits_commande)) {
                try {
                    $pdo->beginTransaction();

                    // Insert commande
                    $stmt = $pdo->prepare("INSERT INTO commandes_produits (client_id, total, adresse_livraison, statut) VALUES (?, ?, ?, 'En préparation')");
                    $stmt->execute([$client_id, $total_commande, $adresse_livraison]);
            $commande_id = $pdo->lastInsertId();
            $stmt->closeCursor();

            // Insert details et maj stock
            $stmt_detail = $pdo->prepare("INSERT INTO details_commande_produits (commande_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?");

            foreach ($produits_commande as $prod) {
                $p_id = (int)$prod['id'];
                $qte = (int)$prod['quantite'];
                $prix_u = (float)$prod['prix_unitaire'];

                $stmt_detail->execute([$commande_id, $p_id, $qte, $prix_u]);
                $stmt_stock->execute([$qte, $p_id]);
            }
            
            $stmt_detail->closeCursor();
            $stmt_stock->closeCursor();

            $pdo->commit();
            $commande_reussie = true;
            $message = "Votre commande a été enregistrée avec succès. Vous pouvez la suivre dans votre espace.";
            $type_message = "alert-success";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Une erreur est survenue lors de la commande : " . $e->getMessage();
            $type_message = "alert-danger";
        }
    } else {
        $message = "Votre panier est vide ou invalide.";
        $type_message = "alert-warning";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - Medivo</title>
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
                    <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span> <span class="brand-sub">— Panier</span></div>
                </a>
                <div class="navbar-right">
                    <a href="catalogue.php" class="btn btn-outline btn-sm">Continuer les achats</a>
                    <a href="dashboard_client.php" class="btn btn-ghost btn-sm">Mon Espace</a>
                </div>
            </div>
        </nav>

        <main class="content-area">
            <h1 class="catalogue-title" style="margin-bottom: 24px;">Mon Panier</h1>

            <?php if ($message): ?>
                <div class="alert <?= $type_message ?>">
                    <span class="material-symbols-outlined"><?= $type_message === 'alert-success' ? 'check_circle' : 'error' ?></span>
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php if ($commande_reussie): ?>
                    <script>
                        // Vider le panier après succès et empêcher le rechargement de commande
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof viderPanierLocal === 'function') {
                                viderPanierLocal();
                            }
                        });
                    </script>
                    <div style="margin-top: 20px;">
                        <a href="dashboard_client.php" class="btn btn-primary">Aller à mon Espace</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$commande_reussie): ?>
                <div id="panier-vide" class="empty-state" style="display: none;">
                    <div class="empty-icon material-symbols-outlined">remove_shopping_cart</div>
                    <h3>Votre panier est vide</h3>
                    <p>Découvrez notre catalogue pour ajouter des produits.</p>
                    <a href="catalogue.php" class="btn btn-primary" style="margin-top: 16px;">Voir le catalogue</a>
                </div>

                <div id="panier-table-wrapper" class="card" style="display: none;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix Unitaire</th>
                                <th style="width: 120px;">Quantité</th>
                                <th>Total</th>
                                <th style="text-align: right; width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="panier-tbody">
                            <!-- Rempli par JavaScript -->
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
                        <div>
                            <button type="button" class="btn btn-ghost" onclick="viderPanierLocal(); chargerPanier(); mettreAJourCompteurPanier();">Vider le panier</button>
                        </div>
                        <div class="cart-summary" style="min-width: 300px;">
                            <div style="font-size: 14px; color: var(--gris-4); margin-bottom: 4px;">Total à payer</div>
                            <div class="cart-total" id="panier-total">0.000 DT</div>
                            
                            <form action="panier.php" method="POST">
                                <div id="panier-data-form">
                                    <!-- Inputs cachés remplis par JavaScript -->
                                </div>
                                
                                <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                                    <label for="adresse_livraison" style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--gris-4);">Adresse de livraison</label>
                                    <textarea name="adresse_livraison" id="adresse_livraison" rows="3" class="form-control" 
                                              placeholder="Indiquez l'adresse précise pour la livraison..." required
                                              style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-family: inherit; resize: vertical;"><?php echo isset($_SESSION['utilisateur_adresse']) ? htmlspecialchars($_SESSION['utilisateur_adresse']) : ''; ?></textarea>
                                </div>

                                <button type="submit" name="commander" class="btn btn-or btn-lg btn-block" style="margin-top: 15px;">
                                    <span class="material-symbols-outlined">payments</span>
                                    Commander maintenant
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer class="footer-simple">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$commande_reussie): ?>
            chargerPanier();
            <?php endif; ?>
        });
    </script>
</body>
</html>