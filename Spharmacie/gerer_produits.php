<?php
session_start();
require_once 'config/database.php';
require_once 'includes/fonctions.php';

// Vérification de sécutité : seul le pharmacien peut accéder
verifierSession('pharmacien');

$pharmacien_id = $_SESSION['utilisateur_id'];
$message = '';
$type_message = '';

// Traitement des actions (Ajout, Modification, Suppression, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nom = nettoyerChaine($_POST['nom'] ?? '');
        $description = nettoyerChaine($_POST['description'] ?? '');
        $prix = (float)($_POST['prix'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $categorie = nettoyerChaine($_POST['categorie'] ?? '');
        $produit_id = isset($_POST['produit_id']) ? (int)$_POST['produit_id'] : 0;
        
        // Gestion de l'upload d'image
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/produits/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            // Vérification simple d'extension
            $extensions_valides = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($imageFileType, $extensions_valides)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO produits (nom, description, prix, stock, categorie, image, pharmacien_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$nom, $description, $prix, $stock, $categorie, $image_path, $pharmacien_id])) {
                $message = "Produit ajouté avec succès.";
                $type_message = "alert-success";
            } else {
                $message = "Erreur lors de l'ajout.";
                $type_message = "alert-danger";
            }
            $stmt->closeCursor();
        } elseif ($action === 'edit' && $produit_id > 0) {
            if ($image_path) {
                $stmt = $pdo->prepare("UPDATE produits SET nom = ?, description = ?, prix = ?, stock = ?, categorie = ?, image = ? WHERE id = ? AND pharmacien_id = ?");
                $params = [$nom, $description, $prix, $stock, $categorie, $image_path, $produit_id, $pharmacien_id];
            } else {
                $stmt = $pdo->prepare("UPDATE produits SET nom = ?, description = ?, prix = ?, stock = ?, categorie = ? WHERE id = ? AND pharmacien_id = ?");
                $params = [$nom, $description, $prix, $stock, $categorie, $produit_id, $pharmacien_id];
            }
            if ($stmt->execute($params)) {
                $message = "Produit modifié avec succès.";
                $type_message = "alert-success";
            } else {
                $message = "Erreur lors de la modification.";
                $type_message = "alert-danger";
            }
            $stmt->closeCursor();
        }
    } elseif ($action === 'delete') {
        $produit_id = (int)($_POST['produit_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE produits SET archive = 1 WHERE id = ? AND pharmacien_id = ?");
        if ($stmt->execute([$produit_id, $pharmacien_id])) {
            $message = "Produit supprimé avec succès.";
            $type_message = "alert-success";
        } else {
            $message = "Erreur lors de la suppression.";
            $type_message = "alert-danger";
        }
        $stmt->closeCursor();
    } elseif ($action === 'toggle') {
        $produit_id = (int)($_POST['produit_id'] ?? 0);
        $disponible = (int)($_POST['disponible'] ?? 0);
        $nouveau_statut = $disponible ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE produits SET disponible = ? WHERE id = ? AND pharmacien_id = ?");
        $stmt->execute([$nouveau_statut, $produit_id, $pharmacien_id]);
        $stmt->closeCursor();
        header("Location: gerer_produits.php");
        exit();
    }
}

// Récupération des produits
$stmt = $pdo->prepare("SELECT * FROM produits WHERE pharmacien_id = ? AND archive = 0 ORDER BY date_ajout DESC");
$stmt->execute([$pharmacien_id]);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Mode édition
$edit_mode = false;
$edit_produit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND pharmacien_id = ?");
    $stmt->execute([$edit_id, $pharmacien_id]);
    $edit_produit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if ($edit_produit) {
        $edit_mode = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer le catalogue - Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
                <a href="dashboard_pharmacien.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
                    <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                        <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
                    </div>
                    <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
                </a>
                <div class="navbar-right">
                    <span class="badge-role pharmacien">Pharmacien</span>
                    <a href="dashboard_pharmacien.php" class="btn btn-outline btn-sm">Tableau de bord</a>
                    <a href="deconnexion.php" class="btn btn-ghost btn-sm">Déconnexion</a>
                </div>
            </div>
        </nav>

        <main class="content-area">
            <h1 class="catalogue-title" style="margin-bottom: 24px;">Gérer le catalogue de produits</h1>
            
            <?php if ($message): ?>
                <div class="alert <?= $type_message ?>">
                    <span class="material-symbols-outlined"><?= $type_message === 'alert-success' ? 'check_circle' : 'error' ?></span>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout/modification -->
            <div class="card" style="margin-bottom: 32px;">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="material-symbols-outlined">vaccines</span>
                        <?= $edit_mode ? 'Modifier le produit' : 'Ajouter un produit' ?>
                    </h2>
                    <?php if ($edit_mode): ?>
                        <a href="gerer_produits.php" class="btn btn-ghost btn-sm">Annuler</a>
                    <?php endif; ?>
                </div>
                
                <form action="gerer_produits.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $edit_mode ? 'edit' : 'add' ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="produit_id" value="<?= $edit_produit['id'] ?>">
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <div class="form-group">
                                <label class="form-label">Nom du produit <span class="required">*</span></label>
                                <input type="text" name="nom" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($edit_produit['nom']) : '' ?>">
                            </div>
                            
                            <div class="form-group" style="display: flex; gap: 16px;">
                                <div style="flex: 1;">
                                    <label class="form-label">Prix (DT) <span class="required">*</span></label>
                                    <input type="number" step="0.001" min="0" name="prix" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($edit_produit['prix']) : '' ?>">
                                </div>
                                <div style="flex: 1;">
                                    <label class="form-label">Stock <span class="required">*</span></label>
                                    <input type="number" min="0" name="stock" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($edit_produit['stock']) : '0' ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Catégorie <span class="required">*</span></label>
                                <select name="categorie" class="form-control" required>
                                    <option value="">Sélectionner une catégorie...</option>
                                    <?php
                                    $cats = ['Vitamines et Compléments', 'Premiers soins', 'Soins de la peau', 'Hygiène', 'Bébé & Maman', 'Matériel Médical'];
                                    foreach ($cats as $c) {
                                        $selected = ($edit_mode && $edit_produit['categorie'] === $c) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars($c)."\" $selected>".htmlspecialchars($c)."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="form-group">
                                <label class="form-label">Description <span class="required">*</span></label>
                                <textarea name="description" class="form-control" style="min-height: 120px;" required><?= $edit_mode ? htmlspecialchars($edit_produit['description']) : '' ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Image du produit <?= !$edit_mode ? '<span class="required">*</span>' : '' ?></label>
                                <input type="file" name="image" class="form-control" accept="image/*" <?= !$edit_mode ? 'required' : '' ?>>
                                <?php if ($edit_mode && $edit_produit['image']): ?>
                                    <div style="margin-top: 10px;">
                                        <img src="<?= htmlspecialchars($edit_produit['image']) ?>" alt="Image actuelle" style="max-height: 80px; border-radius: 8px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 16px;">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined"><?= $edit_mode ? 'save' : 'add_circle' ?></span>
                            <?= $edit_mode ? 'Enregistrer les modifications' : 'Ajouter au catalogue' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Liste des produits -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="material-symbols-outlined">inventory_2</span>
                        Liste de vos produits
                    </h2>
                </div>
                
                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <div class="empty-icon material-symbols-outlined">production_quantity_limits</div>
                        <h3>Aucun produit trouvé</h3>
                        <p>Vous n'avez pas encore ajouté de produit à votre catalogue.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Nom du produit</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if ($p['image']): ?>
                                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="Image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; background: var(--gris-2); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--gris-4);">image_not_supported</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 600; color: var(--vert-fonce);"><?= htmlspecialchars($p['nom']) ?></td>
                                        <td><span class="badge" style="background: var(--gris-1); color: var(--gris-4);"><?= htmlspecialchars($p['categorie']) ?></span></td>
                                        <td style="font-weight: 700; color: var(--or);"><?= number_format($p['prix'], 3, '.', '') ?> DT</td>
                                        <td>
                                            <?php if ($p['stock'] > 0): ?>
                                                <span class="badge badge-acceptee"><?= $p['stock'] ?> en stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-refusee">Rupture</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['disponible']): ?>
                                                <span class="badge badge-acceptee" style="font-size: 11px;">Disponible</span>
                                            <?php else: ?>
                                                <span class="badge badge-annule" style="font-size: 11px;">Masqué</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <form action="gerer_produits.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="disponible" value="<?= $p['disponible'] ?>">
                                                    <button type="submit" class="btn <?= $p['disponible'] ? 'btn-ghost' : 'btn-outline' ?> btn-sm" title="<?= $p['disponible'] ? 'Masquer du catalogue' : 'Rendre disponible' ?>">
                                                        <span class="material-symbols-outlined"><?= $p['disponible'] ? 'visibility_off' : 'visibility' ?></span>
                                                    </button>
                                                </form>
                                                
                                                <a href="gerer_produits.php?edit=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Modifier">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </a>

                                                <form action="gerer_produits.php" method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit définitivement ?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-danger-ol btn-sm" title="Supprimer">
                                                        <span class="material-symbols-outlined">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <footer class="footer-simple">
            <div class="container">
                <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
            </div>
        </footer>
    </div>
</body>
</html>