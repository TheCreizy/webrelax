<?php
/**
 * Relax Tours - Endpoint Conversacional RAG con Gemini + Supabase pgvector (api/chat.php)
 * Verifica sesión/token de Lead, aplica Rate Limiting estricto y ejecuta el flujo RAG sin enviar PII a Gemini.
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/GeminiClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'      => false,
        'code'    => 'METHOD_NOT_ALLOWED',
        'message' => 'Método no permitido. Usá POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
if ($rawBody !== false) {
    // Quitar BOM UTF-8 (\xEF\xBB\xBF) si está presente
    $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody);
}
$input = json_decode($rawBody ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

// 1. Verificar sesión de Lead o validar lead_token firmado con HMAC
$leadId = isset($_SESSION['relax_lead_id']) ? (string) $_SESSION['relax_lead_id'] : '';

if ($leadId === '') {
    $leadToken = trim((string) ($input['lead_token'] ?? ''));
    $email     = strtolower(trim((string) ($input['email'] ?? '')));

    if ($leadToken !== '' && $email !== '' && strpos($leadToken, '.') !== false) {
        list($tokenLeadId, $tokenSignature) = explode('.', $leadToken, 2);
        $secret = (string) (env('SUPABASE_SECRET_KEY', '') ?: env('GEMINI_API_KEY', '') ?: 'relax_default_secret');
        $expectedSignature = hash_hmac('sha256', $tokenLeadId . '|' . $email, $secret);

        if ($tokenLeadId !== '' && hash_equals($expectedSignature, $tokenSignature)) {
            $leadId = $tokenLeadId;
            $_SESSION['relax_lead_id']    = $leadId;
            $_SESSION['relax_lead_email'] = $email;
        }
    }
}

if ($leadId === '') {
    http_response_code(403);
    echo json_encode([
        'ok'      => false,
        'code'    => 'LEAD_REQUIRED',
        'message' => 'Por favor completá tus datos de contacto para iniciar el chat.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Rate Limiting en $_SESSION
$now = time();
$todayKey = date('Y-m-d');

// Reiniciar contador diario si cambió la fecha
if (!isset($_SESSION['daily_msg_date']) || $_SESSION['daily_msg_date'] !== $todayKey) {
    $_SESSION['daily_msg_date']  = $todayKey;
    $_SESSION['daily_msg_count'] = 0;
}

// Cooldown mínimo de 2 segundos entre mensajes
$lastMsgTs = isset($_SESSION['last_msg_ts']) ? (int) $_SESSION['last_msg_ts'] : 0;
if ($lastMsgTs > 0 && ($now - $lastMsgTs) < 2) {
    http_response_code(429);
    echo json_encode([
        'ok'      => false,
        'code'    => 'RATE_LIMIT',
        'message' => 'Por favor aguardá unos segundos antes de enviar otro mensaje.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ventana deslizante de 3 minutos (180 segundos): máximo 12 mensajes
$msgWindow = isset($_SESSION['msg_window']) && is_array($_SESSION['msg_window']) ? $_SESSION['msg_window'] : [];
$msgWindow = array_values(array_filter($msgWindow, function ($ts) use ($now) {
    return ($now - (int) $ts) < 180;
}));

$dailyCount = isset($_SESSION['daily_msg_count']) ? (int) $_SESSION['daily_msg_count'] : 0;

if (count($msgWindow) >= 12 || $dailyCount >= 35) {
    http_response_code(429);
    echo json_encode([
        'ok'      => false,
        'code'    => 'RATE_LIMIT',
        'message' => 'Alcanzaste el límite de consultas seguidas. Podés continuar la charla directamente con nuestros asesores por WhatsApp.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Sanitizar y limitar mensaje del usuario a 400 caracteres
$rawMessage = (string) ($input['message'] ?? '');
$userMessage = mb_substr(trim(strip_tags($rawMessage)), 0, 400, 'UTF-8');

if ($userMessage === '') {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'code'    => 'EMPTY_MESSAGE',
        'message' => 'Por favor escribí tu consulta.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Actualizar contadores de Rate Limiting
$_SESSION['last_msg_ts'] = $now;
$msgWindow[] = $now;
$_SESSION['msg_window'] = $msgWindow;
$_SESSION['daily_msg_count'] = $dailyCount + 1;

// 4. Flujo RAG: Embedding de la pregunta -> Búsqueda vectorial en Supabase -> Generación con Gemini
$supabase = new SupabaseClient();
$gemini   = new GeminiClient();

if (!$gemini->isConfigured()) {
    http_response_code(503);
    echo json_encode([
        'ok'      => false,
        'code'    => 'AI_UNAVAILABLE',
        'message' => 'En este momento nuestro asistente automático no está disponible. Tocá el botón de WhatsApp para hablar directo con un asesor de Relax Tours.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contextItems = [];
if ($supabase->isConfigured()) {
    $queryEmbedding = $gemini->generateEmbedding($userMessage, 'RETRIEVAL_QUERY');
    if (is_array($queryEmbedding) && count($queryEmbedding) === 768) {
        $contextItems = $supabase->matchKnowledge($queryEmbedding, 0.38, 4);
    }
}

// Obtener historial de conversación libre de PII (desde sesión o body si fuera enviado)
$sessionHistory = isset($_SESSION['relax_chat_history']) && is_array($_SESSION['relax_chat_history'])
    ? $_SESSION['relax_chat_history']
    : [];

if (empty($sessionHistory) && !empty($input['history']) && is_array($input['history'])) {
    foreach (array_slice($input['history'], -6) as $h) {
        if (is_array($h) && !empty($h['message'])) {
            $sessionHistory[] = [
                'role'    => (($h['role'] ?? '') === 'model') ? 'model' : 'user',
                'message' => mb_substr(trim((string) $h['message']), 0, 500, 'UTF-8'),
            ];
        }
    }
}

$reply = $gemini->generateChatResponse($contextItems, $sessionHistory, $userMessage);

if ($reply === null || $reply === '') {
    $httpCode = $gemini->getLastHttpCode();
    if ($httpCode === 429 || $httpCode === 503) {
        http_response_code(429);
        echo json_encode([
            'ok'      => false,
            'code'    => 'AI_QUOTA_EXCEEDED',
            'message' => 'Estamos recibiendo muchas consultas en este instante. Tocá el botón de WhatsApp aquí abajo y un asesor de Relax Tours te atenderá de inmediato.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'code'    => 'AI_ERROR',
        'message' => 'No pudimos procesar tu mensaje en este momento. Podés consultarnos directamente tocando el botón de WhatsApp o Correo.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Guardar turno en el historial de sesión (máximo últimos 10 mensajes)
$sessionHistory[] = ['role' => 'user', 'message' => $userMessage];
$sessionHistory[] = ['role' => 'model', 'message' => $reply];
$_SESSION['relax_chat_history'] = array_slice($sessionHistory, -10);

// 5. Si Supabase está configurado y el leadId es un UUID válido, registrar mensajes en public.chat_messages
if ($supabase->isConfigured() && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $leadId)) {
    $supabase->insertChatMessage($leadId, 'user', $userMessage);
    $supabase->insertChatMessage($leadId, 'model', $reply);
}

echo json_encode([
    'ok'    => true,
    'reply' => $reply,
], JSON_UNESCAPED_UNICODE);
