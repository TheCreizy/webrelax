<?php
/**
 * Header Template
 * Variables:
 * $base_url - Base path for assets (e.g., './' or '../')
 * $page_title - Page title
 * $page_description - Page meta description
 * $active_page - Current page for nav active state ('inicio', 'nosotros', 'paquetes', 'ofertas', 'blog', 'contacto')
 */
$base_url = isset($base_url) ? $base_url : './';

// Load Global Config
$dataFile = __DIR__ . '/../data/content.json';
$globalConfig = [];
if (file_exists($dataFile)) {
    $contentData = json_decode(file_get_contents($dataFile), true);
    $globalConfig = $contentData['config'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="es-AR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="<?php echo $page_description ?? ($globalConfig['meta_description'] ?? 'Relax Tours - Empresa de Viajes y Turismo en Villa Devoto'); ?>">
    <?php if (!empty($globalConfig['meta_keywords'])): ?>
        <meta name="keywords" content="<?php echo htmlspecialchars($globalConfig['meta_keywords']); ?>">
    <?php endif; ?>

    <title>
        <?php echo $page_title ?? 'Relax Tours - Empresa de Viajes y Turismo'; ?>
    </title>

    <!-- Google Analytics -->

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZV0G9YTSQP"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());

        gtag('config', 'G-ZV0G9YTSQP');
    </script>

    <!-- Asi estaba antes -->

    <!-- 

<?php if (!empty($globalConfig['analytics_id'])): ?>
 
        <script async
            src="https://www.googletagmanager.com/gtag/js?id=<?php echo $globalConfig['analytics_id']; ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
    gtag('config', '<?php echo $globalConfig['analytics_id']; ?>');  
        </script>
    <?php endif; ?>
 -->
    <!-- Hasta aca estaba antes -->


    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>images/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $base_url; ?>images/favicon-96x96.png">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>images/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo $base_url; ?>images/site.webmanifest">
    <meta name="theme-color" content="#F97316">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/cookies.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php if (isset($active_page) && $active_page === 'inicio'): ?>
        <!-- Mailchimp Styles -->
        <link href="//cdn-images.mailchimp.com/embedcode/classic-061523.css" rel="stylesheet" type="text/css">
        <style type="text/css">
            #mc_embed_signup {
                background: transparent;
                clear: left;
                font: 14px Helvetica, Arial, sans-serif;
                width: 100%;
            }

            #mc_embed_signup .mc-field-group label {
                color: white !important;
            }

            #mc_embed_signup .button {
                background-color: var(--color-orange) !important;
            }

            @media (max-width: 768px) {
                #mc_embed_signup .mc-field-group {
                    padding: 12px 16px !important;
                }
            }
        </style>
    <?php endif; ?>
</head>

<body>
    <!-- NAVEGACIÓN -->
    <nav class="navbar <?php echo ($active_page !== 'inicio') ? 'scrolled navbar-secondary' : ''; ?>">
        <div class="container">
            <a href="<?php echo $base_url; ?>index.php" class="navbar-logo">
                <img src="<?php echo $base_url; ?>images/logo-relax.png" alt="Relax Tours">
            </a>
            <ul class="navbar-links">
                <li><a href="<?php echo $base_url; ?>index.php#inicio"
                        class="<?php echo ($active_page === 'inicio') ? 'active' : ''; ?>">Inicio</a></li>
                <li><a href="<?php echo $base_url; ?><?php echo ($active_page === 'nosotros') ? 'nosotros.php' : 'nosotros.php'; ?>"
                        class="<?php echo ($active_page === 'nosotros') ? 'active' : ''; ?>">Nosotros</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#paquetes">Paquetes</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#ofertas">Ofertas</a></li>
                <li><a href="<?php echo $base_url; ?>blog/index.php"
                        class="<?php echo ($active_page === 'blog') ? 'active' : ''; ?>">Tips</a></li>
                <li><a href="<?php echo $base_url; ?>index.php#contacto">Contacto</a></li>
            </ul>

            <a href="<?php echo $base_url; ?>index.php#contacto" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path
                        d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
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

    <!-- MOBILE MENU -->
    <div class="navbar-mobile-menu" id="mobile-menu">
        <a href="<?php echo $base_url; ?>index.php#inicio"
            class="<?php echo ($active_page === 'inicio') ? 'active' : ''; ?>">Inicio</a>
        <a href="<?php echo $base_url; ?>nosotros.php"
            class="<?php echo ($active_page === 'nosotros') ? 'active' : ''; ?>">Nosotros</a>
        <a href="<?php echo $base_url; ?>index.php#paquetes">Paquetes</a>
        <a href="<?php echo $base_url; ?>index.php#ofertas">Ofertas</a>
        <a href="<?php echo $base_url; ?>blog/index.php"
            class="<?php echo ($active_page === 'blog') ? 'active' : ''; ?>">Tips</a>
        <a href="<?php echo $base_url; ?>index.php#contacto">Contacto</a>
        <a href="<?php echo $base_url; ?>index.php#contacto" class="btn-primary">Consultar</a>
    </div>

    <?php if ($active_page !== 'inicio'): ?>
        <!-- ESPACIADOR PARA NAVBAR FIJO -->
        <div style="height: 80px;"></div>
    <?php endif; ?>