# LLM Context Handoff & Architecture Manifest — Relax Tours Web & IA

> **Propósito de este archivo**: Este documento es el *Single Source of Truth* arquitectónico para cualquier sesión o modelo de IA que asista en el desarrollo, mantenimiento o escalabilidad de la web de **Relax Tours** (`relaxtours.com.ar`). Permite a una LLM adquirir contexto operativo completo en una sola lectura sin tener que recorrer todo el repositorio.

---

## 1. Visión General del Negocio y Stack Tecnológico

* **Empresa**: Relax Tours (Empresa de Viajes y Turismo — EVT Legajo 15.548, 23 años de trayectoria, Villa Devoto, CABA, Argentina).
* **Canales Oficiales**:
  * WhatsApp: `+54 9 11 3859-3924`
  * Teléfono fijo: `11 7079-8079`
  * Email: `info@relaxtours.com.ar`
  * Dirección: Av. Francisco Beiró 4219, Villa Devoto, CABA.
  * Horarios de atención: Lunes a Viernes 10:00–13:00 y 14:00–19:00 hs, Sábados 10:00–13:00 hs (Horario de Argentina `America/Argentina/Buenos_Aires`).
* **Hosting**: Hostinger Business Web Hosting (Apache con `mod_rewrite`, PHP 8.3/8.5, despliegue continuo automático desde GitHub rama `main`).
* **Bases de Datos & Vectores**: Supabase PostgreSQL (`relaxtours-ai`, ID `phzmkeagpobdqqtvfqvv`, región `sa-east-1` São Paulo).
* **Almacenamiento de Medios (Cloud)**: Bucket público `relax-media` en Supabase Storage.
* **Modelos de IA**: Google Gemini API (Free Tier vía AI Studio):
  * Chat: `gemini-3.5-flash-lite` (primario, latencia ~1.5s) con fallback a `gemini-flash-lite-latest` y `gemini-3.5-flash`.
  * Embeddings: `gemini-embedding-001` (vector de 768 dimensiones).

---

## 2. Invariantes Arquitectónicos (Reglas Inquebrantables)

Cualquier cambio de código debe respetar estrictamente estas directivas:

1. **Desacoplamiento Git vs. Estado Mutable:**
   * `data/content.json` está en `.gitignore` y **NUNCA** debe commitearse ni rastrearse en Git (`git rm --cached`). En Git solo vive la plantilla base [`data/content.example.json`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/data/content.example.json).
   * Las carpetas de subida de imágenes (`images/paquetes-images/`, `images/ofertas-images/`, `images/blog-images/`, `images/blog-content/`) están ignoradas en Git (manteniendo solo sus archivos `.gitkeep`).
2. **SEO y Generación Estática de Blog:**
   * Los artículos de blog estáticos en `blog/*.html` y el archivo `sitemap.xml` **DEBEN PERMANECER GENERADOS Y RASTREADOS EN GIT**. No se transforman a enrutamiento puramente dinámico porque Google Search los tiene indexados y generan tráfico orgánico.
3. **Ciclo de Vida Dual de Imágenes (Storage Sync):**
   * Cuando se sube, reemplaza o elimina una imagen desde el panel Admin (`/admin/`), la acción **debe impactar en paralelo en el disco local de Hostinger y en el bucket `relax-media` de Supabase Storage**. Si se reemplaza una foto, la foto anterior se destruye en ambos lados.
4. **Patrón Write-Through Caching:**
   * La fuente de la verdad (*Single Source of Truth*) es la base de datos Supabase PostgreSQL.
   * Hostinger almacena un snapshot local atómico en `data/content.json` para servir lecturas ultrarrápidas (< 5ms) sin consumir cuota de base de datos.
   * Toda escritura en el Admin actualiza primero Supabase y luego refresca el snapshot local de forma atómica (`flock` + `.tmp` + `fsync` + `rename`).
   * **Graceful Degradation:** Si Supabase sufre timeout o caída, la web sigue funcionando leyendo el snapshot local sin interrumpir el servicio a los visitantes.
5. **Autenticación Supabase con Secret API Key:**
   * El backend PHP usa la nueva clave secreta `SUPABASE_SECRET_KEY` (formato `sb_secret_...`) o clave legacy `service_role` en `admin/.env`. Nunca se usan claves públicas `anon` para tareas de escritura.

---

## 3. Mapa del Repositorio y Rutas Críticas

```
├── .htaccess                     # Configuración Apache: SSL forzado, bloqueo de /docs y /scripts, mapeo sin extensiones
├── index.php                     # Landing page principal (Hero, Paquetes, Ofertas, Asistencia Coris, Contacto)
├── paquetes.php                  # Catálogo completo de paquetes
├── send-mail.php                 # Procesador AJAX/JSON de envíos de correo institucional (PHPMailer / mail nativo)
├── sitemap.xml                   # Mapa del sitio optimizado para Googlebot
│
├── admin/
│   ├── index.php                 # Backoffice completo (CRUD Paquetes, Ofertas, Blog, Configuración, Chatbot IA, Backup)
│   ├── .env                      # Variables de entorno secretas (IGNORADO EN GIT)
│   ├── .env.example              # Plantilla de variables de entorno
│   └── css/ & js/                # Assets del panel de administración
│
├── api/
│   ├── ContentRepository.php     # Capa unificada de datos: Write-Through caching, CRUD CMS, sync con Supabase
│   ├── SupabaseClient.php        # Cliente REST para PostgREST, RPC (match_knowledge) y Storage (relax-media)
│   ├── GeminiClient.php          # Cliente Gemini: embeddings 768d, chat RAG, System Prompt, aislamiento de PII
│   ├── content.php               # Endpoint público GET /api/content (JSON consolidado con HTTP Cache 60s)
│   ├── lead.php                  # Endpoint público POST /api/lead (captura de prospectos con validación E.164 y token HMAC)
│   └── chat.php                  # Endpoint público POST /api/chat (RAG completo, rate limiting y sesiones)
│
├── blog/
│   ├── index.php                 # Listado público del blog
│   ├── post.php                  # Renderizador dinámico de artículos
│   └── *.html                    # 10 artículos estáticos prerenderizados para indexación SEO inmediata en Google
│
├── includes/
│   ├── env-loader.php            # Cargador seguro de variables desde admin/.env hacia getenv() y $_ENV
│   ├── header.php                # Cabecera global, navbar responsive y metadata
│   └── footer.php                # Pie de página global con inyección del Widget Omnicanal
│
├── css/
│   ├── style.css                 # Estilos globales de Relax Tours (Paleta: Naranja #FF6A00 / #F97316, Negro, Blanco)
│   └── relax-widget.css          # Estilos del Widget Omnicanal (.relax-omnifab, modal 390x580px, responsive móvil)
│
├── js/
│   ├── main.js                   # Lógica frontend: renderizado de paquetes, ofertas y formularios
│   └── relax-widget.js           # Controlador del Widget Omnicanal (4 vistas: Chat IA, WhatsApp, Correo, Teléfono)
│
├── scripts/                      # Scripts CLI utilitarios y de prueba (Protegidos de acceso HTTP vía .htaccess)
│   ├── ingest_coris_knowledge.php # Ingesta y vectorización de los 10 casos canónicos de Coris en Supabase (768d)
│   └── eval_coris_rag.php        # Suite de evaluación automatizada (Evals) con 26 aserciones de negocio
│
└── data/
    ├── content.example.json      # Plantilla estática base versionada en Git
    └── content.json              # Snapshot local activo de contenido (IGNORADO EN GIT, creado en runtime)
```

---

## 4. Base de Datos Supabase (PostgreSQL & pgvector)

Proyecto: `relaxtours-ai` (`phzmkeagpobdqqtvfqvv`).

### A. Tablas del CMS (Contenido Dinámico)
* **`public.cms_packages`**:
  * `id` (text, PK): ID único alfanumérico (ej: `69936ab90d225`).
  * `nombre` (text), `destino` (text), `duracion` (text), `precio` (numeric), `moneda` (text: `ARS`/`USD`).
  * `incluye` (jsonb array de strings), `imagen` (text: ruta relativa), `popular` (boolean), `activo` (boolean).
  * `orden` (integer), `vigencia_hasta` (date nullable), `info_bot` (text), `operador` (text), `comentarios` (text).
* **`public.cms_offers`**:
  * `id` (text, PK), `titulo` (text), `descripcion` (text), `badge` (text), `tiempo` (text).
  * `imagen` (text), `activo` (boolean), `orden` (integer), `vigencia_hasta` (date nullable), `info_bot` (text).
* **`public.cms_blog_posts`**:
  * `id` (text, PK), `titulo` (text), `extracto` (text), `contenido` (text HTML), `imagen` (text).
  * `fecha` (text), `slug` (text unique), `seo_titulo` (text), `seo_description` (text), `seo_keywords` (text), `activo` (boolean).
* **`public.cms_settings`**:
  * `key` (text, PK): `config` (objeto JSON con WhatsApp, emails, redes, legajo) y `chatbot_general_info` (Markdown institucional).
  * `value` (jsonb).

### B. Tablas del Chatbot IA & RAG
* **`public.leads`**:
  * `id` (uuid, PK, default `gen_random_uuid()`).
  * `nombre` (text), `email` (text), `telefono_raw` (text), `phone_e164` (text: formato normalizado `+549...`).
  * `origen` (text: `widget_prechat`), `ip` (text), `user_agent` (text), `metadata` (jsonb), `created_at` (timestamptz).
* **`public.knowledge_base`**:
  * `id` (uuid, PK), `external_id` (text unique: ej: `paquete_69936...`, `blog_69...`, `agency_general_coris`, `coris_schengen_europa`).
  * `category` (text: `paquete`, `oferta`, `blog`, `agencia`, `coris`).
  * `title` (text), `content_markdown` (text), `valid_until` (date nullable), `is_active` (boolean).
  * `metadata` (jsonb), `updated_at` (timestamptz).
  * `embedding` (`extensions.vector(768)`): Vector HNSW indexado con distancia coseno (`vector_cosine_ops`).
* **`public.chat_messages`**:
  * `id` (uuid, PK), `lead_id` (uuid, FK a `public.leads(id)` con `ON DELETE CASCADE`).
  * `role` (text: `'user'` o `'model'`), `message` (text), `created_at` (timestamptz).
  * *Propósito:* Solo auditoría y soporte comercial humano. **La IA NO aprende de esta tabla**.

### C. Función RPC para Búsqueda Semántica
```sql
public.match_knowledge(
    query_embedding vector(768),
    match_threshold double precision,
    match_count integer
) RETURNS TABLE (
    id uuid,
    external_id text,
    category text,
    title text,
    content_markdown text,
    valid_until date,
    metadata jsonb,
    similarity double precision
);
```
*Filtro automático de vigencia:* Descarta elementos con `valid_until < CURRENT_DATE` o `is_active = false`.

### D. Supabase Storage Bucket
* **Bucket ID**: `relax-media` (público para lectura).
* **URL Base de CDN**: `https://phzmkeagpobdqqtvfqvv.supabase.co/storage/v1/object/public/relax-media/{path}`
* **Políticas RLS**: Lectura abierta para `anon` y `authenticated`; subida, reemplazo y borrado restringidos a `service_role` (backend PHP).

---

## 5. El Flujo RAG y las Reglas Conversacionales de la IA

### A. Pipeline de Consulta
1. El usuario envía una pregunta en el chat a `POST /api/chat`.
2. `api/chat.php` valida el `lead_token` firmado con HMAC-SHA256 (`api/lead.php`) y aplica Rate Limiting (máximo 12 preguntas por sesión / 35 por día).
3. `GeminiClient::generateEmbedding($userMessage, 'RETRIEVAL_QUERY')` genera el vector de 768 floats.
4. `SupabaseClient::matchKnowledge($embedding, threshold: 0.35, count: 4)` recupera los fragmentos más relevantes de `public.knowledge_base`.
5. Si no hay fragmentos en Supabase (ej: sin conexión), `GeminiClient` aplica un **fallback local automático** leyendo paquetes y texto de `ContentRepository`.
6. Se ensambla el prompt con `<contexto_verificado>` y se invoca Gemini con el System Instruction blindado.

### B. Regla Conversacional en 3 Etapas para Asistencia al Viajero Coris
Para evitar saturar al cliente con enlaces repetidos, rige la siguiente máquina de estados conversacional:

| Etapa | Disparador | Estructura de la Respuesta | ¿Lleva Link? | ¿Lleva Canales (WhatsApp/Mail/Tel)? |
| :--- | :--- | :--- | :---: | :---: |
| **1. Primer Mensaje** | Primera consulta sobre Coris/asistencia médica | Asesoría del plan + Tope por evento + Link online + **Follow-up** de fechas/edades | **SÍ** (`/asistencia`) | **NO** (No abrumar) |
| **2. Mensajes Intermedios** | El cliente repregunta o aclara dudas y aún faltan datos | Asesoría directa + **Nueva repregunta puntual** | **NO** | **NO** |
| **3. Cierre Final** | El cliente dio todos los datos o pide emitir/pagar/contactar | Confirmación final + Link online + **Llamado completo a los canales** | **SÍ** (`/asistencia`) | **SÍ** (WhatsApp, Mail, Tel) |

### C. Regla de Oro Anti-Alucinaciones
* La IA **SOLO** informa precios, hoteles o condiciones que figuren textualmente dentro de `<contexto_verificado>`.
* Si consultan por aéreos o destinos no publicados, no inventa tarifas: explica amablemente que los asesores cotizan vuelos y paquetes a medida, e invita a dejar destino, fechas y pasajeros tocando la pestaña de WhatsApp o Correo.

---

## 6. Frontend: El Widget Omnicanal

Inyectado globalmente en [`includes/footer.php`](file:///e:/Herny/2025/Trabajo/Relax%20Tours/New%20Web/Actual/includes/footer.php) y en todos los artículos `blog/*.html`.

* **Botón Flotante (`.relax-omnifab`)**: Con tooltip automático persuasivo a los 4 segundos.
* **4 Vistas Interactivas**:
  1. **Vista 1 (Chat con IA)**:
     * Pre-chat obligatorio: Nombre, Email y Teléfono.
     * Normalizador telefónico argentino estricto: convierte variantes locales (`011 15...`, `113859...`, `15...`) a formato internacional E.164 (`+54911...`).
     * Guarda el token firmado en `localStorage` (`relax_lead_token`) para no volver a pedir datos en recargas.
     * Markdown parser seguro integrado con renderizado de listas, negritas y enlaces limpios.
  2. **Vista 2 (WhatsApp)**:
     * Abre WhatsApp Web en escritorio o app nativa en móvil.
     * Si el cliente ya completó el pre-chat, saluda automáticamente con su nombre: *"Hola Relax Tours, mi nombre es [Nombre]..."*.
  3. **Vista 3 (Correo Electrónico)**:
     * Formulario integrado conectado a `send-mail.php` mediante `fetch` (FormData / JSON).
     * Autocompleta nombre y correo si ya fueron ingresados en el pre-chat.
  4. **Vista 4 (Teléfono)**:
     * Detección horaria exacta en `America/Argentina/Buenos_Aires`:
       * *Abierto:* Lun-Vie 10:00–13:00 y 14:00–19:00 hs, Sáb 10:00–13:00 hs.
     * En móvil dispara `tel:+541170798079`. En PC muestra un modal informativo con los horarios de Villa Devoto.

---

## 7. Backoffice Admin (`/admin/index.php`)

* **Acciones Clave**:
  * `?action=export_backup`: Descarga backup completo en JSON (`relax_tours_backup_YYYY-MM-DD_HH-mm-ss.json`).
  * `?action=rebuild_cache`: Relee Supabase PostgreSQL y regenera `data/content.json` en disco.
  * Solapa `🤖 Chatbot IA` (`?tab=chatbot`):
    * Diagnóstico de conexión en tiempo real con Gemini y Supabase.
    * Editor Markdown del Conocimiento General de la Agencia (`public.cms_settings` -> `chatbot_general_info`).
    * Botón **"🔄 Sincronizar Todo con Supabase (Generar Vectores IA)"**: re-vectoriza automáticamente paquetes, ofertas, blogs y los 10 casos canónicos de Coris.
    * Visor de prospectos capturados en el pre-chat con botón de WhatsApp directo.

---

## 8. Runbook de Diagnóstico Rápido para la LLM

Si iniciás una nueva sesión y querés validar el estado de todo el sistema en 10 segundos, ejecutá estos 3 comandos en PowerShell:

```powershell
# 1. Comprobar que no haya cambios sin commitear en Git y que data/content.json siga ignorado
git status

# 2. Correr la suite de evaluación automatizada de Coris RAG (Evals)
php scripts/eval_coris_rag.php --dry-run

# 3. Comprobar que el endpoint público sirva JSON con paquetes y settings desde Supabase
curl.exe -s "https://relaxtours.com.ar/api/content" | Select-String "paquetes"
```

Si los 3 comandos responden exitosamente (`working tree clean`, `10/10 PASS` y JSON con paquetes), el entorno está 100% operativo y listo para cualquier nueva tarea.
