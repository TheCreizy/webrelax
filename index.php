<?php
$page_title = "Relax Tours - Empresa de Viajes y Turismo";
$page_description = "Viajá por el mundo con Relax Tours. Empresa de viajes y turismo en Villa Devoto con más de 23 años de experiencia.";
$active_page = 'inicio';
$base_url = './';
include 'includes/header.php';
?>

<!-- HERO -->
<section id="inicio" class="hero">
    <div class="hero-bg">
        <img src="images/backround.png" class="hero-bg-image" alt="Nubes de fondo" fetchpriority="high">
        <img src="images/plane.png" class="hero-plane-image" alt="Avión">
        <div class="hero-gradient"></div>
    </div>
    <div class="container hero-content">
        <h1 class="hero-title">VIAJÁ POR EL <span class="text-gradient">MUNDO</span> CON NOSOTROS</h1>
        <p class="hero-subtitle">Somos <strong>Relax Tours</strong>, una empresa de viajes y turismo que lleva más de 23
            años creando experiencias inolvidables.<br>Atención personalizada y el respaldo que te brinda viajar con una
            empresa miembro de la Aviabue (Asociación de viajes de Buenos Aires) y la Faevyt (Federación Argentina de
            Empresas de Viajes y Turismo).</p>
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg></div>
                <div>
                    <div class="stat-value">+5000</div>
                    <div class="stat-label">viajes</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg></div>
                <div>
                    <div class="stat-value">+23</div>
                    <div class="stat-label">años exp.</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 12h20" />
                        <path d="M12 2v20" />
                        <path d="M22 12l-5-5" />
                        <path d="M22 12l-5 5" />
                    </svg></div>
                <div>
                    <div class="stat-value">Leg. 15.548</div>
                    <div class="stat-label">matriculada</div>
                </div>
            </div>
        </div>
        <div class="hero-cta">
            <a href="#paquetes" class="btn-primary hero-btn-reveal">Explorar Paquetes</a>
            <a href="#ofertas" class="btn-secondary hero-btn-reveal">Explorar Ofertas</a>
            <a href="#contacto" class="btn-secondary hero-btn-reveal">Contactanos</a>
        </div>
    </div>
</section>

<!-- PAQUETES -->
<section id="paquetes" class="section section-paquetes">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Paquetes <span class="text-gradient">Destacados</span></h2>

            <div class="carousel-container-wrapper">
                <button class="carousel-nav prev" onclick="moveCarousel(-1)" aria-label="Anterior">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>

                <div id="paquetes-carousel" class="paquetes-carousel">
                    <!-- Los paquetes del carrusel se cargarán dinámicamente desde main.js -->
                </div>

                <button class="carousel-nav next" onclick="moveCarousel(1)" aria-label="Siguiente">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

            <a href="paquetes.php" class="btn-primary" style="margin-top: 40px;">Ver Todos los Paquetes</a>
        </div>
    </div>
</section>

<!-- OFERTAS -->
<section id="ofertas" class="section section-ofertas">
    <div class="container">
        <div class="ofertas-header">
            <div class="ofertas-title-group">
                <div class="section-tag" style="background: rgba(255,255,255,0.2); color: white;">⚡ Ofertas Flash</div>
                <h2 class="section-title" style="color: white; margin-top: 8px;">Oportunidades <span
                        class="text-gradient-white">Únicas</span></h2>
            </div>
            <div class="ofertas-timer">
                <span id="countdown-timer">Cargando...</span>
            </div>
        </div>
        <div id="ofertas-grid" class="ofertas-grid"></div>
        <p class="ofertas-note">* Las ofertas flash están sujetas a disponibilidad y pueden finalizar en cualquier
            momento…</p>
    </div>
</section>

<!-- INSTAGRAM -->
<section class="section section-instagram">
    <div class="container">
        <div class="instagram-header">
            <div class="instagram-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                </svg>
            </div>
            <h2 class="section-title">Seguinos en <span class="text-gradient">Instagram</span></h2>
            <p class="section-subtitle">Acompañanos en cada viaje y enterate primero de todas las promociones.</p>
            <a href="https://www.instagram.com/relax.tours/" target="_blank" class="instagram-handle">@relax.tours</a>
        </div>
        <div class="instagram-widget-wrapper">
            <!-- EmbedSocial WIDGET -->
            <div class="embedsocial-hashtag" data-ref="6c0fbe11111c81e6cfc02f8fcbb5f8ca87152b22" data-lazyload="yes">
            </div>
            <script>
                (function (d, s, id) {
                    var js;
                    if (d.getElementById(id)) {
                        return;
                    }
                    js = d.createElement(s);
                    js.id = id;
                    js.src = "https://embedsocial.com/cdn/ht.js";
                    d.getElementsByTagName("head")[0].appendChild(js);
                }(document, "script", "EmbedSocialHashtagScript"));
            </script>
        </div>
    </div>
</section>

<!-- BLOG -->
<section id="blog" class="section section-blog">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Blog</span>
            <h2 class="section-title">Tips de Viaje</h2>
            <p class="section-subtitle">Inspiración y consejos para tu próxima aventura. Descubrí todo lo que necesitás
                saber antes de viajar.</p>
        </div>
        <div id="blog-grid" class="blog-grid"></div>
        <div style="text-align: center; margin-top: 48px;">
            <a href="blog/" class="btn-outline" style="border-color: var(--color-gray); color: var(--color-dark);">
                Ver Todas las Entradas
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- NEWSLETTER (Mailchimp) -->
<section class="section section-newsletter">
    <div class="container">
        <div class="newsletter-card">
            <div class="newsletter-icon-lightning">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div class="newsletter-content">
                <h2 class="newsletter-title">¿Querés ofertas exclusivas?</h2>
                <p class="newsletter-subtitle">Recibí las mejores promociones antes que nadie.</p>

                <div id="mc_embed_signup">
                    <form
                        action="https://relaxtours.us7.list-manage.com/subscribe/post?u=f60d9aef62683fb9889369762&amp;id=9a47c09cfa&amp;f_id=00fc47e4f0"
                        method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate"
                        target="_blank">
                        <div id="mc_embed_signup_scroll">
                            <div class="mc-field-group">
                                <label for="mce-EMAIL">Email Address <span class="asterisk">*</span></label>
                                <input type="email" name="EMAIL" class="required email" id="mce-EMAIL" required=""
                                    value="">
                            </div>
                            <div id="mce-responses" class="clear foot">
                                <div class="response" id="mce-error-response" style="display: none;"></div>
                                <div class="response" id="mce-success-response" style="display: none;"></div>
                            </div>
                            <div style="position: absolute; left: -5000px;" aria-hidden="true">
                                <input type="text" name="b_f60d9aef62683fb9889369762_9a47c09cfa" tabindex="-1" value="">
                            </div>
                            <div class="optionalParent">
                                <div class="clear foot">
                                    <input type="submit" name="subscribe" id="mc-embedded-subscribe" class="button"
                                        value="Suscribirme">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <script type="text/javascript"
                    src="//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js"></script>
                <script type="text/javascript">
                    (function ($) {
                        window.fnames = new Array();
                        window.ftypes = new Array();
                        fnames[0] = 'EMAIL'; ftypes[0] = 'email';
                        fnames[1] = 'FNAME'; ftypes[1] = 'text';
                        fnames[2] = 'LNAME'; ftypes[2] = 'text';
                        fnames[3] = 'ADDRESS'; ftypes[3] = 'address';
                        fnames[4] = 'PHONE'; ftypes[4] = 'phone';
                        fnames[5] = 'MMERGE5'; ftypes[5] = 'birthday';

                        $.extend($.validator.messages, {
                            required: "Este campo es obligatorio.",
                            remote: "Por favor, rellena este campo.",
                            email: "Por favor, escribe una dirección de correo válida.",
                            url: "Por favor, escribe una URL válida.",
                            date: "Por favor, escribe una fecha válida.",
                            dateISO: "Por favor, escribe una fecha (ISO) válida.",
                            number: "Por favor, escribe un número válido.",
                            digits: "Por favor, escribe sólo dígitos.",
                            creditcard: "Por favor, escribe un número de tarjeta válido.",
                            equalTo: "Por favor, escribe el mismo valor de nuevo.",
                            extension: "Por favor, escribe un valor con una extensión aceptada.",
                            maxlength: $.validator.format("Por favor, no escribas más de {0} caracteres."),
                            minlength: $.validator.format("Por favor, no escribas menos de {0} caracteres."),
                            rangelength: $.validator.format("Por favor, escribe entre {0} y {1} caracteres."),
                            range: $.validator.format("Por favor, escribe un valor entre {0} y {1}."),
                            max: $.validator.format("Por favor, escribe un valor menor o igual a {0}."),
                            min: $.validator.format("Por favor, escribe un valor mayor o igual a {0}.")
                        });
                    }(jQuery));
                    var $mcj = jQuery.noConflict(true);
                </script>
            </div>

            <p class="newsletter-privacy">Podés darte de baja en cualquier momento. No compartimos tu información.</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>