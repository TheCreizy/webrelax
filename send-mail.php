<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 1. Cargar la librería PHPMailer
require 'PHPMailer/PHPMailer-master/src/Exception.php';
require 'PHPMailer/PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer/PHPMailer-master/src/SMTP.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    $type = $_POST['form_type'] ?? '';

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP (Hostinger) ---
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';          // Servidor SMTP de Hostinger
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@relaxtours.com.ar';   // Reemplaza por tu cuenta creada en Hostinger
        $mail->Password = '86V&CVVdFvi*!F=';             // Reemplaza por la contraseña de esa cuenta
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;   // SSL
        $mail->Port = 465;                           // Puerto SSL
        $mail->CharSet = 'UTF-8';

        // Remitente obligatorio (debe coincidir con Username para evitar bloqueos)
        $mail->setFrom('noreply@relaxtours.com.ar', 'Relax Tours Web');

        // --- LÓGICA DE FORMULARIO DE CONTACTO ---
        if ($type === 'contacto') {
            $nombre = strip_tags(trim($_POST['name'] ?? ''));
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $telefono = strip_tags(trim($_POST['phone'] ?? ''));
            $consulta = strip_tags(trim($_POST['message'] ?? ''));

            if (empty($nombre) || empty($email) || empty($consulta)) {
                echo json_encode(['status' => 'error', 'message' => 'Campos obligatorios faltantes']);
                exit;
            }

            // Destinatario y contenido
            $mail->addAddress('ventas1@relaxtours.com.ar');
            $mail->addReplyTo($email, $nombre);
            $mail->Subject = "Nuevo mensaje de contacto - Relax Tours Web";

            $email_content = "Has recibido un nuevo mensaje de contacto desde la web.\n\n";
            $email_content .= "Nombre Completo: $nombre\n";
            $email_content .= "Correo Electrónico: $email\n";
            $email_content .= "Teléfono: " . ($telefono ?: "No especificado") . "\n";
            $email_content .= "Consulta:\n$consulta\n";

            $mail->Body = $email_content;

            // --- LÓGICA DE FORMULARIO DE ARREPENTIMIENTO ---
        } elseif ($type === 'arrepentimiento') {
            $nombre = strip_tags(trim($_POST['name'] ?? ''));
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $fecha_compra = strip_tags(trim($_POST['purchase_date'] ?? ''));
            $fecha_viaje = strip_tags(trim($_POST['travel_date'] ?? ''));
            $reserva = strip_tags(trim($_POST['reservation_number'] ?? ''));
            $servicio = strip_tags(trim($_POST['service'] ?? ''));
            $motivo = strip_tags(trim($_POST['reason'] ?? ''));

            if (empty($nombre) || empty($email) || empty($fecha_compra) || empty($fecha_viaje) || empty($reserva) || empty($servicio) || empty($motivo)) {
                echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios']);
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
            echo json_encode(['status' => 'error', 'message' => 'Tipo de formulario no válido']);
            exit;
        }

        // Enviar el correo
        $mail->send();

        // Respuesta exitosa según lo que espera tu main.js
        if ($type === 'contacto') {
            echo json_encode(['status' => 'success', 'message' => 'Gracias por contactarnos! Te responderemos a la brevedad!']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Solicitud enviada correctamente. El equipo de ventas se contactará con usted.']);
        }

    } catch (Exception $e) {
        // Si PHPMailer falla, devuelve el error específico
        echo json_encode(['status' => 'error', 'message' => "Hubo un error al enviar: {$mail->ErrorInfo}"]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no permitido']);
}
?>