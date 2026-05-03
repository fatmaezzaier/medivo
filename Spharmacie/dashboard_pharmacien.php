<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

verifierSession('pharmacien');
$pharmacien_id = $_SESSION['utilisateur_id'];
$message       = "";
$erreur        = "";

// ACTION 1 : Envoyer un devis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_devis') {
    $oid         = (int)$_POST['ordonnance_id'];
    $prix        = (float)$_POST['prix'];
    $delai       = trim($_POST['delai']);
    $medicaments = trim($_POST['medicaments'] ?? '');

    $check = $pdo->prepare("SELECT id FROM devis WHERE ordonnance_id = ? AND pharmacien_id = ?");
    $check->execute([$oid, $pharmacien_id]);
    $existe = $check->fetch();
    $check->closeCursor();
    
    if ($existe) {
        $erreur = "Vous avez déjà envoyé un devis pour cette ordonnance.";
    } elseif ($prix <= 0) {
        $erreur = "Le prix doit être supérieur à 0.";
    } elseif (empty($delai)) {
        $erreur = "Veuillez indiquer un délai.";
    } else {
        $pdo->prepare("INSERT INTO devis (ordonnance_id, pharmacien_id, prix, delai, medicaments) VALUES (?,?,?,?,?)")
            ->execute([$oid, $pharmacien_id, $prix, $delai, $medicaments]);
        $pdo->prepare("UPDATE ordonnances SET statut='devis_envoye' WHERE id=?")
            ->execute([$oid]);
        $message = "Devis envoyé avec succès au client.";
    }
}

// ACTION 2 : Changer le statut d'une commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'changer_statut') {
    $commande_id    = (int)$_POST['commande_id'];
    $nouveau_statut = $_POST['nouveau_statut'];
    $notes          = trim($_POST['notes_pharmacien'] ?? '');
    $statuts_valides = ['preparation','en_livraison','livre','annule'];

    if (!in_array($nouveau_statut, $statuts_valides)) {
        $erreur = "Statut invalide.";
    } else {
        if ($nouveau_statut === 'livre') {
            $pdo->prepare("UPDATE commandes SET statut=?, notes_pharmacien=?, date_livraison=NOW() WHERE id=? AND pharmacien_id=?")
                ->execute([$nouveau_statut, $notes, $commande_id, $pharmacien_id]);
            $c = $pdo->prepare("SELECT ordonnance_id FROM commandes WHERE id=?");
            $c->execute([$commande_id]);
            $row = $c->fetch(); $c->closeCursor();
            if ($row) {
                $pdo->prepare("UPDATE ordonnances SET statut='livree' WHERE id=?")->execute([$row['ordonnance_id']]);
            }
        } else {
            $pdo->prepare("UPDATE commandes SET statut=?, notes_pharmacien=? WHERE id=? AND pharmacien_id=?")
                ->execute([$nouveau_statut, $notes, $commande_id, $pharmacien_id]);
        }
        $message = "Statut mis à jour.";
    }
}

// ACTION 3 : Changer le statut d'une commande catalogue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'changer_statut_catalogue') {
    $commande_id    = (int)$_POST['commande_id'];
    $nouveau_statut = $_POST['nouveau_statut'];
    
    // On assigne le pharmacien à la commande s'il la traite
    if ($nouveau_statut === 'Livré') {
        $pdo->prepare("UPDATE commandes_produits SET statut=?, pharmacien_id=?, date_livraison=NOW() WHERE id=?")
            ->execute([$nouveau_statut, $pharmacien_id, $commande_id]);
    } else {
        $pdo->prepare("UPDATE commandes_produits SET statut=?, pharmacien_id=? WHERE id=?")
            ->execute([$nouveau_statut, $pharmacien_id, $commande_id]);
    }
    $message = "Statut de la commande catalogue mis à jour.";
}

// LECTURE 1 : Ordonnances en attente de devis
$stmt1 = $pdo->prepare(
    "SELECT o.*, u.nom AS client_nom, u.email AS client_email,
            u.telephone AS client_tel, u.adresse
     FROM ordonnances o
     JOIN utilisateurs u ON u.id = o.client_id
     WHERE o.statut IN ('en_attente', 'devis_envoye')
     AND o.id NOT IN (SELECT ordonnance_id FROM devis WHERE pharmacien_id = ?)
     ORDER BY o.date_demande ASC"
);
$stmt1->execute([$pharmacien_id]);
$ordonnances_attente = $stmt1->fetchAll();
$stmt1->closeCursor();

// LECTURE 2 : Commandes en cours (via ordonnance)
$stmt2 = $pdo->prepare(
    "SELECT c.*, u.nom AS client_nom, u.telephone AS client_tel,
            d.prix, d.delai, d.medicaments
     FROM commandes c
     JOIN utilisateurs u ON u.id = c.client_id
     JOIN devis d         ON d.id = c.devis_id
     WHERE c.pharmacien_id=? AND c.statut IN ('preparation','en_livraison')
     ORDER BY c.date_commande ASC"
);
$stmt2->execute([$pharmacien_id]);
$commandes_actives = $stmt2->fetchAll();
$stmt2->closeCursor();

// LECTURE 3 : Commandes Catalogue (directes)
// On affiche les commandes non assignées OU assignées à ce pharmacien
$stmt4 = $pdo->prepare("
    SELECT cp.*, u.nom AS client_nom, u.telephone AS client_tel, u.adresse AS user_adresse
    FROM commandes_produits cp
    JOIN utilisateurs u ON u.id = cp.client_id
    WHERE cp.statut NOT IN ('Livré', 'Annulé')
    AND (cp.pharmacien_id IS NULL OR cp.pharmacien_id = ?)
    ORDER BY cp.date_commande ASC
");
$stmt4->execute([$pharmacien_id]);
$commandes_catalogue = $stmt4->fetchAll();

// Unification des commandes actives
foreach ($commandes_catalogue as $cc) {
    $cc['is_catalogue'] = true;
    $cc['display_prix'] = $cc['total'];
    $cc['display_adresse'] = !empty($cc['adresse_livraison']) ? $cc['adresse_livraison'] : $cc['user_adresse'];
    
    // Récupérer les produits pour l'affichage
    $stmt_p = $pdo->prepare("SELECT cp.*, p.nom FROM details_commande_produits cp JOIN produits p ON p.id = cp.produit_id WHERE cp.commande_id = ?");
    $stmt_p->execute([$cc['id']]);
    $details = $stmt_p->fetchAll();
    $meds = [];
    foreach($details as $d) $meds[] = $d['nom'] . ' (x' . $d['quantite'] . ')';
    $cc['display_meds'] = implode(', ', $meds);
    
    $commandes_actives[] = $cc;
}
// Trier par date
usort($commandes_actives, function($a, $b) {
    return strtotime($a['date_commande']) - strtotime($b['date_commande']);
});

// LECTURE 4 : Historique
$stmt3 = $pdo->prepare(
    "SELECT c.*, u.nom AS client_nom, d.prix, d.medicaments
     FROM commandes c
     JOIN utilisateurs u ON u.id = c.client_id
     JOIN devis d         ON d.id = c.devis_id
     WHERE c.pharmacien_id=? AND c.statut IN ('livre','annule')
     ORDER BY c.date_commande DESC LIMIT 10"
);
$stmt3->execute([$pharmacien_id]);
$historique_ordo = $stmt3->fetchAll();
$stmt3->closeCursor();

// Historique Catalogue (uniquement ce pharmacien)
$stmt5 = $pdo->prepare("
    SELECT cp.*, u.nom AS client_nom
    FROM commandes_produits cp
    JOIN utilisateurs u ON u.id = cp.client_id
    WHERE cp.statut IN ('Livré', 'Annulé')
    AND cp.pharmacien_id = ?
    ORDER BY cp.date_commande DESC LIMIT 10
");
$stmt5->execute([$pharmacien_id]);
$historique_catalogue = $stmt5->fetchAll();

// Unification de l'historique pour l'affichage
$historique = [];
foreach($historique_ordo as $h) {
    $h['display_prix'] = $h['prix'];
    $h['display_meds'] = $h['medicaments'];
    $h['display_type'] = 'Ordonnance';
    $historique[] = $h;
}
foreach($historique_catalogue as $h) {
    $h['display_prix'] = $h['total'];
    // date_livraison et notes_pharmacien sont déjà dans cp.*
    
    // Récupérer les produits pour l'historique
    $stmt_det = $pdo->prepare("SELECT cp.*, p.nom FROM details_commande_produits cp JOIN produits p ON p.id = cp.produit_id WHERE cp.commande_id = ?");
    $stmt_det->execute([$h['id']]);
    $details = $stmt_det->fetchAll();
    $meds = [];
    foreach($details as $d) $meds[] = $d['nom'] . ' (x' . $d['quantite'] . ')';
    $h['display_meds'] = implode(', ', $meds);
    $h['display_type'] = 'Catalogue';
    $historique[] = $h;
}

// Trier par date décroissante
usort($historique, function($a, $b) {
    return strtotime($b['date_commande']) - strtotime($a['date_commande']);
});

// CA total
$stmt_ca = $pdo->prepare("SELECT SUM(d.prix) as total FROM commandes c JOIN devis d ON d.id=c.devis_id WHERE c.pharmacien_id=? AND c.statut='livre'");
$stmt_ca->execute([$pharmacien_id]);
$res_ca = $stmt_ca->fetch();
$total_ca = (float)$res_ca['total'];

$stmt_ca2 = $pdo->prepare("SELECT SUM(total) as total FROM commandes_produits WHERE statut='Livré' AND pharmacien_id=?");
$stmt_ca2->execute([$pharmacien_id]);
$res_ca2 = $stmt_ca2->fetch();
$total_ca += (float)$res_ca2['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pharmacien — Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
            <span class="badge-role pharmacien">Pharmacien</span>
            <a href="gerer_produits.php" class="btn btn-primary btn-sm">Gérer le catalogue</a>
            <a href="catalogue.php" class="btn btn-outline btn-sm">Voir le catalogue</a>
            <a href="deconnexion.php" class="btn btn-ghost btn-sm">Déconnexion</a>
        </div>
    </div>
</nav>

<!-- ========== CONTENU ========== -->
<div class="content-area">

    <?php if ($message): ?>
        <div class="alert alert-success anim-fade-in"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-danger anim-fade-in"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($erreur); ?></div>
    <?php endif; ?>

    <!-- En-tête -->
    <div class="page-header anim-fade-up">
        <div class="page-header-left">
            <h1>Dashboard pharmacien</h1>
            <p>Gérez les ordonnances et suivez les commandes</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid anim-fade-up-2">
        <div class="stat-card">
            <div class="stat-label">En attente</div>
            <div class="stat-value"><?php echo count($ordonnances_attente); ?></div>
            <div class="stat-sub">ordonnance(s) à traiter</div>
        </div>
        <div class="stat-card or">
            <div class="stat-label">En cours</div>
            <div class="stat-value"><?php echo count($commandes_actives); ?></div>
            <div class="stat-sub">commande(s) active(s)</div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">CA livré</div>
            <div class="stat-value"><?php echo number_format($total_ca, 0); ?></div>
            <div class="stat-sub">dinars</div>
        </div>
    </div>

    <!-- ===== SECTION 1 : Ordonnances en attente ===== -->
    <div class="card anim-fade-up-3">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-hourglass-split"></i></div>
                Ordonnances en attente de devis
                <span class="filter-count"><?php echo count($ordonnances_attente); ?></span>
            </div>
        </div>

        <?php if (count($ordonnances_attente) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Adresse</th>
                        <th>Ordonnance</th>
                        <th>Envoyer le devis</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ordonnances_attente as $o): ?>
                    <tr class="row-nouveau">
                        <td style="font-size:12px;color:var(--gris-4)">
                            <?php echo formatDate($o['date_demande'], true); ?>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="name"><?php echo nettoyerChaine($o['client_nom']); ?></div>
                                <div class="sub"><?php echo nettoyerChaine($o['client_email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="sub"><?php echo nettoyerChaine($o['client_tel'] ?? '—'); ?></div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--gris-4);max-width:140px;">
                            <?php echo nettoyerChaine($o['adresse'] ?? '—'); ?>
                        </td>
                        <td>
                            <a href="voir_ordonnance.php?id=<?php echo $o['id']; ?>"
                               target="_blank" class="btn btn-outline btn-sm">
                                 Voir
                            </a>
                            <?php if ($o['description']): ?>
                                <div style="font-size:11px;color:var(--gris-3);margin-top:4px;">
                                    <?php echo tronquer(nettoyerChaine($o['description']), 50); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Bouton qui ouvre le formulaire via JS toggleFormulaireDevis() -->
                            <button class="btn btn-primary btn-sm"
                                    id="btn_devis_<?php echo $o['id']; ?>"
                                    onclick="toggleFormulaireDevis(<?php echo $o['id']; ?>)">
                                 Envoyer un devis
                            </button>

                            <!-- Formulaire devis (caché par défaut, ouvert par JS) -->
                            <div class="form-devis" id="form_devis_<?php echo $o['id']; ?>">
                                <form method="POST">
                                    <input type="hidden" name="action" value="envoyer_devis">
                                    <input type="hidden" name="ordonnance_id" value="<?php echo $o['id']; ?>">

                                    <input type="number" name="prix" step="0.01" min="0.01"
                                           placeholder="Prix en DT (ex: 45.50)" required>

                                    <input type="text" name="delai"
                                           placeholder="Délai (ex: 2h, demain matin)" required>

                                    <textarea name="medicaments" rows="2"
                                              placeholder="Médicaments (ex: Paracétamol 500mg x2...)"></textarea>

                                    <button type="submit" class="btn btn-or btn-sm">
                                        Confirmer et envoyer
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
       
            <h3>Aucune ordonnance en attente</h3>
            <p>Toutes les ordonnances ont été traitées.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== SECTION 2 : Commandes en cours (Ordonnances + Catalogue) ===== -->
    <div class="card anim-fade-up-4">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-truck"></i></div>
                Commandes en cours
                <span class="filter-count"><?php echo count($commandes_actives); ?></span>
            </div>
        </div>

        <?php if (count($commandes_actives) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Adresse livraison</th>
                        <th>Montant</th>
                        <th>Détails produits</th>
                        <th>Statut actuel</th>
                        <th>Changer le statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commandes_actives as $c): ?>
                    <?php 
                    $is_cat = isset($c['is_catalogue']) && $c['is_catalogue']; 
                    $display_prix = $is_cat ? $c['display_prix'] : $c['prix'];
                    $display_adresse = $is_cat ? $c['display_adresse'] : $c['adresse_livraison'];
                    $display_meds = $is_cat ? $c['display_meds'] : $c['medicaments'];
                    ?>
                    <tr>
                        <td style="font-weight:600;color:var(--gris-4)">
                            #<?php echo $c['id']; ?>
                            <?php if($is_cat): ?>
                                <div style="font-size:10px; color:var(--primary-color)">Catalogue</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="name"><?php echo nettoyerChaine($c['client_nom']); ?></div>
                                <div class="sub"><?php echo nettoyerChaine($c['client_tel'] ?? ''); ?></div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--gris-4);max-width:140px;">
                            <?php echo nettoyerChaine($display_adresse); ?>
                        </td>
                        <td>
                            <span class="montant-badge <?php echo $is_cat ? '' : 'or'; ?>">
                                <?php echo formatPrix((float)$display_prix); ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--gris-4)">
                            <?php echo $display_meds ? tronquer(nettoyerChaine($display_meds), 45) : '—'; ?>
                        </td>
                        <td>
                            <?php
                            if ($is_cat) {
                                $badge_class = 'badge-preparation';
                                $statut_low = strtolower($c['statut']);
                                if (strpos($statut_low, 'livraison') !== false) $badge_class = 'badge-en_livraison';
                                echo '<span class="badge '.$badge_class.'">'.$c['statut'].'</span>';
                            } else {
                                $classeCmd = [
                                    'preparation'  => 'badge-livraison',
                                    'en_livraison' => 'badge-devis',
                                ];
                                $cls = $classeCmd[$c['statut']] ?? '';
                                echo '<span class="badge '.$cls.'">'.getLibelleStatutCommande($c['statut']).'</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="<?php echo $is_cat ? 'changer_statut_catalogue' : 'changer_statut'; ?>">
                                <input type="hidden" name="commande_id" value="<?php echo $c['id']; ?>">

                                <?php if($is_cat): ?>
                                    <select name="nouveau_statut" class="select-statut">
                                        <option value="En préparation" <?php echo $c['statut']==='En préparation' ? 'selected':''; ?>>En préparation</option>
                                        <option value="En livraison" <?php echo $c['statut']==='En livraison' ? 'selected':''; ?>>En livraison</option>
                                        <option value="Livré">Livré <i class="bi bi-check-circle-fill"></i></option>
                                        <option value="Annulé">Annuler <i class="bi bi-x-circle-fill"></i></option>
                                    </select>
                                <?php else: ?>
                                    <select name="nouveau_statut" class="select-statut"
                                            data-precedent="<?php echo $c['statut']; ?>"
                                            onchange="confirmerChangementStatut(this)">
                                        <option value="preparation"  <?php echo $c['statut']==='preparation'  ? 'selected':''; ?>>En préparation</option>
                                        <option value="en_livraison" <?php echo $c['statut']==='en_livraison' ? 'selected':''; ?>>En livraison</option>
                                        <option value="livre">Livré <i class="bi bi-check-circle-fill"></i></option>
                                        <option value="annule">Annuler <i class="bi bi-x-circle-fill"></i></option>
                                    </select>
                                <?php endif; ?>

                                <?php if(!$is_cat): ?>
                                    <textarea name="notes_pharmacien" rows="2"
                                              class="notes-inline"
                                              placeholder="Note pour le client (optionnel)"><?php
                                        echo nettoyerChaine($c['notes_pharmacien'] ?? '');
                                    ?></textarea>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px;">
                                    Mettre à jour
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
         
            <h3>Aucune commande en cours</h3>
            <p>Les commandes acceptées par les clients apparaîtront ici.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== SECTION 4 : Historique ===== -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon"><i class="bi bi-clock-history"></i></div>
                Historique des commandes
                <span class="filter-count"><?php echo count($historique); ?></span>
            </div>
        </div>

        <?php if (count($historique) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Médicaments</th>
                        <th>Statut final</th>
                        <th>Date livraison</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historique as $h): ?>
                    <tr class="history-row">
                        <td style="font-weight:600;color:var(--gris-4)">#<?php echo $h['id']; ?></td>
                        <td style="font-size:12px;color:var(--gris-4)">
                            <?php echo formatDate($h['date_commande']); ?>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="name"><?php echo nettoyerChaine($h['client_nom']); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="montant-badge">
                                <?php echo number_format($h['display_prix'], 3, '.', ''); ?> DT
                            </span>
                            <div style="font-size:10px; color:var(--gris-3); text-align:center;"><?php echo $h['display_type']; ?></div>
                        </td>
                        <td style="font-size:11px;color:var(--gris-4)">
                            <?php echo $h['display_meds'] ? tronquer(nettoyerChaine($h['display_meds']), 45) : '—'; ?>
                        </td>
                        <td>
                            <?php 
                            $status_val = $h['statut'];
                            $is_delivered = in_array($status_val, ['livre', 'Livré', 'livré', 'Livre']);
                            if ($is_delivered): 
                            ?>
                                <span class="badge badge-livre"><i class="bi bi-check-circle-fill"></i> Livré</span>
                            <?php else: ?>
                                <span class="badge badge-annule"><i class="bi bi-x-circle-fill"></i> Annulé</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--gris-4)">
                            <?php echo $h['date_livraison'] ? formatDate($h['date_livraison']) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
         
            <h3>Aucun historique</h3>
            <p>Les commandes livrées ou annulées apparaîtront ici.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .content-area -->

<!-- FOOTER -->
<footer class="footer-simple">
    <p> <strong>Medivo</strong> — Espace pharmacien
    &nbsp;|&nbsp; <a href="index.php">Accueil</a></p>
</footer>

</div><!-- .page-wrapper -->
<script src="assets/js/main.js"></script>
</body>
</html>