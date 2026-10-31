<?php
/**
 * Relax Tours - Cliente REST para Supabase (PostgREST + pgvector)
 * Compatible con Supabase Secret API keys (sb_secret_...) y legacy service_role keys (eyJ...).
 */

require_once __DIR__ . '/../includes/env-loader.php';

class SupabaseClient
{
    private string $url;
    private string $key;
    private string $lastError = '';
    private int $lastHttpCode = 0;

    public function __construct(?string $url = null, ?string $key = null)
    {
        $rawUrl = $url !== null ? $url : (string) env('SUPABASE_URL', '');
        $rawKey = $key !== null ? $key : (string) (env('SUPABASE_SECRET_KEY', '') ?: env('SUPABASE_SERVICE_ROLE_KEY', ''));

        $this->url = rtrim(trim($rawUrl), '/');
        $this->key = trim($rawKey);
    }

    /**
     * Verifica si están configuradas la URL y la clave secreta de Supabase.
     */
    public function isConfigured(): bool
    {
        return !empty($this->url) && !empty($this->key);
    }

    /**
     * Devuelve el último error registrado en una petición HTTP/cURL.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Devuelve el último código de estado HTTP recibido.
     */
    public function getLastHttpCode(): int
    {
        return $this->lastHttpCode;
    }

    /**
     * Valida un access_token emitido por Supabase Auth (GoTrue) consultando /auth/v1/user.
     * Devuelve el array asociativo del usuario si el token es válido o null si es inválido/expirado.
     *
     * @param string $accessToken
     * @return array|null
     */
    public function verifyAccessToken(string $accessToken): ?array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '' || !$this->isConfigured()) {
            $this->lastError = 'Token vacío o Supabase no configurado.';
            return null;
        }

        $url = $this->url . '/auth/v1/user';
        $ch = curl_init($url);
        if ($ch === false) {
            $this->lastError = 'No se pudo inicializar cURL para Auth.';
            return null;
        }

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $rawBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        $this->lastHttpCode = $httpCode;

        if ($rawBody === false || !empty($curlErr)) {
            $this->lastError = 'Error cURL Supabase Auth: ' . $curlErr;
            return null;
        }

        $decoded = ($rawBody !== '') ? json_decode($rawBody, true) : null;

        if ($httpCode !== 200 || !is_array($decoded) || empty($decoded['id'])) {
            $errMsg = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['msg'] ?? 'Token inválido o expirado')
                : 'Respuesta inválida de Auth';
            $this->lastError = "Supabase Auth HTTP {$httpCode}: " . (string) $errMsg;
            return null;
        }

        return $decoded;
    }

    /**
     * Inserta un nuevo lead en public.leads y devuelve su UUID (o null si falla).
     */
    public function insertLead(
        string $fullName,
        string $email,
        string $phoneE164,
        string $source = 'chatbot_web',
        array $metadata = []
    ): ?string {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado (falta SUPABASE_URL o SUPABASE_SECRET_KEY).';
            return null;
        }

        $payload = [
            'full_name'  => $fullName,
            'email'      => $email,
            'phone_e164' => $phoneE164,
            'source'     => $source,
            'metadata'   => (object) $metadata,
        ];

        $response = $this->request(
            'POST',
            '/rest/v1/leads',
            $payload,
            ['Prefer: return=representation']
        );

        if (is_array($response) && isset($response[0]['id'])) {
            return (string) $response[0]['id'];
        }
        if (is_array($response) && isset($response['id'])) {
            return (string) $response['id'];
        }

        return null;
    }

    /**
     * Inserta o actualiza (UPSERT por external_id) un documento vectorizado en public.knowledge_base.
     */
    public function upsertKnowledge(
        string $externalId,
        string $category,
        string $title,
        string $contentMarkdown,
        ?string $validUntil,
        array $metadata,
        array $embedding,
        bool $isActive = true
    ): bool {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado.';
            return false;
        }

        $payload = [
            'external_id'      => $externalId,
            'category'         => $category,
            'title'            => $title,
            'content_markdown' => $contentMarkdown,
            'valid_until'      => (!empty($validUntil)) ? $validUntil : null,
            'metadata'         => (object) $metadata,
            'embedding'        => array_values($embedding),
            'is_active'        => $isActive,
            'updated_at'       => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $response = $this->request(
            'POST',
            '/rest/v1/knowledge_base?on_conflict=external_id',
            $payload,
            ['Prefer: resolution=merge-duplicates,return=representation']
        );

        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300 && $response !== null;
    }

    /**
     * Elimina un registro de public.knowledge_base según su external_id.
     */
    public function deleteKnowledgeByExternalId(string $externalId): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado.';
            return false;
        }

        $endpoint = '/rest/v1/knowledge_base?external_id=eq.' . urlencode($externalId);
        $this->request('DELETE', $endpoint);

        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Ejecuta búsqueda semántica vectorial mediante la función RPC public.match_knowledge.
     */
    public function matchKnowledge(array $queryEmbedding, float $threshold = 0.40, int $count = 4): array
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado.';
            return [];
        }

        $payload = [
            'query_embedding' => array_values($queryEmbedding),
            'match_threshold' => $threshold,
            'match_count'     => $count,
        ];

        $response = $this->request('POST', '/rest/v1/rpc/match_knowledge', $payload);

        if (is_array($response)) {
            return $response;
        }

        return [];
    }

    /**
     * Registra un mensaje de conversación ('user' o 'model') en public.chat_messages.
     */
    public function insertChatMessage(string $leadId, string $role, string $message): bool
    {
        if (!$this->isConfigured() || empty($leadId)) {
            return false;
        }

        $role = in_array($role, ['user', 'model'], true) ? $role : 'user';

        $payload = [
            'lead_id' => $leadId,
            'role'    => $role,
            'message' => $message,
        ];

        $this->request('POST', '/rest/v1/chat_messages', $payload, ['Prefer: return=minimal']);

        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Obtiene los leads más recientes capturados para visualización en el panel Admin.
     */
    public function getRecentLeads(int $limit = 50): array
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado.';
            return [];
        }

        $limit = max(1, min(200, (int) $limit));
        $endpoint = '/rest/v1/leads?select=*&order=created_at.desc&limit=' . $limit;

        $response = $this->request('GET', $endpoint);

        return is_array($response) ? $response : [];
    }

    /**
     * Obtiene los mensajes de chat para un lead específico ordenados cronológicamente por turnos.
     *
     * @param string $leadId UUID del lead
     * @return array
     */
    public function getLeadChatMessages(string $leadId): array
    {
        $leadId = trim($leadId);
        if (!$this->isConfigured() || empty($leadId)) {
            $this->lastError = 'Supabase no está configurado o leadId vacío.';
            return [];
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $leadId)) {
            $this->lastError = 'ID de lead inválido (no es un UUID).';
            return [];
        }

        $cleanId = rawurlencode($leadId);
        $endpoint = '/rest/v1/chat_messages?lead_id=eq.' . $cleanId . '&order=created_at.asc';

        $response = $this->request('GET', $endpoint);

        return is_array($response) ? $response : [];
    }

    /**
     * Elimina completamente un lead de public.leads y sus mensajes en public.chat_messages.
     *
     * @param string $leadId UUID del lead
     * @return bool
     */
    public function deleteLead(string $leadId): bool
    {
        $leadId = trim($leadId);
        if (!$this->isConfigured() || empty($leadId)) {
            $this->lastError = 'Supabase no está configurado o leadId vacío.';
            return false;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $leadId)) {
            $this->lastError = 'ID de lead inválido (no es un UUID).';
            return false;
        }

        $cleanId = rawurlencode($leadId);

        // 1. Eliminar explícitamente los mensajes asociados en public.chat_messages
        $this->request('DELETE', '/rest/v1/chat_messages?lead_id=eq.' . $cleanId);
        $msgsCode = $this->lastHttpCode;

        // 2. Eliminar el registro del lead en public.leads
        $this->request('DELETE', '/rest/v1/leads?id=eq.' . $cleanId);
        $leadCode = $this->lastHttpCode;

        return ($msgsCode >= 200 && $msgsCode < 300) && ($leadCode >= 200 && $leadCode < 300);
    }

    /**
     * Sube un archivo binario a un bucket de Supabase Storage.
     */
    public function uploadStorageFile(
        string $bucket,
        string $remotePath,
        string $localFilePath,
        string $mimeType = 'image/webp',
        bool $upsert = true
    ): ?string {
        if (!$this->isConfigured()) {
            $this->lastError = 'Supabase no está configurado.';
            return null;
        }

        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            $this->lastError = "El archivo local no existe o no es legible: {$localFilePath}";
            return null;
        }

        $fileContent = file_get_contents($localFilePath);
        if ($fileContent === false) {
            $this->lastError = "No se pudo leer el archivo local: {$localFilePath}";
            return null;
        }

        $cleanPath = ltrim($remotePath, '/');
        $endpoint = "/storage/v1/object/{$bucket}/{$cleanPath}";

        $headers = [
            'Content-Type: ' . $mimeType,
        ];
        if ($upsert) {
            $headers[] = 'x-upsert: true';
        }

        $response = $this->rawRequest('POST', $endpoint, $fileContent, $headers);

        if ($this->lastHttpCode >= 200 && $this->lastHttpCode < 300) {
            return "{$this->url}/storage/v1/object/public/{$bucket}/{$cleanPath}";
        }

        return null;
    }

    /**
     * Elimina un archivo de un bucket de Supabase Storage.
     */
    public function deleteStorageFile(string $bucket, string $remotePath): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $cleanPath = ltrim($remotePath, '/');
        $endpoint = "/storage/v1/object/{$bucket}";
        $payload = ['prefixes' => [$cleanPath]];

        $response = $this->request('DELETE', $endpoint, $payload);
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Obtiene la lista de paquetes de public.cms_packages ordenados por sort_order.
     */
    public function getCmsPackages(): array
    {
        $res = $this->request('GET', '/rest/v1/cms_packages?select=*&order=sort_order.asc,created_at.desc');
        return is_array($res) ? $res : [];
    }

    /**
     * Inserta o actualiza un paquete en public.cms_packages.
     */
    public function upsertCmsPackage(array $pkg): bool
    {
        $payload = [
            'id'             => (string) $pkg['id'],
            'nombre'         => (string) ($pkg['nombre'] ?? ''),
            'destino'        => (string) ($pkg['destino'] ?? ''),
            'duracion'       => (string) ($pkg['duracion'] ?? ''),
            'incluye'        => isset($pkg['incluye']) && is_array($pkg['incluye']) ? $pkg['incluye'] : [],
            'precio'         => (float) ($pkg['precio'] ?? 0),
            'moneda'         => (string) ($pkg['moneda'] ?? 'ARS'),
            'imagen'         => (string) ($pkg['imagen'] ?? ''),
            'popular'        => !empty($pkg['popular']),
            'activo'         => !empty($pkg['activo']),
            'operador'       => (string) ($pkg['operador'] ?? 'Sin información'),
            'comentarios'    => (string) ($pkg['comentarios'] ?? 'Sin información'),
            'vigencia_hasta' => !empty($pkg['vigencia_hasta']) ? $pkg['vigencia_hasta'] : null,
            'info_bot'       => (string) ($pkg['info_bot'] ?? ''),
            'sort_order'     => (int) ($pkg['sort_order'] ?? 0),
            'updated_at'     => date('c'),
        ];

        $res = $this->request('POST', '/rest/v1/cms_packages?on_conflict=id', $payload, ['Prefer: resolution=merge-duplicates']);
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Elimina un paquete de public.cms_packages.
     */
    public function deleteCmsPackage(string $id): bool
    {
        $this->request('DELETE', '/rest/v1/cms_packages?id=eq.' . rawurlencode($id));
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Obtiene la lista de ofertas de public.cms_offers ordenadas por sort_order.
     */
    public function getCmsOffers(): array
    {
        $res = $this->request('GET', '/rest/v1/cms_offers?select=*&order=sort_order.asc,created_at.desc');
        return is_array($res) ? $res : [];
    }

    /**
     * Inserta o actualiza una oferta en public.cms_offers.
     */
    public function upsertCmsOffer(array $oferta): bool
    {
        $payload = [
            'id'             => (string) $oferta['id'],
            'titulo'         => (string) ($oferta['titulo'] ?? ''),
            'descripcion'    => (string) ($oferta['descripcion'] ?? ''),
            'imagen'         => (string) ($oferta['imagen'] ?? ''),
            'badge'          => (string) ($oferta['badge'] ?? ''),
            'badge_color'    => (string) ($oferta['badgeColor'] ?? $oferta['badge_color'] ?? 'orange'),
            'tiempo'         => (string) ($oferta['tiempo'] ?? ''),
            'activa'         => !empty($oferta['activa']),
            'operador'       => (string) ($oferta['operador'] ?? 'Sin información'),
            'comentarios'    => (string) ($oferta['comentarios'] ?? 'Sin información'),
            'vigencia_hasta' => !empty($oferta['vigencia_hasta']) ? $oferta['vigencia_hasta'] : null,
            'info_bot'       => (string) ($oferta['info_bot'] ?? ''),
            'sort_order'     => (int) ($oferta['sort_order'] ?? 0),
            'updated_at'     => date('c'),
        ];

        $res = $this->request('POST', '/rest/v1/cms_offers?on_conflict=id', $payload, ['Prefer: resolution=merge-duplicates']);
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Elimina una oferta de public.cms_offers.
     */
    public function deleteCmsOffer(string $id): bool
    {
        $this->request('DELETE', '/rest/v1/cms_offers?id=eq.' . rawurlencode($id));
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Obtiene la lista de artículos del blog de public.cms_blog_posts ordenados por sort_order.
     */
    public function getCmsBlogPosts(): array
    {
        $res = $this->request('GET', '/rest/v1/cms_blog_posts?select=*&order=sort_order.asc,created_at.desc');
        return is_array($res) ? $res : [];
    }

    /**
     * Inserta o actualiza un artículo de blog en public.cms_blog_posts.
     */
    public function upsertCmsBlogPost(array $post): bool
    {
        $payload = [
            'id'              => (string) $post['id'],
            'slug'            => (string) ($post['slug'] ?? ''),
            'titulo'          => (string) ($post['titulo'] ?? ''),
            'extracto'        => (string) ($post['extracto'] ?? ''),
            'contenido'       => (string) ($post['contenido'] ?? ''),
            'imagen'          => (string) ($post['imagen'] ?? ''),
            'fecha'           => (string) ($post['fecha'] ?? ''),
            'seo_titulo'      => (string) ($post['seo_titulo'] ?? ''),
            'seo_description' => (string) ($post['seo_description'] ?? ''),
            'seo_keywords'    => (string) ($post['seo_keywords'] ?? ''),
            'activo'          => !empty($post['activo']),
            'sort_order'      => (int) ($post['sort_order'] ?? 0),
            'updated_at'      => date('c'),
        ];

        $res = $this->request('POST', '/rest/v1/cms_blog_posts?on_conflict=id', $payload, ['Prefer: resolution=merge-duplicates']);
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Elimina un artículo del blog de public.cms_blog_posts.
     */
    public function deleteCmsBlogPost(string $id): bool
    {
        $this->request('DELETE', '/rest/v1/cms_blog_posts?id=eq.' . rawurlencode($id));
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Obtiene los settings (config y/o chatbot_general_info).
     */
    public function getCmsSettings(?string $key = null)
    {
        $endpoint = '/rest/v1/cms_settings?select=*';
        if ($key !== null) {
            $endpoint .= '&key=eq.' . rawurlencode($key);
        }

        $res = $this->request('GET', $endpoint);
        if (!is_array($res)) {
            return null;
        }

        if ($key !== null) {
            return !empty($res[0]['value']) ? $res[0]['value'] : null;
        }

        $all = [];
        foreach ($res as $row) {
            $all[$row['key']] = $row['value'];
        }
        return $all;
    }

    /**
     * Guarda o actualiza un setting en public.cms_settings.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function saveCmsSettings(string $key, mixed $value): bool
    {
        $payload = [
            'key'        => $key,
            'value'      => $value,
            'updated_at' => date('c'),
        ];

        $res = $this->request('POST', '/rest/v1/cms_settings?on_conflict=key', $payload, ['Prefer: resolution=merge-duplicates']);
        return $this->lastHttpCode >= 200 && $this->lastHttpCode < 300;
    }

    /**
     * Helper interno cURL para llamadas a PostgREST con payload binario o crudo.
     */
    private function rawRequest(string $method, string $endpoint, string $rawContent, array $extraHeaders = [])
    {
        $this->lastError = '';
        $this->lastHttpCode = 0;

        $url = $this->url . $endpoint;
        $ch = curl_init($url);

        if ($ch === false) {
            $this->lastError = 'No se pudo inicializar cURL.';
            return null;
        }

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
        ];

        foreach ($extraHeaders as $h) {
            $headers[] = $h;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS     => $rawContent,
        ];

        curl_setopt_array($ch, $options);

        $rawBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        $this->lastHttpCode = $httpCode;

        if ($rawBody === false || !empty($curlErr)) {
            $this->lastError = 'Error cURL Supabase: ' . $curlErr;
            return null;
        }

        $decoded = ($rawBody !== '') ? json_decode($rawBody, true) : [];

        if ($httpCode >= 400) {
            $errMsg = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['hint'] ?? $rawBody)
                : $rawBody;
            $this->lastError = "Supabase HTTP {$httpCode}: " . (string) $errMsg;
            return null;
        }

        return $decoded;
    }

    /**
     * Helper interno cURL para llamadas a PostgREST.
     */
    private function request(string $method, string $endpoint, ?array $data = null, array $extraHeaders = [])
    {
        $this->lastError = '';
        $this->lastHttpCode = 0;

        $url = $this->url . $endpoint;
        $ch = curl_init($url);

        if ($ch === false) {
            $this->lastError = 'No se pudo inicializar cURL.';
            return null;
        }

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        foreach ($extraHeaders as $h) {
            $headers[] = $h;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($data !== null && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);

        $rawBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        $this->lastHttpCode = $httpCode;

        if ($rawBody === false || !empty($curlErr)) {
            $this->lastError = 'Error cURL Supabase: ' . $curlErr;
            return null;
        }

        $decoded = ($rawBody !== '') ? json_decode($rawBody, true) : [];

        if ($httpCode >= 400) {
            $errMsg = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['hint'] ?? $rawBody)
                : $rawBody;
            $this->lastError = "Supabase HTTP {$httpCode}: " . (string) $errMsg;
            return null;
        }

        return $decoded;
    }
}
