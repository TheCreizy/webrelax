<?php
/**
 * Test suite para validar la funcionalidad de Leads y Conversaciones en el Panel Admin y SupabaseClient.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../api/SupabaseClient.php';

echo "=== INICIANDO TESTS DE LEADS Y CONVERSACIONES ===\n";

$testsPassed = 0;
$testsTotal = 0;

function assertTest(bool $condition, string $title) {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$title}\n";
    } else {
        echo "  [FAIL] {$title}\n";
    }
}

// 1. SupabaseClient - Métodos y validaciones
$client = new SupabaseClient();

assertTest(method_exists($client, 'getLeadChatMessages'), 'SupabaseClient posee el método getLeadChatMessages');
assertTest(method_exists($client, 'deleteLead'), 'SupabaseClient posee el método deleteLead');

// Prueba con entrada vacía
$emptyMsgs = $client->getLeadChatMessages('');
assertTest(is_array($emptyMsgs) && empty($emptyMsgs), 'getLeadChatMessages con leadId vacío devuelve array vacío');

$emptyDelete = $client->deleteLead('');
assertTest($emptyDelete === false, 'deleteLead con leadId vacío devuelve false');

// Prueba con UUID malformado
$invalidMsgs = $client->getLeadChatMessages('not-a-valid-uuid');
assertTest(is_array($invalidMsgs) && empty($invalidMsgs), 'getLeadChatMessages con UUID inválido es rechazado de forma segura');

$invalidDelete = $client->deleteLead('invalid-uuid-123');
assertTest($invalidDelete === false, 'deleteLead con UUID inválido es rechazado sin error HTTP');

// 2. admin/index.php - Verificación estructural de código
$adminFile = file_get_contents(__DIR__ . '/../admin/index.php');

assertTest(strpos($adminFile, 'deleteLeadGlobally') !== false, 'admin/index.php contiene función deleteLeadGlobally');
assertTest(strpos($adminFile, "get_lead_chat") !== false, 'admin/index.php maneja endpoint AJAX get_lead_chat');
assertTest(strpos($adminFile, "delete_lead") !== false, 'admin/index.php maneja endpoint AJAX delete_lead');
assertTest(strpos($adminFile, "case 'delete_lead':") !== false, 'admin/index.php maneja acción POST delete_lead');
assertTest(strpos($adminFile, '?tab=leads') !== false, 'admin/index.php incluye pestaña ?tab=leads en navegación');
assertTest(strpos($adminFile, '$activeTab === \'leads\'') !== false, 'admin/index.php renderiza bloque para tab=leads');
assertTest(strpos($adminFile, 'data-lead-display-name=') !== false, 'admin/index.php incluye data-lead-display-name seguro en el DOM');

// Verificar sintaxis del script del cliente (funciones de leads definidas fuera de if (editorArea))
$scriptStart = strrpos($adminFile, '<script>');
$scriptEnd = strrpos($adminFile, '</script>');
if ($scriptStart !== false && $scriptEnd !== false) {
    $scriptBody = substr($adminFile, $scriptStart, $scriptEnd - $scriptStart);
    $hasToggleFn = strpos($scriptBody, 'function toggleLeadChat(') !== false;
    $hasLoadFn = strpos($scriptBody, 'function loadLeadChat(') !== false;
    $hasDeleteFn = strpos($scriptBody, 'function confirmDeleteLead(') !== false;
    // Verificar que if (editorArea) { está cerrado antes del bloque de leads
    $editorAreaPos = strpos($scriptBody, 'if (editorArea) {');
    $leadsJsPos = strpos($scriptBody, '// ===== LEADS & CONVERSATION ACCORDION JS =====');
    $properlyClosed = false;
    if ($editorAreaPos !== false && $leadsJsPos !== false) {
        $between = substr($scriptBody, $editorAreaPos, $leadsJsPos - $editorAreaPos);
        $openBraces = substr_count($between, '{');
        $closeBraces = substr_count($between, '}');
        $properlyClosed = ($openBraces === $closeBraces);
    }
    assertTest($hasToggleFn && $hasLoadFn && $hasDeleteFn && $properlyClosed, 'JavaScript cliente: sintaxis y bloques de control balanceados y funciones de leads accesibles globalmente');
} else {
    assertTest(false, 'No se encontró el bloque <script> final en admin/index.php');
}

// Verificar que se eliminó la tabla de leads del tab chatbot
$chatbotTabPos = strpos($adminFile, "\$activeTab === 'chatbot'");
$leadsTabPos = strpos($adminFile, "\$activeTab === 'leads'");
if ($chatbotTabPos !== false && $leadsTabPos !== false) {
    $chatbotSection = substr($adminFile, $chatbotTabPos, $leadsTabPos - $chatbotTabPos);
    assertTest(strpos($chatbotSection, 'Tabla de Últimos Leads Capturados') === false, 'Tab Chatbot ya NO contiene la tabla de leads abajo');
} else {
    assertTest(false, 'No se pudo aislar sección de tab Chatbot');
}

// 3. Test de deleteLeadGlobally con carga bufferizada (sin imprimir HTML a stdout)
ob_start();
require_once __DIR__ . '/../admin/index.php';
ob_end_clean();

assertTest(function_exists('deleteLeadGlobally'), 'Función deleteLeadGlobally está cargada');

// Test de sesión
$testLeadId = '00000000-0000-4000-8000-' . substr(md5(uniqid()), 0, 12);
$_SESSION['relax_lead_id'] = $testLeadId;
$_SESSION['relax_lead_name'] = 'Usuario Test Suite';
$_SESSION['relax_chat_history'] = [['role' => 'user', 'message' => 'hola']];

// Test de limpieza en archivo JSON temporal dentro de data/
$tempJsonPath = __DIR__ . '/../data/test_temp_leads.json';
$initialJsonData = [
    ['id' => $testLeadId, 'name' => 'Usuario Test Suite'],
    ['id' => 'keep-me-lead-id', 'name' => 'Otro Lead']
];
file_put_contents($tempJsonPath, json_encode($initialJsonData));

deleteLeadGlobally($testLeadId);

assertTest(!isset($_SESSION['relax_lead_id']), 'deleteLeadGlobally limpia $_SESSION[relax_lead_id]');
assertTest(!isset($_SESSION['relax_lead_name']), 'deleteLeadGlobally limpia $_SESSION[relax_lead_name]');
assertTest(!isset($_SESSION['relax_chat_history']), 'deleteLeadGlobally limpia $_SESSION[relax_chat_history]');

$jsonContentAfter = json_decode(@file_get_contents($tempJsonPath) ?: '[]', true);
$foundInJson = false;
foreach ($jsonContentAfter as $row) {
    if (($row['id'] ?? '') === $testLeadId) {
        $foundInJson = true;
    }
}
assertTest(!$foundInJson && count($jsonContentAfter) === 1, 'deleteLeadGlobally purga registros de archivos JSON en data/');
@unlink($tempJsonPath);

// 4. Test en vivo contra Supabase si está configurado
if ($client->isConfigured()) {
    $liveLeadId = $client->insertLead('Lead Test Automatizado', 'test.auto@relaxtours.com.ar', '+5491188887777', 'unit_test');
    if ($liveLeadId) {
        $m1 = $client->insertChatMessage($liveLeadId, 'user', 'Mensaje de prueba usuario');
        $m2 = $client->insertChatMessage($liveLeadId, 'model', 'Respuesta de prueba asistente');
        $chatTurns = $client->getLeadChatMessages($liveLeadId);
        assertTest(count($chatTurns) >= 2, 'Supabase live: registro y lectura de mensajes de chat por turnos exitoso');

        $delSuccess = deleteLeadGlobally($liveLeadId);
        assertTest($delSuccess === true, 'Supabase live: deleteLeadGlobally elimina lead y mensajes de la base de datos');

        $chatAfter = $client->getLeadChatMessages($liveLeadId);
        assertTest(count($chatAfter) === 0, 'Supabase live: mensajes eliminados por completo tras el borrado');
    }
}

echo "\nRESUMEN: {$testsPassed} / {$testsTotal} tests aprobados.\n";
if ($testsPassed === $testsTotal) {
    echo "¡TODOS LOS TESTS PASARON EXITOSAMENTE!\n";
    exit(0);
} else {
    echo "HUBO ERRORES EN LOS TESTS.\n";
    exit(1);
}
