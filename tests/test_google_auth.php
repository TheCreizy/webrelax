<?php
/**
 * Test suite para Google OAuth y Supabase Auth en admin/index.php y SupabaseClient.
 */

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../api/SupabaseClient.php';

echo "=== INICIANDO TESTS DE GOOGLE AUTH & SUPABASE AUTH ===\n";

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

// 1. SupabaseClient verifyAccessToken method
$client = new SupabaseClient();
assertTest(method_exists($client, 'verifyAccessToken'), 'SupabaseClient posee el método verifyAccessToken');

// Empty token check
$emptyRes = $client->verifyAccessToken('');
assertTest($emptyRes === null, 'verifyAccessToken con token vacío devuelve null de forma segura');

// Invalid dummy token check
$invalidRes = $client->verifyAccessToken('invalid.jwt.token.12345');
assertTest($invalidRes === null, 'verifyAccessToken con token inválido devuelve null');

// 2. admin/index.php estructural
$adminCode = file_get_contents(__DIR__ . '/../admin/index.php');

assertTest(strpos($adminCode, 'getAllowedAdminEmails') !== false, 'admin/index.php define función getAllowedAdminEmails');
assertTest(strpos($adminCode, "google_auth_verify") !== false, 'admin/index.php maneja endpoint AJAX google_auth_verify');
assertTest(strpos($adminCode, 'btn-google-auth') !== false, 'admin/index.php contiene botón de Google OAuth');
assertTest(strpos($adminCode, 'startGoogleSignIn') !== false, 'admin/index.php define función startGoogleSignIn');
assertTest(strpos($adminCode, 'verifyTokenWithPhp') !== false, 'admin/index.php define función verifyTokenWithPhp');
assertTest(strpos($adminCode, 'handleAdminLogout') !== false, 'admin/index.php define función handleAdminLogout');
assertTest(strpos($adminCode, '@supabase/supabase-js') !== false, 'admin/index.php carga librería @supabase/supabase-js');

// 3. Simular render de login (!isLoggedIn) vía helper aislado
$loginOutput = shell_exec('php ' . escapeshellarg(__DIR__ . '/render_helper.php') . ' login 2>&1');

assertTest(strpos($loginOutput, 'Continuar con Google') !== false, 'Página de login renderiza botón "Continuar con Google"');
assertTest(strpos($loginOutput, 'googleAuthAlert') !== false, 'Página de login contiene contenedor de alerta googleAuthAlert');

// 4. Simular render de admin autenticado (isLoggedIn) vía helper aislado
$adminOutput = shell_exec('php ' . escapeshellarg(__DIR__ . '/render_helper.php') . ' admin 2>&1');

assertTest(strpos($adminOutput, 'test.admin@relaxtours.com.ar') !== false, 'Navbar renderiza el correo del administrador autenticado');
assertTest(strpos($adminOutput, 'handleAdminLogout') !== false, 'Botón de salida llama a handleAdminLogout');

// 5. Validar que la lista blanca esté configurada
ob_start();
require_once __DIR__ . '/../admin/index.php';
ob_end_clean();
$allowedList = getAllowedAdminEmails();
assertTest(is_array($allowedList) && !empty($allowedList), 'getAllowedAdminEmails lee correctamente los correos de admin/.env');
assertTest(in_array('loco.lerner@gmail.com', $allowedList, true), 'loco.lerner@gmail.com está en la lista blanca');

// 6. Validar sintaxis del script del cliente renderizado
$scriptStart = strrpos($adminOutput, '<script>');
$scriptEnd = strrpos($adminOutput, '</script>');
$clientScript = substr($adminOutput, $scriptStart + 8, $scriptEnd - ($scriptStart + 8));
$tempJs = __DIR__ . '/temp_validated_admin_script.js';
file_put_contents($tempJs, $clientScript);

$nodeCheck = shell_exec('node -c ' . escapeshellarg($tempJs) . ' 2>&1');
@unlink($tempJs);
assertTest(empty($nodeCheck), 'Sintaxis de JavaScript cliente en página renderizada es 100% válida');

echo "\nRESUMEN: {$testsPassed} / {$testsTotal} tests aprobados.\n";
if ($testsPassed === $testsTotal) {
    echo "¡TODOS LOS TESTS DE GOOGLE AUTH PASARON EXITOSAMENTE!\n";
    exit(0);
} else {
    echo "HUBO ERRORES EN LOS TESTS.\n";
    exit(1);
}
