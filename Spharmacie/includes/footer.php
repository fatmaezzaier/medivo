<?php
// includes/footer.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<?php if ($current_page === 'index.php'): ?>
    <!-- FOOTER COMPLET (INDEX) -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-top">
                <!-- Col 1 -->
                <div class="footer-col">
                    <a href="index.php" class="header-logo" style="color: white; margin-bottom: 20px; display: flex;">
                        <div class="logo-icon" style="background: none !important; border-radius: 0; width: auto; height: 100px; margin-bottom: 10px;">
                            <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
                        </div>
                        <span style="color: white; margin-left: 10px;">Medi<span class="green">vo</span></span>
                    </a>
                    <p class="footer-desc">
                        Votre pharmacie en ligne de confiance. Déposez votre ordonnance, recevez votre devis rapidement et faites-vous livrer à domicile.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>

                <!-- Col 2 -->
                <div class="footer-col">
                    <h4 class="footer-col-title">Liens utiles</h4>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="bi bi-chevron-right"></i> Accueil</a></li>
                        <li><a href="dashboard_client.php"><i class="bi bi-chevron-right"></i> Ordonnance</a></li>
                        <li><a href="catalogue.php"><i class="bi bi-chevron-right"></i> Parapharmacie</a></li>
                    </ul>
                </div>

                <!-- Col 3 -->
                <div class="footer-col">
                    <h4 class="footer-col-title">Nos services</h4>
                    <ul class="footer-links">
                        <li><a href="#"><i class="bi bi-upload"></i> Téléchargement d'ordonnance</a></li>
                        <li><a href="#"><i class="bi bi-clock"></i> Devis rapide en moins de 2h</a></li>
                        <li><a href="#"><i class="bi bi-truck"></i> Livraison à domicile</a></li>
                        <li><a href="#"><i class="bi bi-geo-alt"></i> Suivi en temps réel</a></li>
                        <li><a href="#"><i class="bi bi-bag-plus"></i> Produits parapharmacie</a></li>
                    </ul>
                </div>

                <!-- Col 4 -->
                <div class="footer-col">
                    <h4 class="footer-col-title">Contactez-nous</h4>
                    <ul class="contact-info">
                        <li><i class="bi bi-telephone"></i> +216 20 123 456</li>
                        <li><i class="bi bi-envelope"></i> contact@medivo.tn</li>
                        <li><i class="bi bi-geo-alt"></i> Tunis, Tunisia</li>
                    </ul>
                    <div class="newsletter-box">
                        <h4 class="footer-col-title" style="font-size:15px; margin-bottom:15px; padding-bottom:0; border:none;">Newsletter</h4>
                        <div class="newsletter-form">
                            <input type="email" placeholder="Votre email">
                            <button type="button">S'abonner <i class="bi bi-send"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
                <div class="footer-legal">
                    <a href="#">Mentions légales</a>
                    <a href="#">Politique de confidentialité</a>
                    <a href="#">CGU</a>
                </div>
            </div>
        </div>
    </footer>
<?php else: ?>
    <!-- FOOTER SIMPLE (AUTRES PAGES) -->
    <footer class="simple-footer">
        <div class="container">
            <a href="index.php" class="header-logo" style="color: white; margin-bottom: 10px; display: flex; font-size: 18px;">
                <div class="logo-icon" style="width: auto; height: 50px; background: none !important; border-radius: 0;">
                    <img src="uploads/photo/logo.png" alt="Logo Medivo" style="height: 100%; width: auto;">
                </div>
                <span style="color: white; margin-left: 10px;">Medi<span class="green">vo</span></span>
            </a>
            <p>&copy; <?= date('Y') ?> Medivo. Tous droits réservés.</p>
        </div>
    </footer>
<?php endif; ?>

<script src="assets/js/main.js"></script>
