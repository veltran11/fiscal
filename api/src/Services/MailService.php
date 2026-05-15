<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    /**
     * Envía un email usando PHPMailer.
     * Soporta SMTP si está configurado, o fallback a mail().
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $cfg = self::getConfig();
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP (si existe)
            $smtp = $cfg['mail']['smtp'] ?? null;
            if (!empty($smtp['host'])) {
                $mail->isSMTP();
                $mail->Host       = $smtp['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp['username'];
                $mail->Password   = $smtp['password'];
                $mail->SMTPSecure = $smtp['secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtp['port'] ?? 587;
                $mail->CharSet    = 'UTF-8';
            }

            // Remitente
            $mail->setFrom($cfg['mail']['from'], $cfg['mail']['from_name'] ?? '');
            $mail->addReplyTo($cfg['mail']['from'], $cfg['mail']['from_name'] ?? '');

            // Destinatario
            $mail->addAddress($to);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (Exception $e) {
            error_log('MailService error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public static function from(): string
    {
        $cfg = self::getConfig();
        return $cfg['mail']['from'] ?? 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private static function getConfig(): array
    {
        $cfg = require __DIR__ . '/../Config/config.php';

        // Sobreescribir con config.local.php si existe
        $localFile = __DIR__ . '/../Config/config.local.php';
        if (file_exists($localFile)) {
            $local = require $localFile;
            $cfg = array_merge_recursive($cfg, $local);
        }

        return $cfg;
    }
}
