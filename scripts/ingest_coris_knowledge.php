<?php
/**
 * Relax Tours - Ingesta de Conocimiento Especializado: Asistencia al Viajero Coris
 * Carga los 10 casos canónicos de Coris en public.knowledge_base con embeddings vectoriales de 768 dimensiones.
 *
 * Ejecución vía CLI:
 *   php scripts/ingest_coris_knowledge.php
 */

declare(strict_types=1);

// Si se intenta acceder directamente desde la web (no como inclusión), denegar acceso
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'ingest_coris_knowledge.php') {
    http_response_code(403);
    echo "Acceso denegado: este script no admite invocación HTTP directa.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../api/SupabaseClient.php';
require_once __DIR__ . '/../api/GeminiClient.php';

if (!function_exists('normalizeWidgetCall')) {
    /**
     * Normaliza y reemplaza los placeholders (boton de whatsapp), (formulario) y (telefono)
     * por el llamado claro y natural al widget de chat de Relax Tours.
     */
    function normalizeWidgetCall(string $text): string
    {
        // Reemplazo específico del patrón común de cierre
        $pattern = '/o pedir cotización por \(boton de whatsapp\), \(formulario\) o llamando a \(telefono\)\.?/i';
        $replacement = 'o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.';

        $text = preg_replace($pattern, $replacement, $text);

        // Reemplazos de seguridad por si algún placeholder quedó suelto
        $text = str_replace('(boton de whatsapp)', 'tocando la pestaña de WhatsApp', $text);
        $text = str_replace('(formulario)', 'completando el formulario de Correo', $text);
        $text = str_replace('llamando a (telefono)', 'tocando la pestaña de Teléfono aquí abajo en el chat', $text);
        $text = str_replace('(telefono)', 'la pestaña de Teléfono aquí abajo en el chat', $text);
        $text = str_replace('por tocando la pestaña de WhatsApp', 'tocando la pestaña de WhatsApp', $text);

        return trim($text);
    }
}

/**
 * Retorna la definición canónica exhaustiva de los 10 casos estructurados para RAG y vectorización.
 *
 * @return array<int, array{slug: string, title: string, destination: string, metadata: array, content_markdown: string}>
 */
function getCorisCanonicalCases(): array
{
    static $cases = null;
    if ($cases !== null) {
        return $cases;
    }

    $cases = [
    [
        'slug'        => 'europa_schengen',
        'title'       => 'Europa y Zona Schengen — Coberturas Coris Global y Global Plus',
        'destination' => 'Europa / Espacio Schengen (España, Italia, Francia, Alemania, Suiza, etc.)',
        'metadata'    => [
            'slug'                => 'europa_schengen',
            'destination'         => 'Europa / Zona Schengen',
            'schengen_compliant'  => true,
            'suggested_plans'     => ['Coris Global', 'Coris Global Plus'],
            'coverage_per_event'  => 'USD 100.000 / USD 150.000',
            'preexistences'       => 'USD 18.000',
            'cancellation'        => 'USD 2.000',
            'medications'         => 'USD 1.000',
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Europa y Zona Schengen (Tratado Schengen)

## Ámbito Geográfico y Requisitos Migratorios
- **Destino:** Europa y países miembros del Tratado Schengen (España, Italia, Francia, Alemania, Portugal, Suiza, Países Bajos, etc.).
- **Exigencia Migratoria Obligatoria:** Tratado Schengen exige a los turistas un seguro de asistencia médica con cobertura mínima obligatoria de **30.000 EUR** (o su equivalente en dólares) sin deducibles ni franquicias para autorizar el ingreso migratorio a la Unión Europea.

## Planes Recomendados y Coberturas Médicas
- **Coris Global (Opción más recomendada y vendida):**
  - **USD 100.000 por evento médico** (supera con holgura los €30.000 exigidos por Schengen).
  - **USD 18.000** de cobertura para urgencias por enfermedades preexistentes o crónicas.
  - **USD 1.000** en cobertura para medicamentos ambulatorios o de internación.
  - **USD 2.000** de seguro de cancelación multicausa (reembolso por imprevistos antes de salir).
  - Asistencia odontológica de urgencia, repatriación sanitaria y gastos de hotel por convalecencia.
- **Coris Global Plus (Mayor respaldo internacional):**
  - Eleva la cobertura médica a **USD 150.000 por evento médico**.
  - Recomendado para estancias prolongadas o viajes combinados por múltiples países.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Coris opera con tope por evento médico (el monto de cobertura se renueva de forma íntegra ante cada consulta o patología distinta ocurrida durante el viaje).
- **Atención médica 24/7:** Central telefónica y telemedicina en español disponible las 24 horas del día con derivación directa sin desembolso previo.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Qué edades tienen los pasajeros y en qué fechas exactas viajan para armarte la cotización?

## Caso Práctico de Asesoramiento
- **Pregunta:** Hola, viajo a España e Italia por 20 dias, que seguro de Coris me recomendas?
- **Respuesta:** Para viajar a Europa necesitás cumplir con el requisito obligatorio del Tratado Schengen (cobertura mínima de 30.000 EUR sin franquicias). Nuestra opción más vendida y recomendada es el plan **Coris Global**, que ofrece **USD 100.000 por evento médico**, USD 18.000 para enfermedades preexistentes, cobertura en medicamentos (USD 1.000) y seguro de cancelación multicausa por USD 2.000. Si preferís aún mayor respaldo, el plan **Global Plus** eleva la cobertura a **USD 150.000 por evento**.
¿Qué edades tienen los pasajeros y en qué fechas exactas viajan para armarte la cotización?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'estados_unidos_norteamerica',
        'title'       => 'Estados Unidos y Norteamérica — Coberturas Coris Global Plus, Discover y Business',
        'destination' => 'Estados Unidos y Norteamérica (Miami, Orlando, Nueva York, Los Ángeles, etc.)',
        'metadata'    => [
            'slug'                => 'estados_unidos_norteamerica',
            'destination'         => 'Estados Unidos / Norteamérica',
            'suggested_plans'     => ['Coris Global Plus', 'Coris Discover', 'Coris Business'],
            'coverage_per_event'  => 'USD 150.000 / USD 200.000 / USD 300.000',
            'preexistences'       => 'USD 25.000',
            'pediatry_24_7'       => true,
            'telemedicine_24_7'   => true,
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Estados Unidos y Norteamérica

## Contexto Sanitario en EE.UU.
- **Destino:** Estados Unidos (Miami, Orlando, Nueva York, Las Vegas, Los Ángeles, Chicago, etc.), Canadá y Norteamérica.
- **Costos en Destino:** Estados Unidos cuenta con el sistema de salud privado más oneroso del planeta. Una simple atención en guardia o unos puntos de sutura pueden costar entre USD 2.500 y USD 6.000, y una noche de internación o cirugía de apendicitis supera habitualmente los USD 40.000 a USD 80.000. Se recomienda imperativamente viajar con topes de cobertura altos.

## Planes Recomendados y Coberturas Médicas
- **Coris Global Plus:**
  - **USD 150.000 por evento médico**.
  - Excelente equilibrio para viajes familiares, parques temáticos y compras.
- **Coris Discover (Muy recomendado para EE.UU.):**
  - **USD 200.000 por evento médico**.
  - **USD 25.000** en cobertura para afecciones preexistentes o crónicas.
- **Coris Business (Máxima protección del mercado):**
  - **USD 300.000 por evento médico**.
  - Máximo respaldo indemnizatorio ante cualquier siniestro médico grave.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Todos los planes operan con tope por evento (el límite de cobertura se renueva íntegramente ante cada dolencia médica distinta).
- Incluyen servicio de **Pediatría y Telemedicina 24/7**, ideal para consultas con médicos hispanohablantes desde la habitación del hotel sin esperas innecesarias.
- Coordinación directa de facturación con clínicas y prestadores médicos para que el pasajero no tenga que desembolsar dinero en destino.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Cuántos adultos y menores viajan, y cuántos días durará el viaje?

## Caso Práctico de Asesoramiento
- **Pregunta:** Nos vamos a Miami y Orlando en familia, que asistencia nos conviene llevar?
- **Respuesta:** Dado que Estados Unidos tiene el sistema de salud más costoso del mundo, recomendamos viajar con topes altos. Los planes más elegidos para este destino son **Global Plus (USD 150.000 por evento)** y **Discover (USD 200.000 por evento y USD 25.000 en preexistencias)**. Si buscan la máxima protección del mercado, el plan **Business** cubre hasta **USD 300.000 por evento médico**. Todos operan con tope por evento (se renueva ante un problema médico distinto) e incluyen pediatría y telemedicina 24/7.
¿Cuántos adultos y menores viajan, y cuántos días durará el viaje?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'brasil_paises_limitrofes',
        'title'       => 'Brasil y Países Limítrofes — Coberturas Coris Conosur y Global',
        'destination' => 'Brasil (Florianópolis, Camboriú, Río, Nordeste) y Países Limítrofes',
        'metadata'    => [
            'slug'               => 'brasil_paises_limitrofes',
            'destination'        => 'Brasil y Países Limítrofes',
            'suggested_plans'    => ['Coris Conosur', 'Coris Global'],
            'coverage_per_event' => 'USD 17.000 / USD 100.000',
            'regional_scope'     => true,
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Brasil y Países Limítrofes

## Ámbito Geográfico
- **Destino:** Brasil (Florianópolis, Camboriú, Bombinhas, Río de Janeiro, Salvador de Bahía, Porto de Galinhas, etc.), Uruguay, Chile, Paraguay y Bolivia.
- Cobertura aplicable tanto para traslados aéreos como en vehículos particulares o micros.

## Planes Recomendados y Coberturas Médicas
- **Coris Plan Conosur (Económico y Regional):**
  - Diseñado específicamente para países limítrofes.
  - **USD 17.000 por evento médico**.
  - Cubre emergencias médicas, internaciones, medicamentos ambulatorios y odontología de urgencia.
- **Coris Global (Cobertura Internacional Superior):**
  - **USD 100.000 por evento médico**.
  - Incluye **USD 2.000** de seguro de cancelación multicausa, mayor tope de medicamentos y compensación por pérdida de equipaje.
  - Diferencia de tarifa muy reducida para escapadas de 7 a 15 días frente al plan básico.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** El monto asegurado se renueva en su totalidad ante cada nuevo episodio de salud no relacionado.
- Cobertura ágil con red de centros médicos concertados en las principales playas del sur y nordeste brasileño.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Viajan en avión o en auto/micro, y qué edades tienen los viajeros?

## Caso Práctico de Asesoramiento
- **Pregunta:** Me voy una semana a Brasil (Camboriú / Florianópolis). Qué plan económico pero bueno tenés de Coris?
- **Respuesta:** Para viajar a Brasil o países limítrofes tenés dos excelentes alternativas en Coris. Si buscás el plan regional más común y económico, el **Plan Conosur** está diseñado específicamente para países limítrofes y cubre **USD 17.000 por evento médico**. Si preferís viajar con cobertura internacional superior y gastos de cancelación incluidos, el plan **Global (USD 100.000)** tiene una diferencia de tarifa muy accesible por 7 días.
¿Viajan en avión o en auto/micro, y qué edades tienen los viajeros?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'argentina_turismo_nacional',
        'title'       => 'Argentina Turismo Nacional — Coberturas Coris Oxygen y Nacional Premium',
        'destination' => 'Argentina (Turismo Nacional y Escapadas)',
        'metadata'    => [
            'slug'               => 'argentina_turismo_nacional',
            'destination'        => 'Argentina Turismo Nacional',
            'suggested_plans'    => ['Coris Oxygen', 'Coris Nacional Premium'],
            'coverage_per_event' => 'USD 3.000 / USD 5.500',
            'min_distance_km'    => 25,
            'cancellation'       => 'USD 200',
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Argentina Turismo Nacional

## Ámbito Geográfico y Requisito de Activación
- **Destino:** Todo el territorio nacional de Argentina (Patagonia, Bariloche, El Calafate, Ushuaia, Cataratas del Iguazú, Salta, Jujuy, Mendoza, Córdoba, Costa Atlántica) y países limítrofes.
- **Radio de Activación:** La cobertura médica y asistencial entra en vigencia a partir de los **25 km de distancia del domicilio legal/habitual** del pasajero.

## Planes Recomendados y Coberturas Médicas
- **Coris Oxygen:**
  - **USD 3.000 por evento médico**.
  - Cobertura de medicamentos recetados y reintegro por rotura, demora o extravío de equipaje en vuelos de cabotaje o micros.
- **Coris Nacional Premium:**
  - **USD 5.500 por evento médico**.
  - **USD 200** de seguro de cancelación multicausa nacional.
  - Mayor límite en medicamentos, odontología de urgencia y traslados de familiares por convalecencia.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Ambos planes operan con tope renovable por cada problema médico nuevo.
- Representa el respaldo fundamental para evitar la falta de prestadores o trámites de reintegro lentos de prepagas y obras sociales cuando se viaja a puntos turísticos alejados del país.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Cuántas personas viajan y en qué fecha salen para pasarte el valor exacto?

## Caso Práctico de Asesoramiento
- **Pregunta:** Viajo a Bariloche y el Sur argentino por 10 días, hay algún plan nacional de Coris?
- **Respuesta:** ¡Sí! Para recorrer Argentina y países limítrofes contamos con dos planes específicos muy solicitados: el plan **Oxygen** (cubre **USD 3.000** por evento médico, medicamentos y pérdida de equipaje) y el plan **Nacional Premium**, que amplía la cobertura médica a **USD 5.500 por evento**, incluye USD 200 de cancelación multicausa y aplica a partir de los 25 km de tu domicilio.
¿Cuántas personas viajan y en qué fecha salen para pasarte el valor exacto?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'caribe_cancun_punta_cana',
        'title'       => 'Caribe, Cancún y Punta Cana — Coberturas Coris Global y Global Plus',
        'destination' => 'Caribe (Cancún, Playa del Carmen, Punta Cana, Cuba, etc.)',
        'metadata'    => [
            'slug'               => 'caribe_cancun_punta_cana',
            'destination'        => 'Caribe / Cancún / Punta Cana',
            'suggested_plans'    => ['Coris Global', 'Coris Global Plus'],
            'coverage_per_event' => 'USD 100.000 / USD 150.000',
            'cancellation'       => 'USD 2.000',
            'vip_lounge_delay'   => 'USD 50 (> 2 horas)',
            'amateur_sports'     => true,
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Caribe (Cancún, Riviera Maya, Punta Cana)

## Contexto Sanitario y Turístico
- **Destino:** Caribe (Cancún, Riviera Maya, Tulum, Cozumel, Punta Cana, Santo Domingo, Varadero, La Habana, Aruba, etc.).
- **Costos en Destino:** En las zonas hoteleras del Caribe los centros de salud privados cobran tarifas muy altas en dólares para turistas, aun ante consultas habituales como gastroenteritis aguda, cuadros febriles, insolación severa, otitis o cortes con corales.

## Planes Recomendados y Coberturas Médicas
- **Coris Global (La opción preferida para el Caribe):**
  - **USD 100.000 por evento médico**.
  - **USD 2.000** de seguro de cancelación multicausa (para recuperar el valor de aéreos y noches de all inclusive no reembolsables ante imprevistos).
  - Cobertura de deportes acuáticos y actividades amateur en playa y piscina de hotel.
  - **USD 50 para Sala VIP de aeropuerto** si tu vuelo programado sufre una demora mayor a 2 horas.
- **Coris Global Plus:**
  - **USD 150.000 por evento médico** para viajar con máxima tranquilidad ante traslados médicos o intervenciones quirúrgicas.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** El capital asegurado se renueva en cada consulta o evento no conexo.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Cuántos días van a estar de viaje y qué edades tienen los pasajeros?

## Caso Práctico de Asesoramiento
- **Pregunta:** Viajamos a un all inclusive en Punta Cana / Cancún, qué cobertura lleva la mayoría?
- **Respuesta:** Para el Caribe (México, República Dominicana o Cuba), la asistencia más vendida es **Coris Global (USD 100.000 por evento médico)**. En las zonas hoteleras del Caribe las clínicas privadas para turistas manejan tarifas en dólares muy elevadas hasta por cuadros simples como gastroenteritis o insolación. Además, Coris Global te incluye **USD 2.000 de cancelación multicausa**, cobertura en playa/deportes amateur y **USD 50 para Sala VIP** si tu vuelo se demora más de 2 horas.
¿Cuántos días van a estar de viaje y qué edades tienen los pasajeros?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'adultos_mayores_preexistencias',
        'title'       => 'Adultos Mayores (+75 años) y Preexistencias — Coberturas Coris',
        'destination' => 'Mundial / Pasajeros Senior (+75) con Preexistencias',
        'metadata'    => [
            'slug'                  => 'adultos_mayores_preexistencias',
            'destination'           => 'Internacional / Senior',
            'suggested_plans'       => ['Coris Global', 'Coris Discover'],
            'coverage_per_event'    => 'USD 100.000 / USD 200.000',
            'preexistences'         => 'USD 18.000 / USD 25.000',
            'age_increment_pct'     => 50,
            'age_increment_range'   => '76 a 99 años',
            'preexistences_max_age' => 85,
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Adultos Mayores (+75 años) y Preexistencias

## Especialización de Coris para Adultos Mayores
- Coris Argentina se diferencia notablemente en el mercado por brindar amplia protección médica para adultos mayores y personas con enfermedades preexistentes o crónicas diagnosticadas (como hipertensión arterial, diabetes, arritmias, insuficiencias coronarias o asma).

## Planes Sugeridos y Límites de Cobertura
- **Coris Global:**
  - **USD 100.000 por evento médico**.
  - **USD 18.000** específicos para la atención médica, estabilización e internación derivada de enfermedades preexistentes.
- **Coris Discover:**
  - **USD 200.000 por evento médico**.
  - **USD 25.000** específicos para enfermedades preexistentes.

## Reglas de Suscripción y Tarifas por Edad
- **Incremento tarifario entre 76 y 99 años:** Se aplica un adicional del **50% sobre la tarifa base**, manteniendo intacta la calidad, montos indemnizatorios y prestaciones de la asistencia.
- **Límite de edad para preexistencias:** La cobertura especial para patologías preexistentes puede incluirse hasta los **85 años** de edad al momento del viaje.
- **Tope por evento médico:** La cobertura médica opera con tope por evento médico (se renueva ante cada cuadro de salud diferente).

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Cuántos días dura el viaje de tu papá / acompañante para calcularle la tarifa con el rango de edad exacto?

## Caso Práctico de Asesoramiento
- **Pregunta:** Mi papá tiene 78 años, es hipertenso y viaja a Europa. Coris lo cubre?
- **Respuesta:** Sí, Coris se destaca en el mercado por su excelente cobertura para adultos mayores y enfermedades preexistentes (como hipertensión o diabetes). Con 78 años puede contratar los planes **Global (USD 100.000 médicos y USD 18.000 para preexistencias)** o **Discover (USD 200.000 médicos y USD 25.000 para preexistencias)**. Para pasajeros de entre 76 y 99 años se mantiene exactamente la misma calidad de prestaciones aplicando un incremento del 50% en la tarifa, y la cobertura de preexistencias está disponible hasta los 85 años.
¿Cuántos días dura el viaje de tu papá para calcularle la tarifa con el rango de edad?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'embarazadas_cruceros',
        'title'       => 'Embarazadas y Cruceros — Beneficios Incluidos Coris',
        'destination' => 'Cruceros Internacionales y Embarazadas (hasta semana 32)',
        'metadata'    => [
            'slug'               => 'embarazadas_cruceros',
            'destination'        => 'Internacional / Cruceros',
            'suggested_plans'    => ['Coris Global', 'Coris Global Plus', 'Coris Discover'],
            'cruise_included'    => true,
            'pregnancy_max_week' => 32,
            'pregnancy_limit'    => 'USD 10.000',
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Embarazadas y Viajes en Crucero

## Beneficios Incluidos sin Costo Adicional
- En los planes principales de Coris (**Global, Global Plus y Discover**), dos coberturas indispensables vienen **incluidas sin costo adicional**:
  1. **Asistencia Médica en Cruceros:** La enfermería y el médico a bordo de buques de crucero no están incluidos en el costo del pasaje naval y cobran tarifas en dólares sumamente costosas. Coris cubre los gastos médicos a bordo y coordina traslados o desembarcos de emergencia en puerto si fuera necesario.
  2. **Cobertura Plus para Embarazadas:** Protección médica específica hasta la **semana 32 de gestación inclusive**, con un tope de hasta **USD 10.000** para controles médicos de urgencia, ecografías de control o eventualidades imprevistas derivadas del embarazo.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Las demás consultas médicas no asociadas a la gestación mantienen el tope médico total del plan (USD 100k en Global, USD 150k en Global Plus o USD 200k en Discover) con renovación de tope por cada evento.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿En qué fecha embarcan y cuántos días dura el itinerario total desde que salen de su casa?

## Caso Práctico de Asesoramiento
- **Pregunta:** Estoy embarazada de 20 semanas y vamos a hacer un crucero por Brasil, me cubre la asistencia?
- **Respuesta:** ¡Totalmente! Los planes principales de Coris (**Global, Global Plus y Discover**) incluyen dos beneficios clave sin costo extra para tu viaje: **Asistencia médica en Cruceros** (donde la enfermería a bordo no está incluida en el ticket del barco y es muy costosa) y **Cobertura Plus para Embarazadas hasta la semana 32 de gestación** por hasta **USD 10.000** para controles de urgencia o eventualidades derivadas del embarazo.
¿En qué fecha embarcan y cuántos días dura el itinerario total desde que salen de casa?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'multiviajes_anual_larga_estadia',
        'title'       => 'Multiviajes Anual y Larga Estadía — Coberturas Coris',
        'destination' => 'Internacional Frecuente / Estudiantes / Working Holiday',
        'metadata'    => [
            'slug'              => 'multiviajes_anual_larga_estadia',
            'destination'       => 'Mundial Multiviajes',
            'suggested_plans'   => ['Anual Multiviajes 30/60/90', 'Larga Estadía'],
            'validity_days'     => 365,
            'max_days_per_trip' => '30, 60 o 90 días corridos',
            'long_stay_max_age' => 45,
            'coverage_per_event'=> 'USD 100.000 a USD 300.000',
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Plan Anual Multiviajes y Larga Estadía

## Comparativa y Soluciones de Viaje Frecuente

### 1. Plan Anual Multiviajes (365 Días)
- **Perfil de Viajero:** Para personas que realizan dos o más viajes internacionales en el transcurso del año (por negocios, vacaciones familiares o escapadas). Resulta sustancialmente más económico que abonar pólizas por días separados.
- **Características Operativas:**
  - Vigencia de 365 días corridos.
  - Cantidad ilimitada de viajes durante el año.
  - El usuario elige la duración tope por cada viaje: **hasta 30, 60 o 90 días corridos** por salida. Cada vez que el pasajero regresa a Argentina, el contador de días se reinicia para el siguiente viaje.
  - Disponible en las líneas **Global (USD 100.000)**, **Global Plus (USD 150.000)** y **Business (USD 300.000)**.

### 2. Plan Larga Estadía (Estudios, Pasantías, Working Holiday)
- **Perfil de Viajero:** Para pasajeros de hasta **45 años de edad** que viajarán de forma continuada durante varios meses consecutivos al exterior sin regresar a Argentina en el corto plazo.
- **Cobertura:** **USD 100.000 por evento médico** durante todo el período continuo contratado.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Todos los planes operan con tope por evento (la cobertura médica se renueva de forma íntegra ante cada consulta o problema médico independiente).

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿Cuál suele ser la duración máxima de cada uno de tus viajes (menos de 30 días o más)?

## Caso Práctico de Asesoramiento
- **Pregunta:** Viajo varias veces al año por trabajo o vacaciones, me conviene sacar por viaje o un plan anual?
- **Respuesta:** Si hacés más de dos viajes al año al exterior, te resulta mucho más económico contratar un **Plan Anual Multiviajes** de Coris. Te cubre todos los viajes que realices durante 365 días, siempre que cada viaje no supere los **30, 60 o 90 días corridos** (según la modalidad que elijas). Está disponible en las líneas **Global (USD 100.000)**, **Global Plus (USD 150.000)** y **Business (USD 300.000)**. En cambio, si vas a vivir o estudiar de corrido varios meses afuera (hasta 45 años), te conviene el plan **Larga Estadía (USD 100.000)**.
¿Cuál suele ser la duración máxima de cada uno de tus viajes (menos de 30 días o más)?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'upgrades_personalizacion',
        'title'       => 'Upgrades de Personalización — Coris Mascotas, Psicológica y Ellas',
        'destination' => 'Mundial / Upgrades de Viaje',
        'metadata'    => [
            'slug'            => 'upgrades_personalizacion',
            'destination'     => 'Internacional / Upgrades',
            'available_addons'=> ['Coris Mascotas', 'Coris Asistencia Psicológica', 'Coris Ellas'],
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Upgrades de Personalización

## Coberturas Opcionales Adicionales de Coris
Podés personalizar tu asistencia sumando cualquiera de los 3 nuevos **Upgrades de Coris** para viajar con soluciones adaptadas a tu perfil:

### 1. Coris Mascotas
- Cobertura de atención médica veterinaria de urgencia, medicamentos y estadía en guardería por convalecencia del titular para perros o gatos que viajen con el asegurado.

### 2. Coris Asistencia Psicológica
- Brinda hasta **3 sesiones de contención y acompañamiento profesional** en viaje con psicólogos matriculados ante situaciones de alto estrés imprevistas (robos violentos, accidentes o noticias familiares graves a la distancia).

### 3. Coris Ellas
- Prestaciones y consultas médicas especializadas orientadas al cuidado, bienestar y salud integral de la mujer viajera durante su itinerario.

## Reglas Operativas Clave de Coris
- Se pueden añadir como upgrade a cualquiera de los planes internacionales principales (Global, Global Plus, Discover o Business).
- Mantienen el principio de **tope por evento médico** característico de Coris.

## Directivas Comerciales y Canales de Cotización
- Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿A qué destino viajás y cuál de estos upgrades te gustaría sumar a tu presupuesto?

## Caso Práctico de Asesoramiento
- **Pregunta:** Escuché que Coris tiene coberturas nuevas para mascotas o asistencia psicológica en viaje, cómo funcionan?
- **Respuesta:** Exacto, podés personalizar tu asistencia sumando cualquiera de los 3 nuevos **Upgrades de Coris**: 1) **Coris Mascotas**: cobertura de atención veterinaria de urgencia para tu mascota en viaje. 2) **Coris Asistencia Psicológica**: brinda hasta 3 sesiones de contención profesional en viaje ante situaciones de alto estrés (robos, accidentes o noticias familiares difíciles). 3) **Coris Ellas**: prestaciones específicas orientadas a la salud y bienestar integral de la mujer viajera.
¿A qué destino viajás y cuál de estos upgrades te gustaría sumar a tu presupuesto?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
    ],

    [
        'slug'        => 'reglas_operativas_en_viaje',
        'title'       => 'Reglas Operativas — Pasajero en Viaje, Renovación y Carencia Coris',
        'destination' => 'Pasajero ya en destino / Emisión tardía',
        'metadata'    => [
            'slug'                   => 'reglas_operativas_en_viaje',
            'destination'            => 'Internacional / En viaje',
            'prior_issue_required'   => true,
            'carencia_mandatory'     => true,
            'requires_authorization' => true,
        ],
        'content_markdown' => <<<MD
# Asistencia al Viajero Coris — Reglas Operativas: Pasajero ya en Viaje y Carencia

## Regla General de Emisión Obligatoria
- Por políticas operativas estrictas de Coris Argentina y del mercado de asistencia al viajero, el voucher **debe contratarse antes de la fecha de inicio del viaje** desde la República Argentina (país de residencia habitual del pasajero).
- Los sistemas web de emisión automática y venta online **no validan vouchers** si el pasajero ya se encuentra físicamente fuera del país.

## Procedimiento para Pasajeros que ya están de Viaje o Renovaciones
- Si el pasajero ya salió de Argentina y olvidó emitir su asistencia, o se encuentra en destino y necesita renovar su cobertura:
  1. No se puede emitir directamente online.
  2. Es obligatorio solicitar una **autorización especial a la central operativa de Coris** a través de los asesores de Relax Tours.
  3. Toda emisión autorizada para un pasajero ya en viaje rige con un **período de carencia obligatorio** (habitualmente de 3 a 5 días de espera desde la emisión hasta que la cobertura médica se activa), para impedir la contratación con siniestros preexistentes en curso.

## Reglas Operativas Clave de Coris
- **Tope por evento médico:** Una vez transcurrido el período de carencia, la asistencia entra en vigor pleno con su tope por evento médico habitual.

## Directivas Comerciales y Canales de Cotización
- No prometer cobertura médica inmediata sin carencia si el pasajero ya se encuentra fuera del país.
- Los asesores de Relax Tours gestionan la solicitud con Coris previa recepción de los datos del viaje.
- Podés pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
- **Pregunta de seguimiento (follow-up):** ¿En qué fecha saliste de Argentina y hasta qué día te quedás en el exterior para consultar la autorización de carencia con la central?

## Caso Práctico de Asesoramiento
- **Pregunta:** Ya estoy de viaje en Madrid y me olvidé de sacar el seguro antes de salir, lo puedo emitir hoy para usarlo mañana?
- **Respuesta:** Por políticas operativas de Coris Argentina, la asistencia debe contratarse antes del inicio del viaje desde el país de residencia; el sistema web no valida vouchers emitidos de forma automática cuando el pasajero ya se encuentra en destino. Sin embargo, podemos gestionar una solicitud especial de emisión con **período de carencia** (días de espera antes de que se active la cobertura) previa autorización de la central.
¿En qué fecha saliste de Argentina y hasta qué día te quedás en Europa para consultar la autorización?
Podés cotizar y emitir tu asistencia directamente desde https://www.relaxtours.com.ar/asistencia o pedir cotización tocando la pestaña de WhatsApp, completando el formulario de Correo o tocando la pestaña de Teléfono aquí abajo en el chat.
MD
        ],
    ];

    return $cases;
}

if (!function_exists('syncCorisKnowledgeToSupabase')) {
    /**
     * Sincroniza y vectoriza los 10 casos canónicos de Asistencia Coris en Supabase (public.knowledge_base).
     * Reutilizable tanto por CLI como desde el panel Backoffice (admin/index.php).
     *
     * @param SupabaseClient|null $supabase
     * @param GeminiClient|null $gemini
     * @param callable|null $logger Callback opcional para logging: fn(string $msg) => void
     * @return array{ok: bool, synced: int, failed: int, errors: array<string>}
     */
    function syncCorisKnowledgeToSupabase(?SupabaseClient $supabase = null, ?GeminiClient $gemini = null, ?callable $logger = null): array
    {
        if ($supabase === null) {
            $supabase = new SupabaseClient();
        }
        if ($gemini === null) {
            $gemini = new GeminiClient();
        }

        $log = function (string $msg) use ($logger) {
            if ($logger !== null && is_callable($logger)) {
                $logger($msg);
            }
        };

        if (!$supabase->isConfigured()) {
            $err = 'Supabase no está configurado correctamente en admin/.env';
            $log('[ERROR] ' . $err);
            return ['ok' => false, 'synced' => 0, 'failed' => 10, 'errors' => [$err]];
        }

        if (!$gemini->isConfigured()) {
            $err = 'Gemini no está configurado (falta GEMINI_API_KEY en admin/.env)';
            $log('[ERROR] ' . $err);
            return ['ok' => false, 'synced' => 0, 'failed' => 10, 'errors' => [$err]];
        }

        $cases = getCorisCanonicalCases();
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($cases as $index => $case) {
            $num = $index + 1;
            $slug = $case['slug'];
            $externalId = 'coris_' . $slug;
            $title = $case['title'];
            $markdown = normalizeWidgetCall($case['content_markdown']);
            $metadata = $case['metadata'];
            $metadata['canonical_case_number'] = $num;
            $metadata['source'] = 'coris_canonical_dataset';

            $log("--------------------------------------------------------------------");
            $log("[$num/10] Procesando: {$externalId}");
            $log("       Título: {$title}");

            // 1. Generar embedding vectorial de 768 dimensiones
            $startEmb = microtime(true);
            $embedding = $gemini->generateEmbedding($markdown, 'RETRIEVAL_DOCUMENT');
            $embDuration = round((microtime(true) - $startEmb) * 1000, 1);

            if (!is_array($embedding) || empty($embedding)) {
                $err = "Caso {$num} ({$externalId}): Falló la generación de embedding: " . $gemini->getLastError();
                $log("       [ERROR] {$err}");
                $errors[] = $err;
                $failCount++;
                continue;
            }

            $dim = count($embedding);
            if ($dim !== 768) {
                $log("       [ALERTA] La dimensión del embedding es {$dim} (se esperaban 768)");
            } else {
                $log("       [OK] Embedding generado ({$dim} dimensiones, {$embDuration} ms)");
            }

            // 2. Insertar o actualizar (UPSERT) en Supabase public.knowledge_base
            $startUpsert = microtime(true);
            $ok = $supabase->upsertKnowledge(
                $externalId,
                'coris',
                $title,
                $markdown,
                null,
                $metadata,
                $embedding,
                true
            );
            $upsertDuration = round((microtime(true) - $startUpsert) * 1000, 1);

            if ($ok) {
                $log("       [OK] Guardado en Supabase knowledge_base (HTTP {$supabase->getLastHttpCode()}, {$upsertDuration} ms)");
                $successCount++;
            } else {
                $err = "Caso {$num} ({$externalId}): Falló al guardar en Supabase: " . $supabase->getLastError();
                $log("       [ERROR] {$err}");
                $errors[] = $err;
                $failCount++;
            }

            // Pequeña pausa entre llamadas a Gemini para no saturar Rate Limits de Google AI Studio
            usleep(350000);
        }

        return [
            'ok'     => ($failCount === 0),
            'synced' => $successCount,
            'failed' => $failCount,
            'errors' => $errors,
        ];
    }
}

// Ejecución directa vía CLI
$isCliDirect = (php_sapi_name() === 'cli') && (
    (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) ||
    (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__))
);

if ($isCliDirect) {
    echo "====================================================================\n";
    echo " Relax Tours — Ingesta de Conocimiento: Asistencia al Viajero Coris\n";
    echo "====================================================================\n\n";

    $supabase = new SupabaseClient();
    $gemini   = new GeminiClient();

    // Ruta opcional al dataset JSONL original proporcionado
    $jsonlCandidates = [
        'C:/Users/Jeniffer Del Estero/.gemini/antigravity/brain/9b8f280a-258d-4d02-8f05-14f4b649bd2c/.user_uploaded/media_1793347667742_d2669921.jsonl',
        __DIR__ . '/../data/media_1793347667742_d2669921.jsonl',
    ];

    $jsonlPairs = [];
    foreach ($jsonlCandidates as $path) {
        if (file_exists($path) && is_readable($path)) {
            echo "[INFO] Cargando dataset de referencia desde: {$path}\n";
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $item = json_decode($line, true);
                    if (!is_array($item) || empty($item['messages'])) {
                        continue;
                    }
                    $userMsg = '';
                    $assistantMsg = '';
                    foreach ($item['messages'] as $m) {
                        if (($m['role'] ?? '') === 'user') {
                            $userMsg = trim((string) ($m['content'] ?? ''));
                        } elseif (($m['role'] ?? '') === 'assistant') {
                            $assistantMsg = trim((string) ($m['content'] ?? ''));
                        }
                    }
                    if ($userMsg !== '' && $assistantMsg !== '') {
                        $jsonlPairs[] = [
                            'user'      => $userMsg,
                            'assistant' => $assistantMsg,
                        ];
                    }
                }
            }
            break;
        }
    }

    if (!empty($jsonlPairs)) {
        echo "[INFO] Pares Q&A detectados en JSONL: " . count($jsonlPairs) . "\n\n";
    }

    echo "Iniciando ingesta de los 10 casos en Supabase (public.knowledge_base)...\n";

    $res = syncCorisKnowledgeToSupabase($supabase, $gemini, function (string $msg) {
        echo $msg . "\n";
    });

    echo "\n====================================================================\n";
    echo " RESUMEN DE INGESTA CORIS:\n";
    echo " Casos procesados con éxito : {$res['synced']} / 10\n";
    echo " Casos con fallos           : {$res['failed']}\n";
    echo "====================================================================\n";

    if ($res['ok']) {
        echo "\n¡Los 10 registros de Asistencia al Viajero Coris fueron ingestados con éxito!\n";
        exit(0);
    } else {
        echo "\nHubo errores durante la ingesta. Revisá los logs anteriores.\n";
        exit(1);
    }
}
