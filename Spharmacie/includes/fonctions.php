<?php
// includes/fonctions.php
// Fonctions PHP réutilisables — Medivo
// Notions couvertes : fonctions, tableaux, array_sum, count,
// sort, in_array, foreach, passage par référence, closures

// ============================================================
// 1. FONCTIONS LIÉES AUX STATUTS
// ============================================================

/**
 * Retourne le libellé lisible d'un statut d'ordonnance
 * Notion : tableau associatif + in_array()
 */
function getLibelleStatutOrdonnance(string $statut): string {
    $labels = [
        'en_attente'   => 'En attente',
        'devis_envoye' => 'Devis envoyé',
        'acceptee'     => 'Acceptée',
        'refusee'      => 'Refusée',
        'livree'       => 'Livrée',
    ];
    $statuts_valides = array_keys($labels);
    if (in_array($statut, $statuts_valides)) {
        return $labels[$statut];
    }
    return 'Inconnu';
}

/**
 * Retourne le libellé lisible d'un statut de commande
 */
function getLibelleStatutCommande(string $statut): string {
    $labels = [
        'preparation'  => 'En préparation',
        'en_livraison' => 'En livraison',
        'livre'        => 'Livré',
        'annule'       => 'Annulé',
    ];
    return $labels[$statut] ?? 'Inconnu';
}

// ============================================================
// 2. FONCTIONS STATISTIQUES
// Notion : fonctions, array_sum, count, sort
// ============================================================

/**
 * Calcule la moyenne des montants d'un tableau de commandes
 * Notion : array_sum(), count(), fonction avec return
 */
function calculerMoyenneCommandes(array $commandes): float {
    if (count($commandes) === 0) return 0.0;
    $montants = [];
    foreach ($commandes as $cmd) {
        $montants[] = (float)$cmd['prix'];
    }
    return array_sum($montants) / count($montants);
}

/**
 * Retourne le montant total de toutes les commandes
 * Notion : foreach, array_sum
 */
function calculerTotalCommandes(array $commandes): float {
    $montants = [];
    foreach ($commandes as $cmd) {
        $montants[] = (float)$cmd['prix'];
    }
    return array_sum($montants);
}

/**
 * Trie un tableau de commandes par montant croissant
 * Notion : sort() / usort(), tableaux
 */
function trierCommandesParMontant(array &$commandes): void {
    // Passage par référence : modifie directement le tableau original
    usort($commandes, function($a, $b) {
        return $a['prix'] <=> $b['prix'];
    });
}

/**
 * Trouve la commande avec le montant le plus élevé
 * Notion : foreach, comparaison, return
 */
function trouverCommandeMax(array $commandes): ?array {
    if (count($commandes) === 0) return null;
    $max = $commandes[0];
    foreach ($commandes as $cmd) {
        if ((float)$cmd['prix'] > (float)$max['prix']) {
            $max = $cmd;
        }
    }
    return $max;
}

// ============================================================
// 3. FONCTIONS DE COMPTAGE PAR STATUT
// Notion : foreach, tableau associatif, count
// ============================================================

/**
 * Compte les ordonnances par statut
 * Retourne un tableau associatif : ['en_attente' => 3, ...]
 * Notion : tableau associatif, foreach, array_push implicite
 */
function compterOrdonnancesParStatut(array $ordonnances): array {
    $compteurs = [
        'en_attente'   => 0,
        'devis_envoye' => 0,
        'acceptee'     => 0,
        'refusee'      => 0,
        'livree'       => 0,
    ];
    foreach ($ordonnances as $o) {
        $s = $o['statut'];
        if (isset($compteurs[$s])) {
            $compteurs[$s]++;
        }
    }
    return $compteurs;
}

/**
 * Retourne uniquement les ordonnances d'un statut donné
 * Notion : foreach, array_push, in_array
 */
function filtrerOrdonnancesParStatut(array $ordonnances, string $statut): array {
    $statuts_valides = ['en_attente','devis_envoye','acceptee','refusee','livree'];
    if (!in_array($statut, $statuts_valides)) return [];

    $resultat = [];
    foreach ($ordonnances as $o) {
        if ($o['statut'] === $statut) {
            array_push($resultat, $o);
        }
    }
    return $resultat;
}

// ============================================================
// 4. FONCTIONS DE FORMATAGE
// Notion : fonctions, chaînes, return
// ============================================================

/**
 * Formate un prix en dinars tunisiens
 * Notion : fonction simple avec return
 */
function formatPrix(float $prix): string {
    return number_format($prix, 2, '.', ' ') . ' DT';
}

/**
 * Formate une date en français
 * Notion : fonction, chaînes de caractères
 */
function formatDate(string $date_sql, bool $avec_heure = false): string {
    if (empty($date_sql)) return '—';
    if ($avec_heure) {
        return date('d/m/Y à H:i', strtotime($date_sql));
    }
    return date('d/m/Y', strtotime($date_sql));
}

/**
 * Tronque un texte long et ajoute "..."
 * Notion : fonctions, chaînes
 */
function tronquer(string $texte, int $longueur = 60): string {
    if (strlen($texte) <= $longueur) return $texte;
    return substr($texte, 0, $longueur) . '...';
}

// ============================================================
// 5. FONCTIONS DE SÉCURITÉ / VALIDATION
// Notion : fonctions, chaînes, return bool
// ============================================================

/**
 * Vérifie qu'un utilisateur est connecté avec le bon rôle
 * Redirige si non autorisé
 * Notion : fonction, session, header
 */
function verifierSession(string $role_requis): void {
    if (!isset($_SESSION['utilisateur_id'])) {
        header('Location: connexion.php');
        exit();
    }
    if ($_SESSION['utilisateur_role'] !== $role_requis) {
        header('Location: connexion.php');
        exit();
    }
}

/**
 * Nettoie et sécurise une chaîne venant de l'utilisateur
 * Notion : fonctions, chaînes
 */
function nettoyerChaine(?string $chaine): string {
    if ($chaine === null) return '';
    return htmlspecialchars(trim($chaine), ENT_QUOTES, 'UTF-8');
}

/**
 * Valide un numéro de téléphone tunisien (8 chiffres)
 * Notion : fonction, return bool
 */
function validerTelephone(string $tel): bool {
    $tel_propre = preg_replace('/[\s\.\-]/', '', $tel);
    return preg_match('/^[0-9]{8}$/', $tel_propre) === 1;
}

// ============================================================
// 6. CLOSURE (fonction anonyme) — cours 03 p.23
// Notion : closure / fonction anonyme
// ============================================================

/**
 * Retourne une closure qui formate un statut avec un préfixe emoji
 * Exemple d'utilisation des fonctions anonymes
 */
function getFormatteurStatut(): Closure {
    $emojis = [
        'en_attente'   => '<i class="bi bi-hourglass-split"></i>',
        'devis_envoye' => '<i class="bi bi-journal-arrow-up"></i>',
        'acceptee'     => '<i class="bi bi-check-circle-fill"></i>',
        'refusee'      => '<i class="bi bi-x-circle-fill"></i>',
        'livree'       => '<i class="bi bi-truck"></i>',
        'preparation'  => '<i class="bi bi-box-seam"></i>',
        'en_livraison' => '<i class="bi bi-truck"></i>',
        'livre'        => '<i class="bi bi-check-circle-fill"></i>',
        'annule'       => '<i class="bi bi-x-circle-fill"></i>',
    ];

    // Closure : fonction anonyme qui capture $emojis par use
    $formatteur = function(string $statut) use ($emojis): string {
        $emoji = $emojis[$statut] ?? '•';
        $label = getLibelleStatutOrdonnance($statut);
        if ($label === 'Inconnu') {
            $label = getLibelleStatutCommande($statut);
        }
        return $emoji . ' ' . $label;
    };

    return $formatteur;
}