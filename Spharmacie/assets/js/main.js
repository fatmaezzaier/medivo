// assets/js/main.js
// JavaScript — Medivo
// Notions couvertes : DOM, événements, getElementById, querySelector,
// innerHTML, classList, onChange, onClick, onSubmit, confirm, alert,
// addEventListener, manipulation de styles CSS via JS

// ============================================================
// 1. VALIDATION FORMULAIRE UPLOAD D'ORDONNANCE
// Notion : onSubmit, getElementById, alert, DOM
// ============================================================

/**
 * Valide le formulaire d'upload avant envoi au serveur
 * Appelé par onsubmit="return validerFormulaire()"
 */
function validerFormulaireUpload() {
    var fichier = document.getElementById('fichier_ordonnance');
    var adresse = document.getElementById('adresse_livraison');
    var erreurDiv = document.getElementById('erreur_upload');

    // Réinitialiser l'erreur
    erreurDiv.innerHTML = '';
    erreurDiv.style.display = 'none';

    // Vérifier que le fichier est sélectionné
    if (!fichier.files || fichier.files.length === 0) {
        afficherErreur(erreurDiv, 'Veuillez sélectionner un fichier ordonnance.');
        fichier.focus();
        return false;
    }

    var file = fichier.files[0];

    // Vérifier le type de fichier
    var typesAutorises = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    if (typesAutorises.indexOf(file.type) === -1) {
        afficherErreur(erreurDiv, 'Type non autorisé. Utilisez JPEG, PNG ou PDF.');
        return false;
    }

    // Vérifier la taille (5 Mo max)
    var tailleMax = 5 * 1024 * 1024;
    if (file.size > tailleMax) {
        afficherErreur(erreurDiv, 'Fichier trop lourd. Maximum : 5 Mo. Taille actuelle : ' + formatTaille(file.size));
        return false;
    }

    // Vérifier l'adresse de livraison
    if (adresse.value.trim().length < 5) {
        afficherErreur(erreurDiv, 'Veuillez renseigner une adresse de livraison valide.');
        adresse.focus();
        return false;
    }

    return true; // formulaire valide → envoi PHP
}

// ============================================================
// 2. PRÉVISUALISATION DU FICHIER UPLOADÉ
// Notion : onChange, getElementById, innerHTML, DOM
// ============================================================

/**
 * Affiche un aperçu du fichier sélectionné avant upload
 * Appelé par onchange="previsualiserFichier(this)"
 */
function previsualiserFichier(input) {
    var preview = document.getElementById('preview_fichier');
    var infoFich = document.getElementById('info_fichier');

    if (!preview || !input.files || input.files.length === 0) return;

    var file = input.files[0];

    var actionsHTML = '<span style="margin-left: 15px; display: inline-block;">' +
        '<button type="button" class="btn btn-primary btn-sm" style="padding: 2px 6px; font-size: 11px; margin-right: 5px; height: auto;" onclick="document.getElementById(\'fichier_ordonnance\').click();">Modifier</button>' +
        '<button type="button" class="btn btn-danger-ol btn-sm" style="padding: 2px 6px; font-size: 11px; height: auto;" onclick="supprimerApercuFichier()">Supprimer</button>' +
        '</span>';

    // Mettre à jour les infos avec innerHTML en intégrant les boutons
    infoFich.innerHTML = '<strong>' + file.name + '</strong> — ' + formatTaille(file.size) + actionsHTML;

    // Si c'est une image, afficher la prévisualisation
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="max-width:200px; max-height:150px; border-radius:8px; margin-top:8px;">';
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        preview.innerHTML = '<div style="padding:12px; background:#f0f0f0; border-radius:8px; margin-top:8px; font-size:13px;"> Fichier PDF sélectionné</div>';
    }

    preview.style.display = 'block';
}

/**
 * Supprime le fichier sélectionné et masque la prévisualisation
 */
function supprimerApercuFichier() {
    var input = document.getElementById('fichier_ordonnance');
    var preview = document.getElementById('preview_fichier');
    var info = document.getElementById('info_fichier');

    if (input) input.value = '';
    if (preview) {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
    if (info) info.innerHTML = '';
}

// ============================================================
// 3. CONFIRMATION AVANT ACCEPTER / REFUSER UN DEVIS
// Notion : confirm(), événements onClick, DOM
// ============================================================

/**
 * Demande une confirmation avant d'accepter un devis
 * Appelé par onclick="return confirmerAcceptation(montant)"
 */
function confirmerAcceptation(montant) {
    return confirm(
        'Confirmer l\'acceptation du devis ?\n\n' +
        'Montant : ' + montant + '\n\n' +
        'Une commande sera créée automatiquement.'
    );
}

/**
 * Demande une confirmation avant de refuser un devis
 */
function confirmerRefus() {
    return confirm('Êtes-vous sûr de vouloir refuser ce devis ?\nCette action est irréversible.');
}

/**
 * Demande une confirmation avant de supprimer une ordonnance
 * Notion : confirm()
 */
function confirmerSuppression() {
    return confirm('Supprimer cette ordonnance ?\nCette action est définitive.');
}

// ============================================================
// 4. COMPTEUR DE CARACTÈRES SUR LES TEXTAREAS
// Notion : onChange / onKeyUp, getElementById, innerHTML
// ============================================================

/**
 * Met à jour un compteur de caractères en temps réel
 * Appelé par oninput="compterCaracteres(this, 'compteur_id', max)"
 */
function compterCaracteres(textarea, compteurId, max) {
    var compteur = document.getElementById(compteurId);
    if (!compteur) return;

    var restants = max - textarea.value.length;
    compteur.innerHTML = restants + ' caractère(s) restant(s)';

    // Changer la couleur selon le nombre restant — modification CSS via JS
    if (restants < 20) {
        compteur.style.color = '#dc3545';    // rouge
    } else if (restants < 50) {
        compteur.style.color = '#f59e0b';    // orange
    } else {
        compteur.style.color = '#6b7280';    // gris normal
    }
}

// ============================================================
// 5. MISE EN ÉVIDENCE D'UNE LIGNE DE TABLEAU AU SURVOL
// Notion : addEventListener, classList, querySelectorAll, DOM
// ============================================================

/**
 * Ajoute des événements de survol sur toutes les lignes de tableau
 * Appelé au chargement de la page
 */
function activerSurlignageLignes() {
    var lignes = document.querySelectorAll('tbody tr');
    lignes.forEach(function (ligne) {
        ligne.addEventListener('mouseenter', function () {
            this.classList.add('ligne-active');
        });
        ligne.addEventListener('mouseleave', function () {
            this.classList.remove('ligne-active');
        });
    });
}

// ============================================================
// 6. FILTRAGE DYNAMIQUE DU TABLEAU DES ORDONNANCES
// Notion : onchange, querySelector, getElementById, DOM, boucle
// ============================================================

/**
 * Filtre les lignes du tableau selon le statut sélectionné
 * Appelé par onchange="filtrerTableau(this.value)"
 */
function filtrerTableau(statut) {
    var lignes = document.querySelectorAll('tbody tr[data-statut]');
    var compteur = 0;

    lignes.forEach(function (ligne) {
        if (statut === 'tous' || ligne.getAttribute('data-statut') === statut) {
            ligne.style.display = '';
            compteur++;
        } else {
            ligne.style.display = 'none';
        }
    });

    // Mettre à jour le compteur affiché via innerHTML
    var spanCompteur = document.getElementById('nb_resultats');
    if (spanCompteur) {
        spanCompteur.innerHTML = compteur + ' résultat(s)';
    }
}

// ============================================================
// 7. CONFIRMATION CHANGEMENT STATUT PHARMACIEN
// Notion : onchange, confirm, querySelector
// ============================================================

/**
 * Demande confirmation quand le pharmacien change un statut critique
 * Appelé par onchange="confirmerChangementStatut(this)"
 */
function confirmerChangementStatut(select) {
    var valeur = select.value;
    var messages = {
        'livre': 'Marquer cette commande comme LIVRÉE ?\nCette action est définitive.',
        'annule': 'ANNULER cette commande ?\nLe client sera informé.'
    };
    if (messages[valeur]) {
        if (!confirm(messages[valeur])) {
            // Revenir à la valeur précédente
            select.value = select.getAttribute('data-precedent');
            return false;
        }
    }
    // Sauvegarder la nouvelle valeur comme "précédente"
    select.setAttribute('data-precedent', valeur);
    return true;
}

// ============================================================
// 8. AFFICHAGE / MASQUAGE DU FORMULAIRE DEVIS
// Notion : getElementById, style.display, classList, DOM
// ============================================================

/**
 * Affiche ou masque le formulaire d'envoi de devis
 * Appelé par onclick="toggleFormulaireDevis(id)"
 */
function toggleFormulaireDevis(ordonnanceId) {
    var formId = 'form_devis_' + ordonnanceId;
    var form = document.getElementById(formId);
    var bouton = document.getElementById('btn_devis_' + ordonnanceId);

    if (!form) return;

    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        if (bouton) bouton.innerHTML = '✕ Fermer';
        // Focus sur le premier champ
        var premierChamp = form.querySelector('input[type="number"]');
        if (premierChamp) premierChamp.focus();
    } else {
        form.style.display = 'none';
        if (bouton) bouton.innerHTML = ' Envoyer un devis';
    }
}

// ============================================================
// 9. VALIDATION FORMULAIRE CONNEXION / INSCRIPTION
// Notion : getElementById, innerHTML, onsubmit, events
// ============================================================

/**
 * Valide le formulaire de connexion côté client
 */
function validerConnexion() {
    var email = document.getElementById('email');
    var mdp = document.getElementById('mot_de_passe');
    var err = document.getElementById('erreur_connexion');

    if (!email || !mdp) return true;

    if (email.value.trim() === '') {
        if (err) err.innerHTML = 'Veuillez entrer votre adresse email.';
        email.focus();
        return false;
    }
    if (mdp.value.trim() === '') {
        if (err) err.innerHTML = 'Veuillez entrer votre mot de passe.';
        mdp.focus();
        return false;
    }
    return true;
}

/**
 * Valide le formulaire d'inscription côté client
 */
function validerInscription() {
    var nom = document.getElementById('nom');
    var email = document.getElementById('email');
    var mdp = document.getElementById('mot_de_passe');
    var err = document.getElementById('erreur_inscription');

    if (!nom || !email || !mdp) return true;

    var erreurs = [];

    if (nom.value.trim().length < 2)
        erreurs.push('Le nom doit contenir au moins 2 caractères.');

    if (!email.value.includes('@'))
        erreurs.push('L\'adresse email n\'est pas valide.');

    if (mdp.value.length < 6)
        erreurs.push('Le mot de passe doit contenir au moins 6 caractères.');

    if (erreurs.length > 0) {
        if (err) {
            // innerHTML : construction de liste d'erreurs
            err.innerHTML = erreurs.map(function (e) {
                return '• ' + e;
            }).join('<br>');
            err.style.display = 'block';
        }
        return false;
    }
    return true;
}

// ============================================================
// 10. FONCTIONS UTILITAIRES
// ============================================================

/**
 * Affiche un message d'erreur dans un div
 * Notion : getElementById, innerHTML, style
 */
function afficherErreur(divErreur, message) {
    divErreur.innerHTML = '⚠️ ' + message;
    divErreur.style.display = 'block';
    // Scroll vers l'erreur
    divErreur.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Formate une taille de fichier en Ko/Mo
 * Notion : fonction, conditions
 */
function formatTaille(octets) {
    if (octets < 1024) return octets + ' o';
    if (octets < 1024 * 1024) return Math.round(octets / 1024) + ' Ko';
    return (octets / (1024 * 1024)).toFixed(1) + ' Mo';
}

// ============================================================
// 11. INITIALISATION AU CHARGEMENT DE LA PAGE
// Notion : addEventListener sur document, DOMContentLoaded
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // Activer le surlignage des lignes de tableau
    activerSurlignageLignes();

    // Sauvegarder la valeur initiale de chaque select statut
    var selects = document.querySelectorAll('select[name="nouveau_statut"]');
    selects.forEach(function (sel) {
        sel.setAttribute('data-precedent', sel.value);
    });

    // Ajouter confirmation sur les boutons de suppression
    var btnsSuppression = document.querySelectorAll('.btn-supprimer');
    btnsSuppression.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirmerSuppression()) {
                e.preventDefault();
            }
        });
    });

    // Fermer automatiquement les alertes après 5 secondes
    var alertes = document.querySelectorAll('.alert-auto-close');
    alertes.forEach(function (alerte) {
        setTimeout(function () {
            alerte.style.opacity = '0';
            alerte.style.transition = 'opacity 0.5s';
            setTimeout(function () {
                alerte.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // Mise à jour du compteur de panier au chargement
    mettreAJourCompteurPanier();

    // Initialisation des animations au scroll et parallax
    if (typeof initScrollAnimations === 'function') initScrollAnimations();
    if (typeof initHeroParallax === 'function') initHeroParallax();

});

// ============================================================
// 12. GESTION DU CATALOGUE ET DU PANIER (localStorage)
// ============================================================

/**
 * Filtrage des produits du catalogue
 */
function filtrerCatalogue(categorie, btnElement) {
    var produits = document.querySelectorAll('.product-card');
    var boutons = btnElement.parentElement.querySelectorAll('.btn');

    // Mise à jour de l'état actif des boutons
    boutons.forEach(function (btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline');
    });
    btnElement.classList.remove('btn-outline');
    btnElement.classList.add('btn-primary');

    // Filtrage
    produits.forEach(function (produit) {
        if (categorie === 'tous' || produit.getAttribute('data-categorie') === categorie) {
            produit.style.display = 'flex';
        } else {
            produit.style.display = 'none';
        }
    });
}

/**
 * Ajout d'un produit au panier
 */
function ajouterAuPanier(id, nom, prix, stock) {
    var panier = JSON.parse(localStorage.getItem('medivo_panier')) || [];
    var produitExistant = panier.find(p => p.id === id);

    if (produitExistant) {
        if (produitExistant.quantite < stock) {
            produitExistant.quantite++;
            alert('Quantité augmentée pour : ' + nom);
        } else {
            alert('Stock maximum atteint pour ce produit.');
        }
    } else {
        if (stock > 0) {
            panier.push({
                id: id,
                nom: nom,
                prix: parseFloat(prix),
                quantite: 1,
                stock: stock
            });
            alert('Produit ajouté au panier : ' + nom);
        } else {
            alert('Ce produit est en rupture de stock.');
        }
    }

    localStorage.setItem('medivo_panier', JSON.stringify(panier));
    mettreAJourCompteurPanier();
}

/**
 * Mise à jour du compteur sur le bouton flottant du panier
 */
function mettreAJourCompteurPanier() {
    var panier = JSON.parse(localStorage.getItem('medivo_panier')) || [];
    var totalQuantite = panier.reduce((total, p) => total + p.quantite, 0);

    var compteurs = document.querySelectorAll('.cart-count, .cart-count-nav');
    compteurs.forEach(function (compteur) {
        compteur.innerHTML = totalQuantite;
    });
}

/**
 * Charge le panier sur la page panier.php
 */
function chargerPanier() {
    var panier = JSON.parse(localStorage.getItem('medivo_panier')) || [];
    var tbody = document.getElementById('panier-tbody');
    var totalEl = document.getElementById('panier-total');
    var emptyState = document.getElementById('panier-vide');
    var tableWrapper = document.getElementById('panier-table-wrapper');
    var formPanier = document.getElementById('panier-data-form');

    if (!tbody || !totalEl) return;

    if (panier.length === 0) {
        emptyState.style.display = 'block';
        tableWrapper.style.display = 'none';
        totalEl.innerHTML = '0.000 DT';
        if (formPanier) formPanier.innerHTML = ''; // vider form
        return;
    }

    emptyState.style.display = 'none';
    tableWrapper.style.display = 'block';

    var html = '';
    var total = 0;
    var formInputs = '';

    panier.forEach(function (p, index) {
        var sousTotal = p.prix * p.quantite;
        total += sousTotal;

        html += `<tr>
            <td data-label="Produit" class="cart-item-name">${p.nom}</td>
            <td data-label="Prix Unitaire">${p.prix.toFixed(3)} DT</td>
            <td data-label="Quantité">
                <input type="number" class="qty-input" value="${p.quantite}" min="1" max="${p.stock}" onchange="modifierQuantite(${p.id}, this.value)">
            </td>
            <td data-label="Total">${sousTotal.toFixed(3)} DT</td>
            <td data-label="Actions" style="text-align: right;">
                <button type="button" class="btn btn-danger-ol btn-sm" onclick="retirerDuPanier(${p.id})">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </td>
        </tr>`;

        // Inputs cachés pour l'envoi du formulaire via PHP
        formInputs += `<input type="hidden" name="produits[${index}][id]" value="${p.id}">`;
        formInputs += `<input type="hidden" name="produits[${index}][quantite]" value="${p.quantite}">`;
        formInputs += `<input type="hidden" name="produits[${index}][prix_unitaire]" value="${p.prix}">`;
    });

    formInputs += `<input type="hidden" name="total_commande" value="${total.toFixed(3)}">`;

    tbody.innerHTML = html;
    totalEl.innerHTML = total.toFixed(3) + ' DT';
    if (formPanier) {
        formPanier.innerHTML = formInputs;
    }
}

/**
 * Modifie la quantité d'un produit dans le panier
 */
function modifierQuantite(id, nouvelleQte) {
    var panier = JSON.parse(localStorage.getItem('medivo_panier')) || [];
    var produit = panier.find(p => p.id === id);

    if (produit) {
        nouvelleQte = parseInt(nouvelleQte);
        if (nouvelleQte > 0 && nouvelleQte <= produit.stock) {
            produit.quantite = nouvelleQte;
        } else if (nouvelleQte > produit.stock) {
            alert('Stock maximum disponible : ' + produit.stock);
            produit.quantite = produit.stock;
        }
        localStorage.setItem('medivo_panier', JSON.stringify(panier));
        chargerPanier();
        mettreAJourCompteurPanier();
    }
}

/**
 * Retire un produit du panier
 */
function retirerDuPanier(id) {
    if (confirm('Voulez-vous retirer ce produit du panier ?')) {
        var panier = JSON.parse(localStorage.getItem('medivo_panier')) || [];
        panier = panier.filter(p => p.id !== id);
        localStorage.setItem('medivo_panier', JSON.stringify(panier));
        chargerPanier();
        mettreAJourCompteurPanier();
    }
}

/**
 * Vider le panier après commande (appelée après submit via PHP redir)
 */
// ============================================================
// 13. ANIMATIONS AU SCROLL (Intersection Observer)
// Notion : IntersectionObserver, DOM manipulation, animations
// ============================================================

/**
 * Initialise l'observateur pour les animations au scroll
 */
function initScrollAnimations() {
    const options = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-reveal');
                // Optionnel : arrêter d'observer une fois animé
                // observer.unobserve(entry.target);
            }
        });
    }, options);

    // Cibler tous les éléments avec des classes anim-*
    const elements = document.querySelectorAll('[class*="anim-"]');
    elements.forEach(el => {
        observer.observe(el);
    });
}

/**
 * Effet parallaxe léger sur les blobs du hero
 */
function initHeroParallax() {
    const hero = document.querySelector('.hero');
    const blobs = document.querySelectorAll('.hero-blob');

    if (!hero || blobs.length === 0) return;

    hero.addEventListener('mousemove', (e) => {
        const { clientX, clientY } = e;
        const xPos = (clientX / window.innerWidth) - 0.5;
        const yPos = (clientY / window.innerHeight) - 0.5;

        blobs.forEach((blob, index) => {
            const factor = (index + 1) * 20;
            blob.style.transform = `translate(${xPos * factor}px, ${yPos * factor}px)`;
        });
    });
}

/**
 * Vide entièrement le panier dans le localStorage
 */
function viderPanierLocal() {
    localStorage.removeItem('medivo_panier');
    mettreAJourCompteurPanier();
}

// ================================================================
// HAMBURGER MENU
// ================================================================
document.addEventListener('DOMContentLoaded', function () {

    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var navbarRight  = document.getElementById('navbarRight');
    var navOverlay   = document.getElementById('navOverlay');

    if (!hamburgerBtn || !navbarRight) return;

    // Toggle menu open/close
    hamburgerBtn.addEventListener('click', function () {
        var isOpen = navbarRight.classList.contains('open');

        if (isOpen) {
            // Close
            hamburgerBtn.classList.remove('open');
            navbarRight.classList.remove('open');
            navOverlay.classList.remove('active');
            document.body.style.overflow = '';
        } else {
            // Open
            hamburgerBtn.classList.add('open');
            navbarRight.classList.add('open');
            navOverlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // prevent background scroll
        }
    });

    // Close when clicking overlay
    navOverlay.addEventListener('click', function () {
        hamburgerBtn.classList.remove('open');
        navbarRight.classList.remove('open');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
    });

    // Close when clicking a nav link
    navbarRight.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            hamburgerBtn.classList.remove('open');
            navbarRight.classList.remove('open');
            navOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    });

});
