/**
 * ============================================================================
 * RELAX TOURS — WIDGET OMNICANAL GLOBAL (js/relax-widget.js)
 * 4 Canales:
 *  1. Chatbot IA (RAG Gemini + Supabase) con Pre-Chat Gatekeeper y E.164 (+549...)
 *  2. WhatsApp Directo
 *  3. Correo Electrónico (integrado con send-mail.php)
 *  4. Teléfono inteligente condicional por Dispositivo (Móvil/PC) y Horario Comercial AR
 * ============================================================================
 */
(function () {
  "use strict";

  const STORAGE_LEAD_KEY = "relax_lead_data";
  const STORAGE_HISTORY_KEY = "relax_chat_history";
  const STORAGE_TOOLTIP_DISMISSED = "relax_tooltip_dismissed";
  const DEFAULT_WHATSAPP = "5491138593924";
  const PHONE_DISPLAY = "11 7079-8079";
  const PHONE_TEL_URI = "tel:+541170798079";

  /**
   * Detecta automáticamente la ruta base relativa según si la página actual
   * se encuentra dentro de /blog/ o en el directorio raíz del sitio.
   */
  function getBasePath() {
    const path = window.location.pathname || "";
    return path.includes("/blog/") ? "../" : "";
  }

  /**
   * Detecta si el usuario navega desde un dispositivo móvil (Android / iOS).
   */
  function esDispositivoMovil() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || "");
  }

  /**
   * Evalúa si en este instante la agencia se encuentra dentro del horario comercial
   * en el huso horario oficial de Argentina (America/Argentina/Buenos_Aires):
   * - Lunes a Viernes: 10:00 a 13:00 hs y 14:00 a 19:00 hs
   * - Sábados: 10:00 a 13:00 hs
   * - Domingos: Cerrado
   */
  function estaEnHorarioComercialArgentina() {
    try {
      const arString = new Date().toLocaleString("en-US", {
        timeZone: "America/Argentina/Buenos_Aires",
      });
      const arDate = new Date(arString);
      const dia = arDate.getDay(); // 0 = Dom, 1 = Lun, ..., 6 = Sáb
      const hora = arDate.getHours();
      const min = arDate.getMinutes();
      const horaDecimal = hora + min / 60;

      if (dia >= 1 && dia <= 5) {
        return (
          (horaDecimal >= 10 && horaDecimal < 13) ||
          (horaDecimal >= 14 && horaDecimal < 19)
        );
      }
      if (dia === 6) {
        return horaDecimal >= 10 && horaDecimal < 13;
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  /**
   * Normaliza y valida estrictamente cualquier teléfono al estándar único E.164.
   * Para Argentina (+54) siempre produce +549 + 10 dígitos nacionales (sin 0 ni 15).
   *
   * @param {string} rawInput Número ingresado por el usuario
   * @param {string} countryCode Prefijo seleccionado ('54', '598', '56', '55', '1', '34', 'other')
   * @returns {string|null} Teléfono en formato E.164 (ej. "+5491138593924") o null si es inválido
   */
  function normalizePhoneToE164(rawInput, countryCode) {
    if (!rawInput || typeof rawInput !== "string") return null;

    // Si libphonenumber-js está disponible globalmente, intentar primero con él para no-AR
    const cleanRaw = rawInput.trim();
    if (!cleanRaw) return null;

    // Extraer solo dígitos
    let digits = cleanRaw.replace(/\D+/g, "");
    if (!digits || digits.length < 7) return null;

    // Rechazar secuencias repetidas inválidas (ej. 11111111, 00000000, 1234567890)
    if (
      /^(\d)\1{6,}$/.test(digits) ||
      digits === "1234567890" ||
      digits === "0123456789"
    ) {
      return null;
    }

    const code = String(countryCode || "54").replace(/\D+/g, "");

    // Normalización estricta para Argentina (+54 -> +549 + 10 dígitos)
    if (code === "54" || (cleanRaw.startsWith("+54") && code !== "other")) {
      let national = digits;

      // Quitar prefijos internacionales si el usuario los incluyó (00549, 0054, 549, 54)
      if (national.startsWith("00549")) {
        national = national.slice(5);
      } else if (national.startsWith("0054")) {
        national = national.slice(4);
      } else if (national.startsWith("549") && national.length >= 13) {
        national = national.slice(3);
      } else if (national.startsWith("54") && national.length >= 12) {
        national = national.slice(2);
      }

      // Quitar 0 inicial del código de área (ej. 011 -> 11, 0351 -> 351, 0223 -> 223)
      national = national.replace(/^0+/, "");

      // Si el usuario ingresó 10 dígitos comenzando con "15" (ej. 15 3859 3924 -> CABA/GBA área 11)
      if (national.length === 10 && national.startsWith("15")) {
        national = "11" + national.slice(2);
      } else if (national.length === 8 && !national.startsWith("11")) {
        // Si ingresó los 8 dígitos locales de AMBA sin código de área
        national = "11" + national;
      }

      // Quitar el prefijo móvil "15" ubicado después del código de área (2, 3 o 4 dígitos)
      if (national.length === 12) {
        // Caso: área(2) + 15 + abonado(8) | área(3) + 15 + abonado(7) | área(4) + 15 + abonado(6)
        const match12 =
          national.match(/^(11)15(\d{8})$/) ||
          national.match(/^(\d{3})15(\d{7})$/) ||
          national.match(/^(\d{4})15(\d{6})$/);
        if (match12) {
          national = match12[1] + match12[2];
        }
      } else if (national.length === 11) {
        const match11 = national.match(/^(\d{2,4})15(\d{5,7})$/);
        if (
          match11 &&
          (match11[1].length + match11[2].length === 9 ||
            match11[1].length + match11[2].length === 10)
        ) {
          national = match11[1] + match11[2];
        }
      }

      // Validar que el número nacional argentino tenga exactamente 10 dígitos
      if (!/^[1-9]\d{9}$/.test(national)) {
        return null;
      }

      // Verificar nuevamente que los últimos 8 dígitos no sean todos idénticos
      if (/(\d)\1{6,}/.test(national)) {
        return null;
      }

      return "+549" + national;
    }

    // Otros países (Uruguay +598, Chile +56, Brasil +55, EE.UU. +1, España +34, u otro)
    let intlDigits = digits;
    if (intlDigits.startsWith("00")) {
      intlDigits = intlDigits.slice(2);
    }
    if (code && !intlDigits.startsWith(code)) {
      intlDigits = code + intlDigits.replace(/^0+/, "");
    }

    if (intlDigits.length < 10 || intlDigits.length > 15) {
      return null;
    }
    if (/(\d)\1{6,}/.test(intlDigits)) {
      return null;
    }

    return "+" + intlDigits;
  }

  /**
   * Valida Nombre y Apellido (mínimo 2 palabras reales de al menos 2 letras cada una).
   */
  function isValidFullName(name) {
    if (!name || typeof name !== "string") return false;
    const cleaned = name.trim().replace(/\s+/g, " ");
    if (cleaned.length < 4 || cleaned.length > 100) return false;
    return /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ'.-]{2,}(\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ'.-]{2,})+$/.test(
      cleaned,
    );
  }

  /**
   * Valida formato de correo electrónico.
   */
  function isValidEmail(email) {
    if (!email || typeof email !== "string") return false;
    const cleaned = email.trim().toLowerCase();
    return (
      /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(cleaned) && cleaned.length <= 120
    );
  }

  /**
   * Escapa caracteres HTML peligrosos y convierte formato básico Markdown del asistente
   * (**negritas**, listas con - y enlaces) en HTML limpio y seguro.
   */
  function formatBotMessageSafe(rawText) {
    const safe = String(rawText || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

    // Negritas **texto**
    let formatted = safe.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");

    // Enlaces URL http/https
    formatted = formatted.replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    );

    // Separar por líneas para armar párrafos y listas
    const lines = formatted.split(/\r?\n/);
    let html = "";
    let inList = false;

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) {
        if (inList) {
          html += "</ul>";
          inList = false;
        }
        continue;
      }

      if (/^[-•*]\s+/.test(line)) {
        if (!inList) {
          html += "<ul>";
          inList = true;
        }
        html += "<li>" + line.replace(/^[-•*]\s+/, "") + "</li>";
      } else {
        if (inList) {
          html += "</ul>";
          inList = false;
        }
        html += "<p>" + line + "</p>";
      }
    }

    if (inList) {
      html += "</ul>";
    }

    return html || "<p>" + formatted + "</p>";
  }

  /**
   * Lee los datos del Lead guardados en localStorage.
   */
  function getStoredLead() {
    try {
      const raw = localStorage.getItem(STORAGE_LEAD_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (parsed && parsed.lead_token && parsed.email) {
        return parsed;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /**
   * Guarda los datos del Lead en localStorage.
   */
  function saveStoredLead(leadData) {
    try {
      localStorage.setItem(STORAGE_LEAD_KEY, JSON.stringify(leadData));
    } catch (e) {
      // Ignorar si storage está bloqueado
    }
  }

  /**
   * Limpia el Lead de localStorage (por ejemplo si el token expiró o falló la validación).
   */
  function clearStoredLead() {
    try {
      localStorage.removeItem(STORAGE_LEAD_KEY);
    } catch (e) {
      // Ignorar
    }
  }

  /**
   * Obtiene el historial de la sesión actual desde sessionStorage.
   */
  function getChatHistory() {
    try {
      const raw = sessionStorage.getItem(STORAGE_HISTORY_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  /**
   * Guarda el historial de conversación en sessionStorage (últimos 14 mensajes).
   */
  function saveChatHistory(history) {
    try {
      sessionStorage.setItem(
        STORAGE_HISTORY_KEY,
        JSON.stringify(history.slice(-14)),
      );
    } catch (e) {
      // Ignorar
    }
  }

  /**
   * Abre WhatsApp con un mensaje personalizado si se conoce el nombre del usuario.
   */
  function triggerWhatsAppChannel(customText) {
    const lead = getStoredLead();
    const firstName = lead && lead.first_name ? lead.first_name : "";
    let message = customText || "";

    if (!message) {
      message = firstName
        ? "Hola Relax Tours! Soy " +
          firstName +
          ", estuve navegando en la web y quería hacer una consulta."
        : "¡Hola! Soy Atlas de Relax Tours, ¿en qué puedo ayudarte hoy?";
    }

    const rawNum =
      (window.siteData &&
        window.siteData.config &&
        window.siteData.config.whatsapp) ||
      DEFAULT_WHATSAPP;
    const cleanNum = String(rawNum).replace(/\D+/g, "") || DEFAULT_WHATSAPP;

    const url = esDispositivoMovil()
      ? "https://wa.me/" + cleanNum + "?text=" + encodeURIComponent(message)
      : "https://web.whatsapp.com/send?phone=" +
        cleanNum +
        "&text=" +
        encodeURIComponent(message);

    window.open(url, "_blank", "noopener,noreferrer");
  }

  /**
   * Construye e inyecta el widget omnicanal en document.body.
   */
  function initRelaxOmnichannelWidget() {
    if (document.getElementById("relax-widget-root")) return;

    const basePath = getBasePath();
    const logoSrc = basePath + "images/logo-relax.webp";

    const root = document.createElement("div");
    root.id = "relax-widget-root";
    root.className = "relax-widget-root";
    root.innerHTML = `
      <!-- Panel Principal Omnicanal -->
      <section class="relax-widget-panel" id="relax-widget-panel" role="dialog" aria-modal="false" aria-label="Centro de Atención Omnicanal de Relax Tours">
        <!-- Header -->
        <header class="relax-widget-header">
          <div class="relax-widget-header-top">
            <div class="relax-widget-brand">
              <button type="button" class="relax-widget-back-btn" id="rw-back-btn" aria-label="Volver al menú principal" title="Volver al menú">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </button>
              <div class="relax-widget-avatar">
                <img src="${logoSrc}" alt="Relax Tours" onerror="this.style.display='none'; this.parentElement.textContent='RT';">
                <span class="relax-widget-status-dot" title="En línea"></span>
              </div>
              <div class="relax-widget-titles">
                <h3 class="relax-widget-title" id="rw-header-title">Asistente Relax Tours</h3>
                <p class="relax-widget-subtitle" id="rw-header-subtitle">Atención Omnicanal • En línea</p>
              </div>
            </div>
            <button type="button" class="relax-widget-close-btn" id="rw-close-btn" aria-label="Cerrar panel">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>

          <!-- Navegación rápida de los 4 canales -->
          <nav class="relax-widget-nav" aria-label="Canales de contacto">
            <button type="button" class="relax-widget-nav-btn" data-rw-nav="chat">
              <span>💬</span> <span>Chat IA</span>
            </button>
            <button type="button" class="relax-widget-nav-btn" data-rw-nav="whatsapp">
              <span>🟢</span> <span>WhatsApp</span>
            </button>
            <button type="button" class="relax-widget-nav-btn" data-rw-nav="mail">
              <span>✉️</span> <span>Correo</span>
            </button>
            <button type="button" class="relax-widget-nav-btn" data-rw-nav="phone">
              <span>📞</span> <span>Teléfono</span>
            </button>
          </nav>
        </header>

        <!-- Cuerpo de Vistas -->
        <div class="relax-widget-body">
          <!-- VISTA 0: MENÚ PRINCIPAL / SELECTOR OMNICANAL -->
          <div class="relax-view is-active" id="rw-view-menu">
            <div class="relax-menu-container">
              <div class="relax-menu-intro">
                <h4 class="relax-menu-intro-title">¡Hola! 👋 ¿Cómo preferís contactarnos?</h4>
                <p class="relax-menu-intro-desc">Elegí el canal que te resulte más cómodo para planificar tu próximo viaje.</p>
              </div>

              <!-- Opción 1: Chat con Asistente IA -->
              <button type="button" class="relax-channel-card" data-rw-channel="chat">
                <div class="relax-channel-icon relax-channel-icon--ai">💬</div>
                <div class="relax-channel-info">
                  <h5 class="relax-channel-title">
                    Chat con Asistente IA
                    <span class="relax-channel-tag">24/7</span>
                  </h5>
                  <p class="relax-channel-desc">Respuestas al instante sobre paquetes, ofertas y tips</p>
                </div>
                <span class="relax-channel-arrow">›</span>
              </button>

              <!-- Opción 2: WhatsApp Directo -->
              <button type="button" class="relax-channel-card" data-rw-channel="whatsapp">
                <div class="relax-channel-icon relax-channel-icon--whatsapp">🟢</div>
                <div class="relax-channel-info">
                  <h5 class="relax-channel-title">WhatsApp Directo</h5>
                  <p class="relax-channel-desc">Chateá con nuestros asesores en WhatsApp</p>
                </div>
                <span class="relax-channel-arrow">›</span>
              </button>

              <!-- Opción 3: Enviar Correo Electrónico -->
              <button type="button" class="relax-channel-card" data-rw-channel="mail">
                <div class="relax-channel-icon relax-channel-icon--mail">✉️</div>
                <div class="relax-channel-info">
                  <h5 class="relax-channel-title">Enviar Correo Electrónico</h5>
                  <p class="relax-channel-desc">Dejanos tu consulta detallada por mail</p>
                </div>
                <span class="relax-channel-arrow">›</span>
              </button>

              <!-- Opción 4: Llamada Telefónica -->
              <button type="button" class="relax-channel-card" data-rw-channel="phone">
                <div class="relax-channel-icon relax-channel-icon--phone">📞</div>
                <div class="relax-channel-info">
                  <h5 class="relax-channel-title">Llamada Telefónica</h5>
                  <p class="relax-channel-desc">Atención telefónica directa en horario comercial</p>
                </div>
                <span class="relax-channel-arrow">›</span>
              </button>

              <div class="relax-menu-footer">
                Relax Tours • EVT Leg. 15.548 • Atención personalizada
              </div>
            </div>
          </div>

          <!-- VISTA 1A: PRE-CHAT GATEKEEPER (CAPTURA DE LEAD) -->
          <div class="relax-view" id="rw-view-prechat">
            <div class="relax-prechat-wrap">
              <div class="relax-prechat-card">
                <div class="relax-prechat-header">
                  <span class="relax-prechat-badge">✨ Asistente Virtual IA</span>
                  <h4 class="relax-prechat-title">Antes de empezar a chatear</h4>
                  <p class="relax-prechat-subtitle">Completá tus datos por única vez para brindarte atención personalizada y enviarte cotizaciones si lo necesitás.</p>
                </div>

                <form id="rw-prechat-form" novalidate>
                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-lead-name">Nombre y Apellido *</label>
                    <input type="text" id="rw-lead-name" class="relax-form-input" placeholder="Ej: Sofía Martínez" autocomplete="name" required>
                    <span class="relax-field-error" id="rw-err-name">Ingresá tu nombre y apellido reales (mínimo 2 palabras).</span>
                  </div>

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-lead-email">Correo Electrónico *</label>
                    <input type="email" id="rw-lead-email" class="relax-form-input" placeholder="tu@correo.com" autocomplete="email" required>
                    <span class="relax-field-error" id="rw-err-email">Ingresá un correo electrónico válido.</span>
                  </div>

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-lead-phone">Teléfono / WhatsApp *</label>
                    <div class="relax-phone-row">
                      <select id="rw-lead-country" class="relax-form-select" aria-label="Código de país">
                        <option value="54" selected>🇦🇷 +54</option>
                        <option value="598">🇺🇾 +598</option>
                        <option value="56">🇨🇱 +56</option>
                        <option value="55">🇧🇷 +55</option>
                        <option value="34">🇪🇸 +34</option>
                        <option value="1">🇺🇸 +1</option>
                        <option value="other">🌎 Otro</option>
                      </select>
                      <input type="tel" id="rw-lead-phone" class="relax-form-input" placeholder="Ej: 11 3859-3924" autocomplete="tel" required>
                    </div>
                    <span class="relax-form-help">Cód. de área + número (Ej: 11 3859-3924). Se guarda en formato E.164.</span>
                    <span class="relax-field-error" id="rw-err-phone">Ingresá un número válido con código de área (ej: 11 3859-3924).</span>
                  </div>

                  <button type="submit" class="relax-btn-primary" id="rw-prechat-submit">
                    <span>Iniciar Conversación</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="5" y1="12" x2="19" y2="12"></line>
                      <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                  </button>
                </form>

                <p class="relax-privacy-note">🔒 Tus datos están protegidos y no se comparten con terceros.</p>
              </div>
            </div>
          </div>

          <!-- VISTA 1B: VENTANA DE CONVERSACIÓN CHAT IA -->
          <div class="relax-view" id="rw-view-chat">
            <div class="relax-chat-stage">
              <div class="relax-chat-messages" id="rw-chat-messages" aria-live="polite"></div>

              <!-- Indicador Escribiendo -->
              <div class="relax-typing" id="rw-chat-typing" aria-label="El asistente está escribiendo">
                <span class="relax-typing-dot"></span>
                <span class="relax-typing-dot"></span>
                <span class="relax-typing-dot"></span>
              </div>

              <!-- Chips de Consulta Rápida -->
              <div class="relax-quick-chips" id="rw-quick-chips">
                <button type="button" class="relax-chip" data-rw-prompt="¿Cuáles son los paquetes destacados disponibles actualmente?">🎒 Paquetes Destacados</button>
                <button type="button" class="relax-chip" data-rw-prompt="¿Qué Ofertas Flash tienen vigentes hoy?">⚡ Ofertas Flash</button>
                <button type="button" class="relax-chip" data-rw-prompt="Quiero información sobre la asistencia al viajero Coris.">🛡️ Asistencia Coris</button>
                <button type="button" class="relax-chip" data-rw-prompt="Quiero consultar por pasajes aéreos y cotización.">✈️ Consultar Aéreos</button>
                <button type="button" class="relax-chip" data-rw-prompt="¿Qué consejos me dan sobre equipaje de mano y bodega?">🧳 Tips de Equipaje</button>
              </div>

              <!-- Input Inferior -->
              <form class="relax-chat-Composer" id="rw-chat-form" novalidate>
                <div class="relax-chat-input-row">
                  <input
                    type="text"
                    id="rw-chat-input"
                    class="relax-chat-input"
                    placeholder="Escribí tu consulta sobre viajes..."
                    maxlength="400"
                    autocomplete="off"
                  >
                  <button type="submit" class="relax-chat-send" id="rw-chat-send" aria-label="Enviar mensaje">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="22" y1="2" x2="11" y2="13"></line>
                      <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                  </button>
                </div>
                <div class="relax-chat-meta">
                  <span>Asistente IA de Relax Tours</span>
                  <span id="rw-char-counter">0 / 400</span>
                </div>
              </form>
            </div>
          </div>

          <!-- VISTA 3: FORMULARIO DE CORREO ELECTRÓNICO -->
          <div class="relax-view" id="rw-view-mail">
            <div class="relax-mail-wrap">
              <div class="relax-mail-card">
                <div class="relax-prechat-header">
                  <span class="relax-prechat-badge">✉️ Atención por E-mail</span>
                  <h4 class="relax-prechat-title">Envianos tu consulta</h4>
                  <p class="relax-prechat-subtitle">Completá el formulario y nuestro equipo de ventas te responderá a tu correo a la brevedad.</p>
                </div>

                <form id="rw-mail-form" novalidate>
                  <input type="hidden" name="form_type" value="contacto">
                  <input type="hidden" name="source" value="Widget Omnicanal">

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-mail-name">Nombre Completo *</label>
                    <input type="text" id="rw-mail-name" name="name" class="relax-form-input" placeholder="Tu nombre y apellido" required>
                  </div>

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-mail-email">Correo Electrónico *</label>
                    <input type="email" id="rw-mail-email" name="email" class="relax-form-input" placeholder="tu@correo.com" required>
                  </div>

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-mail-phone">Teléfono *</label>
                    <input type="tel" id="rw-mail-phone" name="phone" class="relax-form-input" placeholder="Ej: +54 9 11 3859-3924" required>
                  </div>

                  <div class="relax-form-group">
                    <label class="relax-form-label" for="rw-mail-message">Consulta *</label>
                    <textarea id="rw-mail-message" name="message" class="relax-form-textarea" rows="3" placeholder="Contanos destino, fechas aproximadas y cantidad de pasajeros..." required></textarea>
                  </div>

                  <button type="submit" class="relax-btn-primary" id="rw-mail-submit">
                    Enviar Correo Electrónico
                  </button>

                  <div id="rw-mail-alert" class="relax-alert" role="status"></div>
                </form>
              </div>
            </div>
          </div>

          <!-- VISTA 4: ATENCIÓN TELEFÓNICA INTELIGENTE -->
          <div class="relax-view" id="rw-view-phone">
            <div class="relax-phone-wrap">
              <div class="relax-phone-card">
                <div class="relax-phone-status-badge is-open" id="rw-phone-badge">
                  <span class="relax-phone-status-dot"></span>
                  <span id="rw-phone-badge-text">En horario de atención</span>
                </div>

                <div class="relax-phone-number">${PHONE_DISPLAY}</div>

                <p class="relax-phone-message" id="rw-phone-message">
                  Comunicate de forma directa con nuestras oficinas comerciales en Villa Devoto, Buenos Aires.
                </p>

                <div class="relax-phone-schedule">
                  <div class="relax-phone-schedule-title">🕒 Horarios de Atención (Argentina)</div>
                  <div><strong>Lunes a Viernes:</strong> 10:00 a 13:00 y de 14:00 a 19:00 hs</div>
                  <div><strong>Sábados:</strong> 10:00 a 13:00 hs</div>
                </div>

                <div class="relax-phone-actions">
                  <a href="${PHONE_TEL_URI}" class="relax-btn-call" id="rw-phone-call-btn" style="display: none;">
                    📞 Llamar ahora al ${PHONE_DISPLAY}
                  </a>
                  <button type="button" class="relax-btn-whatsapp" id="rw-phone-wa-btn">
                    🟢 Escribir por WhatsApp
                  </button>
                  <button type="button" class="relax-btn-secondary" id="rw-phone-mail-btn">
                    ✉️ Enviar Correo Electrónico
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Botón Flotante Principal (.relax-omnifab) + Tooltip -->
      <div class="relax-omnifab-wrapper">
        <div class="relax-omnifab-tooltip" id="rw-tooltip" role="status">
          <button type="button" class="relax-omnifab-tooltip-close" id="rw-tooltip-close" aria-label="Cerrar saludo">&times;</button>
          <p class="relax-omnifab-tooltip-text" id="rw-tooltip-body">
            ¡Hola! Soy <strong>Atlas</strong> de Relax Tours, ¿en qué puedo ayudarte hoy?
          </p>
        </div>

        <button type="button" class="relax-omnifab" id="rw-omnifab" aria-label="Abrir centro de contacto de Relax Tours" aria-expanded="false">
          <span class="relax-omnifab-badge"></span>
          <span class="relax-omnifab-icon relax-omnifab-icon--open" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
          </span>
          <span class="relax-omnifab-icon relax-omnifab-icon--close" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </span>
        </button>
      </div>
    `;

    document.body.appendChild(root);

    // Referencias DOM
    const fabBtn = document.getElementById("rw-omnifab");
    const closeBtn = document.getElementById("rw-close-btn");
    const backBtn = document.getElementById("rw-back-btn");
    const tooltip = document.getElementById("rw-tooltip");
    const tooltipClose = document.getElementById("rw-tooltip-close");
    const tooltipBody = document.getElementById("rw-tooltip-body");
    const headerTitle = document.getElementById("rw-header-title");
    const headerSubtitle = document.getElementById("rw-header-subtitle");

    const views = {
      menu: document.getElementById("rw-view-menu"),
      prechat: document.getElementById("rw-view-prechat"),
      chat: document.getElementById("rw-view-chat"),
      mail: document.getElementById("rw-view-mail"),
      phone: document.getElementById("rw-view-phone"),
    };

    const navBtns = root.querySelectorAll("[data-rw-nav]");
    const channelCards = root.querySelectorAll("[data-rw-channel]");

    // Pre-chat form elements
    const prechatForm = document.getElementById("rw-prechat-form");
    const leadNameInput = document.getElementById("rw-lead-name");
    const leadEmailInput = document.getElementById("rw-lead-email");
    const leadCountrySelect = document.getElementById("rw-lead-country");
    const leadPhoneInput = document.getElementById("rw-lead-phone");
    const prechatSubmitBtn = document.getElementById("rw-prechat-submit");
    const errName = document.getElementById("rw-err-name");
    const errEmail = document.getElementById("rw-err-email");
    const errPhone = document.getElementById("rw-err-phone");

    // Chat elements
    const chatMessagesEl = document.getElementById("rw-chat-messages");
    const chatTypingEl = document.getElementById("rw-chat-typing");
    const chatForm = document.getElementById("rw-chat-form");
    const chatInput = document.getElementById("rw-chat-input");
    const chatSendBtn = document.getElementById("rw-chat-send");
    const charCounter = document.getElementById("rw-char-counter");
    const quickChips = root.querySelectorAll("[data-rw-prompt]");

    // Mail elements
    const mailForm = document.getElementById("rw-mail-form");
    const mailName = document.getElementById("rw-mail-name");
    const mailEmail = document.getElementById("rw-mail-email");
    const mailPhone = document.getElementById("rw-mail-phone");
    const mailMessage = document.getElementById("rw-mail-message");
    const mailSubmitBtn = document.getElementById("rw-mail-submit");
    const mailAlert = document.getElementById("rw-mail-alert");

    // Phone elements
    const phoneBadge = document.getElementById("rw-phone-badge");
    const phoneBadgeText = document.getElementById("rw-phone-badge-text");
    const phoneMessage = document.getElementById("rw-phone-message");
    const phoneCallBtn = document.getElementById("rw-phone-call-btn");
    const phoneWaBtn = document.getElementById("rw-phone-wa-btn");
    const phoneMailBtn = document.getElementById("rw-phone-mail-btn");

    let isOpen = false;
    let isSendingChat = false;

    /**
     * Precarga los datos del Lead en el formulario de Correo Electrónico si existen.
     */
    function syncLeadToMailForm() {
      const lead = getStoredLead();
      if (!lead) return;
      if (lead.full_name && !mailName.value) mailName.value = lead.full_name;
      if (lead.email && !mailEmail.value) mailEmail.value = lead.email;
      if (lead.phone_e164 && !mailPhone.value)
        mailPhone.value = lead.phone_e164;
    }

    /**
     * Cambia la vista activa dentro del panel omnicanal.
     */
    function switchView(viewName) {
      Object.keys(views).forEach(function (key) {
        if (views[key]) {
          views[key].classList.toggle("is-active", key === viewName);
        }
      });

      // Mostrar u ocultar botón de volver al menú
      if (viewName === "menu") {
        backBtn.classList.remove("is-visible");
        headerTitle.textContent = "Asistente Relax Tours";
        headerSubtitle.textContent = "Atención Omnicanal • En línea";
      } else {
        backBtn.classList.add("is-visible");
        if (viewName === "prechat" || viewName === "chat") {
          headerTitle.textContent = "Asistente IA • Relax Tours";
          headerSubtitle.textContent = "Respuestas al instante 24/7";
        } else if (viewName === "mail") {
          headerTitle.textContent = "Consulta por Correo";
          headerSubtitle.textContent = "ventas1@relaxtours.com.ar";
        } else if (viewName === "phone") {
          headerTitle.textContent = "Atención Telefónica";
          headerSubtitle.textContent = "Oficina Comercial Devoto";
        }
      }

      // Actualizar estado activo en barra de navegación
      navBtns.forEach(function (btn) {
        const navTarget = btn.getAttribute("data-rw-nav");
        const active =
          (navTarget === "chat" &&
            (viewName === "chat" || viewName === "prechat")) ||
          navTarget === viewName;
        btn.classList.toggle("is-active", active);
      });

      if (viewName === "mail") {
        syncLeadToMailForm();
      }
      if (viewName === "chat") {
        ensureChatInitialized();
        setTimeout(function () {
          chatInput.focus();
        }, 120);
      }
    }

    /**
     * Abre o cierra el panel del widget.
     */
    function toggleWidget(forceState) {
      isOpen = typeof forceState === "boolean" ? forceState : !isOpen;
      root.classList.toggle("is-open", isOpen);
      fabBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");

      if (isOpen) {
        tooltip.classList.remove("is-visible");
        syncLeadToMailForm();
      }
    }

    // Mostrar Tooltip discreto a los 4 segundos si el widget está cerrado
    setTimeout(function () {
      try {
        if (
          !isOpen &&
          sessionStorage.getItem(STORAGE_TOOLTIP_DISMISSED) !== "1"
        ) {
          tooltip.classList.add("is-visible");
        }
      } catch (e) {
        if (!isOpen) tooltip.classList.add("is-visible");
      }
    }, 4000);

    tooltipClose.addEventListener("click", function (e) {
      e.stopPropagation();
      tooltip.classList.remove("is-visible");
      try {
        sessionStorage.setItem(STORAGE_TOOLTIP_DISMISSED, "1");
      } catch (err) {}
    });

    tooltipBody.addEventListener("click", function () {
      tooltip.classList.remove("is-visible");
      toggleWidget(true);
    });

    fabBtn.addEventListener("click", function () {
      toggleWidget();
    });

    closeBtn.addEventListener("click", function () {
      toggleWidget(false);
    });

    backBtn.addEventListener("click", function () {
      switchView("menu");
    });

    /**
     * Maneja la selección de cualquiera de los 4 canales (desde tarjetas del menú o barra superior).
     */
    function handleChannelSelection(channel) {
      if (channel === "chat") {
        const lead = getStoredLead();
        if (lead && lead.lead_token) {
          switchView("chat");
        } else {
          switchView("prechat");
        }
        return;
      }

      if (channel === "whatsapp") {
        triggerWhatsAppChannel();
        return;
      }

      if (channel === "mail") {
        switchView("mail");
        return;
      }

      if (channel === "phone") {
        handlePhoneOptionClick();
      }
    }

    channelCards.forEach(function (card) {
      card.addEventListener("click", function () {
        handleChannelSelection(card.getAttribute("data-rw-channel"));
      });
    });

    navBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        handleChannelSelection(btn.getAttribute("data-rw-nav"));
      });
    });

    // ========================================================================
    // LÓGICA CANAL 4: TELÉFONO CONDICIONAL (DISPOSITIVO + HORARIO ARGENTINA)
    // ========================================================================

    function renderPhoneViewState(inBusinessHours, isMobileDevice) {
      if (inBusinessHours) {
        phoneBadge.className = "relax-phone-status-badge is-open";
        phoneBadgeText.textContent = "Abierto ahora • Línea disponible";
        phoneMessage.textContent =
          "Nuestras líneas telefónicas están habilitadas en este momento. Llamá al 11 7079-8079 o contactanos por WhatsApp o Correo.";
        phoneCallBtn.style.display = isMobileDevice ? "inline-flex" : "none";
      } else {
        phoneBadge.className = "relax-phone-status-badge is-closed";
        phoneBadgeText.textContent = "Cerrado en este momento";
        phoneMessage.textContent =
          "En este momento nuestras líneas telefónicas están cerradas. Nuestro horario de atención es de Lunes a Viernes de 10:00 a 13:00 y de 14:00 a 19:00 hs, y Sábados de 10:00 a 13:00 hs.";
        phoneCallBtn.style.display = "none";
      }
    }

    function handlePhoneOptionClick() {
      const enHorario = estaEnHorarioComercialArgentina();
      const esMovil = esDispositivoMovil();

      renderPhoneViewState(enHorario, esMovil);
      switchView("phone");

      // Caso A: Dispositivo móvil (Android / iOS) + Dentro del horario comercial de Argentina
      if (esMovil && enHorario) {
        window.location.href = PHONE_TEL_URI;
      }
    }

    phoneWaBtn.addEventListener("click", function () {
      triggerWhatsAppChannel();
    });

    phoneMailBtn.addEventListener("click", function () {
      switchView("mail");
    });

    // ========================================================================
    // LÓGICA CANAL 1: PRE-CHAT GATEKEEPER + CONVERSACIÓN IA (RAG)
    // ========================================================================

    function clearPrechatErrors() {
      leadNameInput.classList.remove("has-error");
      leadEmailInput.classList.remove("has-error");
      leadPhoneInput.classList.remove("has-error");
      errName.classList.remove("is-visible");
      errEmail.classList.remove("is-visible");
      errPhone.classList.remove("is-visible");
    }

    prechatForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearPrechatErrors();

      const fullName = leadNameInput.value.trim().replace(/\s+/g, " ");
      const email = leadEmailInput.value.trim().toLowerCase();
      const countryCode = leadCountrySelect.value;
      const rawPhone = leadPhoneInput.value.trim();

      let valid = true;

      if (!isValidFullName(fullName)) {
        leadNameInput.classList.add("has-error");
        errName.classList.add("is-visible");
        valid = false;
      }

      if (!isValidEmail(email)) {
        leadEmailInput.classList.add("has-error");
        errEmail.classList.add("is-visible");
        valid = false;
      }

      const phoneE164 = normalizePhoneToE164(rawPhone, countryCode);
      if (!phoneE164) {
        leadPhoneInput.classList.add("has-error");
        errPhone.classList.add("is-visible");
        valid = false;
      }

      if (!valid) return;

      prechatSubmitBtn.disabled = true;
      const originalBtnHtml = prechatSubmitBtn.innerHTML;
      prechatSubmitBtn.textContent = "Conectando...";

      try {
        const res = await fetch(basePath + "api/lead", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            full_name: fullName,
            email: email,
            phone: phoneE164,
            page_url: window.location.href,
          }),
        });

        const data = await res.json();

        if (!res.ok || !data || !data.ok) {
          const field = data && data.field ? data.field : "phone";
          const msg =
            (data && data.message) || "Verificá los datos ingresados.";
          if (field === "full_name") {
            leadNameInput.classList.add("has-error");
            errName.textContent = msg;
            errName.classList.add("is-visible");
          } else if (field === "email") {
            leadEmailInput.classList.add("has-error");
            errEmail.textContent = msg;
            errEmail.classList.add("is-visible");
          } else {
            leadPhoneInput.classList.add("has-error");
            errPhone.textContent = msg;
            errPhone.classList.add("is-visible");
          }
          return;
        }

        const firstName = data.first_name || fullName.split(" ")[0];
        const leadRecord = {
          lead_id: data.lead_id,
          lead_token: data.lead_token,
          full_name: fullName,
          first_name: firstName,
          email: email,
          phone_e164: data.phone_e164 || phoneE164,
        };

        saveStoredLead(leadRecord);
        syncLeadToMailForm();
        switchView("chat");
      } catch (err) {
        // Fallback local resiliente si la red falla momentáneamente
        leadPhoneInput.classList.add("has-error");
        errPhone.textContent =
          "No pudimos conectar con el servidor. Intentá nuevamente.";
        errPhone.classList.add("is-visible");
      } finally {
        prechatSubmitBtn.disabled = false;
        prechatSubmitBtn.innerHTML = originalBtnHtml;
      }
    });

    /**
     * Agrega una burbuja de mensaje al contenedor del chat.
     */
    function appendMessageBubble(role, text, options) {
      const opts = options || {};
      const div = document.createElement("div");
      div.className =
        "relax-msg " +
        (role === "user" ? "relax-msg--user" : "relax-msg--model");

      if (role === "user") {
        div.textContent = text;
      } else {
        div.innerHTML = formatBotMessageSafe(text);
        if (opts.showWhatsAppCta) {
          const waBtn = document.createElement("button");
          waBtn.type = "button";
          waBtn.className = "relax-msg-wa-cta";
          waBtn.innerHTML = "🟢 Continuar en WhatsApp con un asesor";
          waBtn.addEventListener("click", function () {
            triggerWhatsAppChannel(opts.waPrefill || "");
          });
          div.appendChild(waBtn);
        }
      }

      chatMessagesEl.appendChild(div);
      chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }

    /**
     * Inicializa el historial visual del chat o muestra el saludo inicial personalizado.
     */
    function ensureChatInitialized() {
      if (chatMessagesEl.children.length > 0) return;

      const history = getChatHistory();
      const lead = getStoredLead();
      const firstName = (lead && lead.first_name) || "viajero/a";

      if (history.length > 0) {
        history.forEach(function (item) {
          appendMessageBubble(item.role, item.message);
        });
      } else {
        const welcome =
          "¡Hola " +
          firstName +
          "! 👋 Soy el asistente virtual de **Relax Tours**. Puedo ayudarte con información sobre nuestros **paquetes turísticos**, **ofertas flash**, **asistencia al viajero Coris** y tips para tu próximo viaje. ¿Qué destino tenés en mente?";
        appendMessageBubble("model", welcome);
      }
    }

    /**
     * Envía una consulta al endpoint RAG api/chat.php.
     */
    async function sendChatMessage(rawText) {
      const text = String(rawText || "")
        .trim()
        .slice(0, 400);
      if (!text || isSendingChat) return;

      const lead = getStoredLead();
      if (!lead || !lead.lead_token) {
        switchView("prechat");
        return;
      }

      isSendingChat = true;
      chatInput.value = "";
      charCounter.textContent = "0 / 400";
      chatSendBtn.disabled = true;

      appendMessageBubble("user", text);

      const history = getChatHistory();
      chatTypingEl.classList.add("is-visible");
      chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;

      try {
        const response = await fetch(basePath + "api/chat", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            message: text,
            history: history.slice(-6),
            lead_token: lead.lead_token,
            email: lead.email,
          }),
        });

        const data = await response.json();
        chatTypingEl.classList.remove("is-visible");

        if (
          response.status === 403 ||
          (data && data.code === "LEAD_REQUIRED")
        ) {
          clearStoredLead();
          switchView("prechat");
          return;
        }

        if (
          response.status === 429 ||
          response.status === 503 ||
          (data &&
            (data.code === "RATE_LIMIT" ||
              data.code === "AI_QUOTA_EXCEEDED" ||
              data.code === "AI_UNAVAILABLE"))
        ) {
          const msg =
            (data && data.message) ||
            "Alcanzaste el límite de consultas automáticas. Podés continuar charlando con nuestros asesores por WhatsApp.";
          appendMessageBubble("model", msg, {
            showWhatsAppCta: true,
            waPrefill:
              "Hola Relax Tours! Soy " +
              (lead.first_name || "") +
              ". Mi consulta es: " +
              text,
          });
          return;
        }

        if (!response.ok || !data || !data.ok) {
          const errMsg =
            (data && data.message) ||
            "En este momento no pudimos procesar tu consulta. Tocá el botón debajo para hablar directo por WhatsApp.";
          appendMessageBubble("model", errMsg, {
            showWhatsAppCta: true,
            waPrefill: "Hola Relax Tours! Quería consultar: " + text,
          });
          return;
        }

        appendMessageBubble("model", data.reply);

        history.push({ role: "user", message: text });
        history.push({ role: "model", message: data.reply });
        saveChatHistory(history);
      } catch (err) {
        chatTypingEl.classList.remove("is-visible");
        appendMessageBubble(
          "model",
          "Ocurrió un problema de conexión. Podés escribirnos directamente por WhatsApp para atención inmediata.",
          {
            showWhatsAppCta: true,
            waPrefill: "Hola Relax Tours! Quería consultar: " + text,
          },
        );
      } finally {
        isSendingChat = false;
        chatSendBtn.disabled = false;
      }
    }

    chatInput.addEventListener("input", function () {
      const len = chatInput.value.length;
      charCounter.textContent = len + " / 400";
    });

    chatForm.addEventListener("submit", function (e) {
      e.preventDefault();
      sendChatMessage(chatInput.value);
    });

    quickChips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        const promptText = chip.getAttribute("data-rw-prompt");
        sendChatMessage(promptText);
      });
    });

    // ========================================================================
    // LÓGICA CANAL 3: CORREO ELECTRÓNICO (INTEGRADO CON send-mail.php)
    // ========================================================================

    mailForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      mailAlert.className = "relax-alert";
      mailAlert.textContent = "";

      const nameVal = mailName.value.trim();
      const emailVal = mailEmail.value.trim();
      const phoneVal = mailPhone.value.trim();
      const msgVal = mailMessage.value.trim();

      if (!nameVal || !isValidEmail(emailVal) || !phoneVal || !msgVal) {
        mailAlert.textContent =
          "Por favor completá todos los campos con datos válidos.";
        mailAlert.className = "relax-alert relax-alert--error is-visible";
        return;
      }

      const originalBtnText = mailSubmitBtn.textContent;
      mailSubmitBtn.disabled = true;
      mailSubmitBtn.textContent = "Enviando...";

      try {
        const formData = new FormData(mailForm);
        const response = await fetch(basePath + "send-mail.php", {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result && (result.ok === true || result.status === "success")) {
          mailAlert.textContent =
            result.message ||
            "¡Tu consulta fue enviada con éxito! Te responderemos a la brevedad.";
          mailAlert.className = "relax-alert relax-alert--success is-visible";
          mailMessage.value = "";
        } else {
          mailAlert.textContent =
            (result && result.message) ||
            "No se pudo enviar el correo. Intentá nuevamente o escribinos por WhatsApp.";
          mailAlert.className = "relax-alert relax-alert--error is-visible";
        }
      } catch (err) {
        mailAlert.textContent =
          "Error de conexión al enviar el mensaje. Intentá nuevamente.";
        mailAlert.className = "relax-alert relax-alert--error is-visible";
      } finally {
        mailSubmitBtn.disabled = false;
        mailSubmitBtn.textContent = originalBtnText;
      }
    });

    // Exponer API global opcional para abrir el widget desde cualquier parte del sitio
    window.RelaxOmnichannel = {
      open: function (channel) {
        toggleWidget(true);
        if (channel) handleChannelSelection(channel);
      },
      close: function () {
        toggleWidget(false);
      },
      normalizePhoneToE164: normalizePhoneToE164,
      estaEnHorarioComercialArgentina: estaEnHorarioComercialArgentina,
      esDispositivoMovil: esDispositivoMovil,
    };
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initRelaxOmnichannelWidget);
  } else {
    initRelaxOmnichannelWidget();
  }
})();
