<?php
/**
 * Relax Tours - Endpoint de Contenido Dinámico (api/content.php)
 * Entrega el JSON de paquetes, ofertas, blog y configuración.
 * Auto-hidrata la caché local data/content.json desde Supabase si fuera necesario.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60'); // 1 minuto de caché HTTP en navegador

require_once __DIR__ . '/ContentRepository.php';

$data = ContentRepository::getInstance()->getAllData();

echo json_encode($data, JSON_UNESCAPED_UNICODE);
