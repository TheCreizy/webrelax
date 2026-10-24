<?php
/**
 * Footer Template
 * Variables:
 * $base_url - Base path for assets
 */
$base_url = isset($base_url) ? $base_url : './';
?>
<!-- FOOTER -->
<footer id="contacto" class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Col 1: Brand -->
            <div class="footer-brand">
                <img src="<?php echo $base_url; ?>images/logo-relax.webp" alt="Relax Tours" class="footer-logo">
                <p>Empresa de viajes y Turismo. Más de 23 años de experiencia creando viajes inolvidables.</p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/relax.tours/" target="_blank" rel="noopener noreferrer"
                        aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                        </svg>
                    </a>
                    <a href="https://www.facebook.com/Relaxtours.ar/" target="_blank" rel="noopener noreferrer"
                        aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                        </svg>
                    </a>
                </div>

                <!-- CAROUSEL AFILIACIONES -->
                <div class="footer-affiliations">
                    <div class="affiliations-track">
                        <!-- SET 1 -->
                        <!-- 1. ARCA -->
                        <div class="affiliation-item">
                            
                                <img src="http://www.afip.gob.ar/images/f960/DATAWEB.jpg" border="0"
                                    alt="ARCA - Data Fiscal">
                            
                        </div>
                        <!-- 2. AVIABUE -->
                        <div class="affiliation-item">
                            <a href="https://www.aviabue.org.ar/" target="_blank">
                                <img src="<?php echo $base_url; ?>images/aviabue.webp" alt="AVIABUE">
                            </a>
                        </div>
                        <!-- 3. FAEVYT -->
                        <div class="affiliation-item">
                            <a href="https://www.faevyt.org.ar/" target="_blank">
                                <img src="<?php echo $base_url; ?>images/faevyt.webp" alt="FAEVYT">
                            </a>
                        </div>
                        <!-- 4. PDP -->
                        <div class="affiliation-item">
                            <a href="https://www.argentina.gob.ar/aaip/datospersonales" target="_blank">
                                <img src="<?php echo $base_url; ?>images/pdp.webp" alt="Protección de Datos Personales">
                            </a>
                        </div>
                        <!-- 5. QR Agencias -->
                        <div class="affiliation-item">
                            <a href="https://agenciasdeviajes.ar/agencias/jek4QnYC" target="_blank">
                                <img src="<?php echo $base_url; ?>images/qr.webp" alt="Registro Nacional de Agencias">
                            </a>
                        </div>

                        <!-- SET 2 (Duplicate for infinite scroll) -->
                        <div class="affiliation-item">
                            
                                <img src="http://www.afip.gob.ar/images/f960/DATAWEB.jpg" border="0"
                                    alt="ARCA - Data Fiscal">
                            
                        </div>
                        <div class="affiliation-item">
                            <a href="https://www.aviabue.org.ar/" target="_blank">
                                <img src="<?php echo $base_url; ?>images/aviabue.webp" alt="AVIABUE">
                            </a>
                        </div>
                        <div class="affiliation-item">
                            <a href="https://www.faevyt.org.ar/" target="_blank">
                                <img src="<?php echo $base_url; ?>images/faevyt.webp" alt="FAEVYT">
                            </a>
                        </div>
                        <div class="affiliation-item">
                            <a href="https://www.argentina.gob.ar/aaip/datospersonales" target="_blank">
                                <img src="<?php echo $base_url; ?>images/pdp.webp" alt="Protección de Datos Personales">
                            </a>
                        </div>
                        <div class="affiliation-item">
                            <a href="https://agenciasdeviajes.ar/agencias/jek4QnYC" target="_blank">
                                <img src="<?php echo $base_url; ?>images/qr.webp" alt="Registro Nacional de Agencias">
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Col 2: Info & Links -->
            <div class="footer-links-col">
                <h4 class="footer-title">Acceso Rápido</h4>
                <ul class="footer-links" style="margin-bottom: 0;">
                    <li><a href="<?php echo $base_url; ?>#inicio">Inicio</a></li>
                    <li><a href="<?php echo $base_url; ?>nosotros">Nosotros</a></li>
                    <li><a href="<?php echo $base_url; ?>#paquetes">Paquetes</a></li>
                    <li><a href="<?php echo $base_url; ?>#asistencia">Asistencia</a></li>
                    <li><a href="<?php echo $base_url; ?>#ofertas">Ofertas Flash</a></li>
                    <li><a href="<?php echo $base_url; ?>#blog">Tips</a></li>
                    <li><a href="<?php echo $base_url; ?>#contacto">Contacto</a></li>
                </ul>
            </div>

            <!-- Col 3: Contact Form -->
            <div class="footer-contact-form-container">
                <h4 class="footer-title">Contactanos</h4>
                <form id="contact-form" class="footer-form">
                    <input type="hidden" name="form_type" value="contacto">
                    <div class="form-group-footer">
                        <label for="contact-name" class="sr-only">Nombre Completo</label>
                        <input type="text" id="contact-name" name="name" placeholder="Nombre Completo *" required
                            autocomplete="name">
                    </div>
                    <div class="form-group-footer">
                        <label for="contact-email" class="sr-only">Correo electrónico</label>
                        <input type="email" id="contact-email" name="email" placeholder="Correo electrónico *" required
                            autocomplete="email">
                    </div>
                    <div class="form-group-footer">
                        <label for="contact-phone" class="sr-only">Número de teléfono</label>
                        <input type="tel" id="contact-phone" name="phone" placeholder="Número de teléfono (opcional)"
                            autocomplete="tel">
                    </div>
                    <div class="form-group-footer">
                        <label for="contact-message" class="sr-only">Consulta</label>
                        <textarea id="contact-message" name="message"
                            placeholder="Dejanos tu consulta con la mayor descripción posible. Nos contactaremos a la brevedad! *"
                            required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Enviar Consulta</button>
                    <div id="contact-form-status" class="form-status"></div>
                </form>
            </div>

            <!-- Col 4: Legales -->
            <div class="footer-info-cols">
                <div>
                    <h4 class="footer-title">Legales</h4>
                    <ul class="footer-links-special">
                        <li><a href="<?php echo $base_url; ?>files/Local-virtual-certificado.pdf"
                                target="_blank">Certificado de Habilitación</a></li>
                        <li><a href="<?php echo $base_url; ?>cookies">Política de Cookies</a></li>
                        <li><a href="#" onclick="openArrepentimientoModal(); return false;">Botón de Arrepentimiento</a>
                        </li>
                        <li><a href="https://tramitesadistancia.gob.ar/tramitesadistancia/detalle-tipo?id=624"
                                target="_blank">Denuncia contra una agencia</a></li>
                    </ul>
                </div>
            </div>

            <!-- Col 5: Ubicación & Contacto -->
            <div class="footer-location-col">
                <h4 class="footer-title">Ubicación</h4>
                <div class="footer-map-container">
                    <a href="https://www.google.com/maps/search/Relax+Tours" target="_blank" rel="noopener noreferrer"
                        class="map-link-overlay" aria-label="Abrir ubicación en Google Maps">
                        <span class="map-label">Ver en Google Maps</span>
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3283.963488577944!2d-58.513440423379656!3d-34.605084757571916!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bcb7d263dd8bef%3A0xfd9783e961f4dff8!2sRelax%20Tours!5e0!3m2!1sen!2sar!4v1771102202081!5m2!1sen!2sar"
                            width="100%" height="160" style="border:0;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade" class="footer-map"
                            title="Ubicación de Relax Tours en Google Maps">
                        </iframe>
                    </a>
                </div>

                <h4 class="footer-title" style="margin-top: 8px;">Contacto</h4>
                <div class="footer-contact-item">
                    <div class="footer-contact-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg></div>
                    <div class="footer-contact-value" data-config="direccion">...</div>
                </div>
                <div class="footer-contact-item">
                    <div class="footer-contact-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                        </svg></div>
                    <div class="footer-contact-value" data-config="telefono">...</div>
                </div>
                <div class="footer-contact-item">
                    <div class="footer-contact-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg></div>
                    <div class="footer-contact-value" data-config="email">...</div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="footer-copyright">© <span id="current-year"></span> Relax Tours. Todos los derechos reservados.
            </p>
            <div class="footer-bottom-links">
                <span class="footer-legajo">Leg. 15.548</span>
                <a href="<?php echo $base_url; ?>condicionesgenerales" target="_blank">Términos y
                    Condiciones</a>
            </div>
        </div>
    </div>
</footer>

<!-- MODAL ARREPENTIMIENTO -->
<div id="arrepentimiento-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="arrepentimiento-modal-title">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="arrepentimiento-modal-title">Botón de Arrepentimiento</h3>
            <button onclick="closeArrepentimientoModal()" class="modal-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 20px; font-size: 14px; color: var(--color-gray);">Complete todos los campos para
                solicitar el arrepentimiento de su compra.</p>
            <form id="arrepentimiento-form">
                <input type="hidden" name="form_type" value="arrepentimiento">
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label for="regret-name">Nombre Completo *</label>
                        <input type="text" id="regret-name" name="name" required autocomplete="name">
                    </div>
                    <div class="form-group-modal">
                        <label for="regret-email">Email *</label>
                        <input type="email" id="regret-email" name="email" required autocomplete="email">
                    </div>
                </div>
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label for="regret-purchase-date">Fecha de compra *</label>
                        <input type="date" id="regret-purchase-date" name="purchase_date" required>
                    </div>
                    <div class="form-group-modal">
                        <label for="regret-travel-date">Fecha del Viaje *</label>
                        <input type="date" id="regret-travel-date" name="travel_date" required>
                    </div>
                </div>
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label for="regret-reservation">Número de Reserva *</label>
                        <input type="text" id="regret-reservation" name="reservation_number" required>
                    </div>
                    <div class="form-group-modal">
                        <label for="regret-service">Servicios contratados *</label>
                        <select id="regret-service" name="service" required>
                            <option value="">Seleccione una opción</option>
                            <option value="Aéreos">Aéreos</option>
                            <option value="Hoteles">Hoteles</option>
                            <option value="Paquetes">Paquetes</option>
                            <option value="Seguros de Viaje">Seguros de Viaje</option>
                            <option value="Alquileres de Auto">Alquileres de Auto</option>
                            <option value="Excursiones">Excursiones</option>
                            <option value="Circuitos">Circuitos</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-modal">
                    <label for="regret-reason">Motivo del arrepentimiento *</label>
                    <textarea id="regret-reason" name="reason" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Enviar Solicitud</button>
                <div id="arrepentimiento-form-status" class="form-status"></div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL BLOG -->
<div id="blog-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="blog-modal-title">
    <div class="modal-content blog-modal-content">
        <div class="modal-header">
            <h3 id="blog-modal-title">Tips de Viaje</h3>
            <button onclick="closeBlogModal()" class="modal-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <div id="blog-modal-image-container" class="blog-modal-image">
                <img id="blog-modal-image" src="" alt="">
            </div>
            <div id="blog-modal-meta" class="blog-modal-meta"></div>
            <div id="blog-modal-body" class="blog-modal-body-content">
                <!-- Content loaded via JS -->
            </div>
            <div class="blog-modal-footer">
                <button class="btn-primary" onclick="closeBlogModal(); scrollToContact();">Hacer una consulta</button>
            </div>
        </div>
    </div>
</div>

<!-- WHATSAPP -->
<div class="whatsapp-float">
    <div class="whatsapp-tooltip" style="display: none;">
        <button class="whatsapp-tooltip-close" aria-label="Cerrar tooltip">&times;</button>
        <p class="whatsapp-tooltip-text">¡Hola! Soy Guada de Relax Tours, ¿en qué puedo ayudarte hoy?</p>
    </div>
    <a href="#" class="whatsapp-btn"
        onclick="openWhatsApp('¡Hola! Soy Guada de Relax Tours, ¿en qué puedo ayudarte hoy?'); return false;"
        aria-label="Contactar por WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
        </svg>
    </a>
</div>

<!-- COOKIE BANNER -->
<div id="cookie-consent" class="cookie-consent" role="dialog" aria-label="Consentimiento de cookies">
    <div class="cookie-content">
        <div class="cookie-text">
            <h4>🍪 Política de Cookies</h4>
            <p>Utilizamos cookies propias y de terceros para mejorar tu experiencia y nuestros servicios. Si continuás
                navegando, consideramos que aceptás su uso. Podés obtener más información en nuestra <a
                    href="<?php echo $base_url; ?>cookies">Política de Cookies</a>.</p>
        </div>
        <div class="cookie-buttons">
            <button id="cookie-accept" class="btn-cookie-accept">Aceptar</button>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>js/cookies.js"></script>
<script src="<?php echo $base_url; ?>js/main.js"></script>
<script>document.getElementById('current-year').textContent = new Date().getFullYear();</script>
</body>

</html>