<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 1. Cargar la librería PHPMailer
require __DIR__ . '/PHPMailer/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/PHPMailer-master/src/SMTP.php';

// 2. Cargar variables de entorno desde includes/env-loader.php
require_once __DIR__ . '/includes/env-loader.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Soportar tanto $_POST tradicional (FormData) como body JSON (application/json desde el widget)
    $requestData = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (empty($requestData) || stripos($contentType, 'application/json') !== false) {
        $rawJson = file_get_contents('php://input');
        if (!empty($rawJson)) {
            $decodedJson = json_decode($rawJson, true);
            if (is_array($decodedJson)) {
                $requestData = array_merge($requestData, $decodedJson);
            }
        }
    }

    $mail = new PHPMailer(true);
    $type = $requestData['form_type'] ?? 'contacto';

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP (Hostinger) ---
        $smtpUser = env('SMTP_USER', 'noreply@relaxtours.com.ar');
        $smtpPass = env('SMTP_PASS', '');

        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';          // Servidor SMTP de Hostinger
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;   // SSL
        $mail->Port = 465;                           // Puerto SSL
        $mail->CharSet = 'UTF-8';

        // Remitente obligatorio (debe coincidir con Username para evitar bloqueos)
        $mail->setFrom($smtpUser, 'Relax Tours Web');

        // --- LÓGICA DE FORMULARIO DE CONTACTO (Footer y Widget IA) ---
        if ($type === 'contacto') {
            $nombre = strip_tags(trim((string) ($requestData['name'] ?? $requestData['full_name'] ?? '')));
            $email = filter_var(trim((string) ($requestData['email'] ?? '')), FILTER_SANITIZE_EMAIL);
            $telefono = strip_tags(trim((string) ($requestData['phone'] ?? '')));
            $consulta = strip_tags(trim((string) ($requestData['message'] ?? '')));
            $origen = strip_tags(trim((string) ($requestData['source'] ?? 'Web Relax Tours')));

            if (empty($nombre) || empty($email) || empty($consulta)) {
                echo json_encode([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Campos obligatorios faltantes'
                ]);
                exit;
            }

            // Destinatario y contenido
            $mail->addAddress('ventas1@relaxtours.com.ar');
            $mail->addReplyTo($email, $nombre);
            $mail->Subject = "Nuevo mensaje de contacto - Relax Tours Web";

            $email_content = "Has recibido un nuevo mensaje de contacto desde la web ($origen).\n\n";
            $email_content .= "Nombre Completo: $nombre\n";
            $email_content .= "Correo Electrónico: $email\n";
            $email_content .= "Teléfono: " . ($telefono ?: "No especificado") . "\n";
            $email_content .= "Consulta:\n$consulta\n";

            $mail->Body = $email_content;

            // --- LÓGICA DE FORMULARIO DE ARREPENTIMIENTO ---
        } elseif ($type === 'arrepentimiento') {
            $nombre = strip_tags(trim((string) ($requestData['name'] ?? '')));
            $email = filter_var(trim((string) ($requestData['email'] ?? '')), FILTER_SANITIZE_EMAIL);
            $fecha_compra = strip_tags(trim((string) ($requestData['purchase_date'] ?? '')));
            $fecha_viaje = strip_tags(trim((string) ($requestData['travel_date'] ?? '')));
            $reserva = strip_tags(trim((string) ($requestData['reservation_number'] ?? '')));
            $servicio = strip_tags(trim((string) ($requestData['service'] ?? '')));
            $motivo = strip_tags(trim((string) ($requestData['reason'] ?? '')));

            if (empty($nombre) || empty($email) || empty($fecha_compra) || empty($fecha_viaje) || empty($reserva) || empty($servicio) || empty($motivo)) {
                echo json_encode([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Todos los campos son obligatorios'
                ]);
                exit;
            }

            // Destinatarios y contenido
            $mail->addAddress('ventas1@relaxtours.com.ar');
            $mail->addAddress('miguel.lerner@relaxtours.com.ar');
            $mail->addReplyTo($email, $nombre);
            $mail->Subject = "Arrepentimiento - $nombre - $reserva";

            $email_content = "Se ha recibido una solicitud de arrepentimiento de compra.\n\n";
            $email_content .= "Nombre: $nombre\n";
            $email_content .= "Email: $email\n";
            $email_content .= "Fecha de compra: $fecha_compra\n";
            $email_content .= "Fecha del viaje: $fecha_viaje\n";
            $email_content .= "Número de reserva: $reserva\n";
            $email_content .= "Servicios contratados: $servicio\n";
            $email_content .= "Motivo del arrepentimiento:\n$motivo\n";

            $mail->Body = $email_content;

        } else {
            echo json_encode([
                'ok' => false,
                'status' => 'error',
                'message' => 'Tipo de formulario no válido'
            ]);
            exit;
        }

        // Enviar el correo
        $mail->send();

        // Respuesta exitosa retrocompatible con main.js ('status' => 'success') y con el nuevo widget ('ok' => true)
        if ($type === 'contacto') {
            echo json_encode([
                'ok' => true,
                'status' => 'success',
                'message' => 'Gracias por contactarnos! Te responderemos a la brevedad!'
            ]);
        } else {
            echo json_encode([
                'ok' => true,
                'status' => 'success',
                'message' => 'Solicitud enviada correctamente. El equipo de ventas se contactará con usted.'
            ]);
        }

    } catch (Exception $e) {
        // Security: Log detailed error server-side and avoid leaking internal SMTP/system details ($mail->ErrorInfo) to clients
        error_log("PHPMailer error in send-mail.php: " . $mail->ErrorInfo);
        echo json_encode([
            'ok' => false,
            'status' => 'error',
            'message' => "Hubo un error al enviar el mensaje. Por favor intenta nuevamente o contáctanos por WhatsApp."
        ]);
    }
} else {
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'message' => 'Acceso no permitido'
    ]);
}
?>
