# Dev Log — Widget Omnicanal + Chatbot IA (Relax Tours)

> Registro cronológico de auditorías, decisiones técnicas, migraciones y cambios en el código realizados durante el proyecto.

---

## [2026-10-30 00:08 -03:00] — Inicio, Auditoría del Repositorio y Verificación de Supabase MCP
**Agente**: Orquestador Principal

- Se verificó la conexión con `supabase_mcp` sobre el proyecto `relaxtours-ai` (`phzmkeagpobdqqtvfqvv`, región `sa-east-1`, PostgreSQL `17.11.0`, `ACTIVE_HEALTHY`).
- Se auditó el código de `includes/footer.php`, `includes/header.php`, `send-mail.php`, `admin/index.php`, `data/content.json`, `.htaccess`, `js/main.js`, `css/style.css` y `blog/*.html`.
- Se crearon los artefactos de planificación y seguimiento.

---

## [2026-10-30 00:35 -03:00] — Aprobación del Plan y Ajustes de Alcance
**Agente**: Orquestador Principal

### Decisiones confirmadas por el usuario:
1. **Supabase Secret API Keys (`sb_secret_...`)**: Se confirma que las nuevas *Secret API keys* recomendadas por Supabase son más seguras que el antiguo JWT `service_role` y sirven exactamente igual para saltar RLS desde el backend PHP. `api/SupabaseClient.php` dará soporte nativo a ambas (`sb_secret_...` y `eyJ...`).
2. **Carpeta de seguimiento**: Aprobada en `docs/ai-widget/` (`implementation_plan.md`, `tasks.md`, `dev_log.md`, `thoughts.md`).
3. **Station Travel y Tucano Tours**: No se agregará código especulativo (`TucanoProvider.php` descartado por ahora) ni iframes prematuros, ya que Station Travel irá en un futuro subdominio `paquetes.relaxtours.com.ar` y Tucano aún no tiene estructura definida. Se documentan las ideas arquitectónicas en `thoughts.md` y el chatbot actual pre-cotizará y derivará a un asesor humano cuando le pregunten por aéreos o destinos no cargados.
4. **Inspección de `admin/.env`**: Se verificó que el usuario ya agregó `GEMINI_API_KEY`. Nota técnica registrada: la clave actual comienza con `AQ.Ab8RN...` (formato de token OAuth/Vertex/AQ); dejaremos preparada una prueba de diagnóstico en el panel Admin para verificar la respuesta de Google AI Studio (que usualmente utiliza claves `AIzaSy...` en `generativelanguage.googleapis.com`).

---

## [2026-10-30 00:39 -03:00] — Fase 1 Completada: Base de Datos y Vectores en Supabase
**Agentes**: `supabase-architect` (`d506d2c8-a62c-464a-80e4-fc17b601e408`) + Orquestador Principal (MCP `supabase_mcp`)

- `supabase-architect` preparó el DDL consolidado y detectó que el token OAuth de `supabase_mcp` está ligado a la sesión del Orquestador Principal.
- Se ejecutó el script SQL en `phzmkeagpobdqqtvfqvv` (`relaxtours-ai`):
  - Extensión `vector` habilitada en el esquema `extensions`.
  - Tabla `public.leads` creada con `rls_enabled = true` e índices `idx_leads_created_at`, `idx_leads_email`, `idx_leads_phone`.
  - Tabla `public.knowledge_base` creada con `rls_enabled = true`, `embedding extensions.vector(768)`, restricción `unique` en `external_id`, índice HNSW `idx_kb_embedding_hnsw` (`vector_cosine_ops`) e índices de vigencia y categoría.
  - Tabla `public.chat_messages` creada con `rls_enabled = true`, FK a `public.leads(id) on delete cascade` e índice compuesto `(lead_id, created_at)`.
  - Políticas RLS explícitas creadas para `service_role` (`anon` y `authenticated` sin acceso público).
  - Función RPC `public.match_knowledge` creada con `security invoker`, `set search_path = public, extensions` y ejecución revocada para `public, anon, authenticated`.
- **Verificación MCP**:
  - `list_tables`: Confirmadas las 3 tablas con RLS activo.
  - Prueba de ejecución de `public.match_knowledge`: OK (`[]`).
  - `get_advisors` (`type: "security"`): **`{"lints": []}` (0 vulnerabilidades o alertas de seguridad)**.

---

## [2026-10-30 00:42 -03:00] — Fase 2 Completada: Backend PHP Seguro, Clientes Gemini/Supabase y Correo
**Agente**: `backend-ia-engineer` (`13daa5c0-93b3-4ba2-bcac-993a94212b56`)

- Creado [`includes/env-loader.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/includes/env-loader.php) con `loadRelaxEnv()` y `env()`.
- Actualizados [`admin/.env.example`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/admin/.env.example) y [`admin/.env`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/admin/.env) preservando credenciales existentes y preconfigurando `SUPABASE_URL="https://phzmkeagpobdqqtvfqvv.supabase.co"` y modelos de Gemini Free Tier.
- Actualizado [`.htaccess`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/.htaccess) bloqueando acceso HTTP directo a `^docs/` y a clases internas (`SupabaseClient.php`, `GeminiClient.php`, `env-loader.php`, `.env`).
- Creado [`api/SupabaseClient.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/SupabaseClient.php) con soporte dual para Secret API Keys (`sb_secret_...`) y legacy `service_role`.
- Creado [`api/GeminiClient.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/GeminiClient.php) con generación de embeddings de 768 dimensiones, System Instruction blindado de Relax Tours, redacción automática de PII (`redactPII()`), fallback local a `data/content.json` si Supabase está vacío, y fallback automático de modelos (`gemini-2.5-flash` -> `gemini-2.0-flash` -> `gemini-2.0-flash-lite`) ante error 429/503.
- Creados endpoints [`api/lead.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/lead.php) (con normalizador E.164 argentino y firma HMAC de `lead_token`) y [`api/chat.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/chat.php) (con rate limiting y flujo RAG).
- Adaptado [`send-mail.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/send-mail.php) con soporte para `FormData` y `application/json` y respuesta compatible (`ok: true` + `status: 'success'`).
- Validados todos los archivos con `php -l` sin errores de sintaxis.

---

## [2026-10-30 00:50 -03:00] — Fase 3 Completada: Extensión del Backoffice (`admin/index.php`) y Sincronización IA
**Agente**: `admin-sync-engineer` (`2ab71418-1708-476e-8098-d8dc982755b8`)

- Integrados `includes/env-loader.php`, `api/SupabaseClient.php` y `api/GeminiClient.php` en [`admin/index.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/admin/index.php).
- Añadidos campos `vigencia_hasta` (`date`) e `info_bot` (`textarea`) en los modales y guardado de Paquetes Destacados y Ofertas Flash.
- Implementadas las funciones `formatPaqueteMarkdown()`, `formatOfertaMarkdown()`, `formatBlogMarkdown()`, `syncSingleItemToSupabase()` y `deleteSingleItemFromSupabase()`.
- Creada la nueva pestaña **🤖 Chatbot IA** (`?tab=chatbot`) en [`admin/index.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/admin/index.php#L2563-L2752) con tarjetas de diagnóstico de conexión (Gemini y Supabase), botón **"🔄 Sincronizar Todo con Supabase (Generar Vectores IA)"**, editor de conocimiento general (`chatbot_general_info` inicializado en [`data/content.json`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/data/content.json)) y tabla de últimos Leads capturados con botón directo a WhatsApp.
- Eliminado el cierre duplicado `</body></html>` en `generateBlogPage()`.
- Verificado con `php -l admin/index.php` y validación JSON de `data/content.json`.

---

## [2026-10-30 00:52 -03:00] — Fase 4 Completada: Frontend Omnicanal (`css/relax-widget.css` + `js/relax-widget.js`)
**Agente**: `frontend-widget-engineer` (`f2c13070-b522-4ddc-b476-a4edeca3a606`)

- Creado [`css/relax-widget.css`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/css/relax-widget.css) con botón flotante `.relax-omnifab`, tooltip automático a los 4 segundos y ventana omnicanal responsive (`390px x 580px` en escritorio, adaptable en móvil).
- Creado [`js/relax-widget.js`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/js/relax-widget.js) con las 4 vistas interactivas, detección dinámica de `basePath` (`./` vs `../` en `/blog/`), normalizador E.164 argentino (`+549...`), autocompletado de formulario de correo y evaluación de horario comercial en `America/Argentina/Buenos_Aires` (Lun-Vie 10:00–13:00 y 14:00–19:00 hs, Sáb 10:00–13:00 hs).
- Actualizado [`includes/footer.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/includes/footer.php) y los 10 artículos estáticos en `blog/*.html` reemplazando `.whatsapp-float` por `relax-widget.css` y `relax-widget.js`.
- Ajustado [`js/main.js`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/js/main.js) para usar `basePath + 'send-mail.php'`.

---

## [2026-10-30 00:55 -03:00] — Fase 5: Verificación de Modelos Gemini en Vivo (Octubre 2026)
**Agente**: Orquestador Principal

- Se probó la `GEMINI_API_KEY` (`AQ.Ab8RN...`) cargada por el usuario en `admin/.env` contra `https://generativelanguage.googleapis.com/v1beta/models`.
- **Hallazgo clave**:
  1. La clave `AQ.Ab8RN...` del usuario funciona perfectamente contra la API de Google AI Studio.
  2. Al listar los modelos habilitados para cuentas nuevas en Octubre 2026, Google informó que `gemini-2.5-flash`, `gemini-2.0-flash-lite` y `text-embedding-004` fueron reemplazados por **`gemini-3.8-flash`**, **`gemini-3.5-flash-lite`** (y alias `gemini-flash-latest` / `gemini-flash-lite-latest`) y **`gemini-embedding-001`** (768 dimensiones).
  3. Se actualizaron `admin/.env`, `admin/.env.example` y `api/GeminiClient.php` para utilizar `gemini-3.5-flash-lite` (primario — 1.5s de latencia), `gemini-flash-lite-latest` (fallback) y `gemini-embedding-001` (embeddings 768d).
  4. Prueba en vivo de `GeminiClient::generateChatResponse()` preguntando por paquetes a Mendoza e Iguazú: **ÉXITO en 1510ms**, devolviendo los precios exactos de `data/content.json` ($445.453 y $477.640 ARS), hoteles incluidos y la aclaración legal obligatoria.
  5. Prueba en vivo de `GeminiClient::generateEmbedding()`: **ÉXITO**, devolviendo el vector de 768 dimensiones listo para `public.knowledge_base` en Supabase.

---

## [2026-10-30 01:40 -03:00] — Resolución de Error 405 en `/api/lead`
**Agente**: Orquestador Principal

- **Causa Raíz Diagnosticada**:
  1. En `.htaccess`, la regla 3 de limpieza de extensiones (`RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s([^.]+)\.(php|html)` -> `RewriteRule ^ %1 [L,R=301]`) interceptaba todas las solicitudes con extensión `.php` sin discriminar método HTTP ni ruta.
  2. Cuando el widget enviaba `POST /api/lead.php`, Apache devolvía un `HTTP 301 Moved Permanently` hacia `/api/lead`.
  3. Los navegadores web (Chrome, Edge, Safari, Firefox), por estándar HTTP ante un código 301, reconvierten la petición a `GET` y descartan el cuerpo (body) de la solicitud.
  4. Al llegar `GET /api/lead`, Apache lo mapeaba internamente a `api/lead.php`, donde la guarda `$_SERVER['REQUEST_METHOD'] !== 'POST'` respondía legítimamente `405 Method Not Allowed` con el mensaje `Método no permitido. Usá POST.`.
- **Acciones Correctivas Implementadas**:
  1. En [`.htaccess`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/.htaccess):
     - Se añadió `RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$ [NC]` a la regla 1 (quitar trailing slash) y a la regla 3 (quitar extensiones).
     - Se añadieron excepciones explícitas a la regla 3 para rutas internas y APIs: `RewriteCond %{REQUEST_URI} !(/api/|api/) [NC]`, `RewriteCond %{REQUEST_URI} !send-mail\.php [NC]` y `RewriteCond %{REQUEST_URI} !admin/ [NC]`.
  2. En [`js/relax-widget.js`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/js/relax-widget.js):
     - Se actualizaron los endpoints a `basePath + 'api/lead'` y `basePath + 'api/chat'`, evitando que navegadores con el 301 anterior en caché local intenten redirigirse.
  3. En [`api/lead.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/lead.php) y [`api/chat.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/chat.php):
     - Se añadió limpieza defensiva de BOM UTF-8 (`\xEF\xBB\xBF`) en la lectura del input JSON crudo.
- **Verificación**:
  - `POST` a `/api/lead` validado exitosamente (inserción en Supabase, normalización a `+549...`, firma HMAC y respuesta JSON 200).
  - `POST` a `/api/chat` validado exitosamente en vivo con respuesta RAG de Gemini y Supabase.

---

## [2026-10-30 04:00 -03:00] — Migración de Contenido a Supabase PostgreSQL + Storage con Caché Write-Through Auto-Regenerable
**Agente**: Orquestador Principal

- **Base de Datos & Storage (Supabase)**:
  - Creadas tablas `public.cms_packages`, `public.cms_offers`, `public.cms_blog_posts` y `public.cms_settings` con RLS habilitado y políticas de acceso para `service_role` y lectura pública.
  - Creado bucket público `relax-media` en Supabase Storage con políticas selectivas para lectura pública y subida/borrado seguro desde backend.
  - Se migraron (seed) el 100% de los datos: 4 paquetes, 10 artículos de blog, configuración global y chatbot context.
  - Se sincronizaron las imágenes de paquetes y blogs hacia el bucket `relax-media`.
- **Capa de Persistencia y Caché**:
  - Creado [`api/ContentRepository.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/ContentRepository.php) que implementa el patrón Write-Through con snapshot local atómico (`LOCK_EX` + archivo temporal `.tmp` renombrado) y auto-hidratación instantánea si `data/content.json` falta en disco.
  - Creado endpoint [`api/content.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/content.php) para servir y auto-hidratar la data en clientes web.
  - Actualizados [`admin/index.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/admin/index.php), [`includes/header.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/includes/header.php), [`api/GeminiClient.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/api/GeminiClient.php), [`js/main.js`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/js/main.js), [`blog/index.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/blog/index.php) y [`blog/post.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/blog/post.php).
  - Agregado en Backoffice botón para **Descargar Backup JSON** y **Restaurar Caché desde Supabase**.
  - Ciclo de vida completo de imágenes: al subir, reemplazar o eliminar un paquete/oferta/blog desde el Admin, la imagen se actualiza/elimina tanto del disco local como del bucket `relax-media` de Supabase Storage.
- **Higiene de Git**:
  - `data/content.json` y carpetas mutables de uploads ignorados en [`.gitignore`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/.gitignore).
  - `data/content.json` desindexado de Git (`git rm --cached`).
  - Creado [`data/content.example.json`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/data/content.example.json) como plantilla base rastreada.
  - Las páginas `blog/*.html` y `sitemap.xml` se mantienen generadas y versionadas para máxima velocidad e indexación en Google.

---

## [2026-10-30 04:15 -03:00] — Push a GitHub y Verificación en Vivo en Hostinger
**Agente**: Orquestador Principal

- Commit `5bd2ce3` enviado exitosamente a la rama `main` en GitHub (`origin/main`).
- Auto-despliegue de Hostinger completado satisfactoriamente.
- Pruebas HTTP directas en producción (`https://relaxtours.com.ar`):
  1. `curl.exe -i "https://relaxtours.com.ar/api/content"`: **HTTP/1.1 200 OK**, payload JSON completo servido desde PHP 8.3 en Hostinger con paquetes, blog y configuración de Supabase.
  2. `curl.exe -I "https://relaxtours.com.ar/"`: **HTTP/1.1 200 OK**, frontend operativo con widget omnicanal y carga asíncrona resiliente.
  3. `curl.exe -I "https://relaxtours.com.ar/admin/"`: **HTTP/1.1 200 OK**, backoffice operativo con botones de backup y sincronización en la nube.
- La arquitectura desacoplada está 100% activa: los despliegues de Git ya no interferirán jamás con las ediciones ni cargas de contenido en producción.
