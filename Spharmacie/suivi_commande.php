<?php
session_start();
require_once 'config/database.php';
require_once 'includes/fonctions.php';

// Sécurité : client uniquement
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['utilisateur_role'] !== 'client') {
    header('Location: connexion.php');
    exit();
}

$client_id   = $_SESSION['utilisateur_id'];
$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type        = $_GET['type'] ?? 'ordonnance';

if ($commande_id === 0) {
    header('Location: dashboard_client.php');
    exit();
}

$cmd = null;

if ($type === 'catalogue') {
    // Récupérer la commande catalogue + adresse du client
    $stmt = $pdo->prepare("
        SELECT cp.*, u.adresse AS user_adresse 
        FROM commandes_produits cp 
        JOIN utilisateurs u ON u.id = cp.client_id
        WHERE cp.id = ? AND cp.client_id = ?
    ");
    $stmt->execute([$commande_id, $client_id]);
    $res = $stmt->fetch();
    
    if ($res) {
        // Normaliser le statut pour la timeline
        $statut_key = 'preparation';
        $s = strtolower($res['statut']);
        if (strpos($s, 'livraison') !== false) $statut_key = 'en_livraison';
        elseif (strpos($s, 'livré') !== false || strpos($s, 'livre') !== false) $statut_key = 'livre';
        elseif (strpos($s, 'annulé') !== false || strpos($s, 'annule') !== false) $statut_key = 'annule';

        // Récupérer les produits
        $stmt_p = $pdo->prepare("
            SELECT cp.*, p.nom 
            FROM details_commande_produits cp 
            JOIN produits p ON p.id = cp.produit_id 
            WHERE cp.commande_id = ?
        ");
        $stmt_p->execute([$commande_id]);
        $details = $stmt_p->fetchAll();
        $liste_meds = [];
        foreach($details as $d) $liste_meds[] = $d['nom'] . " (x" . $d['quantite'] . ")";
        
        $cmd = [
            'id' => $res['id'],
            'statut' => $statut_key,
            'date_commande' => $res['date_commande'],
            'prix' => $res['total'],
            'type' => 'catalogue',
            'medicaments' => implode(', ', $liste_meds),
            'delai' => 'Sous 24/48h',
            'pharmacien_nom' => 'Medivo Centrale',
            'pharmacien_tel' => '71 000 000',
            'adresse_livraison' => !empty($res['adresse_livraison']) ? $res['adresse_livraison'] : ($res['user_adresse'] ?? 'Non renseignée'),
            'date_livraison' => null,
            'notes_pharmacien' => null,
            'ordonnance_id' => 0
        ];
    }
} else {
    // Récupérer la commande ordonnance — vérifier qu'elle appartient au client
    $stmt = $pdo->prepare(
        "SELECT c.*,
                o.fichier_ordonnance, o.description AS ordo_description, o.date_demande,
                d.prix, d.delai, d.medicaments,
                u.nom AS pharmacien_nom, u.telephone AS pharmacien_tel
         FROM commandes c
         JOIN ordonnances o ON o.id = c.ordonnance_id
         JOIN devis d       ON d.id = c.devis_id
         JOIN utilisateurs u ON u.id = c.pharmacien_id
         WHERE c.id = ? AND c.client_id = ?"
    );
    $stmt->execute([$commande_id, $client_id]);
    $cmd = $stmt->fetch();
    if ($cmd) $cmd['type'] = 'ordonnance';
}

if (!$cmd || $cmd['statut'] === 'annule' || $cmd['statut'] === 'Annulé') {
    header('Location: dashboard_client.php');
    exit();
}

// Définir les étapes de la timeline
$etapes = [
    'preparation'  => ['label' => 'En préparation',  'desc' => 'Le pharmacien prépare votre commande', 'icon' => 'inventory_2'],
    'en_livraison' => ['label' => 'En livraison',     'desc' => 'Votre commande est en route', 'icon' => 'local_shipping'],
    'livre'        => ['label' => 'Livré',            'desc' => 'Commande livrée à votre adresse', 'icon' => 'task_alt'],
];
$ordre  = array_keys($etapes);
$actuel = array_search($cmd['statut'], $ordre);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi commande #<?php echo $commande_id; ?> — Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .timeline-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 40px 0;
            position: relative;
            padding: 0 20px;
        }
        .timeline-container::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 4px;
            background: var(--gris-2);
            z-index: 1;
        }
        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        .step-bubble {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--blanc);
            border: 4px solid var(--gris-2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            transition: var(--transition);
            color: var(--gris-3);
        }
        .timeline-step.completed .step-bubble {
            background: var(--vert);
            border-color: var(--vert);
            color: white;
        }
        .timeline-step.active .step-bubble {
            background: var(--blanc);
            border-color: var(--vert);
            color: var(--vert);
            box-shadow: 0 0 0 4px var(--vert-clair);
        }
        .step-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--gris-4);
            margin-bottom: 4px;
        }
        .timeline-step.active .step-label { color: var(--vert); }
        .step-desc {
            font-size: 11px;
            color: var(--gris-3);
            max-width: 120px;
            margin: 0 auto;
        }
        .timeline-progress {
            position: absolute;
            top: 20px;
            left: 50px;
            height: 4px;
            background: var(--vert);
            z-index: 1;
            transition: width 0.5s ease;
        }
    </style>
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
                <a href="dashboard_client.php" class="btn btn-ghost btn-sm">← Retour à mon espace</a>
                <a href="deconnexion.php" class="btn btn-ghost btn-sm">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="content-area">
        <div class="page-header anim-fade-up">
            <div class="page-header-left">
                <h1>Suivi de commande #<?php echo $commande_id; ?></h1>
                <p>Passée le <?php echo date('d/m/Y à H:i', strtotime($cmd['date_commande'])); ?></p>
            </div>
            <div class="page-header-right">
                <span class="badge badge-<?php echo $cmd['statut']; ?>"><?php echo $etapes[$cmd['statut']]['label'] ?? $cmd['statut']; ?></span>
            </div>
        </div>

        <?php if ($cmd['statut'] === 'annule'): ?>
            <div class="alert alert-danger anim-fade-in">
                <span class="material-symbols-outlined">cancel</span>
                Cette commande a été annulée.
            </div>
        <?php else: ?>
            <div class="card anim-fade-up-2">
                <div class="timeline-container">
                    <?php 
                    $progress_width = 0;
                    if ($actuel !== false) {
                        $progress_width = ($actuel / (count($ordre) - 1)) * 100;
                    }
                    ?>
                    <div class="timeline-progress" style="width: calc(<?php echo $progress_width; ?>% - 100px); left: 50px;"></div>
                    
                    <?php foreach ($etapes as $key => $info):
                        $idx = array_search($key, $ordre);
                        $is_done = $idx < $actuel;
                        $is_actif = $idx === $actuel;
                        $class = $is_done ? 'completed' : ($is_actif ? 'active' : '');
                    ?>
                        <div class="timeline-step <?php echo $class; ?>">
                            <div class="step-bubble">
                                <span class="material-symbols-outlined"><?php echo $info['icon']; ?></span>
                            </div>
                            <div class="step-label"><?php echo $info['label']; ?></div>
                            <div class="step-desc"><?php echo $info['desc']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid-2 anim-fade-up-3" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
            <!-- Détails -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-icon"><i class="bi bi-box-seam"></i></div>
                        Détails de la livraison
                    </div>
                </div>
                <div class="info-list">
                    <div class="info-item">
                        <div class="info-label">Adresse de livraison</div>
                        <div class="info-value"><?php echo htmlspecialchars($cmd['adresse_livraison']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pharmacien partenaire</div>
                        <div class="info-value">
                            <strong><?php echo htmlspecialchars($cmd['pharmacien_nom']); ?></strong><br>
                            <span style="font-size: 13px; color: var(--gris-3);"><?php echo htmlspecialchars($cmd['pharmacien_tel']); ?></span>
                        </div>
                    </div>
                    <?php if ($cmd['date_livraison']): ?>
                    <div class="info-item">
                        <div class="info-label">Date de livraison effective</div>
                        <div class="info-value"><?php echo date('d/m/Y à H:i', strtotime($cmd['date_livraison'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Récapitulatif -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-icon"><i class="bi bi-receipt-cutoff"></i></div>
                        Récapitulatif financier
                    </div>
                </div>
                <div class="info-list">
                    <div class="info-item">
                        <div class="info-label">Montant total</div>
                        <div class="info-value" style="font-size: 24px; color: var(--vert); font-weight: 700;">
                            <?php echo number_format($cmd['prix'], 3, '.', ' '); ?> DT
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Délai annoncé</div>
                        <div class="info-value"><?php echo htmlspecialchars($cmd['delai']); ?></div>
                    </div>
                    <?php if ($cmd['medicaments']): ?>
                    <div class="info-item">
                        <div class="info-label"><?php echo $cmd['type'] === 'catalogue' ? 'Produits inclus' : 'Médicaments inclus'; ?></div>
                        <div class="info-value" style="font-size: 13px;"><?php echo nl2br(htmlspecialchars($cmd['medicaments'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($cmd['type'] === 'ordonnance'): ?>
                    <div class="info-item" style="border-top: 1px solid var(--gris-2); padding-top: 16px; margin-top: 8px;">
                        <a href="voir_ordonnance.php?id=<?php echo $cmd['ordonnance_id']; ?>" target="_blank" class="btn btn-outline btn-sm btn-block">
                            <span class="material-symbols-outlined">visibility</span> Voir l'ordonnance originale
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($cmd['notes_pharmacien']): ?>
            <div class="card anim-fade-up-4" style="margin-top: 24px;">
                <div class="card-header">
                    <div class="card-title">
                       
                        Note du pharmacien
                    </div>
                </div>
                <div style="padding: 16px; background: var(--gris-1); border-radius: 8px; font-style: italic; color: var(--gris-4);">
                    "<?php echo nl2br(htmlspecialchars($cmd['notes_pharmacien'])); ?>"
                </div>
            </div>
        <?php endif; ?>

    </div>

    <footer class="footer-simple">
        <p> <strong>Medivo</strong> — Votre santé, notre priorité.</p>
    </footer>

</div>
<script src="assets/js/main.js"></script>
</body>
</html>