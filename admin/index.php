<?php
/**
 * RELAX TOURS - BACKOFFICE
 * Panel de administración en PHP
 */

session_start();

// --- CARGAR VARIABLES DE ENTORNO DESDE .ENV ---
if (file_exists(__DIR__ . '/.env')) {
    $lineas = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0)
            continue;
        list($name, $value) = explode('=', $linea, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
    }
}

// --- CARGAR VARIABLES DE ENTORNO DESDE .ENV ---
if (file_exists(__DIR__ . '/.env')) {
    $lineas = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0)
            continue;
        list($name, $value) = explode('=', $linea, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
    }
}

// Configuración
define('DATA_FILE', __DIR__ . '/../data/content.json');
define('ADMIN_USER', $_ENV['ADMIN_USER'] ?? 'admin_por_defecto');
define('ADMIN_PASS', $_ENV['ADMIN_PASS'] ?? 'pass_por_defecto');

// PHPMailer initialization
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../PHPMailer/PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/PHPMailer-master/src/SMTP.php';

function loadData()
{
    if (!file_exists(DATA_FILE)) {
        return ['paquetes' => [], 'ofertas' => [], 'blog' => [], 'config' => []];
    }
    return json_decode(file_get_contents(DATA_FILE), true);
}

function saveData($data)
{
    return file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateId()
{
    return uniqid();
}
function slugify($text)
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

function processAndConvertWebP($sourcePath, $destinationPath, $maxWidth = 1200, $quality = 80)
{
    // Validar si la librería GD está instalada
    if (!function_exists('gd_info')) {
        return "Error: La librería GD no está activada en el servidor.";
    }
    $info = getimagesize($sourcePath);
    if (!$info)
        return "Error: No es una imagen válida.";

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
            echo json_encode(['success' => true, 'url' => 'images/blog-content/' . $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => $result]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
    }
    exit;
}

// Procesar acciones
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = loadData();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save_paquete':
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
                'comentarios' => !empty($_POST['comentarios']) ? $_POST['comentarios'] : 'Sin información'
            ];
            if (!empty($_FILES['imagen_file']['tmp_name'])) {
                $filename = 'paquete-' . $paquete['id'] . '-' . time() . '.webp';
                $dest = __DIR__ . '/../images/paquetes-images/' . $filename;
                $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 800);
                if ($res === true) {
                    $paquete['imagen'] = 'images/paquetes-images/' . $filename;
                }
            }
            $found = false;
            foreach ($data['paquetes'] as &$p) {
                if ($p['id'] === $paquete['id']) {
                    $p = $paquete;
                    $found = true;
                    break;
                }
            }
            if (!$found)
                array_unshift($data['paquetes'], $paquete); // Add to beginning (Newest First)
            saveData($data);
            header('Location: index.php?tab=paquetes&msg=guardado');
            exit;

        case 'delete_paquete':
            foreach ($data['paquetes'] as $p) {
                if ($p['id'] === $_POST['id'] && !empty($p['imagen'])) {
                    $imgFile = __DIR__ . '/../' . $p['imagen'];
                    if (file_exists($imgFile) && strpos($p['imagen'], 'paquetes-images/') !== false) {
                        unlink($imgFile);
                    }
                    break;
                }
            }
            $data['paquetes'] = array_values(array_filter($data['paquetes'], fn($p) => $p['id'] !== $_POST['id']));
            saveData($data);
            header('Location: index.php?tab=paquetes&msg=eliminado');
            exit;

        case 'save_oferta':
            $isNew = empty($_POST['id']);
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
                'comentarios' => !empty($_POST['comentarios']) ? $_POST['comentarios'] : 'Sin información'
            ];
            if (!empty($_FILES['imagen_file']['tmp_name'])) {
                $filename = 'oferta-' . $oferta['id'] . '-' . time() . '.webp';
                $dest = __DIR__ . '/../images/ofertas-images/' . $filename;
                $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 800);
                if ($res === true) {
                    $oferta['imagen'] = 'images/ofertas-images/' . $filename;
                }
            }
            $found = false;
            foreach ($data['ofertas'] as &$o) {
                if ($o['id'] === $oferta['id']) {
                    $o = $oferta;
                    $found = true;
                    break;
                }
            }
            if (!$found)
                array_unshift($data['ofertas'], $oferta); // Add to beginning (Newest First)
            saveData($data);

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
                    $mail->Username = $_ENV['SMTP_USER'] ?? '';
                    $mail->Password = $_ENV['SMTP_PASS'] ?? '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@relaxtours.com.ar', 'Relax Tours Intranet');
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

            header('Location: index.php?tab=ofertas&msg=' . $mailMsg . '&err=' . urlencode($errorMsg));
            exit;

        case 'delete_oferta':
            foreach ($data['ofertas'] as $o) {
                if ($o['id'] === $_POST['id'] && !empty($o['imagen'])) {
                    $imgFile = __DIR__ . '/../' . $o['imagen'];
                    if (file_exists($imgFile) && strpos($o['imagen'], 'ofertas-images/') !== false) {
                        unlink($imgFile);
                    }
                    break;
                }
            }
            $data['ofertas'] = array_values(array_filter($data['ofertas'], fn($o) => $o['id'] !== $_POST['id']));
            saveData($data);
            header('Location: index.php?tab=ofertas&msg=eliminado');
            exit;

        case 'save_blog':
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
            if (!empty($_FILES['imagen_file']['tmp_name'])) {
                $filename = 'blog-' . $blog['id'] . '-' . time() . '.webp';
                $dest = __DIR__ . '/../images/blog-images/' . $filename;
                $res = processAndConvertWebP($_FILES['imagen_file']['tmp_name'], $dest, 1200);
                if ($res === true) {
                    $blog['imagen'] = 'images/blog-images/' . $filename;
                }
            }
            $found = false;
            foreach ($data['blog'] as &$b) {
                if ($b['id'] === $blog['id']) {
                    $b = $blog;
                    $found = true;
                    break;
                }
            }
            if (!$found)
                array_unshift($data['blog'], $blog); // Add to beginning (Newest First)
            saveData($data);
            // Reload data to get the updated blog list (in case a new post was added)
            $data = loadData();
            generateBlogPage($blog, $data['config'], $data['blog']);
            // Regenerate adjacent posts so their prev/next links stay in sync
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
            header('Location: index.php?tab=blog&msg=guardado');
            exit;

        case 'delete_blog':
            foreach ($data['blog'] as $b) {
                if ($b['id'] === $_POST['id'] && !empty($b['imagen'])) {
                    $imgFile = __DIR__ . '/../' . $b['imagen'];
                    if (file_exists($imgFile) && strpos($b['imagen'], 'blog-images/') !== false) {
                        unlink($imgFile);
                    }
                    break;
                }
            }
            $data['blog'] = array_values(array_filter($data['blog'], fn($b) => $b['id'] !== $_POST['id']));
            saveData($data);
            updateSitemap($data);
            header('Location: index.php?tab=blog&msg=eliminado');
            exit;

        case 'save_config':
            $data['config'] = [
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
            saveData($data);
            header('Location: index.php?tab=config&msg=guardado');
            exit;
    }

    if ($action === 'move_up' || $action === 'move_down') {
        $id = $_POST['id'];
        $type = $_GET['tab'] ?? 'paquetes';
        // Allow override if sent via POST, though usually we use the tab
        if (isset($_POST['type']))
            $type = $_POST['type'];

        // Map tab names to data keys if they differ (they match in this app: paquetes, ofertas, blog)
        // But let's be safe
        $dataKey = $type;

        if (isset($data[$dataKey]) && is_array($data[$dataKey])) {
            $list = &$data[$dataKey];
            $index = -1;

            // Find index
            foreach ($list as $key => $item) {
                if ($item['id'] === $id) {
                    $index = $key;
                    break;
                }
            }

            if ($index !== -1) {
                if ($action === 'move_up' && $index > 0) {
                    // Swap with previous
                    $temp = $list[$index];
                    $list[$index] = $list[$index - 1];
                    $list[$index - 1] = $temp;

                    saveData($data);
                    header("Location: index.php?tab={$type}&msg=ordenado");
                    exit;
                } elseif ($action === 'move_down' && $index < count($list) - 1) {
                    // Swap with next
                    $temp = $list[$index];
                    $list[$index] = $list[$index + 1];
                    $list[$index + 1] = $temp;

                    saveData($data);
                    header("Location: index.php?tab={$type}&msg=ordenado");
                    exit;
                }
            }
        }
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

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="es-AR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$seoTitle}</title>
        <meta name="description" content="{$seoDesc}">
        <meta name="keywords" content="{$seoKeys}">
        
        <meta property="og:type" content="article">
        <meta property="og:title" content="{$seoTitle}">
        <meta property="og:description" content="{$seoDesc}">
        <meta property="og:image" content="{$ogImage}">
        <meta property="og:url" content="{$ogUrl}">


        {$analyticsScript}
        <link rel="icon" type="image/svg+xml" href="../images/favicon.svg">
        <link rel="icon" type="image/png" sizes="96x96" href="../images/favicon-96x96.png">
        <link rel="shortcut icon" href="../images/favicon.ico">
        <link rel="apple-touch-icon" href="../images/apple-touch-icon.png">
        <link rel="manifest" href="../images/site.webmanifest">
        <meta name="theme-color" content="#F97316">
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/cookies.css">
    </head>
    <body>
        <nav class="navbar scrolled navbar-secondary">
            <div class="container">
                <a href="../index" class="navbar-logo"><img src="../images/logo-relax.webp" alt="Relax Tours"></a>
                <ul class="navbar-links">
                    <li><a href="../index#inicio">Inicio</a></li>
                    <li><a href="../nosotros">Nosotros</a></li>
                    <li><a href="../index#paquetes">Paquetes</a></li>
                    <li><a href="../index#asistencia">Asistencia</a></li>
                    <li><a href="../index#ofertas">Ofertas</a></li>
                    <li><a href="../index#blog" class="active">Tips</a></li>
                    <li><a href="../index#contacto">Contacto</a></li>
                </ul>
                <a href="../index#contacto" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Consultar
                </a>
                <button class="navbar-toggle" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </nav>
        <div class="navbar-mobile-menu" id="mobile-menu">
            <a href="../index#inicio">Inicio</a>
            <a href="../nosotros">Nosotros</a>
            <a href="../index#paquetes">Paquetes</a>
            <a href="../index#asistencia">Asistencia</a>
            <a href="../index#ofertas">Ofertas</a>
            <a href="../index#blog" class="active">Tips</a>
            <a href="../index#contacto">Contacto</a>
            <a href="../index#contacto" class="btn-primary">Consultar</a>
        </div>
        <div style="height: 80px;"></div>
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
    </body>
    </html>
    HTML;
    file_put_contents(__DIR__ . '/../blog/' . $post['slug'] . '.html', $html);
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
            <a href="?logout=1" class="btn btn-secondary">Salir</a>
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
                <div class="alert alert-success">✅ Eliminado correctamente</div><?php endif; ?>

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
                        <h2>Gestión de Blog</h2><button class="btn btn-primary"
                            onclick="document.getElementById('modal-blog').classList.add('active')">+ Nuevo Post</button>
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
        }

        // Init
        initColorPicker();
    </script>
</body>

</html>