<?php
$page_title = "Paquetes - Relax Tours - Empresa de Viajes y Turismo";
$page_description = "Descubrí todos nuestros paquetes turísticos. Viajes a medida, destinos exóticos y las mejores experiencias con Relax Tours.";
$active_page = 'paquetes';
$base_url = './';
include 'includes/header.php';
?>

<!-- HEADER SMALL -->
<section class="section"
    style="padding-top: 140px; padding-bottom: 40px; background: var(--color-dark); color: white; text-align: center;">
    <div class="container">
        <h1 class="section-title" style="color: white;">Nuestros <span class="text-gradient">Paquetes</span></h1>
        <p class="section-subtitle" style="color: rgba(255,255,255,0.7);">Explorá todos los destinos que tenemos para
            vos.</p>
    </div>
</section>

<!-- PAQUETES GRID -->
<section id="paquetes" class="section section-paquetes" style="background: var(--color-gray-light);">
    <div class="container">
        <div id="paquetes-grid" class="paquetes-grid">
            <!-- Los paquetes se cargarán dinámicamente desde main.js -->
            <div style="grid-column: 1/-1; text-align: center; padding: 40px;">Cargando paquetes...</div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section" style="background: var(--color-white); text-align: center;">
    <div class="container">
        <h2 class="section-title">¿No encontrás lo que buscás?</h2>
        <p class="section-subtitle" style="margin-bottom: 32px;">Armamos viajes a medida. Contanos tu idea y la hacemos
            realidad.</p>
        <a href="#" class="btn-primary"
            onclick="openWhatsApp('Hola Guada! Te escribo por una consulta por un paquete.'); return false;">
            Contactar un Asesor
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>