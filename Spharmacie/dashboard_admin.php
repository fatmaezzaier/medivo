<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

verifierSession('admin');
$admin_id = $_SESSION['utilisateur_id'];
$message  = "";
$erreur   = "";

if (isset($_SESSION['message_admin'])) {
    $message = $_SESSION['message_admin'];
    unset($_SESSION['message_admin']);
}
if (isset($_SESSION['erreur_admin'])) {
    $erreur = $_SESSION['erreur_admin'];
    unset($_SESSION['erreur_admin']);
}

// LECTURE 1: Clients
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE role = 'client' ORDER BY id DESC");
$stmt->execute();
$clients = $stmt->fetchAll();
$stmt->closeCursor();

// LECTURE 2: Pharmaciens
$stmt2 = $pdo->prepare("SELECT * FROM utilisateurs WHERE role = 'pharmacien' ORDER BY id DESC");
$stmt2->execute();
$pharmaciens = $stmt2->fetchAll();
$stmt2->closeCursor();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Medivo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<nav class="navbar" style="border-bottom: 2px solid var(--rouge);">
    <div class="container">
        <a href="index.php" class="navbar-brand" style="gap: 0; margin-left: -8px;">
            <div class="brand-icon" style="background: none !important; border-radius: 0; width: auto; height: 120px;">
                <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
            </div>
            <div>Medi<span style="color:var(--gris-4);font-weight:400">vo</span></div>
        </a>
        <div class="navbar-right">
            <span class="navbar-username"><?php echo nettoyerChaine($_SESSION['utilisateur_nom']); ?></span>
            <span class="badge" style="background-color: var(--rouge); color: white;">Administrateur</span>
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
            <h1>Administration plateforme</h1>
            <p>Gérez les comptes clients et pharmaciens de Medivo</p>
        </div>
        <div class="page-header-right">
            <a href="admin_form_user.php" class="btn btn-primary">Ajouter un utilisateur</a>
        </div>
    </div>

    <!-- Diagramme et Stats -->
    <div class="stats-container anim-fade-up-2" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px;">
        <!-- Petit récapitulatif -->
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <div class="stat-card info" style="margin:0;">
                <div class="stat-label">Clients inscrits</div>
                <div class="stat-value"><?php echo count($clients); ?></div>
            </div>
            <div class="stat-card or" style="margin:0;">
                <div class="stat-label">Pharmaciens</div>
                <div class="stat-value"><?php echo count($pharmaciens); ?></div>
            </div>
        </div>

        <!-- Diagramme -->
        <div class="card" style="margin:0; padding: 20px;">
            <div class="card-title" style="margin-bottom: 15px; font-size: 16px;">
                <i class="bi bi-pie-chart-fill"></i> Répartition des utilisateurs
            </div>
            <div style="height: 200px; display: flex; justify-content: center;">
                <canvas id="userChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('userChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Clients', 'Pharmaciens'],
                datasets: [{
                    data: [<?php echo count($clients); ?>, <?php echo count($pharmaciens); ?>],
                    backgroundColor: ['#1e3a8a', '#be185d'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12, family: "'Inter', sans-serif" }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
    </script>

    <!-- ===== SECTION 1 : Pharmaciens ===== -->
    <div class="card anim-fade-up-3">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon" style= "color:black;"><i class="bi bi-person-heart"></i></div>
                Gestion des pharmaciens
                <span class="filter-count"><?php echo count($pharmaciens); ?></span>
            </div>
            <div class="card-actions">
                <a href="admin_form_user.php?role=pharmacien" class="btn btn-ghost btn-sm">Ajouter</a>
            </div>
        </div>

        <?php if (count($pharmaciens) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom complet</th>
                        <th>Contact</th>
                        <th>Opérations</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pharmaciens as $p): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--gris-4)">#<?php echo $p['id']; ?></td>
                        <td>
                            <div class="client-info">
                                <div class="name"><?php echo nettoyerChaine($p['nom']); ?></div>
                                <div class="sub"><?php echo nettoyerChaine($p['email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="sub"><?php echo nettoyerChaine($p['telephone'] ?? '—'); ?></div>
                            </div>
                        </td>
                        <td>
                            <a href="admin_form_user.php?id=<?php echo $p['id']; ?>" class="btn btn-ghost btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                            <a href="admin_delete_user.php?id=<?php echo $p['id']; ?>" class="btn btn-danger-ol btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce pharmacien ? Cette action supprimera également ses devis et commandes liés.');"><i class="bi bi-trash3"></i> Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-person-heart"></i></div>
            <h3>Aucun pharmacien</h3>
            <p>Il n'y a actuellement aucun pharmacien inscrit sur la plateforme.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== SECTION 2 : Clients ===== -->
    <div class="card anim-fade-up-4">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon" style="color:black;"><i class="bi bi-person-fill-gear"></i></div>
                Gestion des clients
                <span class="filter-count"><?php echo count($clients); ?></span>
            </div>
            <div class="card-actions">
                <a href="admin_form_user.php?role=client" class="btn btn-ghost btn-sm">Ajouter</a>
            </div>
        </div>

        <?php if (count($clients) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Contact / Adresse</th>
                        <th>Opérations</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--gris-4)">#<?php echo $c['id']; ?></td>
                        <td>
                            <div class="client-info">
                                <div class="name"><?php echo nettoyerChaine($c['nom']); ?></div>
                                <div class="sub"><?php echo nettoyerChaine($c['email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="client-info">
                                <div class="sub"><?php echo nettoyerChaine($c['telephone'] ?? '—'); ?></div>
                                <div class="sub" style="font-size:11px;"><?php echo nettoyerChaine($c['adresse'] ?? '—'); ?></div>
                            </div>
                        </td>
                        <td>
                            <a href="admin_form_user.php?id=<?php echo $c['id']; ?>" class="btn btn-ghost btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                            <a href="admin_delete_user.php?id=<?php echo $c['id']; ?>" class="btn btn-danger-ol btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action supprimera ses ordonnances et commandes en cours.');"><i class="bi bi-trash3"></i> Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-person-fill"></i></div>
            <h3>Aucun client</h3>
            <p>Il n'y a actuellement aucun client inscrit sur la plateforme.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .content-area -->

<!-- FOOTER -->
<footer class="footer-simple">
    <p><strong>Medivo</strong> — Espace Administrateur</p>
</footer>

</div><!-- .page-wrapper -->
<script src="assets/js/main.js"></script>
</body>
</html>