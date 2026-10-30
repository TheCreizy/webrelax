# Plan de Implementación Multi-Agente: Widget Omnicanal + Chatbot IA (Gemini + Supabase)

## 1. Descripción del Objetivo

Reemplazar a nivel global el botón flotante actual de WhatsApp (`.whatsapp-float`) en **Relax Tours** (`relaxtours.com.ar`) por un **Widget Omnicanal inteligente** con 4 canales integrados:
1. **Chatbot IA con RAG (Google Gemini Free Tier + Supabase `pgvector`)**:
   - Pre-captura obligatoria de Lead (*Nombre y Apellido, Email y Teléfono validado y normalizado a formato único E.164 `+549...`*).
   - Guardrails estrictos anti-alucinación y aislamiento total de datos personales (PII nunca se envía a Google Gemini).
   - Conocimiento sincronizado desde el Backoffice de la web (`Paquetes Destacados`, `Ofertas Flash`, `Tips del Blog`, `Asistencia al Viajero Coris` e `Información General de Relax Tours`).
   - Cuando un pasajero consulta por pasajes aéreos o destinos a medida que no están cargados en el catálogo, el asistente actúa como pre-cotizador inteligente (consulta destino, fechas aproximadas y cantidad de pasajeros) y lo deriva con un clic a un asesor por WhatsApp o Correo.
2. **WhatsApp Directo**: Redirección inteligente móvil/PC (`5491138593924`) que personaliza el saludo si el usuario ya ingresó su nombre en el widget.
3. **Correo Electrónico Integrado**: Formulario asíncrono dentro del widget conectado a `send-mail.php` (PHPMailer SMTP Hostinger), autocompletando los datos si el usuario ya completó el pre-chat.
4. **Teléfono Inteligente (Dispositivo + Huso Horario Argentina)**:
   - Evalúa siempre sobre `America/Argentina/Buenos_Aires` los horarios comerciales: **Lunes a Viernes de 10:00 a 13:00 y de 14:00 a 19:00 hs | Sábados de 10:00 a 13:00 hs | Domingos cerrado**.
   - En **Android/iOS dentro de horario**: Llama directo a `tel:+541170798079`.
   - En **Android/iOS fuera de horario** (incluyendo de 13:00 a 14:00 hs): Muestra aviso de horarios con botones rápidos a WhatsApp y Formulario de Correo.
   - En **PC (cualquier horario)**: Muestra tarjeta con el teléfono `11 7079-8079`, horarios de atención y accesos directos a WhatsApp Web y Correo.

---

## 2. Soporte de Supabase Secret API Keys (`sb_secret_...`) vs `service_role`

Supabase recomienda actualmente utilizar **Secret API keys (`sb_secret_...`)** en lugar de la antigua `service_role` basada en JWT:
- **¿Es más seguro y sirve igual?** **Sí, 100%**. Las nuevas *Secret API keys* (`sb_secret_...`) tienen exactamente los mismos permisos de backend (saltan Row Level Security para que tu servidor PHP pueda insertar leads, vectores y mensajes), pero no están atadas al secreto JWT maestro de la base de datos y pueden revocarse o rotarse en un segundo desde el panel de Supabase.
- **`api/SupabaseClient.php` soporta ambas**: Nuestro cliente detecta automáticamente si colocaste una nueva `sb_secret_...` (enviándola por el header `apikey`) o una clave `service_role` clásica (`eyJ...`), funcionando de manera transparente con cualquiera de las dos en `SUPABASE_SECRET_KEY` / `SUPABASE_SERVICE_ROLE_KEY`.

---

## 3. Flujo de Trabajo Multi-Agente

1. **Agente Orquestador (Principal)**:
   - Coordina la ejecución, mantiene actualizados `tasks.md`, `implementation_plan.md`, `dev_log.md` y `thoughts.md` en `docs/ai-widget/`, y ejecuta las pruebas de integración finales.
2. **Agente 1 — Supabase & DB Architect (vía MCP `supabase_mcp`)**:
   - Aprovisiona directamente en el proyecto `relaxtours-ai` (`phzmkeagpobdqqtvfqvv`) la extensión `vector`, las tablas `public.leads`, `public.knowledge_base` (con índice HNSW) y `public.chat_messages`, activa RLS y crea la función RPC blindada `public.match_knowledge`.
   - Verifica la seguridad con `get_advisors`.
3. **Agente 2 — Backend PHP, IA & Seguridad**:
   - Crea `includes/env-loader.php`, actualiza `admin/.env` y `admin/.env.example`, protege `.htaccess`, construye `api/SupabaseClient.php`, `api/GeminiClient.php`, `api/lead.php`, `api/chat.php` y adapta `send-mail.php`.
4. **Agente 3 — Backoffice Admin & Sincronizador RAG**:
   - Extiende `admin/index.php` agregando `vigencia_hasta` e `info_bot` en Paquetes y Ofertas, crea la pestaña `🤖 Chatbot IA` (`?tab=chatbot`) con el Conocimiento General de la agencia, el botón **"Sincronizar Todo con Supabase"** y el visor de Leads, y corrige el cierre HTML en `generateBlogPage()`.
5. **Agente 4 — Frontend Omnicanal UI/UX**:
   - Construye `css/relax-widget.css` y `js/relax-widget.js` con las 4 vistas, validación E.164 de teléfono y lógica horaria Argentina, reemplaza `.whatsapp-float` en `includes/footer.php`, ajusta `js/main.js` y regenera los artículos estáticos `blog/*.html`.

---

## 4. Roadmap Futuro Documentado (Ideas para Etapa 2: Station Travel y Tucano Tours)

Tal como acordamos, no ensuciaremos el código actual con integraciones prematuras de Station Travel ni Tucano Tours hasta que estén definidas sus estructuras. Las ideas y diseño preliminar quedan documentadas en [`thoughts.md`](./thoughts.md) para cuando se habilite el subdominio `paquetes.relaxtours.com.ar` y la API/acceso de Tucano.
