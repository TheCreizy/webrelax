<?php
$page_title = "Nosotros - Relax Tours - Empresa de Viajes y Turismo";
$page_description = "Conocé a Relax Tours. Más de 23 años creando experiencias inolvidables en junto a nuestros viajeros en Villa Devoto.";
$active_page = 'nosotros';
$base_url = './';
include 'includes/header.php';
?>

<!-- NOSOTROS (BENTO GRID) -->
<section id="nosotros" class="section section-nosotros">
    <div class="container">
        <div class="section-header fade-in visible">
            <span class="section-tag">Nuestra Familia</span>
            <h2 class="section-title">Quiénes <span class="text-gradient">Somos</span></h2>
            <p class="section-subtitle"><strong>RELAX TOURS</strong> es una Empresa de Viajes y Turismo genial (Legajo 15.548) con sede en
                Buenos Aires Argentina, emisiva, receptiva y multidestino. Somos una Empresa familiar, cuyos integrantes
                estan al frente de la atención de cada viaje, para que nuestros pasajeros viajen seguros y relajados.
                Desde hace 23 años nos dedicamos con esmero a interpretar las necesidades y los gustos de ellos, los
                cuales hemos ido conociendo cada vez mas a lo largo de los sucesivos viajes.</p>
        </div>

        <div class="nosotros-bento">
            <!-- Bloque 1: Mision (Grande) -->
            <div class="bento-item bento-historia fade-in visible">
                <div class="bento-content">
                    <div class="bento-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <h3>Mision</h3>
                    <p>Brindar experiencias de viaje personalizadas y seguras, transformando los deseos de nuestros
                        clientes en experiencias inolvidables.<br>
                        Nos comprometemos a ofrecer asesoramiento experto y acompañamiento constante,
                        garantizando que cada uno de nuestros pasajeros que viaje con nosotros descubra el mundo con
                        total tranquilidad y respaldo.</p>
                </div>
                <div class="bento-bg-glow"></div>
            </div>

            <!-- Bloque 2: Equipo (Placeholder para la foto de las 4 personas) -->
            <div class="bento-item bento-equipo fade-in visible">
                <div class="equipo-placeholder">
                    <div class="placeholder-overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span>El Equipo de Relax</span>
                        <p>Próximamente: Foto oficial de los 4 pilares de la agencia</p>
                    </div>
                    <div class="animated-gradient-bg"></div>
                </div>
            </div>

            <!-- Bloque 3: Vision -->
            <div class="bento-item bento-mision fade-in visible">
                <div class="bento-content">
                    <h3>Vision</h3>
                    <p>Ser una agencia de viajes reconocida por la innovación en nuestros productos,
                        la excelencia en el servicio al cliente y la capacidad de conectar a los viajeros con los
                        destinos más fascinantes del nuestro país
                        y el mundo, promoviendo un turismo responsable y enriquecedor.</p>
                </div>
            </div>

            <!-- Bloque 4: Stats Rápidas -->
            <div class="bento-item bento-stats-mini fade-in visible">
                <div class="stats-grid-mini">
                    <div class="stat-mini">
                        <span class="stat-num">+23</span>
                        <span class="stat-txt">Años</span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-num">100%</span>
                        <span class="stat-txt">Personalizado</span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-num">+5000</span>
                        <span class="stat-txt">Viajes</span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-num">Mas de 23 años</span>
                        <span class="stat-txt">de experiencia</span>
                    </div>
                </div>
            </div>

            <!-- Bloque 5: Valores -->
            <div class="bento-item bento-valores fade-in visible">
                <div class="bento-content">
                    <h3>Valores</h3>
                    <ul class="valores-list">
                        <li>
                            <strong>Compromiso con el cliente:</strong> Ponemos las necesidades y sueños del viajero en
                            el centro de cada decisión.
                        </li>
                        <li>
                            <strong>Transparencia:</strong> Operamos con honestidad y claridad en cada itinerario y
                            servicio contratado.
                        </li>
                        <li>
                            <strong>Pasión por viajar:</strong> Somos exploradores por naturaleza y compartimos ese
                            entusiasmo en cada recomendación.
                        </li>
                        <li>
                            <strong>Confianza y Respaldo:</strong> Ofrecemos seguridad y soluciones rápidas ante
                            cualquier imprevisto en cualquier rincón del mundo.
                        </li>
                        <li>
                            <strong>Adaptabilidad:</strong> Nos mantenemos a la vanguardia de las tendencias globales y
                            las necesidades cambiantes del mercado.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>