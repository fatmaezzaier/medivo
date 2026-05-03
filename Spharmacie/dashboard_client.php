<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

verifierSession('client');
$client_id = $_SESSION['utilisateur_id'];
$message   = "";
$erreur    = "";

// ACTION 1 : Upload ordonnance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ordonnance'])) {
    $description = nettoyerChaine($_POST['description'] ?? '');
    $adresse     = nettoyerChaine($_POST['adresse_livraison'] ?? '');
    $fichier     = $_FILES['ordonnance'];
    $types_ok    = ['image/jpeg','image/png','image/jpg','application/pdf'];

    if ($fichier['error'] !== 0) {
        $erreur = "Erreur lors de l'upload.";
    } elseif (!in_array($fichier['type'], $types_ok)) {
        $erreur = "Type non autorisé (JPEG, PNG, PDF uniquement).";
    } elseif ($fichier['size'] > 5 * 1024 * 1024) {
        $erreur = "Fichier trop lourd (max 5 Mo).";
    } elseif (strlen($adresse) < 5) {
        $erreur = "Adresse de livraison trop courte.";
    } else {
        $ext = pathinfo($fichier['name'], PATHINFO_EXTENSION);
        $nom = time() . '_' . $client_id . '.' . $ext;
        if (!is_dir('uploads/')) mkdir('uploads/', 0755, true);
        if (move_uploaded_file($fichier['tmp_name'], 'uploads/' . $nom)) {
            // Ne plus mettre à jour l'adresse globale de l'utilisateur
            // $pdo->prepare("UPDATE utilisateurs SET adresse=? WHERE id=?")->execute([$adresse, $client_id]);
            
            $stmt = $pdo->prepare("INSERT INTO ordonnances (client_id, fichier_ordonnance, description, adresse_livraison, statut) VALUES (?,?,?,?,'en_attente')");
            if ($stmt->execute([$client_id, 'uploads/'.$nom, $description, $adresse])) {
                $message = "Ordonnance envoyée ! Le pharmacien vous répondra sous 2h.";
            }
        }
    }
}

// ACTION 2 : Accepter / Refuser devis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ordonnance_id'])
    && in_array($_POST['action'], ['accepter','refuser'])) {

    $oid    = (int)$_POST['ordonnance_id'];
    $action = $_POST['action'];
    $chk    = $pdo->prepare("SELECT * FROM ordonnances WHERE id=? AND client_id=?");
    $chk->execute([$oid, $client_id]);
    $ordo = $chk->fetch();
    $chk->closeCursor();

    if (!$ordo || $ordo['statut'] !== 'devis_envoye') {
        $erreur = "Action non autorisée.";
    } elseif ($action === 'accepter' && isset($_POST['devis_id'])) {
        $devis_id = (int)$_POST['devis_id'];
        
        $d = $pdo->prepare("SELECT * FROM devis WHERE id=? AND ordonnance_id=?");
        $d->execute([$devis_id, $oid]); 
        $devis = $d->fetch(); 
        $d->closeCursor();
        
        if (!$devis) {
            $erreur = "Devis invalide.";
        } else {
            $pdo->prepare("UPDATE ordonnances SET statut='acceptee', reponse_client='acceptee' WHERE id=?")->execute([$oid]);
            
            // On utilise l'adresse stockée dans l'ordonnance
            $adresse_finale = !empty($ordo['adresse_livraison']) ? $ordo['adresse_livraison'] : '';
            
            $pdo->prepare("INSERT INTO commandes (ordonnance_id,client_id,pharmacien_id,devis_id,adresse_livraison,statut) VALUES (?,?,?,?,?,'preparation')")
                ->execute([$oid, $client_id, $devis['pharmacien_id'], $devis['id'], $adresse_finale]);
            $message = "Devis accepté ! Commande en cours de préparation.";
        }
    } else {
        $pdo->prepare("UPDATE ordonnances SET statut='refusee', reponse_client='refusee' WHERE id=?")->execute([$oid]);
        $message = "Devis refusé.";
    }
}

// ACTION 3 : Supprimer ordonnance (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ordonnance_id'])
    && $_POST['action'] === 'supprimer') {
    $oid = (int)$_POST['ordonnance_id'];
    $chk = $pdo->prepare("SELECT id, fichier_ordonnance FROM ordonnances WHERE id=? AND client_id=? AND statut='en_attente'");
    $chk->execute([$oid, $client_id]);
    $ordo = $chk->fetch(); $chk->closeCursor();
    if ($ordo) {
        if (file_exists($ordo['fichier_ordonnance'])) unlink($ordo['fichier_ordonnance']);
        $pdo->prepare("DELETE FROM ordonnances WHERE id=? AND client_id=?")->execute([$oid, $client_id]);
        $message = "Ordonnance supprimée.";
    } else {
        $erreur = "Impossible de supprimer.";
    }
}

// ACTION 4 : Modifier ordonnance (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ordonnance_id'])
    && $_POST['action'] === 'modifier') {
    $oid = (int)$_POST['ordonnance_id'];
    $description = nettoyerChaine($_POST['description'] ?? '');
    
    $chk = $pdo->prepare("SELECT id, fichier_ordonnance FROM ordonnances WHERE id=? AND client_id=? AND statut='en_attente'");
    $chk->execute([$oid, $client_id]);
    $ordo = $chk->fetch(); $chk->closeCursor();

    if ($ordo) {
        // Nouveau fichier uploadé
        if (isset($_FILES['nouvelle_ordonnance']) && $_FILES['nouvelle_ordonnance']['error'] === 0) {
            $fichier = $_FILES['nouvelle_ordonnance'];
            $types_ok = ['image/jpeg','image/png','image/jpg','application/pdf'];
            
            if (!in_array($fichier['type'], $types_ok)) {
                $erreur = "Type non autorisé (JPEG, PNG, PDF uniquement).";
            } elseif ($fichier['size'] > 5 * 1024 * 1024) {
                $erreur = "Fichier trop lourd (max 5 Mo).";
            } else {
                if (file_exists($ordo['fichier_ordonnance'])) unlink($ordo['fichier_ordonnance']);
                $ext = pathinfo($fichier['name'], PATHINFO_EXTENSION);
                $nom = time() . '_' . $client_id . '_mod.' . $ext;
                if (!is_dir('uploads/')) mkdir('uploads/', 0755, true);
                if (move_uploaded_file($fichier['tmp_name'], 'uploads/' . $nom)) {
                    $pdo->prepare("UPDATE ordonnances SET fichier_ordonnance=?, description=? WHERE id=?")->execute(['uploads/'.$nom, $description, $oid]);
                    $message = "Ordonnance et fichier modifiés avec succès.";
                }
            }
        } else {
            // Modification de la description uniquement
            $pdo->prepare("UPDATE ordonnances SET description=? WHERE id=?")->execute([$description, $oid]);
            $message = "Commentaire de l'ordonnance modifié avec succès.";
        }
    } else {
        $erreur = "Impossible de modifier cette ordonnance (déjà traitée).";
    }
}

// LECTURE
$stmt = $pdo->prepare(
    "SELECT o.*, 
            c.id AS commande_id, c.statut AS statut_commande,
            d.prix AS prix_paye, d.delai AS delai_prevu, d.medicaments AS meds_prevus
     FROM ordonnances o
     LEFT JOIN commandes c ON c.ordonnance_id = o.id
     LEFT JOIN devis d     ON d.id = c.devis_id
     WHERE o.client_id=? ORDER BY o.date_demande DESC"
);
$stmt->execute([$client_id]);
$ordonnances = $stmt->fetchAll();
$stmt->closeCursor();

$adresse_save = '';
// $u2 = $pdo->prepare("SELECT adresse FROM utilisateurs WHERE id=?");
// $u2->execute([$client_id]);
// $adresse_save = $u2->fetch()['adresse'] ?? '';
// $u2->closeCursor();

$sc = $pdo->prepare("SELECT d.prix FROM commandes c JOIN devis d ON d.id=c.devis_id WHERE c.client_id=? AND c.statut!='annule'");
$sc->execute([$client_id]);
$mes_commandes = $sc->fetchAll();
$sc->closeCursor();

// ACTION 5 : Récupérer les achats catalogue
$sc_catalogue = $pdo->prepare("SELECT * FROM commandes_produits WHERE client_id = ? ORDER BY date_commande DESC");
$sc_catalogue->execute([$client_id]);
$mes_achats_catalogue = $sc_catalogue->fetchAll();
$sc_catalogue->closeCursor();

// Fonctions PHP
$compteurs       = compterOrdonnancesParStatut($ordonnances);
$total_depense   = calculerTotalCommandes($mes_commandes);

// Ajouter le total des achats catalogue à la dépense totale
foreach ($mes_achats_catalogue as $achat) {
    $total_depense += (float)$achat['total'];
}

$nb_total        = count($ordonnances);
$formatterStatut = getFormatteurStatut();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace — Medivo</title>
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

<!-- ========== NAVBAR ========== -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
            <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
            </div>
            <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
        </a>
        <div class="navbar-right">
            <span class="navbar-username"><?php echo nettoyerChaine($_SESSION['utilisateur_nom']); ?></span>
            <span class="badge-role client">Client</span>
            <a href="panier.php" class="btn btn-ghost btn-sm" title="Mon Panier">
                <span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">shopping_cart</span>
                <span class="cart-count-nav" style="background: var(--danger); color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 2px;">0</span>
            </a>
            <a href="deconnexion.php" class="btn btn-ghost btn-sm">Déconnexion</a>
        </div>
    </div>
</nav>

<!-- ========== CONTENU ========== -->
<div class="content-area">

    <!-- Alertes -->
    <?php if ($message): ?>
        <div class="alert alert-success anim-fade-in">
            <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-danger anim-fade-in">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($erreur); ?>
        </div>
    <?php endif; ?>

    <!-- En-tête de page -->
    <div class="page-header anim-fade-up">
        <div class="page-header-left">
            <h1>Bonjour, <?php echo nettoyerChaine($_SESSION['utilisateur_nom']); ?> !</h1>
            <p>Gérez vos ordonnances et suivez vos commandes</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid anim-fade-up-2">
        <div class="stat-card">
            <div class="stat-label">Ordonnances</div>
            <div class="stat-value"><?php echo $nb_total; ?></div>
            <div class="stat-sub">au total</div>
        </div>
        <div class="stat-card or">
            <div class="stat-label">Total dépensé</div>
            <div class="stat-value"><?php echo number_format($total_depense, 0); ?></div>
            <div class="stat-sub">dinars</div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">En attente</div>
            <div class="stat-value"><?php echo $compteurs['en_attente']; ?></div>
            <div class="stat-sub">de réponse</div>
        </div>
    </div>

    <!-- ===== FORMULAIRE UPLOAD ===== -->
    <div class="card anim-fade-up-3">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-upload"></i></div>
                Déposer une nouvelle ordonnance
            </div>
        </div>

        <div id="erreur_upload"></div>

        <form method="POST" enctype="multipart/form-data"
              onsubmit="return validerFormulaireUpload()">

            <div class="form-group">
                <label class="form-label">
                    Fichier ordonnance <span class="required">*</span>
                    <span style="text-transform:none;letter-spacing:0;font-weight:400;color:var(--gris-3)"> — JPEG, PNG ou PDF, max 5 Mo</span>
                </label>
                <div style="margin: 8px 0;">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('fichier_ordonnance').click()">
                        <i class="bi bi-file-earmark-plus"></i> Choisir un fichier
                    </button>
                </div>
                <input type="file" id="fichier_ordonnance" name="ordonnance"
                       accept="image/jpeg,image/png,application/pdf"
                       style="position: absolute; left: -9999px;"
                       onchange="previsualiserFichier(this)" required>
                <span id="info_fichier" class="form-hint"></span>
                <div id="preview_fichier"></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="adresse_livraison">
                    Adresse de livraison <span class="required">*</span>
                </label>
                <input type="text" id="adresse_livraison" name="adresse_livraison"
                       class="form-control"
                       value="<?php echo htmlspecialchars($adresse_save); ?>"
                       placeholder="Ex : 12 rue Habib Bourguiba, Tunis" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Commentaire (optionnel)</label>
                <textarea id="description" name="description"
                          class="form-control" maxlength="200"
                          oninput="compterCaracteres(this,'compteur_desc',200)"
                          placeholder="Ex : médicaments urgents, allergie à..."></textarea>
                <div id="compteur_desc" class="form-hint compteur-chars">200 caractère(s) restant(s)</div>
            </div>

            <button type="submit" class="btn btn-primary">
                 Envoyer l'ordonnance
            </button>
        </form>
    </div>

    <!-- ===== TABLEAU ORDONNANCES ===== -->
    <div class="card anim-fade-up-4">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-journal-text"></i></div>
                Mes ordonnances
                <span class="filter-count" id="nb_resultats"><?php echo $nb_total; ?></span>
            </div>
            <!-- onchange JS filtrerTableau() -->
            <div class="filter-bar" style="margin-bottom:0">
                <label>Filtrer :</label>
                <select onchange="filtrerTableau(this.value)">
                    <option value="tous">Tous (<?php echo $nb_total; ?>)</option>
                    <option value="en_attente">En attente (<?php echo $compteurs['en_attente']; ?>)</option>
                    <option value="devis_envoye">Devis envoyé (<?php echo $compteurs['devis_envoye']; ?>)</option>
                    <option value="acceptee">Acceptée (<?php echo $compteurs['acceptee']; ?>)</option>
                    <option value="refusee">Refusée (<?php echo $compteurs['refusee']; ?>)</option>
                </select>
            </div>
        </div>

        <?php if ($nb_total > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Devis reçu</th>
                        <th>Commande</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ordonnances as $o): ?>
                    <tr data-statut="<?php echo $o['statut']; ?>">
                        <td style="font-size:13px;color:var(--gris-4)">
                            <?php echo formatDate($o['date_demande'], true); ?>
                        </td>
                        <td>
                            <a href="voir_ordonnance.php?id=<?php echo $o['id']; ?>"
                               target="_blank" class="btn btn-outline btn-sm">
                                 Voir
                            </a>
                        </td>
                        <td>
                            <?php
                            $classeBadge = [
                                'en_attente'   => 'badge-attente',
                                'devis_envoye' => 'badge-devis',
                                'acceptee'     => 'badge-acceptee',
                                'refusee'      => 'badge-refusee',
                                'livree'       => 'badge-livre',
                            ];
                            $classe = $classeBadge[$o['statut']] ?? '';
                            ?>
                            <span class="badge <?php echo $classe; ?>">
                                <?php echo $formatterStatut($o['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($o['prix_paye']): ?>
                                <span class="montant-badge"><?php echo formatPrix((float)$o['prix_paye']); ?></span><br>
                                <span style="font-size:12px;color:var(--gris-3)">
                                    Délai : <?php echo htmlspecialchars($o['delai_prevu']); ?>
                                </span>
                                <?php if ($o['meds_prevus']): ?>
                                    <br><span style="font-size:11px;color:var(--gris-3)">
                                        <?php echo tronquer(htmlspecialchars($o['meds_prevus']), 40); ?>
                                    </span>
                                <?php endif; ?>
                            <?php elseif ($o['statut'] === 'devis_envoye'): 
                                // Compter les devis reçus
                                $st_count = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE ordonnance_id = ?");
                                $st_count->execute([$o['id']]);
                                $nb_devis = $st_count->fetchColumn();
                            ?>
                                <span class="badge badge-devis" style="font-size:11px;">
                                    <?php echo $nb_devis; ?> devis reçu(s)
                                </span><br>
                                <span style="font-size:11px;color:var(--gris-3)">Choisissez-en un ci-dessous</span>
                            <?php else: ?>
                                <span style="font-size:12px;color:var(--gris-3)">En attente de réponse...</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['commande_id']): ?>
                                <span class="badge badge-livraison">
                                    <?php echo getLibelleStatutCommande($o['statut_commande']); ?>
                                </span><br>
                                <?php if ($o['statut_commande'] !== 'annule'): ?>
                                    <a href="suivi_commande.php?id=<?php echo $o['commande_id']; ?>"
                                       class="btn btn-outline btn-sm" style="margin-top: 4px;">
                                        Voir le suivi →
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--gris-3);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['statut'] === 'devis_envoye'): 
                                $stmt_d = $pdo->prepare("SELECT d.*, u.nom as ph_nom FROM devis d JOIN utilisateurs u ON u.id = d.pharmacien_id WHERE d.ordonnance_id = ?");
                                $stmt_d->execute([$o['id']]);
                                $devis_list = $stmt_d->fetchAll();
                             ?>
                                <div class="devis-selection" style="display:flex; flex-direction:column; gap:10px;">
                                    <?php foreach ($devis_list as $dv): ?>
                                        <div class="card" style="padding:10px; background:var(--vert-clair); border:1px solid var(--vert); border-radius:var(--rayon-sm);">
                                            <div style="font-size:12px; margin-bottom:5px;">
                                                <strong>Pharmacie :</strong> <?php echo htmlspecialchars($dv['ph_nom']); ?><br>
                                                <strong>Prix :</strong> <?php echo formatPrix((float)$dv['prix']); ?><br>
                                                <strong>Délai :</strong> <?php echo htmlspecialchars($dv['delai']); ?><br>
                                                <small><?php echo htmlspecialchars($dv['medicaments']); ?></small>
                                            </div>
                                            <form method="POST" style="display:flex; gap:6px;">
                                                <input type="hidden" name="ordonnance_id" value="<?php echo $o['id']; ?>">
                                                <input type="hidden" name="devis_id" value="<?php echo $dv['id']; ?>">
                                                <button type="submit" name="action" value="accepter" 
                                                        class="btn btn-primary btn-sm"
                                                        onclick="return confirmerAcceptation('<?php echo formatPrix((float)$dv['prix']); ?>')">
                                                    Accepter ce devis
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                    <form method="POST">
                                        <input type="hidden" name="ordonnance_id" value="<?php echo $o['id']; ?>">
                                        <button type="submit" name="action" value="refuser" class="btn btn-danger-ol btn-sm" style="width:100%; margin-top:5px;">
                                            <i class="bi bi-x-circle-fill"></i> Refuser tout
                                        </button>
                                    </form>
                                </div>
                             <?php elseif ($o['statut'] === 'en_attente'): ?>
                                <div style="display:flex;gap:6px;flex-wrap:wrap; margin-bottom:8px;">
                                    <button type="button" class="btn btn-warning"
                                            onclick="var f=document.getElementById('form_modif_<?php echo $o['id']; ?>'); f.style.display = f.style.display==='none'?'block':'none';">
                                        Modifier
                                    </button>
                                    <form method="POST">
                                        <input type="hidden" name="ordonnance_id" value="<?php echo $o['id']; ?>">
                                        <button type="submit" name="action" value="supprimer"
                                                class="btn btn-danger">
                                            Supprimer
                                        </button>
                                    </form>
                                </div>
                                <div class="form-devis" id="form_modif_<?php echo $o['id']; ?>" style="display:none; text-align: left;">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="modifier">
                                        <input type="hidden" name="ordonnance_id" value="<?php echo $o['id']; ?>">
                                        
                                        <label class="form-label" style="font-size: 11px; margin-bottom: 2px;">Nouveau fichier (vide = pas de changement)</label>
                                        <input type="file" name="nouvelle_ordonnance" accept="image/jpeg,image/png,application/pdf" class="form-control" style="font-size: 11px; padding: 4px; margin-bottom:8px;">
                                        
                                        <label class="form-label" style="font-size: 11px; margin-bottom: 2px;">Commentaire</label>
                                        <textarea name="description" rows="2" class="form-control" style="font-size: 12px; margin-bottom:8px;"><?php echo htmlspecialchars($o['description']); ?></textarea>
                                        
                                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Enregistrer</button>
                                    </form>
                                </div>

                            <?php elseif ($o['statut'] === 'acceptee'): ?>
                                <span style="font-size:12px;color:var(--vert);font-weight:500;">Confirmée <i class="bi bi-check-circle-fill"></i></span>
                            <?php elseif ($o['statut'] === 'refusee'): ?>
                                <span style="font-size:12px;color:var(--danger);">Refusée <i class="bi bi-x-circle-fill"></i></span>
                            <?php else: ?>
                                <span style="color:var(--gris-3);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-journal-text"></i></div>
            <h3>Aucune ordonnance</h3>
            <p>Déposez votre première ordonnance ci-dessus.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== TABLEAU ACHATS CATALOGUE ===== -->
    <div class="card anim-fade-up-2" style="margin-top: 24px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-bag"></i></div>
                Mes achats catalogue
                <span class="filter-count"><?php echo count($mes_achats_catalogue); ?></span>
            </div>
        </div>

        <?php if (count($mes_achats_catalogue) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Suivi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mes_achats_catalogue as $achat): ?>
                    <tr>
                        <td style="font-size:13px;color:var(--gris-4)">
                            <?php echo date('d/m/Y à H:i', strtotime($achat['date_commande'])); ?>
                        </td>
                        <td>
                            <span class="montant-badge"><?php echo number_format($achat['total'], 3, '.', ''); ?> DT</span>
                        </td>
                        <td>
                            <?php 
                            $badge_class = 'badge-preparation';
                            $statut_low = strtolower($achat['statut']);
                            if (strpos($statut_low, 'livraison') !== false) $badge_class = 'badge-en_livraison';
                            elseif (strpos($statut_low, 'livré') !== false || strpos($statut_low, 'livre') !== false) $badge_class = 'badge-livre';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $achat['statut']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (strtolower($achat['statut']) !== 'annulé' && strtolower($achat['statut']) !== 'annule'): ?>
                                <a href="suivi_commande.php?type=catalogue&id=<?php echo $achat['id']; ?>"
                                   class="btn btn-outline btn-sm">
                                    Voir le suivi →
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
           
            <h3>Aucun achat catalogue</h3>
            <p>Découvrez nos produits dans le <a href="catalogue.php" style="color:var(--vert); font-weight:600;">catalogue</a>.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .content-area -->

<!-- FOOTER -->
<footer class="footer-simple">
    <p> <strong>Medivo</strong> — Votre ordonnance, livrée chez vous.
    &nbsp;|&nbsp; <a href="index.php">Accueil</a></p>
</footer>

</div><!-- .page-wrapper -->

<script src="assets/js/main.js"></script>
<script>
    // Forcer la mise à jour au cas où
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof mettreAJourCompteurPanier === 'function') {
            mettreAJourCompteurPanier();
        }
    });
</script>
</body>
</html>