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
    public function getRecentLeads(int $limit = 30): array
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

        if ($data !== null && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);

        $rawBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
