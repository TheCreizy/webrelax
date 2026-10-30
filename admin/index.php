<?php
/**
 * RELAX TOURS - BACKOFFICE
 * Panel de administración en PHP
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CARGAR VARIABLES DE ENTORNO Y CLIENTES IA / SUPABASE / REPOSITORY ---
require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../api/SupabaseClient.php';
require_once __DIR__ . '/../api/GeminiClient.php';
require_once __DIR__ . '/../api/ContentRepository.php';
if (file_exists(__DIR__ . '/../scripts/ingest_coris_knowledge.php')) {
    require_once __DIR__ . '/../scripts/ingest_coris_knowledge.php';
}

// Configuración
define('DATA_FILE', __DIR__ . '/../data/content.json');
define('ADMIN_USER', env('ADMIN_USER', 'admin_por_defecto'));
define('ADMIN_PASS', env('ADMIN_PASS', 'pass_por_defecto'));

// PHPMailer initialization
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../PHPMailer/PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/PHPMailer-master/src/SMTP.php';

function loadData()
{
    return ContentRepository::getInstance()->getAllData();
}

function saveData($data)
{
    return ContentRepository::getInstance()->saveLocalSnapshot($data);
}

function generateId()
{
    return uniqid();
}
function slugify($text)
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

/**
 * Genera el texto estructurado limpio en Markdown de un Paquete Destacado para vectorización RAG.
 */
function formatPaqueteMarkdown(array $p): string
{
    $nombre   = trim((string) ($p['nombre'] ?? 'Paquete Turístico'));
    $destino  = trim((string) ($p['destino'] ?? ''));
    $duracion = trim((string) ($p['duracion'] ?? ''));
    $moneda   = trim((string) ($p['moneda'] ?? 'USD'));
    $precio   = isset($p['precio']) ? number_format((float) $p['precio'], 0, ',', '.') : 'Consultar';
    $incluye  = (!empty($p['incluye']) && is_array($p['incluye']))
        ? implode(', ', $p['incluye'])
        : (string) ($p['incluye'] ?? 'Consultar servicios incluidos');
    $vigencia = trim((string) ($p['vigencia_hasta'] ?? ''));
    $infoBot  = trim((string) ($p['info_bot'] ?? ''));

    $lines = [
        "Categoría: Paquete Destacado",
        "Título: {$nombre}",
        "Destino: {$destino}",
        "Duración: {$duracion}",
        "Incluye: {$incluye}",
        "Precio desde: {$moneda} \${$precio} por persona (tarifa referencial sujeta a disponibilidad y modificaciones al momento de reservar).",
    ];

    if ($vigencia !== '') {
        $lines[] = "Vigencia hasta: {$vigencia}";
    }
    if ($infoBot !== '') {
        $lines[] = "Información adicional del paquete: {$infoBot}";
    }

    return implode("\n", $lines);
}

/**
 * Genera el texto estructurado limpio en Markdown de una Oferta Flash para vectorización RAG.
 */
function formatOfertaMarkdown(array $o): string
{
    $titulo      = trim((string) ($o['titulo'] ?? 'Oferta Flash'));
    $descripcion = trim((string) ($o['descripcion'] ?? ''));
    $badge       = trim((string) ($o['badge'] ?? ''));
    $tiempo      = trim((string) ($o['tiempo'] ?? ''));
    $vigencia    = trim((string) ($o['vigencia_hasta'] ?? ''));
    $infoBot     = trim((string) ($o['info_bot'] ?? ''));

    $lines = [
        "Categoría: Oferta Flash / Promoción Especial",
        "Título: {$titulo}",
        "Descripción: {$descripcion}",
    ];

    if ($badge !== '') {
        $lines[] = "Etiqueta promocional: {$badge}";
    }
    if ($tiempo !== '') {
        $lines[] = "Duración de la oferta / Cupos: {$tiempo} (sujeto a disponibilidad al momento de reservar)";
    }
    if ($vigencia !== '') {
        $lines[] = "Vigencia hasta: {$vigencia}";
    }
    if ($infoBot !== '') {
        $lines[] = "Información adicional de la oferta: {$infoBot}";
    }

    return implode("\n", $lines);
}

/**
 * Limpia <style> y <script> del HTML de un artículo del blog y devuelve un resumen estructurado (máx ~2500 caracteres).
 */
function formatBlogMarkdown(array $b): string
{
    $titulo   = trim((string) ($b['titulo'] ?? 'Artículo del Blog'));
    $extracto = trim((string) ($b['extracto'] ?? ''));
    $fecha    = trim((string) ($b['fecha'] ?? ''));
    $slug     = trim((string) ($b['slug'] ?? ''));
    $rawHtml  = (string) ($b['contenido'] ?? '');

    // Eliminar bloques <style> y <script> completos
    $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $rawHtml);
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', (string) $clean);
    $clean = preg_replace('#<!--.*?-->#s', ' ', (string) $clean);

    // Convertir cierres de bloque y saltos en saltos de línea legibles
    $clean = preg_replace('#<(br|hr)\b[^>]*>#i', "\n", (string) $clean);
    $clean = preg_replace('#</(p|div|h1|h2|h3|h4|li|tr|table|ul|ol|blockquote)>#i', "\n", (string) $clean);
    $clean = preg_replace('#<li\b[^>]*>#i', "- ", (string) $clean);
    $clean = preg_replace('#<t[dh]\b[^>]*>#i', " | ", (string) $clean);

    $text = strip_tags((string) $clean);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalizar espacios y líneas múltiples
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n\s*\n+/', "\n", $text);
    $text = trim((string) $text);

    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 2200, 'UTF-8');
    } else {
        $text = substr($text, 0, 2200);
    }

    $lines = [
        "Categoría: Guía y Consejos de Viaje (Blog Relax Tours)",
        "Título: {$titulo}",
    ];
    if ($fecha !== '') {
        $lines[] = "Fecha de publicación: {$fecha}";
    }
    if ($slug !== '') {
        $lines[] = "URL del artículo: https://www.relaxtours.com.ar/blog/{$slug}";
    }
    if ($extracto !== '') {
        $lines[] = "Resumen: {$extracto}";
    }
    if ($text !== '') {
        $lines[] = "Contenido clave:\n{$text}";
    }

    $result = implode("\n", $lines);
    if (function_exists('mb_substr')) {
        return mb_substr($result, 0, 2500, 'UTF-8');
    }
    return substr($result, 0, 2500);
}

/**
 * Genera el vector de embedding con Gemini y realiza upsert en Supabase public.knowledge_base.
 * Nunca lanza excepciones fatales para no interrumpir el flujo del Backoffice.
 *
 * @return array{ok: bool, error: string}
 */
function syncSingleItemToSupabase(
    string $externalId,
    string $category,
    string $title,
    string $markdown,
    ?string $validUntil,
    array $metadata,
    bool $isActive
): array {
    try {
        $supabase = new SupabaseClient();
        $gemini   = new GeminiClient();

        if (!$supabase->isConfigured()) {
            return ['ok' => false, 'error' => 'Supabase no está configurado (falta SUPABASE_URL o SUPABASE_SECRET_KEY).'];
        }
        if (!$gemini->isConfigured()) {
            return ['ok' => false, 'error' => 'Gemini no está configurado (falta GEMINI_API_KEY).'];
        }

        $embedding = $gemini->generateEmbedding($markdown, 'RETRIEVAL_DOCUMENT');
        if (!is_array($embedding) || empty($embedding)) {
            return ['ok' => false, 'error' => 'Error al generar embedding en Gemini: ' . $gemini->getLastError()];
        }

        $validDate = (!empty($validUntil) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) ? $validUntil : null;

        $ok = $supabase->upsertKnowledge(
            $externalId,
            $category,
            $title,
            $markdown,
            $validDate,
            $metadata,
            $embedding,
            $isActive
        );

        if (!$ok) {
            return ['ok' => false, 'error' => 'Error al guardar en Supabase: ' . $supabase->getLastError()];
        }

        return ['ok' => true, 'error' => ''];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'Excepción en sincronización: ' . $e->getMessage()];
    }
}

/**
 * Elimina un documento vectorizado de Supabase según su external_id en segundo plano.
 */
function deleteSingleItemFromSupabase(string $externalId): bool
{
    try {
        $supabase = new SupabaseClient();
        if (!$supabase->isConfigured()) {
            return false;
        }
        return $supabase->deleteKnowledgeByExternalId($externalId);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Elimina completamente un lead y todo su historial de conversación de Supabase y de cualquier archivo JSON o sesión.
 */
function deleteLeadGlobally(string $leadId): bool
{
    $leadId = trim($leadId);
    if ($leadId === '') {
        return false;
    }

    $supabaseSuccess = true;
    try {
        $supabase = new SupabaseClient();
        if ($supabase->isConfigured()) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $leadId)) {
                $supabaseSuccess = $supabase->deleteLead($leadId);
            }
        }
    } catch (\Throwable $e) {
        $supabaseSuccess = false;
    }

    // 1. Limpieza exhaustiva en todos los archivos JSON del directorio data/ (content.json, leads.json, etc.)
    $dataDir = __DIR__ . '/../data';
    if (is_dir($dataDir)) {
        $jsonFiles = glob($dataDir . '/*.json');
        if (is_array($jsonFiles)) {
            foreach ($jsonFiles as $jsonPath) {
                if (!is_file($jsonPath) || !is_readable($jsonPath) || !is_writable($jsonPath)) {
                    continue;
                }
                $raw = @file_get_contents($jsonPath);
                if ($raw === false || strpos($raw, $leadId) === false) {
                    continue;
                }
                $jsonData = json_decode($raw, true);
                if (!is_array($jsonData)) {
                    continue;
                }

                $dirty = false;
                // Si es un arreglo simple de registros
                if (isset($jsonData[0]) && is_array($jsonData[0])) {
                    $origCount = count($jsonData);
                    $jsonData = array_values(array_filter($jsonData, function ($item) use ($leadId) {
                        return is_array($item) && ($item['id'] ?? '') !== $leadId && ($item['lead_id'] ?? '') !== $leadId;
                    }));
                    if (count($jsonData) !== $origCount) {
                        $dirty = true;
                    }
                } else {
                    // Si es un objeto asociativo con colecciones
                    foreach ($jsonData as $key => $collection) {
                        if (is_array($collection) && !empty($collection) && isset($collection[0]) && is_array($collection[0])) {
                            $origCount = count($collection);
                            $jsonData[$key] = array_values(array_filter($collection, function ($item) use ($leadId) {
                                return is_array($item) && ($item['id'] ?? '') !== $leadId && ($item['lead_id'] ?? '') !== $leadId;
                            }));
                            if (count($jsonData[$key]) !== $origCount) {
                                $dirty = true;
                            }
                        }
                    }
                }

                if ($dirty) {
                    @file_put_contents($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                }
            }
        }
    }

    // 2. Limpieza de sesión si corresponde al lead activo
    if (isset($_SESSION) && isset($_SESSION['relax_lead_id']) && $_SESSION['relax_lead_id'] === $leadId) {
        unset(
            $_SESSION['relax_lead_id'],
            $_SESSION['relax_lead_persisted'],
            $_SESSION['relax_lead_name'],
            $_SESSION['relax_lead_email'],
            $_SESSION['relax_lead_phone'],
            $_SESSION['relax_chat_history']
        );
    }

    return $supabaseSuccess;
}

function processAndConvertWebP($sourcePath, $destinationPath, $maxWidth = 1200, $quality = 80)
{
    // Validar si la librería GD está instalada
    if (!function_exists('gd_info')) {
        return "Error: La librería GD no está activada en el servidor.";
    }

    // Asegurar que el directorio destino exista
    $targetDir = dirname($destinationPath);
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return "Error: No se pudo crear el directorio destino: $targetDir";
        }
    }

    $info = getimagesize($sourcePath);
    if (!$info)
        return "Error: El archivo subido no es una imagen válida o compatible.";

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    // Calcular proporciones
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int) ($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    // Crear recurso de imagen según el original
    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($sourcePath);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($sourcePath);
            break;
        default:
            return "Error: El formato $mime no es compatible.";
    }

    // Redimensionar
    $tmp = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Guardar como WebP
    $success = imagewebp($tmp, $destinationPath, $quality);

    imagedestroy($src);
    imagedestroy($tmp);

    return $success ? true : "Error al convertir a WebP.";
}

// Login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Usuario o contraseña incorrectos';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

// Acciones GET (Exportar Backup / Reconstruir Caché)
if ($isLoggedIn && isset($_GET['action'])) {
    if ($_GET['action'] === 'export_backup') {
        $data = loadData();
        $filename = 'relaxtours-backup-' . date('Y-m-d-His') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_GET['action'] === 'rebuild_cache') {
        $data = ContentRepository::getInstance()->rebuildLocalCacheFromSupabase();
        foreach ($data['blog'] as $b) {
            if (!empty($b['slug'])) {
                generateBlogPage($b, $data['config'] ?? [], $data['blog']);
            }
        }
        updateSitemap($data);
        header('Location: index.php?msg=cache_rebuilt');
        exit;
    }
}

// AJAX: Upload blog content image
if ($isLoggedIn && isset($_GET['ajax']) && $_GET['ajax'] === 'upload_blog_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $uploadDir = __DIR__ . '/../images/blog-content/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);
    if (!empty($_FILES['image']['tmp_name'])) {
        $filename = 'blog-img-' . uniqid() . '-' . time() . '.webp';
        $targetPath = $uploadDir . $filename;
        $result = processAndConvertWebP($_FILES['image']['tmp_name'], $targetPath, 1000);
        if ($result === true) {
            $relUrl = 'images/blog-content/' . $filename;
            ContentRepository::getInstance()->syncImageToStorage($relUrl);
            echo json_encode(['success' => true, 'url' => $relUrl]);
        } else {
            echo json_encode(['success' => false, 'error' => $result]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
    }
    exit;
}

// AJAX: Obtener mensajes de chat para un lead específico (por turnos)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_lead_chat') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autorizado. Iniciá sesión en el panel.']);
        exit;
    }
    $leadId = trim((string) ($_GET['lead_id'] ?? ''));
    if ($leadId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parámetro lead_id requerido.']);
        exit;
    }

    $supabase = new SupabaseClient();
    if (!$supabase->isConfigured()) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Supabase no está configurado (revisá SUPABASE_SECRET_KEY en admin/.env).']);
        exit;
    }

    $messages = $supabase->getLeadChatMessages($leadId);
    echo json_encode([
        'ok'       => true,
        'lead_id'  => $leadId,
        'count'    => count($messages),
        'messages' => $messages,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: Eliminar lead completo y su conversación de la base de datos y cualquier JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_lead' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autorizado. Iniciá sesión en el panel.']);
        exit;
    }
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput ?: '', true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }
    $leadId = trim((string) ($inputData['lead_id'] ?? ''));
    if ($leadId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'lead_id no especificado.']);
        exit;
    }

    $deleted = deleteLeadGlobally($leadId);
    echo json_encode([
        'ok'      => $deleted,
        'lead_id' => $leadId,
        'message' => $deleted ? 'Lead y conversación eliminados con éxito.' : 'No se pudo eliminar el lead de Supabase.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Procesar acciones
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = loadData();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete_lead':
            $leadId = trim((string) ($_POST['lead_id'] ?? ''));
            if (!empty($leadId)) {
                $deleted = deleteLeadGlobally($leadId);
                if ($deleted) {
                    header('Location: index.php?tab=leads&msg=lead_eliminado');
                } else {
                    header('Location: index.php?tab=leads&msg=lead_error&err=' . urlencode('No se pudo eliminar el registro de la base de datos.'));
                }
            } else {
                header('Location: index.php?tab=leads');
            }
            exit;
        case 'save_paquete':
            $oldImage = !empty($_POST['id']) ? ($_POST['imagen'] ?? null) : null;
            $paquete = [
                'id' => $_POST['id'] ?: generateId(),
                'nombre' => $_POST['nombre'],
                'destino' => $_POST['destino'],
                'duracion' => $_POST['duracion'],
                'incluye' => array_filter(array_map('trim', explode(',', $_POST['incluye']))),
                'precio' => (int) $_POST['precio'],
                'moneda' => $_POST['moneda'] ?? 'USD',
                'imagen' => $_POST['imagen'],
                'popular' => isset($_POST['popular']),
                'activo' => isset($_POST['activo']),
                'operador' => !empty($_POST['operador']) ? $_POST['operador'] : 'Sin información',
                'comentarios' => !empty($_POST['comentarios']) ? $_POST['comentarios'] : 'Sin información',
                'vigencia_hasta' => !empty($_POST['vigencia_hasta']) ? trim($_POST['vigencia_hasta']) : null,
                'info_bot' => !empty($_POST['info_bot']) ? trim($_POST['info_bot']) : ''
            ];
            $imgErr = '';
            if (!empty($_FILES['imagen_file']['name'])) {
                if ($_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
                    $filename = 'paquete-' . $paquete['id'] . '-' . time() . '.webp';
                    $dest = __DIR__ . '/../images/paquetes-images/' . $filename;
                    $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 800);
                    if ($res === true) {
                        $paquete['imagen'] = 'images/paquetes-images/' . $filename;
                    } else {
                        $imgErr = $res;
                    }
                } else {
                    $imgErr = 'Error al subir el archivo (código ' . $_FILES['imagen_file']['error'] . ')';
                }
            }

            // Persistir en ContentRepository (Supabase SSOT + Storage + Caché Local)
            ContentRepository::getInstance()->savePackage($paquete, $oldImage);

            // Sincronización automática de embeddings en segundo plano con Supabase pgvector
            syncSingleItemToSupabase(
                'paquete_' . $paquete['id'],
                'paquete',
                $paquete['nombre'] . ' - ' . $paquete['destino'],
                formatPaqueteMarkdown($paquete),
                $paquete['vigencia_hasta'],
                [
                    'id'      => $paquete['id'],
                    'destino' => $paquete['destino'],
                    'precio'  => $paquete['precio'],
                    'moneda'  => $paquete['moneda'],
                ],
                (bool) $paquete['activo']
            );

            $redirectUrl = 'Location: index.php?tab=paquetes&msg=guardado';
            if (!empty($imgErr)) {
                $redirectUrl .= '&img_err=' . urlencode($imgErr);
            }
            header($redirectUrl);
            exit;

        case 'delete_paquete':
            if (!empty($_POST['id'])) {
                ContentRepository::getInstance()->deletePackage($_POST['id']);
                deleteSingleItemFromSupabase('paquete_' . $_POST['id']);
            }
            header('Location: index.php?tab=paquetes&msg=eliminado');
            exit;

        case 'save_oferta':
            $isNew = empty($_POST['id']);
            $oldImage = !empty($_POST['id']) ? ($_POST['imagen'] ?? null) : null;
            $oferta = [
                'id' => $_POST['id'] ?: generateId(),
                'titulo' => $_POST['titulo'],
                'descripcion' => $_POST['descripcion'],
                'imagen' => $_POST['imagen'],
                'badge' => $_POST['badge'],
                'badgeColor' => $_POST['badgeColor'],
                'tiempo' => $_POST['tiempo'],
                'activa' => isset($_POST['activa']),
                'operador' => !empty($_POST['operador']) ? $_POST['operador'] : 'Sin información',
                'comentarios' => !empty($_POST['comentarios']) ? $_POST['comentarios'] : 'Sin información',
                'vigencia_hasta' => !empty($_POST['vigencia_hasta']) ? trim($_POST['vigencia_hasta']) : null,
                'info_bot' => !empty($_POST['info_bot']) ? trim($_POST['info_bot']) : ''
            ];
            $imgErr = '';
            if (!empty($_FILES['imagen_file']['name'])) {
                if ($_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
                    $filename = 'oferta-' . $oferta['id'] . '-' . time() . '.webp';
                    $dest = __DIR__ . '/../images/ofertas-images/' . $filename;
                    $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 800);
                    if ($res === true) {
                        $oferta['imagen'] = 'images/ofertas-images/' . $filename;
                    } else {
                        $imgErr = $res;
                    }
                } else {
                    $imgErr = 'Error al subir el archivo (código ' . $_FILES['imagen_file']['error'] . ')';
                }
            }

            // Persistir en ContentRepository (Supabase SSOT + Storage + Caché Local)
            ContentRepository::getInstance()->saveOffer($oferta, $oldImage);

            // Sincronización automática en segundo plano con Supabase (si está configurado)
            syncSingleItemToSupabase(
                'oferta_' . $oferta['id'],
                'oferta',
                $oferta['titulo'],
                formatOfertaMarkdown($oferta),
                $oferta['vigencia_hasta'],
                [
                    'id'     => $oferta['id'],
                    'badge'  => $oferta['badge'],
                    'tiempo' => $oferta['tiempo'],
                ],
                (bool) $oferta['activa']
            );

            // Send Email if New Offer
            $mailMsg = 'guardado';
            $errorMsg = '';

            if ($isNew) {
                $mail = new PHPMailer(true);
                try {
                    // --- CONFIGURACIÓN DEL SERVIDOR SMTP (Hostinger) ---
                    $mail->isSMTP();
                    $mail->Host = 'smtp.hostinger.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USER'] ?? '';
                    $mail->Password = $_ENV['SMTP_PASS'] ?? '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@relaxtours.com.ar', 'Relax Tours Intranet');
                    $mail->addAddress('ventas1@relaxtours.com.ar');

                    $mail->Subject = "Promo Relax + " . $oferta['titulo'];

                    // Embed Image
                    $cid = '';
                    if (!empty($oferta['imagen']) && file_exists(__DIR__ . '/../' . $oferta['imagen'])) {
                        $mail->addEmbeddedImage(__DIR__ . '/../' . $oferta['imagen'], 'promo_image');
                        $cid = '<br><img src="cid:promo_image" style="max-width:100%; height:auto; border-radius:8px; margin-top:20px;"><br>';
                    }

                    $body = "<h2>Nueva Promoción Creada: {$oferta['titulo']}</h2>";
                    $body .= "<p><strong>Operador:</strong> {$oferta['operador']}</p>";
                    $body .= "<p><strong>Comentarios:</strong> {$oferta['comentarios']}</p>";
                    $body .= $cid;
                    $body .= "<hr><p><small>Este es un mensaje automático del sistema interno.</small></p>";

                    $mail->isHTML(true);
                    $mail->Body = $body;

                    $mail->send();
                    $mailMsg = 'guardado_email';
                } catch (Exception $e) {
                    $mailMsg = 'guardado_error_email';
                    $errorMsg = $mail->ErrorInfo;
                }
            }

            $redirectUrl = 'Location: index.php?tab=ofertas&msg=' . $mailMsg . '&err=' . urlencode($errorMsg);
            if (!empty($imgErr)) {
                $redirectUrl .= '&img_err=' . urlencode($imgErr);
            }
            header($redirectUrl);
            exit;

        case 'delete_oferta':
            if (!empty($_POST['id'])) {
                ContentRepository::getInstance()->deleteOffer($_POST['id']);
                deleteSingleItemFromSupabase('oferta_' . $_POST['id']);
            }
            header('Location: index.php?tab=ofertas&msg=eliminado');
            exit;

        case 'save_blog':
            $oldImage = !empty($_POST['id']) ? ($_POST['imagen'] ?? null) : null;
            $blog = [
                'id' => $_POST['id'] ?: generateId(),
                'titulo' => $_POST['titulo'],
                'extracto' => $_POST['extracto'],
                'contenido' => $_POST['contenido'],
                'imagen' => $_POST['imagen'],
                'fecha' => $_POST['fecha'],
                'slug' => $_POST['slug'] ?: slugify($_POST['titulo']),
                'seo_titulo' => $_POST['seo_titulo'] ?? '',
                'seo_description' => $_POST['seo_description'] ?? '',
                'seo_keywords' => $_POST['seo_keywords'] ?? '',
                'activo' => isset($_POST['activo'])
            ];
            $imgErr = '';
            if (!empty($_FILES['imagen_file']['name'])) {
                if ($_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
                    $filename = 'blog-' . $blog['id'] . '-' . time() . '.webp';
                    $dest = __DIR__ . '/../images/blog-images/' . $filename;
                    $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 1200);
                    if ($res === true) {
                        $blog['imagen'] = 'images/blog-images/' . $filename;
                    } else {
                        $imgErr = $res;
                    }
                } else {
                    $imgErr = 'Error al subir el archivo (código ' . $_FILES['imagen_file']['error'] . ')';
                }
            }

            // Persistir en ContentRepository (Supabase SSOT + Storage + Caché Local)
            ContentRepository::getInstance()->saveBlogPost($blog, $oldImage);

            // Generar página HTML estática y adyacentes para SEO e indexación en Google
            $data = loadData();
            generateBlogPage($blog, $data['config'], $data['blog']);
            $activePosts = array_values(array_filter($data['blog'], fn($p) => !empty($p['activo'])));
            $currentIdx = -1;
            foreach ($activePosts as $idx => $p) {
                if ($p['id'] === $blog['id']) {
                    $currentIdx = $idx;
                    break;
                }
            }
            if ($currentIdx > 0) {
                generateBlogPage($activePosts[$currentIdx - 1], $data['config'], $data['blog']);
            }
            if ($currentIdx >= 0 && $currentIdx < count($activePosts) - 1) {
                generateBlogPage($activePosts[$currentIdx + 1], $data['config'], $data['blog']);
            }
            updateSitemap($data);

            // Sincronización automática de embeddings con Supabase pgvector
            syncSingleItemToSupabase(
                'blog_' . $blog['id'],
                'blog',
                $blog['titulo'],
                formatBlogMarkdown($blog),
                null,
                [
                    'id'    => $blog['id'],
                    'slug'  => $blog['slug'],
                    'fecha' => $blog['fecha'],
                ],
                (bool) $blog['activo']
            );

            $redirectUrl = 'Location: index.php?tab=blog&msg=guardado';
            if (!empty($imgErr)) {
                $redirectUrl .= '&img_err=' . urlencode($imgErr);
            }
            header($redirectUrl);
            exit;

        case 'regenerate_all_blogs':
            foreach ($data['blog'] as $b) {
                if (!empty($b['slug'])) {
                    generateBlogPage($b, $data['config'] ?? [], $data['blog']);
                }
            }
            updateSitemap($data);
            header('Location: index.php?tab=blog&msg=guardado');
            exit;

        case 'delete_blog':
            if (!empty($_POST['id'])) {
                ContentRepository::getInstance()->deleteBlogPost($_POST['id']);
                deleteSingleItemFromSupabase('blog_' . $_POST['id']);
            }
            $data = loadData();
            foreach ($data['blog'] as $b) {
                if (!empty($b['slug'])) {
                    generateBlogPage($b, $data['config'] ?? [], $data['blog']);
                }
            }
            updateSitemap($data);
            header('Location: index.php?tab=blog&msg=eliminado');
            exit;

        case 'save_config':
            $config = [
                'whatsapp' => $_POST['whatsapp'],
                'email' => $_POST['email'],
                'telefono' => $_POST['telefono'],
                'direccion' => $_POST['direccion'],
                'horario' => $_POST['horario'],
                'instagram' => $_POST['instagram'],
                'facebook' => $_POST['facebook'],
                'legajo' => $_POST['legajo'],
                'analytics_id' => $_POST['analytics_id'],
                'meta_keywords' => $_POST['meta_keywords'],
                'meta_description' => $_POST['meta_description']
            ];
            ContentRepository::getInstance()->saveConfig($config);
            header('Location: index.php?tab=config&msg=guardado');
            exit;

        case 'save_chatbot_info':
            $chatbotGeneralInfo = trim((string) ($_POST['chatbot_general_info'] ?? ''));
            ContentRepository::getInstance()->saveChatbotInfo($chatbotGeneralInfo);

            if ($chatbotGeneralInfo !== '') {
                syncSingleItemToSupabase(
                    'agency_general_coris',
                    'agencia',
                    'Información General de Relax Tours y Asistencia Coris',
                    $chatbotGeneralInfo,
                    null,
                    ['type' => 'general_info'],
                    true
                );
            }

            header('Location: index.php?tab=chatbot&msg=guardado');
            exit;

        case 'sync_all_supabase':
            $supabase = new SupabaseClient();
            $gemini   = new GeminiClient();

            if (!$gemini->isConfigured()) {
                header('Location: index.php?tab=chatbot&msg=sync_error&err=' . urlencode('Falta configurar GEMINI_API_KEY en admin/.env.'));
                exit;
            }
            if (!$supabase->isConfigured()) {
                header('Location: index.php?tab=chatbot&msg=sync_error&err=' . urlencode('Falta configurar SUPABASE_URL o SUPABASE_SECRET_KEY en admin/.env.'));
                exit;
            }

            $syncedCount = 0;
            $errorsList  = [];

            // 1. Conocimiento General de la Agencia y Asistencia Coris
            $generalInfo = trim((string) ($data['chatbot_general_info'] ?? ''));
            if ($generalInfo !== '') {
                $res = syncSingleItemToSupabase(
                    'agency_general_coris',
                    'agencia',
                    'Información General de Relax Tours y Asistencia Coris',
                    $generalInfo,
                    null,
                    ['type' => 'general_info'],
                    true
                );
                if ($res['ok']) {
                    $syncedCount++;
                } else {
                    $errorsList[] = 'Conocimiento General: ' . $res['error'];
                }
            }

            // 2. Asistencia al Viajero Coris (10 casos canónicos estructurados con embeddings 768d)
            if (function_exists('syncCorisKnowledgeToSupabase')) {
                $corisRes = syncCorisKnowledgeToSupabase($supabase, $gemini);
                $syncedCount += ($corisRes['synced'] ?? 0);
                if (!empty($corisRes['errors'])) {
                    foreach ($corisRes['errors'] as $cErr) {
                        $errorsList[] = 'Coris: ' . $cErr;
                    }
                }
            }

            // 3. Paquetes Destacados activos
            if (!empty($data['paquetes']) && is_array($data['paquetes'])) {
                foreach ($data['paquetes'] as $p) {
                    if (empty($p['activo'])) {
                        continue;
                    }
                    $res = syncSingleItemToSupabase(
                        'paquete_' . $p['id'],
                        'paquete',
                        ($p['nombre'] ?? 'Paquete') . ' - ' . ($p['destino'] ?? ''),
                        formatPaqueteMarkdown($p),
                        $p['vigencia_hasta'] ?? null,
                        [
                            'id'      => $p['id'],
                            'destino' => $p['destino'] ?? '',
                            'precio'  => $p['precio'] ?? 0,
                            'moneda'  => $p['moneda'] ?? 'USD',
                        ],
                        true
                    );
                    if ($res['ok']) {
                        $syncedCount++;
                    } else {
                        $errorsList[] = 'Paquete "' . ($p['nombre'] ?? $p['id']) . '": ' . $res['error'];
                    }
                }
            }

            // 4. Ofertas Flash activas
            if (!empty($data['ofertas']) && is_array($data['ofertas'])) {
                foreach ($data['ofertas'] as $o) {
                    if (empty($o['activa'])) {
                        continue;
                    }
                    $res = syncSingleItemToSupabase(
                        'oferta_' . $o['id'],
                        'oferta',
                        $o['titulo'] ?? 'Oferta Flash',
                        formatOfertaMarkdown($o),
                        $o['vigencia_hasta'] ?? null,
                        [
                            'id'     => $o['id'],
                            'badge'  => $o['badge'] ?? '',
                            'tiempo' => $o['tiempo'] ?? '',
                        ],
                        true
                    );
                    if ($res['ok']) {
                        $syncedCount++;
                    } else {
                        $errorsList[] = 'Oferta "' . ($o['titulo'] ?? $o['id']) . '": ' . $res['error'];
                    }
                }
            }

            // 5. Artículos del Blog publicados
            if (!empty($data['blog']) && is_array($data['blog'])) {
                foreach ($data['blog'] as $b) {
                    if (empty($b['activo'])) {
                        continue;
                    }
                    $res = syncSingleItemToSupabase(
                        'blog_' . $b['id'],
                        'blog',
                        $b['titulo'] ?? 'Post del Blog',
                        formatBlogMarkdown($b),
                        null,
                        [
                            'id'    => $b['id'],
                            'slug'  => $b['slug'] ?? '',
                            'fecha' => $b['fecha'] ?? '',
                        ],
                        true
                    );
                    if ($res['ok']) {
                        $syncedCount++;
                    } else {
                        $errorsList[] = 'Blog "' . ($b['titulo'] ?? $b['id']) . '": ' . $res['error'];
                    }
                }
            }

            if (!empty($errorsList)) {
                $firstErr = implode(' | ', array_slice($errorsList, 0, 2));
                header('Location: index.php?tab=chatbot&msg=sync_partial&synced=' . $syncedCount . '&err=' . urlencode($firstErr));
                exit;
            }

            header('Location: index.php?tab=chatbot&msg=sync_ok&synced=' . $syncedCount);
            exit;
    }

    if ($action === 'move_up' || $action === 'move_down') {
        $id = $_POST['id'];
        $type = $_GET['tab'] ?? 'paquetes';
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }

        ContentRepository::getInstance()->reorder($type, $id, $action);
        if ($type === 'blog') {
            $data = loadData();
            foreach ($data['blog'] as $b) {
                if (!empty($b['slug'])) {
                    generateBlogPage($b, $data['config'] ?? [], $data['blog']);
                }
            }
            updateSitemap($data);
        }
        header("Location: index.php?tab={$type}&msg=ordenado");
        exit;
    }
}

function generateBlogPage($post, $config = [], $allBlog = [])
{
    $seoTitle = !empty($post['seo_titulo']) ? $post['seo_titulo'] : $post['titulo'] . ' - Relax Tours Blog';
    $seoDesc = !empty($post['seo_description']) ? $post['seo_description'] : $post['extracto'];
    $seoKeys = !empty($post['seo_keywords']) ? $post['seo_keywords'] : '';

    // Preparar URLs absolutas para Redes Sociales (Open Graph)

    // Nos aseguramos de que la imagen no tenga '../' al principio si es que lo tiene guardado así

    $cleanImage = str_replace('../', '', $post['imagen']);

    $ogImage = 'https://www.relaxtours.com.ar/' . $cleanImage;

    $ogUrl = 'https://www.relaxtours.com.ar/blog/' . $post['slug'];

    // Analytics
    $analyticsScript = '';
    if (!empty($config['analytics_id'])) {
        $gid = $config['analytics_id'];
        $analyticsScript = <<<HTML
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={$gid}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '{$gid}');
        </script>
HTML;
    }

    // Render the shared footer template with $base_url = '../'
    $base_url = '../';
    ob_start();
    include __DIR__ . '/../includes/footer.php';
    $footerHtml = ob_get_clean();

    // Determine previous and next posts
    $prevPost = null;
    $nextPost = null;
    if (!empty($allBlog)) {
        $activePosts = array_values(array_filter($allBlog, fn($p) => !empty($p['activo'])));
        $currentIdx = -1;
        foreach ($activePosts as $idx => $p) {
            if ($p['id'] === $post['id']) {
                $currentIdx = $idx;
                break;
            }
        }
        if ($currentIdx >= 0) {
            // Array is newest-first, so index+1 = older (anterior), index-1 = newer (próximo)
            if ($currentIdx < count($activePosts) - 1) {
                $prevPost = $activePosts[$currentIdx + 1];
            }
            if ($currentIdx > 0) {
                $nextPost = $activePosts[$currentIdx - 1];
            }
        }
    }

    // Build navigation HTML
    $navHtml = '';
    if ($prevPost || $nextPost) {
        $navHtml = '<div class="blog-post-nav">';
        if ($prevPost) {
            $prevImg = str_replace('../', '', $prevPost['imagen']);
            $navHtml .= '
            <a href="' . $prevPost['slug'] . '" class="blog-post-nav-item blog-post-nav-prev">
                <span class="blog-post-nav-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Ver Tip anterior
                </span>
                <div class="blog-post-nav-card">
                    <img src="../' . $prevImg . '" alt="' . htmlspecialchars($prevPost['titulo']) . '" class="blog-post-nav-thumb">
                    <span class="blog-post-nav-title">' . htmlspecialchars($prevPost['titulo']) . '</span>
                </div>
            </a>';
        } else {
            $navHtml .= '<div class="blog-post-nav-item blog-post-nav-empty"></div>';
        }
        if ($nextPost) {
            $nextImg = str_replace('../', '', $nextPost['imagen']);
            $navHtml .= '
            <a href="' . $nextPost['slug'] . '" class="blog-post-nav-item blog-post-nav-next">
                <span class="blog-post-nav-label">
                    Ver próximo Tip
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </span>
                <div class="blog-post-nav-card">
                    <img src="../' . $nextImg . '" alt="' . htmlspecialchars($nextPost['titulo']) . '" class="blog-post-nav-thumb">
                    <span class="blog-post-nav-title">' . htmlspecialchars($nextPost['titulo']) . '</span>
                </div>
            </a>';
        } else {
            $navHtml .= '<div class="blog-post-nav-item blog-post-nav-empty"></div>';
        }
        $navHtml .= '</div>';
    }

    $base_url = '../';
    $forzar_base_url = true;
    $page_title = $seoTitle;
    $page_description = $seoDesc;
    $active_page = 'blog';
    $extra_head_meta = <<<META
    <meta property="og:type" content="article">
    <meta property="og:title" content="{$seoTitle}">
    <meta property="og:description" content="{$seoDesc}">
    <meta property="og:image" content="{$ogImage}">
    <meta property="og:url" content="{$ogUrl}">
    META;
    ob_start();
    include __DIR__ . '/../includes/header.php';
    $headerHtml = ob_get_clean();

    $html = <<<HTML
    {$headerHtml}
        <article class="section" style="padding-top: 70px; background: #fff;">
            <div class="container" style="max-width: 900px;">
                <div style="margin-bottom: 40px; text-align: center;">
                    <a href="../index#blog" style="color: var(--color-orange); display: inline-flex; align-items: center; gap: 8px; margin-bottom: 24px; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 1px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Volver al Blog
                    </a>
                    <h1 style="font-size: clamp(2rem, 5vw, 3.5rem); margin-bottom: 24px; line-height: 1.1;">{$post['titulo']}</h1>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; color: var(--color-gray); font-size: 14px;">
                        <span style="display: flex; align-items: center; gap: 6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> {$post['fecha']}</span>
                    </div>
                </div>
                
                <div style="position: relative; margin-bottom: 48px; border-radius: 32px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                    <img src="../{$post['imagen']}" alt="{$post['titulo']}" style="width: 100%; height: auto; display: block;">
                </div>
    
                <div class="blog-post-content">
                    {$post['contenido']}
                </div>

                {$navHtml}

                <div style="margin-top: 60px; padding: 40px; background: #f9f9f9; border-radius: 24px; text-align: center;">
                    <h3 style="margin-bottom: 12px; font-size: 1.5rem;">¿Estás planeando tu próximo viaje?</h3>
                    <p style="color: #6b7280; margin-bottom: 24px;">Dejanos ayudarte a crear la experiencia perfecta. Consultanos sin compromiso.</p>
                    <a href="#" class="btn-primary" onclick="openWhatsApp('Hola! Leí el artículo \"{$post['titulo']}\" y quiero más información'); return false;" style="padding: 16px 32px; font-size: 1rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Consultar por WhatsApp
                    </a>
                </div>
            </div>
        </article>
        {$footerHtml}
    HTML;
    $blogDir = __DIR__ . '/../blog';
    if (!is_dir($blogDir)) {
        @mkdir($blogDir, 0755, true);
    }
    file_put_contents($blogDir . '/' . $post['slug'] . '.html', $html);
}

function updateSitemap($data)
{
    $sitemapFile = __DIR__ . '/../sitemap.xml';
    $baseUrl = 'https://www.relaxtours.com.ar';
    $today = date('Y-m-d');

    // Leer el sitemap actual y preservar las URLs estáticas (no blog)
    $staticUrls = [];
    if (file_exists($sitemapFile)) {
        $xml = simplexml_load_file($sitemapFile);
        if ($xml) {
            foreach ($xml->url as $urlNode) {
                $loc = (string) $urlNode->loc;
                // Preservar todo lo que NO sea un artículo individual del blog
                // (mantenemos /blog/ índice, pero no /blog/slug-del-post)
                if (!preg_match('#/blog/[^/]+$#', $loc)) {
                    $staticUrls[] = [
                        'loc' => $loc,
                        'lastmod' => (string) $urlNode->lastmod,
                        'changefreq' => (string) $urlNode->changefreq,
                        'priority' => (string) $urlNode->priority
                    ];
                }
            }
        }
    }

    // Construir el nuevo XML
    $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xmlContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xmlContent .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n\n";

    // Escribir las URLs estáticas preservadas
    foreach ($staticUrls as $url) {
        $xmlContent .= "  <url>\n";
        $xmlContent .= "    <loc>{$url['loc']}</loc>\n";
        $xmlContent .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
        $xmlContent .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
        $xmlContent .= "    <priority>{$url['priority']}</priority>\n";
        $xmlContent .= "  </url>\n\n";
    }

    // Agregar las entradas de blog activas
    if (!empty($data['blog'])) {
        $xmlContent .= "  <!-- Artículos del Blog (auto-generado) -->\n";
        foreach ($data['blog'] as $post) {
            if (!empty($post['activo']) && !empty($post['slug'])) {
                $postDate = !empty($post['fecha']) ? $post['fecha'] : $today;
                $xmlContent .= "  <url>\n";
                $xmlContent .= "    <loc>{$baseUrl}/blog/{$post['slug']}</loc>\n";
                $xmlContent .= "    <lastmod>{$today}</lastmod>\n";
                $xmlContent .= "    <changefreq>monthly</changefreq>\n";
                $xmlContent .= "    <priority>0.7</priority>\n";
                $xmlContent .= "  </url>\n\n";
            }
        }
    }

    $xmlContent .= "</urlset>\n";

    file_put_contents($sitemapFile, $xmlContent);
}

$data = loadData();
$activeTab = $_GET['tab'] ?? 'paquetes';
$message = $_GET['msg'] ?? '';

$editItem = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $type = $_GET['type'] ?? '';
    switch ($type) {
        case 'paquete':
            $editItem = array_values(array_filter($data['paquetes'], fn($p) => $p['id'] === $id))[0] ?? null;
            break;
        case 'oferta':
            $editItem = array_values(array_filter($data['ofertas'], fn($o) => $o['id'] === $id))[0] ?? null;
            break;
        case 'blog':
            $editItem = array_values(array_filter($data['blog'], fn($b) => $b['id'] === $id))[0] ?? null;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es-AR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice - Relax Tours - <a href="https://www.relaxtours.com.ar" target="_blank"> relaxtours.com.ar </a>
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-box h1 {
            text-align: center;
            margin-bottom: 24px;
            color: #333;
        }

        .login-box .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 32px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #F97316;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #F97316;
            color: white;
        }

        .btn-primary:hover {
            background: #EA580C;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .btn-success {
            background: #dcfce7;
            color: #16a34a;
        }

        .btn-block {
            width: 100%;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
        }

        .admin-header {
            background: white;
            border-bottom: 1px solid #e5e5e5;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-header h1 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-header .logo-small {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .admin-nav {
            background: white;
            border-bottom: 1px solid #e5e5e5;
            padding: 0 24px;
        }

        .admin-nav ul {
            display: flex;
            list-style: none;
            gap: 8px;
        }

        .admin-nav a {
            display: block;
            padding: 16px 20px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            color: #F97316;
            border-bottom-color: #F97316;
        }

        .admin-content {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
        }

        tr:hover {
            background: #f9f9f9;
        }

        td img {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 6px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .badge-orange {
            background: #fff7ed;
            color: #F97316;
        }

        .badge-green {
            background: #f0fdf4;
            color: #22c55e;
        }

        .badge-gray {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .checkbox-group {
            display: flex;
            gap: 24px;
            margin-top: 8px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .image-preview {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .save-btn-container {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 16px 0;
            border-top: 1px solid #e5e5e5;
            margin-top: 16px;
        }

        @media (max-width: 768px) {
            .admin-nav ul {
                overflow-x: auto;
            }

            .admin-content {
                padding: 16px;
            }

            table {
                font-size: 14px;
            }

            th,
            td {
                padding: 12px;
            }
        }

        /* ===== WYSIWYG EDITOR ===== */
        .wysiwyg-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            padding: 8px;
            background: #f9fafb;
            border: 2px solid #e5e5e5;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            align-items: center;
        }

        .wysiwyg-toolbar .tb-separator {
            width: 1px;
            height: 24px;
            background: #d1d5db;
            margin: 0 4px;
        }

        .wysiwyg-toolbar button,
        .wysiwyg-toolbar select {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .wysiwyg-toolbar button:hover {
            background: #f3f4f6;
            border-color: #F97316;
            color: #F97316;
        }

        .wysiwyg-toolbar button.active {
            background: #FFF7ED;
            border-color: #F97316;
            color: #F97316;
        }

        .wysiwyg-toolbar select {
            padding: 6px 8px;
            min-width: 80px;
            font-weight: 500;
        }

        .wysiwyg-editor {
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
            padding: 24px;
            border: 2px solid #e5e5e5;
            border-radius: 0 0 8px 8px;
            outline: none;
            font-size: 16px;
            line-height: 1.8;
            color: #1f2937;
            background: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .wysiwyg-editor:focus {
            border-color: #F97316;
        }

        .wysiwyg-editor h2 {
            font-size: 1.5em;
            margin: 16px 0 8px;
            color: #111;
        }

        .wysiwyg-editor h3 {
            font-size: 1.25em;
            margin: 14px 0 6px;
            color: #222;
        }

        .wysiwyg-editor h4 {
            font-size: 1.1em;
            margin: 12px 0 6px;
            color: #333;
        }

        .wysiwyg-editor p {
            margin-bottom: 12px;
        }

        .wysiwyg-editor blockquote {
            border-left: 4px solid #F97316;
            margin: 16px 0;
            padding: 12px 20px;
            background: #FFF7ED;
            border-radius: 0 8px 8px 0;
            color: #9a3412;
        }

        .wysiwyg-editor img {
            max-width: 100%;
            border-radius: 8px;
            margin: 8px 0;
            cursor: pointer;
        }

        .wysiwyg-editor img.img-small {
            width: 33%;
        }

        .wysiwyg-editor img.img-medium {
            width: 50%;
        }

        .wysiwyg-editor img.img-large {
            width: 100%;
        }

        .wysiwyg-editor img.img-left {
            float: left;
            margin-right: 16px;
            margin-bottom: 8px;
        }

        .wysiwyg-editor img.img-right {
            float: right;
            margin-left: 16px;
            margin-bottom: 8px;
        }

        .wysiwyg-editor img.img-center {
            display: block;
            margin-left: auto;
            margin-right: auto;
            float: none;
        }

        .wysiwyg-editor img.img-selected {
            outline: 3px solid #F97316;
            outline-offset: 2px;
        }

        .wysiwyg-editor hr {
            border: none;
            height: 2px;
            background: #e5e7eb;
            margin: 24px 0;
        }

        .wysiwyg-editor a {
            color: #F97316;
            text-decoration: underline;
        }

        .img-toolbar {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 8px;
            gap: 4px;
            z-index: 1001;
            flex-wrap: wrap;
            max-width: 300px;
        }

        .img-toolbar.visible {
            display: flex;
        }

        .img-toolbar button {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 11px;
            color: #374151;
            transition: all 0.15s;
        }

        .img-toolbar button:hover,
        .img-toolbar button.active {
            background: #FFF7ED;
            border-color: #F97316;
            color: #F97316;
        }

        .img-toolbar .it-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 0.5px;
            width: 100%;
            padding: 2px 4px;
        }

        /* Color Picker */
        .color-picker-wrap {
            position: relative;
            display: inline-flex;
        }

        .color-picker-btn {
            position: relative;
            padding-bottom: 4px !important;
        }

        .color-picker-btn .color-indicator {
            position: absolute;
            bottom: 3px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 3px;
            border-radius: 2px;
            background: #dc2626;
        }

        .color-picker-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            padding: 12px;
            z-index: 1002;
            width: 200px;
        }

        .color-picker-dropdown.visible {
            display: block;
        }

        .color-picker-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            margin-bottom: 8px;
        }

        .color-swatch {
            width: 22px;
            height: 22px;
            border-radius: 4px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform 0.1s, border-color 0.1s;
        }

        .color-swatch:hover {
            transform: scale(1.2);
            border-color: #374151;
        }

        .color-hex-input {
            display: flex;
            gap: 4px;
            margin-top: 4px;
        }

        .color-hex-input input {
            flex: 1;
            padding: 4px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 12px;
            font-family: monospace;
        }

        .color-hex-input button {
            padding: 4px 8px;
            font-size: 11px;
            min-width: 24px;
            height: 28px;
        }

        .modal-content.modal-wide {
            max-width: 1100px;
        }

        /* ===== EDITOR TABS ===== */
        .editor-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
        }

        .editor-tab {
            padding: 10px 20px;
            background: #f3f4f6;
            border: 2px solid #e5e5e5;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .editor-tab:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .editor-tab.active {
            background: #f9fafb;
            color: #F97316;
            border-color: #e5e5e5;
            position: relative;
        }

        .editor-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #f9fafb;
        }

        #html-editor {
            width: 100%;
            min-height: 400px;
            max-height: 600px;
            padding: 16px;
            border: 2px solid #e5e5e5;
            border-radius: 0 0 8px 8px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            color: #1f2937;
            background: #1e1e2e;
            color: #cdd6f4;
            tab-size: 4;
            resize: vertical;
            outline: none;
        }

        #html-editor:focus {
            border-color: #F97316;
        }

        @media (max-width: 768px) {
            .wysiwyg-editor {
                min-height: 250px;
            }

            .wysiwyg-toolbar {
                gap: 1px;
                padding: 6px;
            }

            .wysiwyg-toolbar button {
                min-width: 28px;
                height: 28px;
                padding: 4px 6px;
                font-size: 12px;
            }

            #html-editor {
                min-height: 250px;
                font-size: 12px;
            }
        }

        /* ===== LEADS & CONVERSATION ACCORDION ===== */
        .lead-row {
            transition: background-color 0.15s ease;
        }

        .lead-row:hover {
            background: #fafafa;
        }

        .lead-row.is-open {
            background: #f1f5f9;
        }

        .btn-toggle-chat {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-toggle-chat:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .btn-toggle-chat .arrow {
            display: inline-block;
            transition: transform 0.25s ease;
            font-size: 10px;
            color: #64748b;
        }

        .lead-row.is-open .btn-toggle-chat {
            background: #f97316;
            color: #ffffff;
            border-color: #f97316;
        }

        .lead-row.is-open .btn-toggle-chat .arrow {
            transform: rotate(90deg);
            color: #ffffff;
        }

        .chat-drawer-cell {
            padding: 0 !important;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .chat-drawer-container {
            padding: 20px 24px;
            background: #ffffff;
            border-top: 1px dashed #cbd5e1;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .chat-drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chat-turns-stream {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 480px;
            overflow-y: auto;
            padding: 16px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .chat-turn {
            display: flex;
            flex-direction: column;
            max-width: 82%;
            animation: fadeInTurn 0.2s ease-out;
        }

        @keyframes fadeInTurn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-turn.turn-user {
            align-self: flex-start;
        }

        .chat-turn.turn-model {
            align-self: flex-end;
        }

        .turn-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .turn-user .turn-header {
            color: #2563eb;
        }

        .turn-model .turn-header {
            color: #c2410c;
            justify-content: flex-end;
        }

        .turn-time {
            font-size: 11px;
            color: #94a3b8;
            font-weight: normal;
        }

        .turn-bubble {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.55;
            word-break: break-word;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .turn-user .turn-bubble {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-top-left-radius: 2px;
        }

        .turn-model .turn-bubble {
            background: #fff7ed;
            color: #7c2d12;
            border: 1px solid #fed7aa;
            border-top-right-radius: 2px;
        }

        .turn-bubble p {
            margin-bottom: 8px;
        }

        .turn-bubble p:last-child {
            margin-bottom: 0;
        }

        .turn-bubble ul, .turn-bubble ol {
            padding-left: 20px;
            margin: 6px 0;
        }

        .turn-bubble a {
            color: #ea580c;
            text-decoration: underline;
        }

        .chat-drawer-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }

        .leads-filter-input {
            width: 100%;
            max-width: 320px;
            padding: 9px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .leads-filter-input:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }
    </style>
</head>

<body>
    <?php if (!$isLoggedIn): ?>
        <div class="login-container">
            <div class="login-box">
                <div class="logo">✈️</div>
                <h1>Backoffice Relax Tours</h1>
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-error"><?php echo $loginError; ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group"><label>Usuario</label><input type="text" name="username" placeholder="admin"
                            required></div>
                    <div class="form-group"><label>Contraseña</label><input type="password" name="password"
                            placeholder="••••••••" required></div>
                    <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                </form>
                <a href="https://relaxtours.com.ar" target="_blank"
                    style="text-align: center; margin-top: 20px; color: #e05d20ff; font-size: 14px;">Ir a la web</a>
            </div>
        </div>
    <?php else: ?>
        <header class="admin-header">
            <h1>
                <div class="logo-small">✈️</div>Relax Tours - Panel de Administración - <a
                    href="https://www.relaxtours.com.ar" target="_blank"> relaxtours.com.ar </a>
            </h1>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a href="?action=export_backup" class="btn btn-secondary" style="font-size: 13px;" title="Descargar copia de seguridad completa en JSON">💾 Descargar Backup</a>
                <a href="?action=rebuild_cache" class="btn btn-secondary" style="font-size: 13px;" title="Regenerar la caché local y sitemap desde la base de datos Supabase" onclick="return confirm('¿Regenerar la caché local desde Supabase?')">🔄 Restaurar Caché</a>
                <a href="?logout=1" class="btn btn-secondary">Salir</a>
            </div>
        </header>
        <nav class="admin-nav">
            <ul>
                <li><a href="?tab=paquetes" class="<?php echo $activeTab === 'paquetes' ? 'active' : ''; ?>">Paquetes</a>
                </li>
                <li><a href="?tab=ofertas" class="<?php echo $activeTab === 'ofertas' ? 'active' : ''; ?>">Ofertas Flash</a>
                </li>
                <li><a href="?tab=blog" class="<?php echo $activeTab === 'blog' ? 'active' : ''; ?>">Blog</a></li>
                <li><a href="?tab=config" class="<?php echo $activeTab === 'config' ? 'active' : ''; ?>">Configuración</a>
                </li>
                <li><a href="?tab=chatbot" class="<?php echo $activeTab === 'chatbot' ? 'active' : ''; ?>">🤖 Chatbot IA</a>
                </li>
                <li><a href="?tab=leads" class="<?php echo $activeTab === 'leads' ? 'active' : ''; ?>">👥 Leads & Conversaciones</a>
                </li>
            </ul>
        </nav>
        <main class="admin-content">
            <?php if ($message === 'guardado'): ?>
                <div class="alert alert-success">✅ Guardado correctamente</div>
            <?php elseif ($message === 'guardado_email'): ?>
                <div class="alert alert-success">✅ Oferta guardada y correo enviado correctamente</div>
            <?php elseif ($message === 'guardado_error_email'): ?>
                <div class="alert alert-error">⚠️ Oferta guardada, pero hubo un error al enviar el correo:
                    <?php echo htmlspecialchars($_GET['err'] ?? ''); ?></div>
            <?php elseif ($message === 'eliminado'): ?>
                <div class="alert alert-success">✅ Eliminado correctamente</div>
            <?php elseif ($message === 'lead_eliminado'): ?>
                <div class="alert alert-success">✅ Lead y conversación completa eliminados correctamente de la base de datos y registros.</div>
            <?php elseif ($message === 'lead_error'): ?>
                <div class="alert alert-error">⚠️ Error al eliminar el lead: <?php echo htmlspecialchars($_GET['err'] ?? ''); ?></div>
            <?php elseif ($message === 'sync_ok'): ?>
                <div class="alert alert-success">✅ Sincronización completada con éxito: se vectorizaron y actualizaron
                    <strong><?php echo (int) ($_GET['synced'] ?? 0); ?></strong> documentos en Supabase (pgvector).</div>
            <?php elseif ($message === 'sync_partial'): ?>
                <div class="alert alert-error">⚠️ Se sincronizaron <strong><?php echo (int) ($_GET['synced'] ?? 0); ?></strong>
                    documentos, pero ocurrieron errores: <?php echo htmlspecialchars($_GET['err'] ?? ''); ?></div>
            <?php elseif ($message === 'sync_error'): ?>
                <div class="alert alert-error">❌ No se pudo sincronizar con Supabase:
                    <?php echo htmlspecialchars($_GET['err'] ?? ''); ?></div>
            <?php elseif ($message === 'cache_rebuilt'): ?>
                <div class="alert alert-success">✅ Caché local (`data/content.json`), páginas del blog y sitemap regenerados con éxito desde Supabase.</div>
            <?php endif; ?>
            <?php if (!empty($_GET['img_err'])): ?>
                <div class="alert alert-error">⚠️ Atención con la imagen: <?php echo htmlspecialchars($_GET['img_err']); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Paquetes Activos</h3>
                    <div class="value"><?php echo count(array_filter($data['paquetes'], fn($p) => $p['activo'])); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ofertas Flash</h3>
                    <div class="value"><?php echo count(array_filter($data['ofertas'], fn($o) => $o['activa'])); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Posts Blog</h3>
                    <div class="value"><?php echo count(array_filter($data['blog'], fn($b) => $b['activo'])); ?></div>
                </div>
            </div>

            <?php if ($activeTab === 'paquetes'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Gestión de Paquetes</h2><button class="btn btn-primary"
                            onclick="document.getElementById('modal-paquete').classList.add('active')">+ Nuevo Paquete</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Destino</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['paquetes'] as $index => $paquete): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><img src="../<?php echo $paquete['imagen']; ?>" alt=""></td>
                                    <td><?php echo htmlspecialchars($paquete['nombre']); ?>             <?php if ($paquete['popular']): ?><span
                                                class="badge badge-orange">Popular</span><?php endif; ?></td>
                                    <td><?php echo htmlspecialchars($paquete['destino']); ?></td>
                                    <td><?php echo ($paquete['moneda'] ?? 'USD') === 'USD' ? '$' : '$'; ?>
                                        <?php echo number_format($paquete['precio']); ?>             <?php echo $paquete['moneda'] ?? 'USD'; ?>
                                    </td>
                                    <td><?php if ($paquete['activo']): ?><span
                                                class="badge badge-green">Activo</span><?php else: ?><span
                                                class="badge badge-gray">Inactivo</span><?php endif; ?></td>
                                    <td class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_up">
                                            <input type="hidden" name="id" value="<?php echo $paquete['id']; ?>">
                                            <input type="hidden" name="type" value="paquetes">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === 0 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬆️</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_down">
                                            <input type="hidden" name="id" value="<?php echo $paquete['id']; ?>">
                                            <input type="hidden" name="type" value="paquetes">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === count($data['paquetes']) - 1 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬇️</button>
                                        </form>
                                        <a href="?tab=paquetes&edit=<?php echo $paquete['id']; ?>&type=paquete"
                                            class="btn btn-secondary btn-small">Editar</a>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('¿Eliminar este paquete?')"><input type="hidden" name="action"
                                                value="delete_paquete"><input type="hidden" name="id"
                                                value="<?php echo $paquete['id']; ?>"><button type="submit"
                                                class="btn btn-danger btn-small">Eliminar</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="modal-paquete" class="modal <?php echo $editItem && $activeTab === 'paquetes' ? 'active' : ''; ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><?php echo $editItem ? 'Editar' : 'Nuevo'; ?> Paquete</h3><button class="modal-close"
                                onclick="window.location.href='?tab=paquetes'">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_paquete">
                                <input type="hidden" name="id" value="<?php echo $editItem['id'] ?? ''; ?>">
                                <div class="form-row">
                                    <div class="form-group"><label>Nombre del Paquete</label><input type="text" name="nombre"
                                            value="<?php echo $editItem['nombre'] ?? ''; ?>" required></div>
                                    <div class="form-group"><label>Destino</label><input type="text" name="destino"
                                            value="<?php echo $editItem['destino'] ?? ''; ?>" required></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Duración</label><input type="text" name="duracion"
                                            value="<?php echo $editItem['duracion'] ?? ''; ?>"
                                            placeholder="Ej: 7 Noches / 8 Días" required></div>
                                    <div class="form-group"><label>Precio</label><input type="number" name="precio"
                                            value="<?php echo $editItem['precio'] ?? ''; ?>" required></div>
                                    <div class="form-group"><label>Moneda</label><select name="moneda">
                                            <option value="USD" <?php echo ($editItem['moneda'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>Dólares (USD)</option>
                                            <option value="ARS" <?php echo ($editItem['moneda'] ?? '') === 'ARS' ? 'selected' : ''; ?>>Pesos (ARS)</option>
                                        </select></div>
                                </div>
                                <div class="form-row"
                                    style="background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0;">
                                    <div class="form-group"><label>Operador (Interno)</label><input type="text" name="operador"
                                            value="<?php echo $editItem['operador'] ?? 'Sin información'; ?>"
                                            placeholder="Nombre del operador"></div>
                                    <div class="form-group"><label>Comentarios (Interno)</label><input type="text"
                                            name="comentarios"
                                            value="<?php echo $editItem['comentarios'] ?? 'Sin información'; ?>"
                                            placeholder="Comentarios adicionales"></div>
                                </div>
                                <div class="form-group"><label>Incluye (separado por comas)</label><input type="text"
                                        name="incluye"
                                        value="<?php echo isset($editItem['incluye']) ? implode(', ', $editItem['incluye']) : ''; ?>"
                                        placeholder="Ej: Aéreos, Traslados, Hotel"></div>
                                <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 16px;">
                                    <div class="form-group">
                                        <label>Vigencia hasta (Opcional - el Chatbot dejará de ofrecerlo tras esta fecha)</label>
                                        <input type="date" name="vigencia_hasta"
                                            value="<?php echo htmlspecialchars($editItem['vigencia_hasta'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Información extra para el Chatbot IA (Opcional - horarios de vuelo, equipaje, señas, etc.)</label>
                                        <textarea name="info_bot" rows="2"
                                            placeholder="Ej: Incluye equipaje de mano de 10kg, salidas garantizadas en mayo..."><?php echo htmlspecialchars($editItem['info_bot'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Imagen</label>
                                    <?php if ($editItem && $editItem['imagen']): ?><img
                                            src="../<?php echo $editItem['imagen']; ?>" class="image-preview"><?php endif; ?>
                                    <input type="file" name="imagen_file" accept="image/*">
                                    <input type="hidden" name="imagen" value="<?php echo $editItem['imagen'] ?? ''; ?>">
                                </div>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="popular" <?php echo ($editItem['popular'] ?? false) ? 'checked' : ''; ?>> Marcar como Popular</label>
                                    <label><input type="checkbox" name="activo" <?php echo ($editItem['activo'] ?? true) ? 'checked' : ''; ?>> Activo</label>
                                </div>
                                <div class="save-btn-container"><button type="submit" class="btn btn-primary btn-block">💾
                                        Guardar Cambios</button></div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'ofertas'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Gestión de Ofertas Flash</h2><button class="btn btn-primary"
                            onclick="document.getElementById('modal-oferta').classList.add('active')">+ Nueva Oferta</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Imagen</th>
                                <th>Título</th>
                                <th>Badge</th>
                                <th>Tiempo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['ofertas'] as $index => $oferta): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><img src="../<?php echo $oferta['imagen']; ?>" alt=""></td>
                                    <td><?php echo htmlspecialchars($oferta['titulo']); ?></td>
                                    <td><span class="badge badge-orange"><?php echo $oferta['badge']; ?></span></td>
                                    <td><?php echo $oferta['tiempo']; ?></td>
                                    <td><?php if ($oferta['activa']): ?><span
                                                class="badge badge-green">Activa</span><?php else: ?><span
                                                class="badge badge-gray">Inactiva</span><?php endif; ?></td>
                                    <td class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_up">
                                            <input type="hidden" name="id" value="<?php echo $oferta['id']; ?>">
                                            <input type="hidden" name="type" value="ofertas">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === 0 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬆️</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_down">
                                            <input type="hidden" name="id" value="<?php echo $oferta['id']; ?>">
                                            <input type="hidden" name="type" value="ofertas">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === count($data['ofertas']) - 1 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬇️</button>
                                        </form>
                                        <a href="?tab=ofertas&edit=<?php echo $oferta['id']; ?>&type=oferta"
                                            class="btn btn-secondary btn-small">Editar</a>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('¿Eliminar esta oferta?')"><input type="hidden" name="action"
                                                value="delete_oferta"><input type="hidden" name="id"
                                                value="<?php echo $oferta['id']; ?>"><button type="submit"
                                                class="btn btn-danger btn-small">Eliminar</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="modal-oferta" class="modal <?php echo $editItem && $activeTab === 'ofertas' ? 'active' : ''; ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><?php echo $editItem ? 'Editar' : 'Nueva'; ?> Oferta</h3><button class="modal-close"
                                onclick="window.location.href='?tab=ofertas'">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data"
                                onsubmit="this.querySelector('button[type=submit]').innerHTML='⏳ Guardando y Enviando...'; this.querySelector('button[type=submit]').disabled=true;">
                                <input type="hidden" name="action" value="save_oferta">
                                <input type="hidden" name="id" value="<?php echo $editItem['id'] ?? ''; ?>">
                                <div class="form-group"><label>Título</label><input type="text" name="titulo"
                                        value="<?php echo $editItem['titulo'] ?? ''; ?>" required></div>
                                <div class="form-group"><label>Descripción</label><input type="text" name="descripcion"
                                        value="<?php echo $editItem['descripcion'] ?? ''; ?>" required></div>
                                <div class="form-row"
                                    style="background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0;">
                                    <div class="form-group"><label>Operador (Interno)</label><input type="text" name="operador"
                                            value="<?php echo $editItem['operador'] ?? 'Sin información'; ?>"
                                            placeholder="Nombre del operador"></div>
                                    <div class="form-group"><label>Comentarios (Interno)</label><input type="text"
                                            name="comentarios"
                                            value="<?php echo $editItem['comentarios'] ?? 'Sin información'; ?>"
                                            placeholder="Comentarios adicionales"></div>
                                </div>
                                <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 16px;">
                                    <div class="form-group">
                                        <label>Vigencia hasta (Opcional - el Chatbot dejará de ofrecerlo tras esta fecha)</label>
                                        <input type="date" name="vigencia_hasta"
                                            value="<?php echo htmlspecialchars($editItem['vigencia_hasta'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label>Información extra para el Chatbot IA (Opcional - horarios de vuelo, equipaje, señas, etc.)</label>
                                        <textarea name="info_bot" rows="2"
                                            placeholder="Ej: Cupos limitados, tarifa en base doble, incluye carry-on..."><?php echo htmlspecialchars($editItem['info_bot'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Texto del Badge</label><input type="text" name="badge"
                                            value="<?php echo $editItem['badge'] ?? '¡OFERTA ESPECIAL!'; ?>"></div>
                                    <div class="form-group"><label>Color del Badge</label><select name="badgeColor">
                                            <option value="bg-red-500" <?php echo ($editItem['badgeColor'] ?? '') === 'bg-red-500' ? 'selected' : ''; ?>>Rojo (Urgente)</option>
                                            <option value="bg-orange-500" <?php echo ($editItem['badgeColor'] ?? '') === 'bg-orange-500' ? 'selected' : ''; ?>>Naranja</option>
                                            <option value="bg-green-500" <?php echo ($editItem['badgeColor'] ?? '') === 'bg-green-500' ? 'selected' : ''; ?>>Verde</option>
                                            <option value="bg-blue-500" <?php echo ($editItem['badgeColor'] ?? '') === 'bg-blue-500' ? 'selected' : ''; ?>>Azul</option>
                                            <option value="bg-purple-500" <?php echo ($editItem['badgeColor'] ?? '') === 'bg-purple-500' ? 'selected' : ''; ?>>Violeta</option>
                                        </select></div>
                                </div>
                                <div class="form-group"><label>Tiempo Restante</label><input type="text" name="tiempo"
                                        value="<?php echo $editItem['tiempo'] ?? '48hs'; ?>" placeholder="Ej: 48hs"></div>
                                <div class="form-group">
                                    <label>Imagen/Flyer</label>
                                    <?php if ($editItem && $editItem['imagen']): ?><img
                                            src="../<?php echo $editItem['imagen']; ?>" class="image-preview"><?php endif; ?>
                                    <input type="file" name="imagen_file" accept="image/*">
                                    <input type="hidden" name="imagen" value="<?php echo $editItem['imagen'] ?? ''; ?>">
                                </div>
                                <div class="checkbox-group"><label><input type="checkbox" name="activa" <?php echo ($editItem['activa'] ?? true) ? 'checked' : ''; ?>> Oferta Activa</label></div>
                                <div class="save-btn-container"><button type="submit" class="btn btn-primary btn-block">💾
                                        Guardar Cambios</button></div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'blog'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Gestión de Blog</h2>
                        <div style="display: flex; gap: 10px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="regenerate_all_blogs">
                                <button type="submit" class="btn btn-secondary">🔄 Actualizar todos los Posts</button>
                            </form>
                            <button class="btn btn-primary"
                                onclick="document.getElementById('modal-blog').classList.add('active')">+ Nuevo Post</button>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Imagen</th>
                                <th>Título</th>
                                <th>Fecha</th>
                                <th>Slug</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['blog'] as $index => $post): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><img src="../<?php echo $post['imagen']; ?>" alt=""></td>
                                    <td><?php echo htmlspecialchars($post['titulo']); ?></td>
                                    <td><?php echo $post['fecha']; ?></td>
                                    <td><?php echo $post['slug']; ?></td>
                                    <td><?php if ($post['activo']): ?><span
                                                class="badge badge-green">Publicado</span><?php else: ?><span
                                                class="badge badge-gray">Borrador</span><?php endif; ?></td>
                                    <td class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_up">
                                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                            <input type="hidden" name="type" value="blog">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === 0 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬆️</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="move_down">
                                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                            <input type="hidden" name="type" value="blog">
                                            <button type="submit" class="btn btn-secondary btn-small" style="padding: 4px 8px;"
                                                <?php echo $index === count($data['blog']) - 1 ? 'disabled style="opacity:0.3; padding: 4px 8px;"' : ''; ?>>⬇️</button>
                                        </form>
                                        <a href="../blog/<?php echo $post['slug']; ?>.html" target="_blank"
                                            class="btn btn-success btn-small">Ver</a>
                                        <a href="?tab=blog&edit=<?php echo $post['id']; ?>&type=blog"
                                            class="btn btn-secondary btn-small">Editar</a>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('¿Eliminar este post?')"><input type="hidden" name="action"
                                                value="delete_blog"><input type="hidden" name="id"
                                                value="<?php echo $post['id']; ?>"><button type="submit"
                                                class="btn btn-danger btn-small">Eliminar</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="modal-blog" class="modal <?php echo $editItem && $activeTab === 'blog' ? 'active' : ''; ?>">
                    <div class="modal-content modal-wide">
                        <div class="modal-header">
                            <h3><?php echo $editItem ? 'Editar' : 'Nuevo'; ?> Post</h3><button class="modal-close"
                                onclick="window.location.href='?tab=blog'">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data" id="blog-form" onsubmit="return syncEditor()">
                                <input type="hidden" name="action" value="save_blog">
                                <input type="hidden" name="id" value="<?php echo $editItem['id'] ?? ''; ?>">
                                <div class="form-group"><label>Título</label><input type="text" name="titulo"
                                        value="<?php echo $editItem['titulo'] ?? ''; ?>" required></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Fecha</label><input type="text" name="fecha"
                                            value="<?php echo $editItem['fecha'] ?? date('d M, Y'); ?>"></div>
                                    <div class="form-group"><label>URL (Slug)</label><input type="text" name="slug"
                                            value="<?php echo $editItem['slug'] ?? ''; ?>"
                                            placeholder="Se genera automáticamente"></div>
                                </div>
                                <div class="form-group"><label>Extracto</label><textarea name="extracto" rows="2"
                                        placeholder="Breve descripción para la lista..."><?php echo $editItem['extracto'] ?? ''; ?></textarea>
                                </div>

                                <div class="card"
                                    style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 16px;">
                                    <h4 style="margin-bottom: 12px; color: #475569;">Optimización SEO (Opcional)</h4>
                                    <div class="form-group"><label>Título SEO</label><input type="text" name="seo_titulo"
                                            value="<?php echo $editItem['seo_titulo'] ?? ''; ?>"
                                            placeholder="Dejar vacío para usar el título del post"></div>
                                    <div class="form-group"><label>Descripción Meta</label><textarea name="seo_description"
                                            rows="2"
                                            placeholder="Dejar vacío para usar el extracto"><?php echo $editItem['seo_description'] ?? ''; ?></textarea>
                                    </div>
                                    <div class="form-group"><label>Palabras Clave</label><input type="text" name="seo_keywords"
                                            value="<?php echo $editItem['seo_keywords'] ?? ''; ?>"
                                            placeholder="viajes, turismo, playa, oferta"></div>
                                </div>

                                <!-- WYSIWYG EDITOR -->
                                <div class="form-group" style="position: relative;">
                                    <label>Contenido del Post</label>
                                    <div class="editor-tabs">
                                        <button type="button" class="editor-tab active" id="tab-visual"
                                            onclick="switchEditorTab('visual')">✏️ Visual</button>
                                        <button type="button" class="editor-tab" id="tab-html"
                                            onclick="switchEditorTab('html')">🖥️ HTML</button>
                                    </div>
                                    <div id="visual-editor-wrap">
                                        <div class="wysiwyg-toolbar" id="editor-toolbar">
                                            <!-- Text formatting -->
                                            <button type="button" onclick="editorCmd('bold')"
                                                title="Negrita (Ctrl+B)"><b>B</b></button>
                                            <button type="button" onclick="editorCmd('italic')"
                                                title="Cursiva (Ctrl+I)"><i>I</i></button>
                                            <button type="button" onclick="editorCmd('underline')"
                                                title="Subrayado (Ctrl+U)"><u>U</u></button>
                                            <button type="button" onclick="editorCmd('strikeThrough')"
                                                title="Tachado"><s>S</s></button>
                                            <span class="tb-separator"></span>

                                            <!-- Headings -->
                                            <select onchange="editorBlock(this.value); this.value='';" title="Formato">
                                                <option value="">Formato</option>
                                                <option value="P">Párrafo</option>
                                                <option value="H2">Título H2</option>
                                                <option value="H3">Subtítulo H3</option>
                                                <option value="H4">Subtítulo H4</option>
                                            </select>
                                            <span class="tb-separator"></span>

                                            <!-- Alignment -->
                                            <button type="button" onclick="editorCmd('justifyLeft')"
                                                title="Alinear izquierda">⫷</button>
                                            <button type="button" onclick="editorCmd('justifyCenter')"
                                                title="Centrar">⫸</button>
                                            <button type="button" onclick="editorCmd('justifyRight')"
                                                title="Alinear derecha">⫸</button>
                                            <button type="button" onclick="editorCmd('justifyFull')"
                                                title="Justificar">☰</button>
                                            <span class="tb-separator"></span>

                                            <!-- Lists -->
                                            <button type="button" onclick="editorCmd('insertUnorderedList')"
                                                title="Lista con viñetas">• ≡</button>
                                            <button type="button" onclick="editorCmd('insertOrderedList')"
                                                title="Lista numerada">1.
                                                ≡</button>
                                            <span class="tb-separator"></span>

                                            <!-- Link -->
                                            <button type="button" onclick="editorLink()" title="Insertar enlace">🔗</button>

                                            <!-- Image -->
                                            <button type="button" onclick="editorImage()" title="Insertar imagen">🖼️</button>
                                            <span class="tb-separator"></span>

                                            <!-- Color picker -->
                                            <div class="color-picker-wrap">
                                                <button type="button" class="color-picker-btn" onclick="toggleColorPicker()"
                                                    title="Color de texto">
                                                    A<span class="color-indicator" id="color-indicator"></span>
                                                </button>
                                                <div class="color-picker-dropdown" id="color-picker">
                                                    <div class="color-picker-grid" id="color-grid"></div>
                                                    <div class="color-hex-input">
                                                        <input type="text" id="color-hex" placeholder="#F97316" maxlength="7">
                                                        <button type="button" onclick="applyHexColor()">✓</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="tb-separator"></span>

                                            <!-- Extras -->
                                            <button type="button" onclick="editorBlock('BLOCKQUOTE')" title="Cita">❝</button>
                                            <button type="button" onclick="editorInsertHR()" title="Separador">—</button>
                                            <button type="button" onclick="editorCleanFormat()"
                                                title="Limpiar formato">🧹</button>
                                        </div>
                                        <div class="wysiwyg-editor" id="editor-area" contenteditable="true">
                                            <?php echo $editItem['contenido'] ?? '<p>Escribí tu contenido aquí...</p>'; ?>
                                        </div>
                                    </div><!-- /visual-editor-wrap -->
                                    <textarea id="html-editor" style="display:none;"
                                        placeholder="Pegá tu código HTML, CSS y JavaScript aquí..."></textarea>
                                    <textarea name="contenido" id="editor-hidden" style="display:none;"></textarea>

                                    <!-- Image toolbar (contextual) -->
                                    <div class="img-toolbar" id="img-toolbar">
                                        <span class="it-label">Tamaño</span>
                                        <button type="button" onclick="setImgClass('img-small')">Chica</button>
                                        <button type="button" onclick="setImgClass('img-medium')">Mediana</button>
                                        <button type="button" onclick="setImgClass('img-large')">Grande</button>
                                        <span class="it-label">Posición</span>
                                        <button type="button" onclick="setImgPos('img-left')">◧ Izq</button>
                                        <button type="button" onclick="setImgPos('img-center')">◫ Centro</button>
                                        <button type="button" onclick="setImgPos('img-right')">◨ Der</button>
                                        <span class="it-label"></span>
                                        <button type="button" onclick="removeSelectedImg()" style="color:#dc2626;">🗑
                                            Borrar</button>
                                    </div>
                                    <input type="file" id="editor-img-upload" accept="image/*" style="display:none;"
                                        onchange="uploadEditorImage(this)">
                                </div>

                                <div class="form-group">
                                    <label>Imagen Destacada</label>
                                    <?php if ($editItem && $editItem['imagen']): ?><img
                                            src="../<?php echo $editItem['imagen']; ?>" class="image-preview"><?php endif; ?>
                                    <input type="file" name="imagen_file" accept="image/*">
                                    <input type="hidden" name="imagen" value="<?php echo $editItem['imagen'] ?? ''; ?>">
                                </div>
                                <div class="checkbox-group"><label><input type="checkbox" name="activo" <?php echo ($editItem['activo'] ?? true) ? 'checked' : ''; ?>> Publicado</label></div>
                                <div class="save-btn-container"><button type="submit" class="btn btn-primary btn-block">💾
                                        Guardar Cambios</button></div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'config'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Configuración General</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_config">
                            <div class="form-row">
                                <div class="form-group"><label>Número WhatsApp</label><input type="text" name="whatsapp"
                                        value="<?php echo $data['config']['whatsapp'] ?? ''; ?>" placeholder="5491123456789">
                                </div>
                                <div class="form-group"><label>Email</label><input type="email" name="email"
                                        value="<?php echo $data['config']['email'] ?? ''; ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Teléfono</label><input type="text" name="telefono"
                                        value="<?php echo $data['config']['telefono'] ?? ''; ?>"></div>
                                <div class="form-group"><label>Dirección</label><input type="text" name="direccion"
                                        value="<?php echo $data['config']['direccion'] ?? ''; ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Horario</label><input type="text" name="horario"
                                        value="<?php echo $data['config']['horario'] ?? ''; ?>"></div>
                                <div class="form-group"><label>Instagram</label><input type="text" name="instagram"
                                        value="<?php echo $data['config']['instagram'] ?? ''; ?>"></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Facebook</label><input type="text" name="facebook"
                                        value="<?php echo $data['config']['facebook'] ?? ''; ?>"></div>
                                <div class="form-group"><label>Legajo</label><input type="text" name="legajo"
                                        value="<?php echo $data['config']['legajo'] ?? '16649'; ?>"></div>
                            </div>

                            <hr style="margin: 24px 0; border: 0; border-top: 1px solid #e5e5e5;">
                            <h3 style="margin-bottom: 16px; font-size: 16px; color: #333;">SEO y Analítica</h3>

                            <div class="form-group"><label>Google Analytics ID (G-XXXXXXXXXX)</label><input type="text"
                                    name="analytics_id" value="<?php echo $data['config']['analytics_id'] ?? ''; ?>"
                                    placeholder="G-XXXXXXXXXX"></div>

                            <div class="form-group"><label>Palabras Clave Globales</label><input type="text"
                                    name="meta_keywords" value="<?php echo $data['config']['meta_keywords'] ?? ''; ?>"
                                    placeholder="agencia de viajes, turismo, villa devoto, paquetes"></div>

                            <div class="form-group"><label>Descripción Global del Sitio</label><textarea name="meta_description"
                                    rows="2"
                                    placeholder="Agencia de viajes y turismo en Villa Devoto..."><?php echo $data['config']['meta_description'] ?? ''; ?></textarea>
                            </div>
                            <div style="margin-top: 24px;"><button type="submit" class="btn btn-primary">💾 Guardar
                                    Configuración</button></div>
                        </form>
                    </div>
                </div>

            <?php elseif ($activeTab === 'chatbot'): ?>
                <?php
                $geminiKeyRaw      = trim((string) env('GEMINI_API_KEY', ''));
                $geminiConfigured  = ($geminiKeyRaw !== '');
                $geminiChatModel   = trim((string) env('GEMINI_CHAT_MODEL', 'gemini-3.5-flash-lite')) ?: 'gemini-3.5-flash-lite';
                $geminiEmbedModel  = trim((string) env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001')) ?: 'gemini-embedding-001';
                $geminiStartsAq    = false;

                $supaUrlRaw        = trim((string) env('SUPABASE_URL', ''));
                $supaSecretRaw     = trim((string) (env('SUPABASE_SECRET_KEY', '') ?: env('SUPABASE_SERVICE_ROLE_KEY', '')));
                $supaConfigured    = ($supaUrlRaw !== '' && $supaSecretRaw !== '');

                ?>

                <!-- 1. Tarjetas de Estado de Conexión -->
                <div class="form-row" style="margin-bottom: 24px;">
                    <div class="card" style="padding: 20px; border-left: 4px solid <?php echo $geminiConfigured ? '#22c55e' : '#ef4444'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <h3 style="font-size: 16px; color: #111;">🧠 Google Gemini API (IA & Embeddings)</h3>
                            <?php if ($geminiConfigured): ?>
                                <span class="badge badge-green">Configurada</span>
                            <?php else: ?>
                                <span class="badge" style="background: #fee2e2; color: #dc2626;">Falta configurar</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 14px; color: #555; margin-bottom: 6px;">
                            <strong>Modelo Chat activo:</strong> <code><?php echo htmlspecialchars($geminiChatModel); ?></code>
                            &nbsp;|&nbsp;
                            <strong>Embeddings:</strong> <code><?php echo htmlspecialchars($geminiEmbedModel); ?></code> (768 dims)
                        </p>
                        <?php if ($geminiStartsAq): ?>
                            <div style="margin-top: 10px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 13px; color: #92400e;">
                                ℹ️ <strong>Nota sobre GEMINI_API_KEY:</strong> La clave actual en <code>admin/.env</code> comienza con <code>AQ.</code> en lugar del formato estándar <code>AIzaSy...</code>. Si al sincronizar recibís error de <em>API Key not valid</em> en Google AI Studio, generá una clave que empiece con <code>AIzaSy...</code> desde <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #d97706; font-weight: 600;">aistudio.google.com</a> y pegala en <code>admin/.env</code>.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card" style="padding: 20px; border-left: 4px solid <?php echo $supaConfigured ? '#22c55e' : '#f59e0b'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <h3 style="font-size: 16px; color: #111;">🗄️ Supabase (PostgreSQL + pgvector + Leads)</h3>
                            <?php if ($supaConfigured): ?>
                                <span class="badge badge-green">Conectado</span>
                            <?php else: ?>
                                <span class="badge badge-orange">Falta SUPABASE_SECRET_KEY</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 14px; color: #555; margin-bottom: 6px;">
                            <strong>URL Proyecto:</strong> <code><?php echo htmlspecialchars($supaUrlRaw ?: 'No definida'); ?></code>
                        </p>
                        <?php if (!$supaConfigured): ?>
                            <div style="margin-top: 10px; padding: 10px 12px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; font-size: 13px; color: #9a3412;">
                                ⚠️ <strong>Acción requerida:</strong> Pegá tu clave secreta (<code>sb_secret_...</code> o <code>service_role</code>) en la variable <code>SUPABASE_SECRET_KEY=</code> dentro del archivo <code>admin/.env</code> para habilitar la sincronización vectorial y la gestión de Leads.
                            </div>
                        <?php else: ?>
                            <p style="font-size: 13px; color: #16a34a; margin-top: 6px;">
                                ✔️ Clave secreta cargada correctamente en <code>admin/.env</code>. Sincronización automática activa al guardar o eliminar paquetes, ofertas y posts.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Acción Principal: Sincronizar Todo con Supabase -->
                <div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); border: 1px solid #fed7aa;">
                    <div style="padding: 20px 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
                        <div>
                            <h2 style="font-size: 18px; color: #9a3412; margin-bottom: 6px;">🚀 Base de Conocimiento Vectorial (RAG)</h2>
                            <p style="font-size: 14px; color: #555; max-width: 760px;">
                                Generá y actualizá en un clic los vectores de IA (768 dimensiones) de la <strong>Información General de la Agencia</strong>, los <strong>10 Casos Canónicos de Asistencia Coris</strong>, todos los <strong>Paquetes activos</strong>, las <strong>Ofertas Flash activas</strong> y los <strong>Posts publicados del Blog</strong> en Supabase.
                            </p>
                        </div>
                        <form method="POST" onsubmit="const btn = this.querySelector('button[type=submit]'); btn.innerHTML='⏳ Generando vectores IA y sincronizando...'; btn.disabled=true;">
                            <input type="hidden" name="action" value="sync_all_supabase">
                            <button type="submit" class="btn btn-primary" style="padding: 14px 24px; font-size: 15px; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);">
                                🔄 Sincronizar Todo con Supabase (Generar Vectores IA)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 3. Formulario de Conocimiento General de la Agencia y Asistencia Coris -->
                <div class="card">
                    <div class="card-header">
                        <h2>📘 Conocimiento General de la Agencia y Asistencia Coris</h2>
                    </div>
                    <div style="padding: 24px;">
                        <p style="font-size: 14px; color: #666; margin-bottom: 16px;">
                            Este texto alimenta directamente al asistente virtual sobre quiénes somos, horarios, dirección en Villa Devoto, canales de contacto, cobertura de <strong>Asistencia al Viajero Coris</strong> y cotización de aéreos o paquetes a medida.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_chatbot_info">
                            <div class="form-group">
                                <label>Texto institucional y directrices operativas para el Chatbot IA (Markdown)</label>
                                <textarea name="chatbot_general_info" rows="15" style="font-family: 'Consolas', 'Monaco', monospace; font-size: 14px; line-height: 1.6;" required><?php echo htmlspecialchars($data['chatbot_general_info'] ?? ''); ?></textarea>
                            </div>
                            <div style="display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary">💾 Guardar Conocimiento General</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($activeTab === 'leads'): ?>
                <?php
                $supabaseClientInstance = new SupabaseClient();
                $supaConfigured = $supabaseClientInstance->isConfigured();
                $recentLeads = [];
                $leadsError  = '';
                if ($supaConfigured) {
                    $recentLeads = $supabaseClientInstance->getRecentLeads(100);
                    if (empty($recentLeads) && $supabaseClientInstance->getLastError() !== '') {
                        $leadsError = $supabaseClientInstance->getLastError();
                    }
                }
                ?>
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h2>👥 Leads y Conversaciones del Chatbot</h2>
                            <p style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                Consultá los prospectos capturados en el pre-chat. Tocá <strong>"Ver Chat"</strong> en cualquier fila para desplegar bajo demanda todos los turnos que tuvo el usuario con el Asistente IA. Podés eliminar registros completos tanto de la base de datos como de archivos locales.
                            </p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <input type="text"
                                id="leadsFilterInput"
                                class="leads-filter-input"
                                placeholder="🔍 Buscar por nombre, email o teléfono..."
                                onkeyup="filterLeads(this.value)">
                            <span id="leads-count-badge" class="badge badge-orange"><?php echo count($recentLeads); ?> registros</span>
                            <a href="?tab=leads" class="btn btn-secondary btn-small" title="Recargar lista completa">🔄 Actualizar</a>
                        </div>
                    </div>

                    <?php if (!$supaConfigured): ?>
                        <div style="padding: 24px;">
                            <div class="alert alert-error" style="margin-bottom: 0;">
                                ⚠️ Para visualizar los Leads y sus conversaciones, completá la variable <code>SUPABASE_SECRET_KEY=tu_clave_secreta</code> en el archivo <code>admin/.env</code> (disponible en Supabase &rarr; Project Settings &rarr; API).
                            </div>
                        </div>
                    <?php elseif ($leadsError !== ''): ?>
                        <div style="padding: 24px;">
                            <div class="alert alert-error" style="margin-bottom: 0;">
                                ⚠️ Error al consultar Leads en Supabase: <?php echo htmlspecialchars($leadsError); ?>
                            </div>
                        </div>
                    <?php elseif (empty($recentLeads)): ?>
                        <div style="padding: 40px 24px; text-align: center; color: #6b7280;">
                            <div style="font-size: 32px; margin-bottom: 10px;">💬</div>
                            <strong style="color: #374151; font-size: 16px;">Todavía no hay leads registrados en el Chatbot.</strong>
                            <p style="font-size: 14px; margin-top: 6px;">Cuando un visitante complete sus datos para iniciar el chat en la web, aparecerá aquí automáticamente con su historial.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table id="leadsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 140px; text-align: center;">Conversación</th>
                                        <th>Fecha / Hora</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>Teléfono / WhatsApp</th>
                                        <th>Origen</th>
                                        <th style="width: 100px; text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLeads as $lead): ?>
                                        <?php
                                        $leadId    = (string) ($lead['id'] ?? '');
                                        $rawDate   = (string) ($lead['created_at'] ?? '');
                                        $formattedDate = $rawDate;
                                        if (!empty($rawDate)) {
                                            try {
                                                $dt = new DateTime($rawDate);
                                                $dt->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
                                                $formattedDate = $dt->format('d/m/Y H:i') . ' hs';
                                            } catch (\Throwable $e) {
                                                $formattedDate = $rawDate;
                                            }
                                        }
                                        $leadName  = (string) ($lead['full_name'] ?? 'Sin nombre');
                                        $leadEmail = (string) ($lead['email'] ?? '');
                                        $leadPhone = (string) ($lead['phone_e164'] ?? '');
                                        $leadSrc   = (string) ($lead['source'] ?? 'chatbot_web');
                                        $leadMeta  = (is_array($lead['metadata'] ?? null)) ? $lead['metadata'] : [];
                                        $pageUrl   = (string) ($leadMeta['page_url'] ?? '');
                                        $waDigits  = preg_replace('/\D+/', '', $leadPhone);
                                        $waText    = urlencode("Hola {$leadName}! Te escribimos de Relax Tours por tu consulta en nuestro asistente web.");
                                        ?>
                                        <!-- Fila Principal del Lead -->
                                        <tr id="lead-row-<?php echo htmlspecialchars($leadId); ?>"
                                            class="lead-row"
                                            data-lead-name="<?php echo htmlspecialchars(mb_strtolower($leadName, 'UTF-8')); ?>"
                                            data-lead-display-name="<?php echo htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-lead-email="<?php echo htmlspecialchars(mb_strtolower($leadEmail, 'UTF-8')); ?>"
                                            data-lead-phone="<?php echo htmlspecialchars($leadPhone); ?>"
                                            data-lead-source="<?php echo htmlspecialchars(mb_strtolower($leadSrc, 'UTF-8')); ?>">
                                            <td style="text-align: center;">
                                                <button type="button"
                                                    class="btn-toggle-chat"
                                                    id="btn-toggle-<?php echo htmlspecialchars($leadId); ?>"
                                                    onclick="toggleLeadChat('<?php echo htmlspecialchars($leadId); ?>')"
                                                    title="Ver conversación completa por turnos">
                                                    <span class="arrow">▶</span>
                                                    <span class="txt">Ver Chat</span>
                                                </button>
                                            </td>
                                            <td style="white-space: nowrap; font-size: 13px; color: #555;">
                                                <?php echo htmlspecialchars($formattedDate); ?>
                                            </td>
                                            <td>
                                                <strong style="color: #111827;"><?php echo htmlspecialchars($leadName); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($leadEmail !== ''): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($leadEmail); ?>" style="color: #F97316; text-decoration: none; font-size: 14px;">
                                                        <?php echo htmlspecialchars($leadEmail); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #9ca3af;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                    <span style="font-size: 14px;"><?php echo htmlspecialchars($leadPhone); ?></span>
                                                    <?php if ($waDigits !== ''): ?>
                                                        <a href="https://wa.me/<?php echo htmlspecialchars($waDigits); ?>?text=<?php echo $waText; ?>"
                                                            target="_blank"
                                                            class="btn btn-success btn-small"
                                                            style="padding: 4px 10px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"
                                                            title="Abrir WhatsApp oficial con mensaje predefinido">
                                                            💬 WhatsApp
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-gray"><?php echo htmlspecialchars($leadSrc); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button"
                                                    class="btn btn-danger btn-small"
                                                    onclick="confirmDeleteLead('<?php echo htmlspecialchars($leadId); ?>')"
                                                    style="padding: 6px 12px; font-size: 12px;"
                                                    title="Eliminar usuario, datos y conversación entera de la base de datos y de cualquier archivo">
                                                    🗑️ Borrar
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Fila Desplegable del Chat (Acordeón Lazy Load) -->
                                        <tr id="chat-drawer-<?php echo htmlspecialchars($leadId); ?>"
                                            class="chat-drawer-row"
                                            style="display: none;"
                                            data-loaded="false">
                                            <td colspan="7" class="chat-drawer-cell">
                                                <div class="chat-drawer-container">
                                                    <div class="chat-drawer-header">
                                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                                            <strong style="color: #0f172a; font-size: 15px;">💬 Historial de Conversación — <?php echo htmlspecialchars($leadName); ?></strong>
                                                            <span style="font-size: 12px; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 4px;">ID: <?php echo htmlspecialchars($leadId); ?></span>
                                                            <?php if (!empty($pageUrl)): ?>
                                                                <span style="font-size: 12px; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 4px;" title="<?php echo htmlspecialchars($pageUrl); ?>">
                                                                    📍 Origen: <?php echo htmlspecialchars(strlen($pageUrl) > 42 ? substr($pageUrl, 0, 39) . '...' : $pageUrl); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <button type="button"
                                                                class="btn btn-secondary btn-small"
                                                                onclick="refreshLeadChat('<?php echo htmlspecialchars($leadId); ?>')"
                                                                style="font-size: 12px; padding: 5px 10px;">
                                                                🔄 Recargar mensajes
                                                            </button>
                                                            <button type="button"
                                                                class="btn btn-secondary btn-small"
                                                                onclick="toggleLeadChat('<?php echo htmlspecialchars($leadId); ?>')"
                                                                style="font-size: 12px; padding: 5px 10px;">
                                                                ✖ Cerrar
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Contenedor dinámico de turnos -->
                                                    <div id="chat-stream-<?php echo htmlspecialchars($leadId); ?>" class="chat-turns-stream">
                                                        <div style="text-align: center; padding: 24px; color: #64748b;">
                                                             ⏳ Cargando mensajes desde la base de datos...
                                                        </div>
                                                    </div>

                                                    <div class="chat-drawer-footer">
                                                        <div id="chat-count-<?php echo htmlspecialchars($leadId); ?>" style="font-size: 13px; color: #64748b; font-weight: 500;">
                                                            Consultando turnos...
                                                        </div>
                                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                            <?php if ($waDigits !== ''): ?>
                                                                <a href="https://wa.me/<?php echo htmlspecialchars($waDigits); ?>?text=<?php echo $waText; ?>"
                                                                    target="_blank"
                                                                    class="btn btn-success btn-small"
                                                                    style="font-size: 12px;">
                                                                    💬 Continuar por WhatsApp
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($leadEmail !== ''): ?>
                                                                <a href="mailto:<?php echo htmlspecialchars($leadEmail); ?>"
                                                                    class="btn btn-secondary btn-small"
                                                                    style="font-size: 12px;">
                                                                    ✉️ Enviar Correo
                                                                </a>
                                                            <?php endif; ?>
                                                            <button type="button"
                                                                class="btn btn-danger btn-small"
                                                                onclick="confirmDeleteLead('<?php echo htmlspecialchars($leadId); ?>')"
                                                                style="font-size: 12px;">
                                                                🗑️ Borrar Registro Completo
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario Oculto de Respaldo para Eliminación sin JS -->
                <form id="fallbackDeleteLeadForm" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="delete_lead">
                    <input type="hidden" name="lead_id" id="fallbackDeleteLeadId" value="">
                </form>
            <?php endif; ?>
        </main>
    <?php endif; ?>
    <script>
        // ===== WYSIWYG EDITOR JS =====
        const editorArea = document.getElementById('editor-area');
        const editorHidden = document.getElementById('editor-hidden');
        const imgToolbar = document.getElementById('img-toolbar');
        let selectedImg = null;
        let savedSelection = null;

        // --- Color palette ---
        const COLORS = [
            '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#ffffff',
            '#dc2626', '#ea580c', '#F97316', '#eab308', '#22c55e', '#06b6d4', '#3b82f6',
            '#7c3aed', '#ec4899', '#f43f5e', '#f97316', '#84cc16', '#14b8a6', '#0ea5e9',
            '#6366f1', '#a855f7', '#d946ef', '#be123c', '#9a3412', '#854d0e', '#166534'
        ];

        function initColorPicker() {
            const grid = document.getElementById('color-grid');
            if (!grid) return;
            COLORS.forEach(c => {
                const swatch = document.createElement('div');
                swatch.className = 'color-swatch';
                swatch.style.background = c;
                swatch.onclick = () => applyColor(c);
                grid.appendChild(swatch);
            });
        }

        function toggleColorPicker() {
            const picker = document.getElementById('color-picker');
            if (!picker) return;
            saveSelection();
            picker.classList.toggle('visible');
        }

        function applyColor(color) {
            restoreSelection();
            document.execCommand('foreColor', false, color);
            document.getElementById('color-indicator').style.background = color;
            document.getElementById('color-picker').classList.remove('visible');
            editorArea.focus();
        }

        function applyHexColor() {
            const hex = document.getElementById('color-hex').value.trim();
            if (/^#[0-9A-Fa-f]{3,6}$/.test(hex)) {
                applyColor(hex);
            } else {
                alert('Ingresá un color hexadecimal válido (ej: #F97316)');
            }
        }

        // --- Selection save/restore (for color picker) ---
        function saveSelection() {
            const sel = window.getSelection();
            if (sel.rangeCount > 0) {
                savedSelection = sel.getRangeAt(0);
            }
        }

        function restoreSelection() {
            if (savedSelection) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedSelection);
            }
        }

        // --- Core editor commands ---
        function editorCmd(cmd) {
            if (!editorArea) return;
            editorArea.focus();
            document.execCommand(cmd, false, null);
        }

        function editorBlock(tag) {
            if (!tag || !editorArea) return;
            editorArea.focus();
            document.execCommand('formatBlock', false, tag);
        }

        function editorInsertHR() {
            if (!editorArea) return;
            editorArea.focus();
            document.execCommand('insertHTML', false, '<hr>');
        }

        // --- Clean format: removes inline AND block formatting ---
        function editorCleanFormat() {
            if (!editorArea) return;
            editorArea.focus();
            document.execCommand('removeFormat', false, null);
            // Also reset block format to paragraph
            // Check if inside a blockquote and unwrap manually
            const sel = window.getSelection();
            if (sel.anchorNode) {
                let node = sel.anchorNode;
                while (node && node !== editorArea) {
                    if (node.tagName === 'BLOCKQUOTE') {
                        // Replace blockquote with its children
                        const parent = node.parentNode;
                        while (node.firstChild) {
                            parent.insertBefore(node.firstChild, node);
                        }
                        parent.removeChild(node);
                        break;
                    }
                    node = node.parentNode;
                }
            }
            document.execCommand('formatBlock', false, 'P');
        }

        function editorLink() {
            const sel = window.getSelection();
            const selectedText = sel.toString();

            // Check if we're inside a link already
            let parentLink = null;
            if (sel.anchorNode) {
                let node = sel.anchorNode;
                while (node && node !== editorArea) {
                    if (node.tagName === 'A') { parentLink = node; break; }
                    node = node.parentNode;
                }
            }

            const currentUrl = parentLink ? parentLink.href : '';
            const url = prompt('URL del enlace:', currentUrl || 'https://');

            if (url === null) return; // Cancelled
            if (url === '') {
                // Remove link
                if (parentLink) {
                    document.execCommand('unlink', false, null);
                }
                return;
            }

            editorArea.focus();
            if (selectedText) {
                document.execCommand('createLink', false, url);
                // Make link open in new tab
                const links = editorArea.querySelectorAll('a');
                links.forEach(l => {
                    if (l.href === url) l.target = '_blank';
                });
            } else {
                const linkText = prompt('Texto del enlace:', 'Click aquí');
                if (linkText) {
                    document.execCommand('insertHTML', false, `<a href="${url}" target="_blank">${linkText}</a>`);
                }
            }
        }

        // --- Image upload ---
        function editorImage() {
            document.getElementById('editor-img-upload').click();
        }

        function uploadEditorImage(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append('image', file);

            // Show uploading feedback
            const placeholder = document.createElement('div');
            placeholder.style.cssText = 'padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666; margin: 8px 0;';
            placeholder.textContent = '⏳ Subiendo imagen...';
            editorArea.appendChild(placeholder);

            fetch('index.php?ajax=upload_blog_image', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    placeholder.remove();
                    if (data.success) {
                        const img = document.createElement('img');
                        img.src = '../' + data.url;
                        img.className = 'img-large img-center';
                        img.alt = 'Imagen del blog';
                        img.onclick = function () { selectImg(this); };
                        editorArea.appendChild(img);
                        // Add a paragraph after the image for continued editing
                        const p = document.createElement('p');
                        p.innerHTML = '<br>';
                        editorArea.appendChild(p);
                    } else {
                        alert('Error al subir imagen: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(err => {
                    placeholder.remove();
                    alert('Error de red al subir imagen');
                    console.error(err);
                });

            input.value = ''; // Reset file input
        }

        // --- Image toolbar ---
        function selectImg(img) {
            // Deselect previous
            if (selectedImg) selectedImg.classList.remove('img-selected');
            selectedImg = img;
            img.classList.add('img-selected');

            // Position toolbar near image
            const rect = img.getBoundingClientRect();
            const editorRect = editorArea.closest('.form-group').getBoundingClientRect();
            imgToolbar.style.top = (rect.top - editorRect.top - 8) + 'px';
            imgToolbar.style.left = (rect.left - editorRect.left) + 'px';
            imgToolbar.classList.add('visible');
        }

        function setImgClass(cls) {
            if (!selectedImg) return;
            selectedImg.classList.remove('img-small', 'img-medium', 'img-large');
            selectedImg.classList.add(cls);
        }

        function setImgPos(cls) {
            if (!selectedImg) return;
            selectedImg.classList.remove('img-left', 'img-center', 'img-right');
            selectedImg.classList.add(cls);
        }

        function removeSelectedImg() {
            if (!selectedImg) return;
            if (confirm('¿Eliminar esta imagen?')) {
                selectedImg.remove();
                selectedImg = null;
                imgToolbar.classList.remove('visible');
            }
        }

        // Click on editor images to select them
        if (editorArea) {
            editorArea.addEventListener('click', function (e) {
                if (e.target.tagName === 'IMG') {
                    e.preventDefault();
                    selectImg(e.target);
                } else {
                    // Deselect image
                    if (selectedImg) {
                        selectedImg.classList.remove('img-selected');
                        selectedImg = null;
                        imgToolbar.classList.remove('visible');
                    }
                }
            });
        }

        // Close color picker when clicking outside
        document.addEventListener('click', function (e) {
            const picker = document.getElementById('color-picker');
            const btn = document.querySelector('.color-picker-btn');
            if (picker && !picker.contains(e.target) && btn && !btn.contains(e.target)) {
                picker.classList.remove('visible');
            }
        });

        // --- Editor Tab Switching ---
        let currentEditorMode = 'visual';
        const htmlEditor = document.getElementById('html-editor');
        const visualWrap = document.getElementById('visual-editor-wrap');
        const tabVisual = document.getElementById('tab-visual');
        const tabHtml = document.getElementById('tab-html');

        function switchEditorTab(mode) {
            if (!editorArea || !htmlEditor || !visualWrap) return;
            if (mode === currentEditorMode) return;

            if (mode === 'html') {
                // Visual → HTML: copy WYSIWYG innerHTML to textarea
                editorArea.querySelectorAll('.img-selected').forEach(img => img.classList.remove('img-selected'));
                if (imgToolbar) imgToolbar.classList.remove('visible');
                selectedImg = null;
                htmlEditor.value = formatHtml(editorArea.innerHTML);
                visualWrap.style.display = 'none';
                htmlEditor.style.display = 'block';
                htmlEditor.focus();
                tabVisual.classList.remove('active');
                tabHtml.classList.add('active');
            } else {
                // HTML → Visual: copy textarea content back to WYSIWYG
                editorArea.innerHTML = htmlEditor.value;
                // Re-attach click handlers for images
                editorArea.querySelectorAll('img').forEach(img => {
                    img.onclick = function () { selectImg(this); };
                });
                htmlEditor.style.display = 'none';
                visualWrap.style.display = 'block';
                editorArea.focus();
                tabHtml.classList.remove('active');
                tabVisual.classList.add('active');
            }
            currentEditorMode = mode;
        }

        // Simple HTML formatter for readability in the HTML tab
        function formatHtml(html) {
            // Light formatting: add newlines after closing block tags
            return html
                .replace(/></g, '>\n<')
                .replace(/\n\s*\n/g, '\n')
                .trim();
        }

        // --- Sync editor content to hidden textarea on submit ---
        function syncEditor() {
            if (!editorHidden) return true;
            if (currentEditorMode === 'html' && htmlEditor) {
                // HTML mode: save the textarea content directly
                editorHidden.value = htmlEditor.value;
            } else if (editorArea) {
                // Visual mode: save the WYSIWYG innerHTML
                editorArea.querySelectorAll('.img-selected').forEach(img => img.classList.remove('img-selected'));
                editorHidden.value = editorArea.innerHTML;
            }
            return true;
        }

        // --- Keyboard shortcuts + Enter key handler ---
        if (editorArea) {
            editorArea.addEventListener('keydown', function (e) {
                // Ctrl shortcuts
                if (e.ctrlKey || e.metaKey) {
                    switch (e.key.toLowerCase()) {
                        case 'b': e.preventDefault(); editorCmd('bold'); break;
                        case 'i': e.preventDefault(); editorCmd('italic'); break;
                        case 'u': e.preventDefault(); editorCmd('underline'); break;
                    }
                    return;
                }

                // Enter key: break out of H2, H3, H4, BLOCKQUOTE
                if (e.key === 'Enter' && !e.shiftKey) {
                    const sel = window.getSelection();
                    if (!sel.anchorNode) return;

                    let block = sel.anchorNode;
                    // Walk up to find the block-level parent
                    while (block && block !== editorArea) {
                        const tag = block.tagName;
                        if (tag && ['H2', 'H3', 'H4', 'BLOCKQUOTE'].includes(tag)) {
                            // Check if cursor is at end of this block
                            const range = sel.getRangeAt(0);
                            const cloned = range.cloneRange();
                            cloned.selectNodeContents(block);
                            cloned.setStart(range.endContainer, range.endOffset);
                            const textAfter = cloned.toString().trim();

                            if (textAfter === '') {
                                // Cursor is at the end — break out to a new <p>
                                e.preventDefault();
                                const p = document.createElement('p');
                                p.innerHTML = '<br>';
                                block.parentNode.insertBefore(p, block.nextSibling);
                                // Move cursor into the new paragraph
                                const newRange = document.createRange();
                                newRange.setStart(p, 0);
                                newRange.collapse(true);
                                sel.removeAllRanges();
                                sel.addRange(newRange);
                            }
                            return;
                        }
                        block = block.parentNode;
                    }
                }
            });
        // ===== LEADS & CONVERSATION ACCORDION JS =====
        function toggleLeadChat(leadId) {
            const row = document.getElementById('lead-row-' + leadId);
            const drawer = document.getElementById('chat-drawer-' + leadId);
            const btn = document.getElementById('btn-toggle-' + leadId);
            if (!row || !drawer) return;

            const isOpen = (drawer.style.display !== 'none');
            if (isOpen) {
                drawer.style.display = 'none';
                row.classList.remove('is-open');
                if (btn) {
                    btn.querySelector('.txt').textContent = 'Ver Chat';
                }
            } else {
                drawer.style.display = '';
                row.classList.add('is-open');
                if (btn) {
                    btn.querySelector('.txt').textContent = 'Ocultar';
                }
                if (drawer.dataset.loaded !== 'true') {
                    loadLeadChat(leadId);
                }
            }
        }

        function refreshLeadChat(leadId) {
            const drawer = document.getElementById('chat-drawer-' + leadId);
            if (drawer) {
                drawer.dataset.loaded = 'false';
                loadLeadChat(leadId);
            }
        }

        function loadLeadChat(leadId) {
            const row = document.getElementById('lead-row-' + leadId);
            const stream = document.getElementById('chat-stream-' + leadId);
            const countInfo = document.getElementById('chat-count-' + leadId);
            const drawer = document.getElementById('chat-drawer-' + leadId);
            if (!stream || !drawer) return;

            if (drawer.dataset.loading === 'true') {
                return;
            }
            drawer.dataset.loading = 'true';

            const leadName = row?.dataset?.leadDisplayName || 'Usuario';

            stream.innerHTML = '<div style="text-align: center; padding: 24px; color: #64748b;">⏳ Consultando historial de mensajes en Supabase...</div>';
            if (countInfo) countInfo.textContent = 'Cargando conversación...';

            fetch('index.php?ajax=get_lead_chat&lead_id=' + encodeURIComponent(leadId))
                .then(r => {
                    if (!r.ok) {
                        return r.json().then(
                            errData => Promise.reject(new Error(errData.error || ('Error HTTP ' + r.status))),
                            () => Promise.reject(new Error('Error del servidor (HTTP ' + r.status + ')'))
                        );
                    }
                    return r.json();
                })
                .then(data => {
                    drawer.dataset.loading = 'false';
                    if (!data.ok) {
                        stream.innerHTML = '<div style="padding: 16px; background: #fee2e2; color: #b91c1c; border-radius: 8px;">⚠️ ' + escapeHtmlChat(data.error || 'Error al obtener mensajes') + '</div>';
                        if (countInfo) countInfo.textContent = 'Error al cargar';
                        return;
                    }

                    drawer.dataset.loaded = 'true';
                    const messages = data.messages || [];

                    if (countInfo) {
                        countInfo.textContent = messages.length === 0
                            ? '0 mensajes registrados'
                            : (messages.length + (messages.length === 1 ? ' mensaje registrado' : ' mensajes registrados en orden cronológico'));
                    }

                    if (messages.length === 0) {
                        stream.innerHTML = '<div style="text-align: center; padding: 32px 16px; color: #64748b;">💬 Este usuario completó el pre-chat pero todavía no envió mensajes en la conversación.</div>';
                        return;
                    }

                    let html = '';
                    messages.forEach((msg) => {
                        const isUser = (msg.role === 'user');
                        const roleName = isUser ? leadName : 'Relax IA';
                        const avatarIcon = isUser ? '👤' : '🤖';
                        const timeStr = formatChatTimestamp(msg.created_at);

                        html += '<div class="chat-turn ' + (isUser ? 'turn-user' : 'turn-model') + '">';
                        html += '  <div class="turn-header">';
                        html += '    <span>' + avatarIcon + ' ' + escapeHtmlChat(roleName) + '</span>';
                        if (timeStr) {
                            html += '    <span class="turn-time" title="' + escapeHtmlChat(msg.created_at || '') + '">' + escapeHtmlChat(timeStr) + '</span>';
                        }
                        html += '  </div>';
                        html += '  <div class="turn-bubble">';
                        html += renderChatMessageContent(msg.message || '');
                        html += '  </div>';
                        html += '</div>';
                    });

                    stream.innerHTML = html;
                    stream.scrollTop = stream.scrollHeight;
                })
                .catch(err => {
                    drawer.dataset.loading = 'false';
                    stream.innerHTML = '<div style="padding: 16px; background: #fee2e2; color: #b91c1c; border-radius: 8px;">⚠️ Error al cargar conversación: ' + escapeHtmlChat(err.message) + '</div>';
                    if (countInfo) countInfo.textContent = 'Error de conexión';
                });
        }

        function formatChatTimestamp(isoDate) {
            if (!isoDate) return '';
            try {
                const d = new Date(isoDate);
                if (isNaN(d.getTime())) return isoDate;
                return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' hs (' + d.toLocaleDateString('es-AR') + ')';
            } catch (e) {
                return isoDate;
            }
        }

        function escapeHtmlChat(str) {
            const p = document.createElement('p');
            p.textContent = str || '';
            return p.innerHTML;
        }

        function renderChatMessageContent(rawText) {
            let escaped = escapeHtmlChat(rawText);
            // Formatear viñetas limpias
            escaped = escaped.replace(/(?:^|\n)\s*[\-\*]\s+(.*?)(?=(?:\n|$))/g, '\n• $1');
            // Formatear negritas
            escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            // Formatear enlaces sanitizados
            escaped = escaped.replace(/\[(.*?)\]\((https?:\/\/[^\s\)\"\']+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
            // Saltos de línea
            escaped = escaped.replace(/\n/g, '<br>');
            return escaped;
        }

        function confirmDeleteLead(leadId) {
            const row = document.getElementById('lead-row-' + leadId);
            const leadName = row?.dataset?.leadDisplayName || 'este usuario';
            const msg = '¿Estás seguro de que deseás eliminar a "' + leadName + '"?\n\nEsta acción borrará definitivamente:\n• Los datos personales del usuario\n• La conversación completa de la base de datos (Supabase)\n• Cualquier registro en archivos JSON o memoria\n\nEsta acción es irreversible.';
            if (!confirm(msg)) {
                return;
            }

            fetch('index.php?ajax=delete_lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lead_id: leadId })
            })
            .then(r => {
                if (!r.ok) {
                    return r.json().catch(() => ({ ok: false, message: 'Error del servidor (HTTP ' + r.status + ')' }));
                }
                return r.json();
            })
            .then(data => {
                if (data.ok) {
                    const drawer = document.getElementById('chat-drawer-' + leadId);
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.98)';
                        setTimeout(() => row.remove(), 300);
                    }
                    if (drawer) {
                        drawer.remove();
                    }
                    setTimeout(() => {
                        const filterInput = document.getElementById('leadsFilterInput');
                        const q = (filterInput?.value || '').trim();
                        if (q) {
                            filterLeads(q);
                        } else {
                            const badge = document.getElementById('leads-count-badge');
                            if (badge) {
                                const remaining = document.querySelectorAll('.lead-row:not([style*="display: none"])').length;
                                badge.textContent = remaining + ' registros';
                            }
                        }
                    }, 350);
                } else {
                    const fbForm = document.getElementById('fallbackDeleteLeadForm');
                    const fbInput = document.getElementById('fallbackDeleteLeadId');
                    if (fbForm && fbInput) {
                        fbInput.value = leadId;
                        fbForm.submit();
                    } else {
                        alert('⚠️ ' + (data.message || 'No se pudo eliminar el lead.'));
                    }
                }
            })
            .catch(() => {
                const fbForm = document.getElementById('fallbackDeleteLeadForm');
                const fbInput = document.getElementById('fallbackDeleteLeadId');
                if (fbForm && fbInput) {
                    fbInput.value = leadId;
                    fbForm.submit();
                }
            });
        }

        function filterLeads(query) {
            const q = (query || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.lead-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.dataset.leadName || '';
                const email = row.dataset.leadEmail || '';
                const phone = row.dataset.leadPhone || '';
                const src = row.dataset.leadSource || '';
                const leadId = row.id.replace('lead-row-', '');
                const drawer = document.getElementById('chat-drawer-' + leadId);
                const btn = document.getElementById('btn-toggle-' + leadId);

                const matches = !q || name.includes(q) || email.includes(q) || phone.includes(q) || src.includes(q);

                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    if (drawer) {
                        drawer.style.display = 'none';
                        row.classList.remove('is-open');
                        if (btn) {
                            btn.querySelector('.txt').textContent = 'Ver Chat';
                        }
                    }
                }
            });

            const badge = document.getElementById('leads-count-badge');
            if (badge) {
                if (q) {
                    badge.textContent = visibleCount + ' coincidentes';
                } else {
                    badge.textContent = visibleCount + ' registros';
                }
            }
        }

        // Init
        initColorPicker();
    </script>
</body>

</html>