# Tasks — Widget Omnicanal + Chatbot IA (Relax Tours)

> Archivo vivo de seguimiento en `docs/ai-widget/tasks.md`. Se actualiza conforme cada agente avanza y completa su fase.

## Fase 0: Diagnóstico, Planificación y Setup Documental (Agente Orquestador)
- [x] Analizar documento de requerimientos y arquitectura adjunto por el usuario.
- [x] Inspeccionar estructura actual del repositorio (`includes/footer.php`, `includes/header.php`, `send-mail.php`, `admin/index.php`, `data/content.json`, `.htaccess`, `js/main.js`, `css/style.css`, `blog/*.html`).
- [x] Verificar conexión y estado del MCP de Supabase (`supabase_mcp` → Proyecto `relaxtours-ai` / `phzmkeagpobdqqtvfqvv` en `sa-east-1` activo).
- [x] Crear archivos iniciales de seguimiento y recibir aprobación del usuario sobre el `/plan`.
- [x] Crear carpeta `docs/ai-widget/` con `implementation_plan.md`, `tasks.md`, `dev_log.md` y `thoughts.md`.

## Fase 1: Base de Datos y Vectores en Supabase (Agente 1 — Supabase & DB Architect)
- [x] Habilitar extensión `vector` (`pgvector`) en el proyecto `phzmkeagpobdqqtvfqvv`.
- [x] Crear tabla `public.leads` con índices en `email`, `phone_e164` y `created_at`.
- [x] Crear tabla `public.knowledge_base` con columna `embedding vector(768)`, restricción `unique` en `external_id`, índice HNSW (`vector_cosine_ops`) e índice de vigencia (`is_active`, `valid_until`).
- [x] Crear tabla `public.chat_messages` vinculada a `public.leads(id)` con `ON DELETE CASCADE`.
- [x] Habilitar Row Level Security (`ENABLE ROW LEVEL SECURITY`) en las 3 tablas (acceso exclusivo desde el backend PHP con Secret API Key / `service_role`).
- [x] Crear función RPC `public.match_knowledge` con filtro estricto de vigencia (`valid_until IS NULL OR valid_until >= CURRENT_DATE`) y `search_path` seguro.
- [x] Ejecutar auditoría de seguridad y rendimiento con `get_advisors` del MCP de Supabase (`lints: []` en seguridad).

## Fase 2: Backend PHP Seguro, Integración Gemini & Correo (Agente 2 — Backend & IA)
- [x] Crear `includes/env-loader.php` para carga segura y unificada de `admin/.env`.
- [x] Actualizar `admin/.env` y `admin/.env.example` con las variables de Gemini (`gemini-2.5-flash` + fallback `gemini-2.0-flash` + `text-embedding-004`) y Supabase (`https://phzmkeagpobdqqtvfqvv.supabase.co` + soporte `SUPABASE_SECRET_KEY` / `SUPABASE_SERVICE_ROLE_KEY`).
- [x] Reforzar `.htaccess` para proteger archivos sensibles, clases internas y el directorio `docs/`.
- [x] Implementar `api/SupabaseClient.php` compatible tanto con nuevas **Secret API Keys (`sb_secret_...`)** como con claves `service_role` clásicas (`insertLead`, `upsertKnowledge`, `deleteKnowledgeByExternalId`, `matchKnowledge`, `insertChatMessage`, `getRecentLeads`).
- [x] Implementar `api/GeminiClient.php` (`generateEmbedding` a 768 dims, `generateChatResponse` con guardrails, `temperature: 0.15`, aislamiento de PII y fallback automático ante error 429).
- [x] Implementar endpoint `api/lead.php` con validación servidor de Nombre, Email y Teléfono E.164 + firma HMAC de `lead_token`.
- [x] Implementar endpoint `api/chat.php` con validación de sesión/token HMAC, Rate Limiting por sesión/IP, truncado a 400 caracteres y flujo RAG completo.
- [x] Adaptar `send-mail.php` para soportar peticiones AJAX/JSON del widget manteniendo 100% de compatibilidad con el formulario del footer y el botón de arrepentimiento.

## Fase 3: Extensión del Backoffice y Sincronización IA (Agente 3 — Backoffice & Sync)
- [x] Agregar campos opcionales `vigencia_hasta` (`date`) e `info_bot` (`textarea`) en los formularios y guardado de Paquetes y Ofertas Flash en `admin/index.php`.
- [x] Agregar pestaña `🤖 Chatbot IA` (`?tab=chatbot`) en `admin/index.php` con:
  - [x] Editor de Conocimiento General de la Agencia, Asistencia Coris, Medios de Pago y Horarios.
  - [x] Botón **"Sincronizar Todo con Supabase (Generar Vectores IA)"** que vectorice paquetes, ofertas, posts del blog y conocimiento general.
  - [x] Visor de los últimos Leads capturados en el pre-chat.
- [x] Conectar guardado/eliminación de paquetes y ofertas en `admin/index.php` con sincronización automática hacia Supabase cuando las credenciales estén configuradas.
- [x] Corregir etiqueta duplicada `</body></html>` en `generateBlogPage()` de `admin/index.php`.

## Fase 4: Frontend del Widget Omnicanal Global (Agente 4 — Frontend UI/UX)
- [x] Diseñar y crear `css/relax-widget.css` alineado a la identidad visual de Relax Tours (`#F97316`, `Outfit`, `Inter`, responsive mobile/desktop).
- [x] Desarrollar `js/relax-widget.js` con las 4 vistas interactivas:
  - [x] **Vista 1 (Chatbot IA)**: Pre-chat obligatorio con validación y normalización estricta de teléfono a formato único E.164 (`+549...`), persistencia en `localStorage` (`lead_token`), chips rápidos, formato Markdown seguro y manejo elegante de errores/429.
  - [x] **Vista 2 (WhatsApp)**: Apertura inteligente en app móvil o WhatsApp Web con saludo personalizado si ya se conoce el nombre del cliente.
  - [x] **Vista 3 (Correo Electrónico)**: Formulario integrado con autocompletado desde el pre-chat y envío vía `fetch` a `send-mail.php`.
  - [x] **Vista 4 (Teléfono Condicional)**: Evaluación exacta en huso horario `America/Argentina/Buenos_Aires` (Lun-Vie 10-13 y 14-19 hs, Sáb 10-13 hs) + detección Android/iOS vs. PC.
- [x] Reemplazar `.whatsapp-float` en `includes/footer.php` por la inyección de `css/relax-widget.css` y `js/relax-widget.js`.
- [x] Ajustar `js/main.js` para convivir limpiamente con el nuevo widget manteniendo `openWhatsApp()` global.
- [x] Regenerar las páginas estáticas en `blog/*.html` para que carguen el nuevo widget omnicanal.

## Fase 5: Verificación Integral y Cierre (Agente Orquestador)
- [x] Validar sintaxis PHP (`php -l`) y JS (`node -c`) en todos los archivos del proyecto.
- [x] Ejecutar pruebas automatizadas de normalización de teléfonos E.164, lógica horaria Argentina y llamada en vivo a Gemini API (`gemini-3.5-flash-lite` + `gemini-embedding-001` respondieron en 1.5s con precisión exacta).
- [x] Verificar tablas, políticas RLS y función `match_knowledge` en Supabase MCP (`lints: []`).
- [x] Actualizar `dev_log.md`, `thoughts.md`, `tasks.md` y generar el `walkthrough.md` final.

## Fase 6: Migración Definitiva a Base de Datos Supabase & Bucket `relax-media` (Multi-Agente)
- [x] **Arquitectura y Modelado Supabase (`supabase-architect`)**:
  - [x] Crear tablas `public.cms_packages`, `public.cms_offers`, `public.cms_blog_posts`, `public.cms_settings` con RLS habilitado.
  - [x] Crear bucket de almacenamiento público `relax-media` en Supabase con políticas de lectura pública y escritura/borrado `service_role`.
  - [x] Migrar y sembrar 100% de datos desde `data/content.json` hacia Supabase (4 paquetes, 0 ofertas, 10 blog posts, settings de agencia y chatbot).
  - [x] Migrar y respaldar imágenes existentes en el bucket `relax-media`.
  - [x] Auditoría de seguridad con `get_advisors`: 0 alertas de seguridad.
- [x] **Backend & Repositorio de Contenido (`backend-ia-engineer`)**:
  - [x] Implementar `api/ContentRepository.php` con patrón *Write-Through Caching*, bloqueo atómico con `flock`, `fsync()` y *Graceful Degradation*.
  - [x] Implementar soporte de almacenamiento dual (disco local + bucket `relax-media`) en `SupabaseClient.php` (soporte de borrado con JSON payload `{"prefixes": [...]}`).
  - [x] Crear endpoint `api/content.php` (`/api/content`) que sirve el JSON hidratado desde caché / Supabase.
  - [x] Conectar `includes/header.php` y `api/GeminiClient.php` con `ContentRepository`.
- [x] **Backoffice y Gestión de Ciclo de Vida de Medios (`admin-sync-engineer`)**:
  - [x] Refactorizar `admin/index.php` para realizar CRUD mediante `ContentRepository`.
  - [x] Implementar borrado y reemplazo sincronizado de imágenes: al modificar o eliminar un paquete, oferta o post, se elimina la imagen anterior del disco local y del bucket `relax-media`.
  - [x] Soporte para imágenes inline de artículos de blog (`images/blog-content/`).
  - [x] Agregar botón de descarga de backup (`?action=export_backup`) y reconstrucción manual de caché (`?action=rebuild_cache`).
  - [x] Mantener generación estática de páginas de blog (`blog/*.html`) y actualización de `sitemap.xml` para SEO según requerimiento explícito del usuario.
- [x] **Frontend Resiliente y Desacoplamiento Git (`frontend-widget-engineer` + Orquestador)**:
  - [x] Actualizar `js/main.js`, `blog/index.php` y `blog/post.php` con fallback inteligente (`data/content.json` -> `api/content` -> `api/content.php`).
  - [x] Agregar estado vacío elegante para ofertas flash cuando no haya items cargados.
  - [x] Configurar `.gitignore` para ignorar `data/content.json`, `.lock`, `.tmp.*` y carpetas de uploads, manteniendo `.gitkeep`.
  - [x] Crear `data/content.example.json` como plantilla base para el repositorio.
  - [x] Desvincular `data/content.json` del índice de Git sin borrarlo del disco (`git rm --cached`).
  - [x] Commit y Push a `origin/main` (`5bd2ce3`).
  - [x] Verificación en vivo en producción Hostinger (`https://relaxtours.com.ar/api/content` → HTTP 200 OK y admin funcional).

## Fase 7: Pipeline RAG Especializado para Asistencia al Viajero Coris & Evals (Multi-Agente)
- [x] **Ingesta Vectorial de Coris (`backend-ia-engineer`)**:
  - [x] Crear script `scripts/ingest_coris_knowledge.php` para normalizar los 10 casos canónicos del dataset reemplazando `(boton de whatsapp)`, `(formulario)` y `(telefono)` por el llamado contextual al widget.
  - [x] Generar embeddings de 768 dimensiones con `gemini-embedding-001` y almacenarlos en `public.knowledge_base` en Supabase con `category = 'coris'`.
- [x] **Alineación de Prompting y Calificación de Leads (`backend-ia-engineer`)**:
  - [x] Actualizar `api/GeminiClient.php` para detectar `category === 'coris'` e instruir el modelo a:
    - Destacar que Coris opera con tope médico por evento.
    - Realizar siempre la pregunta de seguimiento (*follow-up*) para relevar destino, fechas, edades y duración.
    - Citar la URL oficial `https://www.relaxtours.com.ar/asistencia` y guiar a las pestañas de WhatsApp, Correo y Teléfono del widget.
- [x] **Verificación Vectorial y Segmentación (`supabase-architect`)**:
  - [x] Verificar los 10 registros en `public.knowledge_base` con índice HNSW y similitud coseno.
  - [x] Comprobar aislamiento: preguntas turísticas no activan Coris, preguntas de salud/seguros activan Coris con similitud $> 0.65$.
- [x] **Sincronización en Backoffice (`admin-sync-engineer`)**:
  - [x] Conectar la ingesta de Coris con la función de sincronización global en `admin/index.php`.
- [x] **Golden Benchmark Automatizado & QA (`frontend-widget-engineer` + Orquestador)**:
  - [x] Crear y ejecutar `scripts/eval_coris_rag.php` testeando los 10 prompts del dataset.
  - [x] Verificar métricas de acierto (10/10 planes correctos, follow-ups presentes, URLs válidas).
  - [x] Proteger `scripts/` en `.htaccess`.
  - [x] Git commit, push a GitHub y verificación final.
