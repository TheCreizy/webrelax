# Thoughts, Decisiones de Arquitectura & Ideas Futuras — Relax Tours IA

> Espacio donde los agentes registran razonamientos técnicos, análisis de riesgos, optimizaciones e ideas a futuro (Station Travel y Tucano Tours).

---

## 1. Supabase Secret API Keys (`sb_secret_...`) vs Legacy `service_role` JWT
- Supabase introdujo las nuevas **Secret API keys** (con prefijo `sb_secret_...`) para reemplazar la clave `service_role` tradicional (que era un JWT largo `eyJ...`).
- **¿Por qué son mejores las Secret API keys (`sb_secret_...`)?**
  - Saltan Row Level Security (RLS) igual que `service_role`, permitiendo que tu backend PHP lea y escriba en `leads`, `knowledge_base` y `chat_messages` mientras las tablas permanecen 100% cerradas al público.
  - Si algún día querés rotar la clave, podés borrar o crear una nueva `sb_secret_...` en 1 clic desde el panel de Supabase sin invalidar el secreto JWT global del proyecto.
  - Además, si alguien intenta usar una `sb_secret_...` desde un navegador (con headers `Origin` / `Referer` de navegador), el API Gateway de Supabase la bloquea por seguridad; solo acepta llamadas desde servidores backend (como tu cURL en PHP).
- **Implementación en `api/SupabaseClient.php`**:
  - Enviaremos siempre `apikey: $key`.
  - Si la clave es un JWT clásico (`strpos($key, 'eyJ') === 0`), enviaremos también `Authorization: Bearer $key`. Si es una nueva Secret API key (`strpos($key, 'sb_secret_') === 0`), basta con `apikey: $key` (y `Authorization: Bearer $key` también es aceptado por el gateway de Supabase).

## 2. Verificación en Vivo de `GEMINI_API_KEY` y Modelos Activos (Octubre 2026)
- Al probar en vivo la `GEMINI_API_KEY` (`AQ.Ab8RN...`) cargada por el usuario en `admin/.env` contra `generativelanguage.googleapis.com/v1beta/models`:
  1. Confirmamos que la clave `AQ.Ab8RN...` es 100% válida y operativa.
  2. Detectamos que en Octubre 2026 Google retiró `gemini-2.5-flash`, `gemini-2.0-flash-lite` y `text-embedding-004` para nuevas claves, reemplazándolos por **`gemini-3.5-flash-lite`**, **`gemini-flash-lite-latest`** y **`gemini-embedding-001`** (`outputDimensionality: 768`).
  3. Medimos la latencia real: `gemini-3.5-flash-lite` responde consultas RAG completas en **1.5 segundos (1510ms)** y `gemini-embedding-001` genera vectores de 768 dimensiones en menos de 1 segundo. Dejamos ambos configurados por defecto en `admin/.env` y `api/GeminiClient.php`.

## 3. Persistencia del Pre-Chat con Token Firmado HMAC (Sin errores 403 en Hostinger)
- En Hostinger, `$_SESSION` expira a los ~24 minutos de inactividad, pero `localStorage` en el navegador del usuario persiste por meses.
- Para evitar que un usuario que ya completó sus datos ayer reciba un error `403` hoy en `api/chat.php`, `api/lead.php` emitirá un `lead_token` firmado con `hash_hmac('sha256', $leadId, $secret)`.
- Cuando `$_SESSION['relax_lead_id']` haya expirado, `api/chat.php` validará criptográficamente el `lead_token` y restaurará la sesión sin volver a pedirle Nombre, Email y Teléfono al pasajero.

## 4. Ideas y Arquitectura Futura: Station Travel y Tucano Tours (Etapa 2)
*Documentado como referencia conceptual para cuando estén listos el subdominio y los accesos de mayoristas (sin agregar peso al código actual):*

### A) Subdominio `paquetes.relaxtours.com.ar` (Station Travel Marca Blanca)
- Cuando habilites el subdominio `paquetes.relaxtours.com.ar` con el motor/iframe de Station Travel:
  1. **Cross-Domain Lead Sharing (Opcional)**: Como tanto `www.relaxtours.com.ar` como `paquetes.relaxtours.com.ar` comparten el dominio raíz `.relaxtours.com.ar`, si en ese subdominio colocás un encabezado/pie propio con `relax-widget.js`, podemos guardar el estado del pre-chat en una cookie de dominio `.relaxtours.com.ar` además de `localStorage`. Así, el pasajero que abrió el chat en la home seguirá viendo su misma conversación cuando salte al subdominio `paquetes.relaxtours.com.ar`.
  2. **Sincronización o Ingesta de Destacados**: Cuando sepas cómo Station entrega las promos (si por flyers PDF/WhatsApp o link directo), en la pestaña `🤖 Chatbot IA` ya tenés el bloque de texto libre de conocimiento general donde podés pegar cualquier promo puntual, y en el futuro podemos sumar un botón para redirigir directo a `https://paquetes.relaxtours.com.ar` con filtros en la URL si el motor de Station soporta query params (ej. `?destino=brasil`).

### B) Aéreos con Tucano Tours
- **Hoy**: Si un usuario pregunta por pasajes aéreos en el chatbot, el System Instruction de Gemini está entrenado para no inventar tarifas aéreas, preguntarle *Origen, Destino, Fechas aproximadas, Cantidad de pasajeros y si incluye equipaje*, y ofrecerle el botón directo a WhatsApp o Correo con esos datos para que el equipo de Relax Tours lo cotice en el sistema de Tucano/GDS.
- **Futuro (cuando tengan API o tarifario de cupos)**:
  - Si son **cupos aéreos fijos** (ej. cupos a Brasil o Bariloche en temporada alta), se cargan como texto en la pestaña `🤖 Chatbot IA` del panel Admin y se vectorizan en Supabase en 1 clic.
  - Si más adelante contratan **Tucano API Vuelos**, se podrá conectar como una *Tool (Function Calling)* en `api/GeminiClient.php` para consultar disponibilidad en tiempo real.
