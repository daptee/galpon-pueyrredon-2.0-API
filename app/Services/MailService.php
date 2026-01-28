<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Envía un correo y lo guarda en la carpeta de enviados via IMAP
     */
    public static function sendAndSave($to, $mailable)
    {
        // Enviar el correo
        Mail::to($to)->send($mailable);

        // Intentar guardar en carpeta de enviados
        try {
            self::saveToSentFolder($mailable);
        } catch (\Exception $e) {
            Log::warning('No se pudo guardar el correo en la carpeta de enviados', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
        }
    }

    /**
     * Guarda el correo en la carpeta de enviados usando IMAP
     */
    private static function saveToSentFolder($mailable)
    {
        $host = env('IMAP_HOST');
        $port = env('IMAP_PORT', 993);
        $encryption = env('IMAP_ENCRYPTION', 'ssl');
        $username = env('IMAP_USERNAME');
        $password = env('IMAP_PASSWORD');
        $sentFolder = env('IMAP_SENT_FOLDER', 'INBOX.Sent');

        if (!$host || !$username || !$password) {
            Log::info('IMAP no configurado, no se guardará en carpeta de enviados');
            return;
        }

        // Construir la cadena de conexión IMAP
        $mailbox = "{" . $host . ":" . $port . "/imap/" . $encryption . "}" . $sentFolder;

        // Conectar al servidor IMAP
        $imap = @imap_open($mailbox, $username, $password);

        if (!$imap) {
            $error = imap_last_error();
            Log::error('Error al conectar con IMAP', ['error' => $error]);
            throw new \Exception('Error IMAP: ' . $error);
        }

        try {
            // Renderizar el correo como string
            $message = self::renderMailable($mailable);

            // Guardar en la carpeta de enviados
            $result = imap_append($imap, $mailbox, $message, "\\Seen");

            if (!$result) {
                throw new \Exception('No se pudo guardar el correo: ' . imap_last_error());
            }

            Log::info('Correo guardado en carpeta de enviados');
        } finally {
            imap_close($imap);
        }
    }

    /**
     * Renderiza un Mailable a formato de mensaje de correo
     */
    private static function renderMailable($mailable)
    {
        // Construir el mailable para obtener subject y otras propiedades
        $mailable->build();

        // Construir manualmente el mensaje en formato RFC 822
        $to = $mailable->to[0]['address'] ?? '';
        $subject = $mailable->subject ?? '';
        $from = env('MAIL_FROM_ADDRESS');
        $date = date('r');

        // Obtener las propiedades públicas del Mailable como datos para la vista
        $viewData = [];
        $reflection = new \ReflectionClass($mailable);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (!in_array($name, ['to', 'cc', 'bcc', 'replyTo', 'subject', 'from', 'callbacks'])) {
                $viewData[$name] = $property->getValue($mailable);
            }
        }

        // Renderizar el contenido usando el sistema de markdown de Laravel con estilos
        $markdown = app(\Illuminate\Mail\Markdown::class);
        $html = $markdown->render($mailable->markdown, $viewData)->toHtml();

        $boundary = md5(time());

        // Codificar el subject en UTF-8 para soportar caracteres especiales
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = "From: {$from}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subjectEncoded}\r\n";
        $headers .= "Date: {$date}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $headers .= "\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $body .= "\r\n";
        $body .= quoted_printable_encode($html);
        $body .= "\r\n";

        // Agregar adjuntos si existen
        if (!empty($mailable->attachments)) {
            foreach ($mailable->attachments as $attachment) {
                $filePath = $attachment['file'] ?? null;
                $options = $attachment['options'] ?? [];

                if ($filePath && file_exists($filePath)) {
                    $fileName = $options['as'] ?? basename($filePath);
                    $mimeType = $options['mime'] ?? mime_content_type($filePath);
                    $fileContent = file_get_contents($filePath);

                    // Codificar nombre del archivo para soportar caracteres especiales
                    $fileNameEncoded = '=?UTF-8?B?' . base64_encode($fileName) . '?=';

                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Type: {$mimeType}; name=\"{$fileNameEncoded}\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"{$fileNameEncoded}\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n";
                    $body .= "\r\n";
                    $body .= chunk_split(base64_encode($fileContent));
                }
            }
        }

        $body .= "--{$boundary}--\r\n";

        return $headers . $body;
    }
}
