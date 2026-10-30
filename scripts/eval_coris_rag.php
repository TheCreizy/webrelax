<?php
/**
 * Relax Tours - Script de Evaluación Automatizada (Evals) Coris RAG
 * Archivo: scripts/eval_coris_rag.php
 *
 * Evalúa los 10 casos canónicos de Asistencia al Viajero Coris contra el pipeline RAG
 * de Gemini + Supabase pgvector. Valida la recuperación vectorial de contexto y la
 * presencia de las aserciones clave requeridas en cada escenario.
 *
 * Uso CLI:
 *   php scripts/eval_coris_rag.php               # Ejecuta los 10 casos en vivo
 *   php scripts/eval_coris_rag.php --case=1      # Ejecuta solo el caso 1
 *   php scripts/eval_coris_rag.php --dry-run     # Evalúa respuestas modelo (Golden Fixtures) sin llamar APIs
 *   php scripts/eval_coris_rag.php --delay=2     # Pausa de 2s entre llamadas a Gemini
 *   php scripts/eval_coris_rag.php --threshold=0.35 # Umbral de similitud vectorial
 *   php scripts/eval_coris_rag.php --json        # Exporta resultados en formato JSON
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../api/SupabaseClient.php';
require_once __DIR__ . '/../api/GeminiClient.php';

// Configuración y flags CLI
$options = getopt('h', ['help', 'case:', 'c:', 'dry-run', 'mock', 'delay:', 'threshold:', 'top-k:', 'json', 'verbose']);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
Relax Tours - Suite de Evaluación Coris RAG (Evals)
===================================================
Uso: php scripts/eval_coris_rag.php [opciones]

Opciones:
  --case=N, -c N       Ejecuta solo el caso número N (1 a 10).
  --dry-run, --mock    Evalúa las aserciones sobre respuestas canónicas (Golden Fixtures).
  --delay=SEGS         Segundos de espera entre consultas a Gemini (default: 1.5).
  --threshold=VAL      Umbral de similitud para match_knowledge (default: 0.35).
  --top-k=N            Cantidad máxima de fragmentos recuperados (default: 4).
  --json               Emite el resumen final en formato JSON.
  --verbose            Muestra el texto completo de las respuestas generadas.
  -h, --help           Muestra esta ayuda.

HELP;
    exit(0);
}

$targetCase  = isset($options['case']) ? (int)$options['case'] : (isset($options['c']) ? (int)$options['c'] : null);
$isDryRun    = isset($options['dry-run']) || isset($options['mock']);
$delaySecs   = isset($options['delay']) ? max(0.0, (float)$options['delay']) : 1.5;
$threshold   = isset($options['threshold']) ? (float)$options['threshold'] : 0.35;
$topK        = isset($options['top-k']) ? max(1, (int)$options['top-k']) : 4;
$isJson      = isset($options['json']);
$isVerbose   = isset($options['verbose']);

// Definición de colores ANSI si la terminal lo soporta
$hasAnsi = (DIRECTORY_SEPARATOR !== '\\' || getenv('ANSICON') !== false || getenv('WT_SESSION') !== false || (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)));
$cBold   = $hasAnsi ? "\033[1m" : '';
$cGreen  = $hasAnsi ? "\033[32m" : '';
$cRed    = $hasAnsi ? "\033[31m" : '';
$cYellow = $hasAnsi ? "\033[33m" : '';
$cCyan   = $hasAnsi ? "\033[36m" : '';
$cGray   = $hasAnsi ? "\033[90m" : '';
$cReset  = $hasAnsi ? "\033[0m" : '';

/**
 * Normaliza una cadena para comparaciones flexibles (minúsculas, sin acentos ni diacríticos).
 */
function normalizeText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $trans = transliterator_transliterate('Any-Latin; Latin-ASCII;', $text);
        if ($trans !== false && $trans !== null) {
            return $trans;
        }
    }
    return strtr($text, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
    ]);
}

/**
 * Dataset Canónico de los 10 Casos de Evaluación de Asistencia al Viajero Coris.
 */
$evalCases = [
    1 => [
        'id'          => 1,
        'title'       => 'Viaje a España e Italia (Europa Schengen)',
        'prompt'      => 'Hola, viajo a España e Italia por 20 dias, que seguro de Coris me recomendas?',
        'golden'      => 'Para viajar a Europa debés cumplir con el Tratado Schengen obligatorio (€30.000). En Coris te recomendamos el plan Global (USD 100k) o Global Plus (USD 150k), ambos con tope por evento médico renovable. Podés cotizar y emitir tu asistencia online directamente en https://www.relaxtours.com.ar/asistencia. ¿Qué edades tienen los pasajeros y en qué fechas exactas viajan para armarte la cotización?',
        'assertions'  => [
            [
                'name' => 'Tratado Schengen',
                'desc' => 'Menciona el Tratado o espacio Schengen y/o requisito obligatorio para Europa',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/schengen/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Plan Global o Global Plus',
                'desc' => 'Menciona el plan Global (USD 100k) o Global Plus (USD 150k)',
                'test' => function (string $raw, string $norm): bool {
                    return (preg_match('/global\s*plus|global\b/i', $norm) === 1)
                        && (preg_match('/100|150|cien\s*mil|ciento\s*cincuenta/i', $norm) === 1);
                }
            ],
            [
                'name' => 'Tope por evento',
                'desc' => 'Menciona la característica de tope por evento',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/por\s+evento|tope\s+por\s+evento/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Follow-up edades/fechas',
                'desc' => 'Pregunta por edades de viajeros, cantidad de pasajeros o fechas exactas',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(edad|edades|fecha|fechas|cuantos|pasajeros)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Estructura Primer Mensaje: Link online + Follow-up',
                'desc' => 'En el primer mensaje incluye el link /asistencia y el follow-up sin botones de salida',
                'test' => function (string $raw, string $norm): bool {
                    $hasLink = preg_match('/relaxtours\.com\.ar\/asistencia/i', $norm) === 1;
                    $hasQuestion = preg_match('/\?/u', $raw) === 1;
                    $hasExcessiveExit = preg_match('/(whatsapp|pestaña)/i', $norm) === 1;
                    return $hasLink && $hasQuestion && !$hasExcessiveExit;
                }
            ],
        ],
    ],
    2 => [
        'id'          => 2,
        'title'       => 'Viaje Familiar a Miami y Orlando (Estados Unidos)',
        'prompt'      => 'Nos vamos a Miami y Orlando en familia, que asistencia nos conviene llevar?',
        'golden'      => 'Para Estados Unidos la atención médica es una de las más caras del mundo, por lo que una internación simple puede costar decenas de miles de dólares. Te sugerimos planes robustos como Coris Global Plus (USD 150k), Discover (USD 200k) o Business (USD 300k), que operan con tope por evento. ¿Cuántos integrantes son en la familia y qué edades tienen para cotizarles la mejor alternativa? Cotizá online en https://www.relaxtours.com.ar/asistencia.',
        'assertions'  => [
            [
                'name' => 'Altos costos de salud en EEUU',
                'desc' => 'Advierte sobre los altos costos médicos o la sanidad en Estados Unidos / EEUU',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(ee\.?uu|estados\s*unidos|costos?\s+(m[eé]dicos?|salud|elevados?|altos?)|salud\s+(muy\s+)?costosa|cara|caras)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Plan Global Plus, Discover o Business',
                'desc' => 'Menciona Global Plus (USD 150k), Discover (USD 200k) o Business (USD 300k)',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(global\s*plus|discover|business|150\s*k|200\s*k|300\s*k|150\.?000|200\.?000|300\.?000)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Tope por evento',
                'desc' => 'Menciona la modalidad de cobertura con tope por evento',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/por\s+evento|tope\s+por\s+evento/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Follow-up cantidad/edades',
                'desc' => 'Pregunta por cantidad de integrantes, familia o edades de los pasajeros',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(cuantos|integrantes|familia|pasajeros|edad|edades|fechas)/i', $norm) === 1;
                }
            ],
        ],
    ],
    3 => [
        'id'          => 3,
        'title'       => 'Viaje Económico a Brasil (Camboriú / Florianópolis)',
        'prompt'      => 'Me voy una semana a Brasil (Camboriú / Florianópolis). Qué plan económico pero bueno tenés de Coris?',
        'golden'      => 'Para viajar a Brasil disponés del Plan Conosur (cobertura médica de USD 17.000), una opción súper accesible pensada para países limítrofes, o bien el plan Global (USD 100k) si buscás mayor tranquilidad médica. Ambos cuentan con atención en español las 24 hs. ¿Qué fechas tenés pensadas para viajar y qué edad tenés? Podés cotizar en https://www.relaxtours.com.ar/asistencia.',
        'assertions'  => [
            [
                'name' => 'Plan Conosur o Global',
                'desc' => 'Menciona Plan Conosur (USD 17k) o Global (USD 100k)',
                'test' => function (string $raw, string $norm): bool {
                    return (preg_match('/(conosur|cono\s*sur|global)/i', $norm) === 1)
                        && (preg_match('/17|100|diecisiete|cien\s*mil/i', $norm) === 1);
                }
            ],
            [
                'name' => 'Adecuación para Brasil / Limítrofes',
                'desc' => 'Relaciona la asistencia con destino Brasil, limítrofes o viaje económico',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(brasil|limitrofes?|economico|conosur|semana|camboriu|florianopolis)/i', $norm) === 1;
                }
            ],
        ],
    ],
    4 => [
        'id'          => 4,
        'title'       => 'Turismo Nacional en Bariloche / Sur Argentino',
        'prompt'      => 'Viajo a Bariloche y el Sur argentino por 10 días, hay algún plan nacional de Coris?',
        'golden'      => '¡Sí! Para viajar dentro de Argentina Coris ofrece coberturas nacionales específicas: el plan Oxygen (USD 3.000) o Nacional Premium (USD 5.500). Tené en cuenta que la cobertura nacional rige a partir de los 25 km de distancia respecto de tu domicilio o residencia habitual. ¿En qué fechas viajás y cuántas personas van?',
        'assertions'  => [
            [
                'name' => 'Plan Oxygen o Nacional Premium',
                'desc' => 'Menciona Oxygen (USD 3k) o Nacional Premium (USD 5.5k)',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(oxygen|nacional\s*premium|3\.?000|5\.?500|3\s*k|5\.?5\s*k|plan\s+nacional)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Franquicia desde 25 km',
                'desc' => 'Menciona el requisito de distancia mínima o franquicia desde 25 km del domicilio',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(25\s*km|25\s*kilometros|veinticinco\s*km|distancia|domicilio|residencia)/i', $norm) === 1;
                }
            ],
        ],
    ],
    5 => [
        'id'          => 5,
        'title'       => 'Caribe All Inclusive (Punta Cana / Cancún)',
        'prompt'      => 'Viajamos a un all inclusive en Punta Cana / Cancún, qué cobertura lleva la mayoría?',
        'golden'      => 'Para el Caribe (Punta Cana, Cancún, Riviera Maya), la mayoría de los viajeros eligen el plan Coris Global (USD 100k) o Global Plus (USD 150k). Estos planes brindan excelente cobertura médica y beneficios ideales para el Caribe como seguro de cancelación multicausa o acceso a sala VIP ante demoras prolongadas de vuelo. ¿Cuántos viajan y en qué fechas?',
        'assertions'  => [
            [
                'name' => 'Plan Global o Global Plus',
                'desc' => 'Menciona el plan Global (USD 100k) o Global Plus (USD 150k)',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(global\s*plus|global\b|100\s*k|150\s*k|100\.?000|150\.?000)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Cancelación Multicausa o Sala VIP',
                'desc' => 'Menciona beneficio de cancelación multicausa, sala VIP o cobertura de demoras/vuelos',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(cancelacion|multicausa|sala\s*vip|vip|demoras?|equipaje|vuelo)/i', $norm) === 1;
                }
            ],
        ],
    ],
    6 => [
        'id'          => 6,
        'title'       => 'Viajero Senior de 78 años con Hipertensión a Europa',
        'prompt'      => 'Mi papá tiene 78 años, es hipertenso y viaja a Europa. Coris lo cubre?',
        'golden'      => 'Sí, absolutamente. Coris brinda cobertura para enfermedades preexistentes (como la hipertensión) hasta los 85 años inclusive. Para pasajeros entre 76 y 99 años de edad, se aplica un recargo tarifario del 50% sobre la tarifa base del plan. De este modo viaja totalmente protegido cumpliendo además con los requisitos de Europa. ¿Qué fechas tienen para el viaje?',
        'assertions'  => [
            [
                'name' => 'Preexistencias hasta 85 años',
                'desc' => 'Menciona la cobertura de preexistencias y/o el límite de hasta 85 años',
                'test' => function (string $raw, string $norm): bool {
                    return (preg_match('/(preexistenc|hipertens)/i', $norm) === 1)
                        && (preg_match('/(85|cubre|cubiert|incluid)/i', $norm) === 1);
                }
            ],
            [
                'name' => 'Recargo 50% entre 76 y 99 años',
                'desc' => 'Menciona el recargo del 50% para pasajeros a partir de 76 años (76-99)',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(50\s*%|cincuenta\s*por\s*ciento|recargo|76\s*(a|-)\s*99|a\s*partir\s*de\s*los?\s*76|mayores\s+de\s+75)/i', $norm) === 1;
                }
            ],
        ],
    ],
    7 => [
        'id'          => 7,
        'title'       => 'Embarazo de 20 Semanas en Crucero por Brasil',
        'prompt'      => 'Estoy embarazada de 20 semanas y vamos a hacer un crucero por Brasil, me cubre la asistencia?',
        'golden'      => '¡Sí, felicitaciones por el viaje! Coris incluye cobertura para embarazadas hasta la semana 32 de gestación inclusive, y la cobertura en cruceros está contemplada dentro de los planes internacionales sin costo adicional. Te asistirán tanto en tierra como a bordo ante cualquier eventualidad. ¿En qué fechas sale el crucero? Cotizá en https://www.relaxtours.com.ar/asistencia.',
        'assertions'  => [
            [
                'name' => 'Cruceros incluidos',
                'desc' => 'Confirma cobertura en cruceros o atención a bordo',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(crucero|cruceros|a\s*bordo)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Cobertura hasta semana 32',
                'desc' => 'Indica que el embarazo está cubierto hasta la semana 32 de gestación',
                'test' => function (string $raw, string $norm): bool {
                    return (preg_match('/(32|semana\s*32)/i', $norm) === 1)
                        && (preg_match('/(embaraz|gestacion|semana)/i', $norm) === 1);
                }
            ],
        ],
    ],
    8 => [
        'id'          => 8,
        'title'       => 'Viajero Frecuente: Anual Multiviajes vs Larga Estadía',
        'prompt'      => 'Viajo varias veces al año por trabajo o vacaciones, me conviene sacar por viaje o un plan anual?',
        'golden'      => 'Si viajás más de dos o tres veces al año, te conviene definitivamente un plan Anual Multiviajes. Este plan te cubre durante los 365 días para viajes de hasta 30, 60 o 90 días por salida según elijas. Si en cambio vas a hacer un único viaje continuo extenso, la opción recomendada es Larga Estadía. ¿Cuánto suele durar cada viaje tuyo habitualmente?',
        'assertions'  => [
            [
                'name' => 'Plan Anual Multiviajes (30, 60 o 90 días)',
                'desc' => 'Menciona el plan Anual Multiviajes y opciones de 30, 60 o 90 días por viaje',
                'test' => function (string $raw, string $norm): bool {
                    return (preg_match('/(anual|multiviajes?|multi-viaje)/i', $norm) === 1)
                        && (preg_match('/(30|60|90|dias\s+por\s+viaje)/i', $norm) === 1);
                }
            ],
            [
                'name' => 'Larga Estadía o comparación económica',
                'desc' => 'Menciona Larga Estadía o explica por qué conviene frente al seguro por viaje único',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(larga\s*estadia|conviene|ahorro|por\s+viaje|frecuente)/i', $norm) === 1;
                }
            ],
        ],
    ],
    9 => [
        'id'          => 9,
        'title'       => 'Upgrades Coris: Mascotas, Asistencia Psicológica y Coris Ellas',
        'prompt'      => 'Escuché que Coris tiene coberturas nuevas para mascotas o asistencia psicológica en viaje, cómo funcionan?',
        'golden'      => 'Coris cuenta con upgrades innovadores que podés sumar a tu plan: cobertura para Mascotas (asistencia veterinaria de urgencia y medicamentos para perros o gatos en viaje), Asistencia Psicológica (contención y teleasistencia emocional ante imprevistos) y el upgrade Coris Ellas con asistencias específicas pensadas para la mujer. Consultanos para agregarlos a tu cotización.',
        'assertions'  => [
            [
                'name' => 'Upgrade Mascotas',
                'desc' => 'Menciona la cobertura o upgrade para mascotas (veterinaria, perros/gatos)',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(mascota|mascotas|veterin|perro|gato)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Asistencia Psicológica',
                'desc' => 'Menciona la cobertura de asistencia psicológica o contención emocional',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(psicolog|salud\s*mental|emocion)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Coris Ellas',
                'desc' => 'Menciona el beneficio o upgrade exclusivo Coris Ellas / asistencia para mujeres',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(coris\s*ellas|ellas|mujeres)/i', $norm) === 1;
                }
            ],
        ],
    ],
    10 => [
        'id'          => 10,
        'title'       => 'Emisión en Destino (Ya de Viaje en Madrid): Carencia y Autorización',
        'prompt'      => 'Ya estoy de viaje en Madrid y me olvidé de sacar el seguro antes de salir, lo puedo emitir hoy para usarlo mañana?',
        'golden'      => 'Si ya te encontrás en el exterior podés contratar Coris, pero tené en cuenta que la emisión requiere autorización previa de la central y aplica un período de carencia (generalmente de varios días posteriores a la emisión) antes de que la cobertura médica quede activa. No tiene efecto retroactivo para eventos ocurridos antes. Escribinos urgente por WhatsApp para solicitar la autorización.',
        'assertions'  => [
            [
                'name' => 'Período de Carencia',
                'desc' => 'Menciona la aplicación de un período de carencia o días de espera',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(carencia|periodo\s+de\s+carencia|\bdias\s+de\s+espera\b)/i', $norm) === 1;
                }
            ],
            [
                'name' => 'Autorización Previa de Central',
                'desc' => 'Menciona que requiere autorización previa de la central para emitir en viaje',
                'test' => function (string $raw, string $norm): bool {
                    return preg_match('/(autorizacion|central|aprobacion|permiso|evaluacion)/i', $norm) === 1;
                }
            ],
        ],
    ],
];

// Filtrar si el usuario solicitó un caso específico
if ($targetCase !== null) {
    if (!isset($evalCases[$targetCase])) {
        fwrite(STDERR, "Error: El caso {$targetCase} no existe. Rango válido: 1 a 10.\n");
        exit(1);
    }
    $evalCases = [$targetCase => $evalCases[$targetCase]];
}

// Inicialización de Clientes
$supabase = new SupabaseClient();
$gemini   = new GeminiClient();

if (!$isDryRun) {
    if (!$gemini->isConfigured()) {
        fwrite(STDERR, "Error: GEMINI_API_KEY no está configurada en admin/.env. Usá --dry-run para evaluar con Golden Fixtures.\n");
        exit(1);
    }
    if (!$supabase->isConfigured()) {
        fwrite(STDERR, "Advertencia: Supabase no está configurado. La búsqueda vectorial no recuperará fragmentos de base de datos.\n");
    }
}

// Banner inicial
if (!$isJson) {
    echo "\n" . str_repeat('=', 74) . "\n";
    echo " {$cBold}RELAX TOURS — SUITE DE EVALUACIÓN AUTOMATIZADA CORIS RAG (EVALS){$cReset}\n";
    echo str_repeat('=', 74) . "\n";
    echo " Modo:        " . ($isDryRun ? "{$cYellow}DRY-RUN / MOCK (Golden Fixtures){$cReset}" : "{$cCyan}LIVE (Supabase matchKnowledge + Gemini RAG){$cReset}") . "\n";
    echo " Umbral RAG:  {$threshold} | Top-K: {$topK} | Delay: {$delaySecs}s\n";
    echo " Casos:       " . count($evalCases) . " a evaluar\n";
    echo str_repeat('-', 74) . "\n\n";
}

$results      = [];
$totalPassed  = 0;
$totalFailed  = 0;
$totalAsserts = 0;
$passedAsserts= 0;
$caseIndex    = 0;

foreach ($evalCases as $cid => $case) {
    $caseIndex++;
    $prompt = $case['prompt'];
    $reply  = '';
    $retrievedItems = [];
    $ragTimeMs = 0;

    if ($isDryRun) {
        $reply = $case['golden'];
    } else {
        $t0 = microtime(true);
        // 1. Invocación de Embedding y matchKnowledge
        if ($supabase->isConfigured()) {
            $queryEmb = $gemini->generateEmbedding($prompt, 'RETRIEVAL_QUERY');
            if (is_array($queryEmb) && count($queryEmb) === 768) {
                $retrievedItems = $supabase->matchKnowledge($queryEmb, $threshold, $topK);
            }
        }

        // 2. Invocación de respuesta RAG de Gemini
        $generated = $gemini->generateChatResponse($retrievedItems, [], $prompt);
        $t1 = microtime(true);
        $ragTimeMs = round(($t1 - $t0) * 1000);

        if ($generated === null || trim($generated) === '') {
            $err = $gemini->getLastError() ?: 'Respuesta nula recibida de Gemini';
            $reply = "ERROR_GEMINI: {$err}";
        } else {
            $reply = $generated;
        }
    }

    $normReply = normalizeText($reply);

    // Evaluar cada aserción
    $assertResults = [];
    $casePass = true;

    foreach ($case['assertions'] as $as) {
        $totalAsserts++;
        $ok = false;
        try {
            $ok = ($as['test'])($reply, $normReply);
        } catch (\Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            $passedAsserts++;
        } else {
            $casePass = false;
        }

        $assertResults[] = [
            'name' => $as['name'],
            'desc' => $as['desc'],
            'pass' => $ok,
        ];
    }

    if ($casePass) {
        $totalPassed++;
    } else {
        $totalFailed++;
    }

    $caseResult = [
        'id'             => $cid,
        'title'          => $case['title'],
        'prompt'         => $prompt,
        'pass'           => $casePass,
        'time_ms'        => $ragTimeMs,
        'retrieved_count'=> count($retrievedItems),
        'retrieved_titles'=> array_map(function ($it) {
            return ($it['category'] ?? '') . ': ' . ($it['title'] ?? '');
        }, $retrievedItems),
        'reply'          => $reply,
        'assertions'     => $assertResults,
    ];
    $results[$cid] = $caseResult;

    // Reporte en consola en tiempo real
    if (!$isJson) {
        $badge = $casePass ? "{$cGreen}{$cBold}[PASS]{$cReset}" : "{$cRed}{$cBold}[FAIL]{$cReset}";
        $num = str_pad((string)$cid, 2, '0', STR_PAD_LEFT);
        echo "{$badge} {$cBold}Caso {$num}: {$case['title']}{$cReset}\n";
        echo "   {$cGray}Prompt: \"{$prompt}\"{$cReset}\n";

        if (!$isDryRun) {
            $cats = !empty($retrievedItems) ? implode(', ', array_map(function ($i) {
                return ($i['category'] ?? 'info') . ' (' . round((float)($i['similarity'] ?? 0), 2) . ')';
            }, $retrievedItems)) : 'ninguno';
            echo "   {$cGray}RAG: {$ragTimeMs}ms | Fragmentos: " . count($retrievedItems) . " [{$cats}]{$cReset}\n";
        }

        foreach ($assertResults as $ar) {
            $icon = $ar['pass'] ? "{$cGreen}✓{$cReset}" : "{$cRed}✗{$cReset}";
            echo "   [{$icon}] {$ar['name']}: {$cGray}{$ar['desc']}{$cReset}\n";
        }

        if ($isVerbose) {
            echo "   {$cGray}--- Respuesta ---{$cReset}\n";
            echo "   " . str_replace("\n", "\n   ", trim($reply)) . "\n";
            echo "   {$cGray}-----------------{$cReset}\n";
        }
        echo "\n";
    }

    // Delay entre llamadas para no saturar Rate Limits de Gemini
    if (!$isDryRun && $caseIndex < count($evalCases) && $delaySecs > 0) {
        usleep((int)($delaySecs * 1000000));
    }
}

// Resumen Final
$totalCases = count($evalCases);
$allPassed  = ($totalPassed === $totalCases);
$scorePct   = $totalCases > 0 ? round(($totalPassed / $totalCases) * 100, 1) : 0;
$assertPct  = $totalAsserts > 0 ? round(($passedAsserts / $totalAsserts) * 100, 1) : 0;

if ($isJson) {
    echo json_encode([
        'ok'             => $allPassed,
        'mode'           => $isDryRun ? 'dry-run' : 'live',
        'score'          => "{$totalPassed}/{$totalCases}",
        'score_percent'  => $scorePct,
        'asserts_score'  => "{$passedAsserts}/{$totalAsserts}",
        'asserts_percent'=> $assertPct,
        'cases'          => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo str_repeat('=', 74) . "\n";
    echo " RESUMEN FINAL DEL BENCHMARK CORIS RAG\n";
    echo str_repeat('=', 74) . "\n";
    foreach ($results as $cid => $res) {
        $badge = $res['pass'] ? "{$cGreen}PASS{$cReset}" : "{$cRed}FAIL{$cReset}";
        $num   = str_pad((string)$cid, 2, '0', STR_PAD_LEFT);
        echo " Caso {$num}: [{$badge}] {$res['title']}\n";
    }
    echo str_repeat('-', 74) . "\n";

    $scoreColor = $allPassed ? $cGreen : ($totalPassed >= 7 ? $cYellow : $cRed);
    echo " {$cBold}CASOS APROBADOS:{$cReset} {$scoreColor}{$cBold}{$totalPassed}/{$totalCases} PASS ({$scorePct}%){$cReset}\n";
    echo " {$cBold}ASERCIONES:{$cReset}      {$scoreColor}{$passedAsserts}/{$totalAsserts} ({$assertPct}%){$cReset}\n";
    echo str_repeat('=', 74) . "\n\n";
}

exit($allPassed ? 0 : 1);
