<?php
/**
 * Relax Tours - ContentRepository
 * Capa centralizada de persistencia de contenido dinámico (CMS).
 * Implementa el patrón Write-Through con PostgreSQL en Supabase como Fuente Única de Verdad (SSOT)
 * y una caché materializada local en `data/content.json` (auto-regenerable ante despliegues de Git).
 * También gestiona el ciclo de vida de imágenes tanto en disco local como en Supabase Storage (bucket `relax-media`).
 */

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/GeminiClient.php';

class ContentRepository
{
    private static ?ContentRepository $instance = null;
    private string $cacheFile;
    private SupabaseClient $supabase;
    private ?array $memoryData = null;
    private string $lastSyncError = '';

    public function __construct(?string $cacheFile = null, ?SupabaseClient $supabase = null)
    {
        $this->cacheFile = $cacheFile ?: (__DIR__ . '/../data/content.json');
        $this->supabase = $supabase ?: new SupabaseClient();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Devuelve el último error de sincronización con Supabase (si ocurrió degradación agraciada).
     */
    public function getLastSyncError(): string
    {
        return $this->lastSyncError;
    }

    /**
     * Lee y valida de forma defensiva el archivo de caché local data/content.json si existe.
     */
    private function readLocalDiskCache(): ?array
    {
        if (!is_file($this->cacheFile) || !is_readable($this->cacheFile)) {
            return null;
        }

        $raw = @file_get_contents($this->cacheFile);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['config']) && is_array($decoded['config'])) {
            return $decoded;
        }

        return null;
    }

    /**
     * Estructura por defecto de emergencia si no existe caché en disco ni conexión a Supabase.
     */
    private function getDefaultStructure(): array
    {
        return [
            'paquetes'             => [],
            'ofertas'              => [],
            'blog'                 => [],
            'config'               => [
                'whatsapp'         => '5491138593924',
                'email'            => 'info@relaxtours.com.ar',
                'telefono'         => '+54 11 7079-8079',
                'direccion'        => 'Av. Francisco Beiró 4219, Villa Devoto, CABA',
                'horario'          => 'Lun-Vie 10-13hs / 14-19hs. Sab 10-13hs',
                'instagram'        => 'relax.tours',
                'facebook'         => 'relaxtours.ar',
                'legajo'           => '15.548',
                'analytics_id'     => 'ZV0G9YTSQP',
                'meta_keywords'    => 'Agencia de viajes, turismo, empresa de viajes y turismo, villa devoto, paquetes',
                'meta_description' => 'Agencia de viajes y turismo en Villa Devoto.',
            ],
            'chatbot_general_info' => '',
        ];
    }

    /**
     * Devuelve toda la estructura de contenido de la agencia.
     * Si el archivo local no existe (ej. tras un deploy de Git), lo auto-reconstruye desde Supabase.
     * Si Supabase falla (timeout o credenciales inválidas), preserva la caché local como escudo de disponibilidad.
     */
    public function getAllData(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && $this->memoryData !== null) {
            return $this->memoryData;
        }

        // 1. Intentar leer desde caché en disco si existe y no se fuerza refresco
        if (!$forceRefresh) {
            $cached = $this->readLocalDiskCache();
            if ($cached !== null) {
                $this->memoryData = $cached;
                return $cached;
            }
        }

        // 2. Si falta la caché local o se fuerza refresco, auto-hidratar desde Supabase con degradación agraciada
        return $this->rebuildLocalCacheFromSupabase();
    }

    /**
     * Consulta Supabase y regenera atómicamente data/content.json.
     * Si Supabase no está configurado o sufre un error de red/timeout/credenciales,
     * NO sobrescribe data/content.json y retorna el snapshot local existente (Graceful Degradation).
     */
    public function rebuildLocalCacheFromSupabase(): array
    {
        $this->lastSyncError = '';
        $existingCache = $this->readLocalDiskCache() ?? $this->memoryData;

        if (!$this->supabase->isConfigured()) {
            $this->lastSyncError = 'Supabase no está configurado.';
            if ($existingCache !== null) {
                $this->memoryData = $existingCache;
                return $existingCache;
            }
            $default = $this->getDefaultStructure();
            $this->memoryData = $default;
            return $default;
        }

        $errors = [];

        $packages = $this->supabase->getCmsPackages();
        if ($this->supabase->getLastError() !== '' || $this->supabase->getLastHttpCode() < 200 || $this->supabase->getLastHttpCode() >= 300) {
            $errors[] = 'cms_packages: ' . ($this->supabase->getLastError() ?: 'HTTP ' . $this->supabase->getLastHttpCode());
        }

        // Fail-fast: si la primera consulta falló por red/timeout/credenciales, no bloquear multiplicando por 4 el timeout
        $offers = [];
        $blogPosts = [];
        $settings = null;

        if (empty($errors)) {
            $offers = $this->supabase->getCmsOffers();
            if ($this->supabase->getLastError() !== '' || $this->supabase->getLastHttpCode() < 200 || $this->supabase->getLastHttpCode() >= 300) {
                $errors[] = 'cms_offers: ' . ($this->supabase->getLastError() ?: 'HTTP ' . $this->supabase->getLastHttpCode());
            }
        }

        if (empty($errors)) {
            $blogPosts = $this->supabase->getCmsBlogPosts();
            if ($this->supabase->getLastError() !== '' || $this->supabase->getLastHttpCode() < 200 || $this->supabase->getLastHttpCode() >= 300) {
                $errors[] = 'cms_blog_posts: ' . ($this->supabase->getLastError() ?: 'HTTP ' . $this->supabase->getLastHttpCode());
            }
        }

        if (empty($errors)) {
            $settings = $this->supabase->getCmsSettings();
            if (!is_array($settings) || $this->supabase->getLastError() !== '' || $this->supabase->getLastHttpCode() < 200 || $this->supabase->getLastHttpCode() >= 300) {
                $errors[] = 'cms_settings: ' . ($this->supabase->getLastError() ?: 'HTTP ' . $this->supabase->getLastHttpCode());
            }
        }

        // Escudo de disponibilidad (Graceful Degradation): si falló la comunicación con Supabase,
        // preservar intacta la caché local en disco sin sobrescribirla con datos vacíos o parciales.
        if (!empty($errors)) {
            $this->lastSyncError = implode(' | ', $errors);
            if ($existingCache !== null) {
                $this->memoryData = $existingCache;
                return $existingCache;
            }
            $default = $this->getDefaultStructure();
            $this->memoryData = $default;
            return $default;
        }

        $config = isset($settings['config']) && is_array($settings['config']) ? $settings['config'] : [];
        $chatbotGeneralInfo = (string) ($settings['chatbot_general_info'] ?? '');

        // Si las tablas de Supabase aún están totalmente vacías pero existe caché local válida, preservar config de respaldo
        if (empty($packages) && empty($offers) && empty($blogPosts) && empty($config) && $existingCache !== null) {
            $this->lastSyncError = 'Tablas CMS en Supabase vacías; preservando caché local existente.';
            $this->memoryData = $existingCache;
            return $existingCache;
        }

        if (empty($config)) {
            $config = $existingCache['config'] ?? $this->getDefaultStructure()['config'];
        }

        // Normalizar paquetes
        $normPackages = array_map(function ($p) {
            return [
                'id'             => (string) $p['id'],
                'nombre'         => (string) ($p['nombre'] ?? ''),
                'destino'        => (string) ($p['destino'] ?? ''),
                'duracion'       => (string) ($p['duracion'] ?? ''),
                'incluye'        => isset($p['incluye']) && is_array($p['incluye']) ? $p['incluye'] : [],
                'precio'         => (float) ($p['precio'] ?? 0),
                'moneda'         => (string) ($p['moneda'] ?? 'ARS'),
                'imagen'         => (string) ($p['imagen'] ?? ''),
                'popular'        => !empty($p['popular']),
                'activo'         => !empty($p['activo']),
                'operador'       => (string) ($p['operador'] ?? 'Sin información'),
                'comentarios'    => (string) ($p['comentarios'] ?? 'Sin información'),
                'vigencia_hasta' => !empty($p['vigencia_hasta']) ? $p['vigencia_hasta'] : null,
                'info_bot'       => (string) ($p['info_bot'] ?? ''),
            ];
        }, $packages);

        // Normalizar ofertas
        $normOffers = array_map(function ($o) {
            return [
                'id'             => (string) $o['id'],
                'titulo'         => (string) ($o['titulo'] ?? ''),
                'descripcion'    => (string) ($o['descripcion'] ?? ''),
                'imagen'         => (string) ($o['imagen'] ?? ''),
                'badge'          => (string) ($o['badge'] ?? ''),
                'badgeColor'     => (string) ($o['badge_color'] ?? $o['badgeColor'] ?? 'orange'),
                'tiempo'         => (string) ($o['tiempo'] ?? ''),
                'activa'         => !empty($o['activa']),
                'operador'       => (string) ($o['operador'] ?? 'Sin información'),
                'comentarios'    => (string) ($o['comentarios'] ?? 'Sin información'),
                'vigencia_hasta' => !empty($o['vigencia_hasta']) ? $o['vigencia_hasta'] : null,
                'info_bot'       => (string) ($o['info_bot'] ?? ''),
            ];
        }, $offers);

        // Normalizar blog
        $normBlog = array_map(function ($b) {
            return [
                'id'              => (string) $b['id'],
                'titulo'          => (string) ($b['titulo'] ?? ''),
                'extracto'        => (string) ($b['extracto'] ?? ''),
                'contenido'       => (string) ($b['contenido'] ?? ''),
                'imagen'          => (string) ($b['imagen'] ?? ''),
                'fecha'           => (string) ($b['fecha'] ?? ''),
                'slug'            => (string) ($b['slug'] ?? ''),
                'seo_titulo'      => (string) ($b['seo_titulo'] ?? ''),
                'seo_description' => (string) ($b['seo_description'] ?? ''),
                'seo_keywords'    => (string) ($b['seo_keywords'] ?? ''),
                'activo'          => !empty($b['activo']),
            ];
        }, $blogPosts);

        $data = [
            'paquetes'             => $normPackages,
            'ofertas'              => $normOffers,
            'blog'                 => $normBlog,
            'config'               => $config,
            'chatbot_general_info' => $chatbotGeneralInfo,
        ];

        $this->saveLocalSnapshot($data);
        $this->memoryData = $data;

        // Asegurar que todas las imágenes referenciadas existan en el disco local (descargándolas de Supabase Storage si faltan tras un deploy)
        foreach ($normPackages as $p) {
            if (!empty($p['imagen'])) {
                $this->ensureLocalImageFromStorage($p['imagen']);
            }
        }
        foreach ($normOffers as $o) {
            if (!empty($o['imagen'])) {
                $this->ensureLocalImageFromStorage($o['imagen']);
            }
        }
        foreach ($normBlog as $b) {
            if (!empty($b['imagen'])) {
                $this->ensureLocalImageFromStorage($b['imagen']);
            }
            foreach ($this->extractInlineBlogImages($b['contenido'] ?? '') as $inlineImg) {
                $this->ensureLocalImageFromStorage($inlineImg);
            }
        }

        return $data;
    }

    /**
     * Guarda el snapshot JSON de manera atómica con bloqueo de concurrencia (flock)
     * y archivo temporal + rename() para prevenir lecturas parciales o condiciones de carrera.
     */
    public function saveLocalSnapshot(array $data): bool
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || $json === '') {
            return false;
        }

        $expectedBytes = strlen($json);
        $lockFile = $this->cacheFile . '.lock';
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp !== false) {
            @flock($lockFp, LOCK_EX);
        }

        try {
            $randSuffix = bin2hex(random_bytes(6));
            $tmpFile = $this->cacheFile . '.tmp.' . getmypid() . '.' . $randSuffix;

            $fp = @fopen($tmpFile, 'wb');
            if ($fp === false) {
                return false;
            }

            $written = @fwrite($fp, $json);
            @fflush($fp);
            if (function_exists('fsync')) {
                @fsync($fp);
            }
            @fclose($fp);

            if ($written === false || $written !== $expectedBytes) {
                @unlink($tmpFile);
                return false;
            }

            // Reintentar rename hasta 3 veces (maneja bloqueos breves de descriptores en Windows)
            $renamed = false;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (@rename($tmpFile, $this->cacheFile)) {
                    $renamed = true;
                    break;
                }
                usleep(10000); // 10ms
            }

            if (!$renamed) {
                $copied = (file_put_contents($this->cacheFile, $json, LOCK_EX) === $expectedBytes);
                @unlink($tmpFile);
                if (!$copied) {
                    return false;
                }
            }

            clearstatcache(true, $this->cacheFile);
            $this->memoryData = $data;
            return true;
        } finally {
            if ($lockFp !== false) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }

    // =========================================================================
    // GESTIÓN DE PAQUETES
    // =========================================================================

    public function savePackage(array $pkg, ?string $oldImage = null): bool
    {
        $data = $this->getAllData();
        $isNew = true;
        $prevImage = null;

        // Calcular sort_order
        $sortOrder = 0;
        foreach ($data['paquetes'] as $idx => &$p) {
            if ($p['id'] === $pkg['id']) {
                $sortOrder = $idx;
                $prevImage = $p['imagen'] ?? null;
                $p = $pkg;
                $isNew = false;
                break;
            }
        }
        unset($p);

        if ($isNew) {
            array_unshift($data['paquetes'], $pkg);
        }

        $effectiveOldImage = !empty($oldImage) ? $oldImage : $prevImage;

        // Sincronizar imagen con Supabase Storage
        if (!empty($pkg['imagen'])) {
            $this->syncImageToStorage($pkg['imagen']);
        }
        if (!empty($effectiveOldImage) && $effectiveOldImage !== ($pkg['imagen'] ?? '')) {
            $this->deleteImageFromStorageAndDisk($effectiveOldImage, 'paquetes-images');
        }

        // Guardar en Supabase PostgreSQL
        $pkg['sort_order'] = $sortOrder;
        if ($this->supabase->isConfigured()) {
            $this->supabase->upsertCmsPackage($pkg);
        }

        // Regenerar caché local
        $this->saveLocalSnapshot($data);
        return true;
    }

    public function deletePackage(string $id): bool
    {
        $data = $this->getAllData();
        $deletedPkg = null;

        $newPackages = [];
        foreach ($data['paquetes'] as $p) {
            if ($p['id'] === $id) {
                $deletedPkg = $p;
            } else {
                $newPackages[] = $p;
            }
        }
        $data['paquetes'] = $newPackages;

        if ($deletedPkg) {
            if (!empty($deletedPkg['imagen'])) {
                $this->deleteImageFromStorageAndDisk($deletedPkg['imagen'], 'paquetes-images');
            }
            if ($this->supabase->isConfigured()) {
                $this->supabase->deleteCmsPackage($id);
            }
            $this->saveLocalSnapshot($data);
            return true;
        }

        return false;
    }

    // =========================================================================
    // GESTIÓN DE OFERTAS
    // =========================================================================

    public function saveOffer(array $oferta, ?string $oldImage = null): bool
    {
        $data = $this->getAllData();
        $isNew = true;
        $prevImage = null;

        $sortOrder = 0;
        foreach ($data['ofertas'] as $idx => &$o) {
            if ($o['id'] === $oferta['id']) {
                $sortOrder = $idx;
                $prevImage = $o['imagen'] ?? null;
                $o = $oferta;
                $isNew = false;
                break;
            }
        }
        unset($o);

        if ($isNew) {
            array_unshift($data['ofertas'], $oferta);
        }

        $effectiveOldImage = !empty($oldImage) ? $oldImage : $prevImage;

        if (!empty($oferta['imagen'])) {
            $this->syncImageToStorage($oferta['imagen']);
        }
        if (!empty($effectiveOldImage) && $effectiveOldImage !== ($oferta['imagen'] ?? '')) {
            $this->deleteImageFromStorageAndDisk($effectiveOldImage, 'ofertas-images');
        }

        $oferta['sort_order'] = $sortOrder;
        if ($this->supabase->isConfigured()) {
            $this->supabase->upsertCmsOffer($oferta);
        }

        $this->saveLocalSnapshot($data);
        return true;
    }

    public function deleteOffer(string $id): bool
    {
        $data = $this->getAllData();
        $deletedOffer = null;

        $newOffers = [];
        foreach ($data['ofertas'] as $o) {
            if ($o['id'] === $id) {
                $deletedOffer = $o;
            } else {
                $newOffers[] = $o;
            }
        }
        $data['ofertas'] = $newOffers;

        if ($deletedOffer) {
            if (!empty($deletedOffer['imagen'])) {
                $this->deleteImageFromStorageAndDisk($deletedOffer['imagen'], 'ofertas-images');
            }
            if ($this->supabase->isConfigured()) {
                $this->supabase->deleteCmsOffer($id);
            }
            $this->saveLocalSnapshot($data);
            return true;
        }

        return false;
    }

    // =========================================================================
    // GESTIÓN DE ARTÍCULOS DE BLOG
    // =========================================================================

    public function saveBlogPost(array $post, ?string $oldImage = null): bool
    {
        $data = $this->getAllData();
        $isNew = true;
        $prevImage = null;
        $prevContent = '';
        $prevSlug = '';

        $sortOrder = 0;
        foreach ($data['blog'] as $idx => &$b) {
            if ($b['id'] === $post['id']) {
                $sortOrder = $idx;
                $prevImage = $b['imagen'] ?? null;
                $prevContent = (string) ($b['contenido'] ?? '');
                $prevSlug = (string) ($b['slug'] ?? '');
                $b = $post;
                $isNew = false;
                break;
            }
        }
        unset($b);

        if ($isNew) {
            array_unshift($data['blog'], $post);
        }

        $effectiveOldImage = !empty($oldImage) ? $oldImage : $prevImage;

        if (!empty($post['imagen'])) {
            $this->syncImageToStorage($post['imagen']);
        }
        if (!empty($effectiveOldImage) && $effectiveOldImage !== ($post['imagen'] ?? '')) {
            $this->deleteImageFromStorageAndDisk($effectiveOldImage, 'blog-images');
        }

        // Sincronizar imágenes inline nuevas y eliminar imágenes inline descartadas del contenido
        $oldInlineImages = $this->extractInlineBlogImages($prevContent);
        $newInlineImages = $this->extractInlineBlogImages((string) ($post['contenido'] ?? ''));
        foreach ($newInlineImages as $inlineImg) {
            $this->syncImageToStorage($inlineImg);
        }
        foreach (array_diff($oldInlineImages, $newInlineImages) as $removedInlineImg) {
            $this->deleteImageFromStorageAndDisk($removedInlineImg, 'blog-content');
        }

        // Si cambió el slug de un post existente, eliminar el archivo HTML estático con el slug viejo
        if ($prevSlug !== '' && !empty($post['slug']) && $prevSlug !== $post['slug']) {
            $oldHtmlFile = __DIR__ . '/../blog/' . basename($prevSlug) . '.html';
            if (file_exists($oldHtmlFile) && is_file($oldHtmlFile)) {
                @unlink($oldHtmlFile);
            }
        }

        $post['sort_order'] = $sortOrder;
        if ($this->supabase->isConfigured()) {
            $this->supabase->upsertCmsBlogPost($post);
        }

        $this->saveLocalSnapshot($data);
        return true;
    }

    public function deleteBlogPost(string $id): bool
    {
        $data = $this->getAllData();
        $deletedPost = null;

        $newBlog = [];
        foreach ($data['blog'] as $b) {
            if ($b['id'] === $id) {
                $deletedPost = $b;
            } else {
                $newBlog[] = $b;
            }
        }
        $data['blog'] = $newBlog;

        if ($deletedPost) {
            if (!empty($deletedPost['imagen'])) {
                $this->deleteImageFromStorageAndDisk($deletedPost['imagen'], 'blog-images');
            }
            // Eliminar también cualquier imagen inline del post
            foreach ($this->extractInlineBlogImages((string) ($deletedPost['contenido'] ?? '')) as $inlineImg) {
                $this->deleteImageFromStorageAndDisk($inlineImg, 'blog-content');
            }
            // Eliminar el archivo HTML estático si existía
            if (!empty($deletedPost['slug'])) {
                $htmlFile = __DIR__ . '/../blog/' . basename($deletedPost['slug']) . '.html';
                if (file_exists($htmlFile) && is_file($htmlFile)) {
                    @unlink($htmlFile);
                }
            }
            if ($this->supabase->isConfigured()) {
                $this->supabase->deleteCmsBlogPost($id);
            }
            $this->saveLocalSnapshot($data);
            return true;
        }

        return false;
    }

    // =========================================================================
    // CONFIGURACIÓN Y CHATBOT
    // =========================================================================

    public function saveConfig(array $config): bool
    {
        $data = $this->getAllData();
        $data['config'] = $config;

        if ($this->supabase->isConfigured()) {
            $this->supabase->saveCmsSettings('config', $config);
        }

        return $this->saveLocalSnapshot($data);
    }

    public function saveChatbotInfo(string $info): bool
    {
        $data = $this->getAllData();
        $data['chatbot_general_info'] = $info;

        if ($this->supabase->isConfigured()) {
            $this->supabase->saveCmsSettings('chatbot_general_info', $info);
        }

        return $this->saveLocalSnapshot($data);
    }

    // =========================================================================
    // REORDENAMIENTO
    // =========================================================================

    public function reorder(string $type, string $id, string $direction): bool
    {
        $data = $this->getAllData();
        if (!isset($data[$type]) || !is_array($data[$type])) {
            return false;
        }

        $list = &$data[$type];
        $index = -1;
        foreach ($list as $key => $item) {
            if ($item['id'] === $id) {
                $index = $key;
                break;
            }
        }

        if ($index === -1) {
            return false;
        }

        if ($direction === 'move_up' && $index > 0) {
            $temp = $list[$index];
            $list[$index] = $list[$index - 1];
            $list[$index - 1] = $temp;
        } elseif ($direction === 'move_down' && $index < count($list) - 1) {
            $temp = $list[$index];
            $list[$index] = $list[$index + 1];
            $list[$index + 1] = $temp;
        } else {
            return false;
        }

        // Actualizar sort_order en Supabase
        if ($this->supabase->isConfigured()) {
            foreach ($list as $orderIdx => $item) {
                if ($type === 'paquetes') {
                    $item['sort_order'] = $orderIdx;
                    $this->supabase->upsertCmsPackage($item);
                } elseif ($type === 'ofertas') {
                    $item['sort_order'] = $orderIdx;
                    $this->supabase->upsertCmsOffer($item);
                } elseif ($type === 'blog') {
                    $item['sort_order'] = $orderIdx;
                    $this->supabase->upsertCmsBlogPost($item);
                }
            }
        }

        return $this->saveLocalSnapshot($data);
    }

    // =========================================================================
    // SINCRONIZACIÓN DE IMÁGENES CON SUPABASE STORAGE (BUCKET: relax-media)
    // =========================================================================

    public function syncImageToStorage(string $relativeImgPath): ?string
    {
        if (!$this->supabase->isConfigured()) {
            return null;
        }

        $cleanPath = ltrim(str_replace('../', '', $relativeImgPath), '/');
        $localFile = __DIR__ . '/../' . $cleanPath;

        if (!file_exists($localFile) || !is_readable($localFile)) {
            return null;
        }

        $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        $mime = 'image/webp';
        if ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'svg') {
            $mime = 'image/svg+xml';
        }

        return $this->supabase->uploadStorageFile('relax-media', $cleanPath, $localFile, $mime, true);
    }

    public function deleteImageFromStorageAndDisk(string $relativeImgPath, string $expectedFolder = ''): bool
    {
        $cleanPath = ltrim(str_replace('../', '', $relativeImgPath), '/');
        if ($cleanPath === '') {
            return false;
        }

        // Eliminar del disco local y de Supabase Storage si pertenece a las carpetas esperadas de uploads
        if ($expectedFolder === '' || strpos($cleanPath, $expectedFolder) !== false) {
            $localFile = __DIR__ . '/../' . $cleanPath;
            if (file_exists($localFile) && is_file($localFile)) {
                @unlink($localFile);
            }
            if ($this->supabase->isConfigured()) {
                $this->supabase->deleteStorageFile('relax-media', $cleanPath);
            }
        }

        return true;
    }

    /**
     * Extrae rutas relativas de imágenes inline subidas en el contenido de un post (images/blog-content/...).
     */
    public function extractInlineBlogImages(string $html): array
    {
        if ($html === '') {
            return [];
        }
        if (preg_match_all('#images/blog-content/[a-zA-Z0-9._\-]+#i', $html, $matches)) {
            return array_values(array_unique($matches[0]));
        }
        return [];
    }

    /**
     * Si una imagen referenciada no existe en el disco local (ej. tras un deploy de Git),
     * la descarga automáticamente desde el bucket público `relax-media` de Supabase Storage.
     */
    public function ensureLocalImageFromStorage(string $relativeImgPath): bool
    {
        $cleanPath = ltrim(str_replace('../', '', $relativeImgPath), '/');
        if ($cleanPath === '') {
            return false;
        }

        $localFile = __DIR__ . '/../' . $cleanPath;
        if (file_exists($localFile) && filesize($localFile) > 0) {
            return true;
        }

        $supaUrl = rtrim(trim((string) env('SUPABASE_URL', '')), '/');
        if ($supaUrl === '') {
            return false;
        }

        $publicUrl = $supaUrl . '/storage/v1/object/public/relax-media/' . $cleanPath;
        $ch = curl_init($publicUrl);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && is_string($raw) && strlen($raw) > 0) {
            $dir = dirname($localFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return @file_put_contents($localFile, $raw) !== false;
        }

        return false;
    }
}
