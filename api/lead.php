<?php
/**
 * Relax Tours - Endpoint de Captura y Validación de Leads del Chatbot (api/lead.php)
 * Valida nombre, email y teléfono E.164, persiste en Supabase y emite lead_token firmado con HMAC.
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/SupabaseClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'      => false,
        'message' => 'Método no permitido. Usá POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normaliza un número telefónico al formato internacional E.164 (priorizando numeración móvil de Argentina +549...).
 */
function normalizePhoneE164(string $rawPhone): ?string
{
    $rawPhone = trim($rawPhone);
    if ($rawPhone === '') {
        return null;
    }

    $hasPlus = strpos($rawPhone, '+') === 0;
    $digits = preg_replace('/\D+/', '', $rawPhone);

    if ($digits === '' || strlen($digits) < 8) {
        return null;
    }

    // Rechazar números con 7 o más dígitos idénticos consecutivos (ej. 11111111)
    if (preg_match('/(\d)\1{6,}/', $digits)) {
        return null;
    }

    // Si tiene '+' explícito y NO empieza con 54 (ej. +598, +34, +1), validar longitud internacional E.164
    if ($hasPlus && strpos($digits, '54') !== 0) {
        if (strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+' . $digits;
        }
        return null;
    }

    // Normalización para números de Argentina (+549 + 10 dígitos nacionales sin 0 ni 15)
    if (strpos($digits, '549') === 0) {
        $national = substr($digits, 3);
    } elseif (strpos($digits, '54') === 0) {
        $national = substr($digits, 2);
    } else {
        $national = $digits;
    }

    // Quitar el 0 inicial del código de área (ej. 011 -> 11, 0351 -> 351)
    $national = ltrim($national, '0');

    // Si escribió solo "15" + 8 dígitos (típico de AMBA), asumir área 11
    if (strlen($national) === 10 && strpos($national, '15') === 0) {
        $national = '11' . substr($national, 2);
    }

    // Si tiene 11 o 12 dígitos por incluir el prefijo móvil local "15" luego del código de área (2 a 4 dígitos)
    if (strlen($national) === 12 && preg_match('/^(\d{2,4})15(\d{6,8})$/', $national, $matches)) {
        if (strlen($matches[1]) + strlen($matches[2]) === 10) {
            $national = $matches[1] . $matches[2];
        }
    } elseif (strlen($national) === 11 && preg_match('/^(\d{2,4})15(\d{5,7})$/', $national, $matches)) {
        $national = $matches[1] . $matches[2];
    }

    // Si quedó exactamente de 10 dígitos nacionales argentinos, formatear como +549XXXXXXXXXX
    if (strlen($national) === 10) {
        $e164Digits = '549' . $national;
    } else {
        $e164Digits = (strpos($digits, '54') === 0) ? $digits : ('549' . $national);
    }

    $len = strlen($e164Digits);
    if ($len < 10 || $len > 15) {
        return null;
    }

    return '+' . $e164Digits;
}

/**
 * Genera un UUID v4 local como respaldo cuando Supabase no está configurado o sin conexión.
 */
function generateUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Leer body JSON o fallback a $_POST
$rawBody = file_get_contents('php://input');
if ($rawBody !== false) {
    // Quitar BOM UTF-8 (\xEF\xBB\xBF) si está presente
    $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody);
}
$input = json_decode($rawBody ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$fullName = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($input['full_name'] ?? $input['name'] ?? ''))));
$email    = strtolower(trim((string) ($input['email'] ?? '')));
$rawPhone = trim((string) ($input['phone'] ?? ''));

// 1. Validar Nombre y Apellido (mínimo 2 palabras reales, letras/tildes/ñ, entre 4 y 100 caracteres)
$nameLen = mb_strlen($fullName, 'UTF-8');
$words = array_values(array_filter(explode(' ', $fullName), function ($w) {
    return mb_strlen($w, 'UTF-8') >= 2;
}));

if (
    $nameLen < 4 ||
    $nameLen > 100 ||
    count($words) < 2 ||
    !preg_match('/^[\p{L}\s\'\.\-]+$/u', $fullName)
) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'field'   => 'full_name',
        'message' => 'Por favor ingresá tu nombre y apellido reales (mínimo 2 palabras).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validar Email
if ($email === '' || strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'field'   => 'email',
        'message' => 'Por favor ingresá un correo electrónico válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Validar y normalizar Teléfono a E.164
$phoneE164 = normalizePhoneE164($rawPhone);
if ($phoneE164 === null) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'field'   => 'phone',
        'message' => 'Por favor ingresá un teléfono válido con código de área (ej. 11 3859-3924).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Insertar Lead en Supabase (o fallback UUID v4 local si aún no se configuró SUPABASE_SECRET_KEY)
$supabase = new SupabaseClient();
$leadId = null;
$persistedInSupabase = false;

if ($supabase->isConfigured()) {
    $metadata = [
        'page_url'   => substr((string) ($input['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 250),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
    ];
    $leadId = $supabase->insertLead($fullName, $email, $phoneE164, 'chatbot_web', $metadata);
    if (!empty($leadId)) {
        $persistedInSupabase = true;
    }
}

if (empty($leadId)) {
    $leadId = generateUuidV4();
}

// 5. Generar firma HMAC para el lead_token
$secret = (string) (env('SUPABASE_SECRET_KEY', '') ?: env('GEMINI_API_KEY', '') ?: 'relax_default_secret');
$leadToken = $leadId . '.' . hash_hmac('sha256', $leadId . '|' . $email, $secret);

// 6. Guardar en sesión PHP (aislado del prompt de IA)
$_SESSION['relax_lead_id']        = $leadId;
$_SESSION['relax_lead_persisted'] = $persistedInSupabase;
$_SESSION['relax_lead_name']      = $fullName;
$_SESSION['relax_lead_email']     = $email;
$_SESSION['relax_lead_phone']     = $phoneE164;

if (!isset($_SESSION['relax_chat_history']) || !is_array($_SESSION['relax_chat_history'])) {
    $_SESSION['relax_chat_history'] = [];
}

$firstName = explode(' ', $fullName)[0];

echo json_encode([
    'ok'         => true,
    'lead_id'    => $leadId,
    'lead_token' => $leadToken,
    'phone_e164' => $phoneE164,
    'first_name' => $firstName,
], JSON_UNESCAPED_UNICODE);
