<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Classe utilitaire d'envoi d'email via SMTP en PHP natif.
 * Utilise la fonction mail() avec des en-têtes SMTP ou fsockopen() direct.
 * Pour la production, PHPMailer est recommandé. Cette classe utilise
 * une connexion SMTP directe sans dépendance externe.
 */
class Mailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private string $encryption;

    public function __construct()
    {
        $this->host       = getenv('SMTP_HOST') ?: 'localhost';
        $this->port       = (int) (getenv('SMTP_PORT') ?: 587);
        $this->username   = getenv('SMTP_USER') ?: '';
        $this->password   = getenv('SMTP_PASS') ?: '';
        $this->fromEmail  = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@scores.local';
        $this->fromName   = getenv('MAIL_FROM_NAME') ?: 'Scores App';
        $this->encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
    }

    /**
     * Envoie un email via SMTP.
     *
     * @throws \RuntimeException si l'envoi échoue
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $socket = $this->connect();

        try {
            $this->expect($socket, 220);
            $this->command($socket, "EHLO " . gethostname(), 250);

            // STARTTLS si nécessaire
            if ($this->encryption === 'tls' && $this->port !== 465) {
                $this->command($socket, "STARTTLS", 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->command($socket, "EHLO " . gethostname(), 250);
            }

            // Authentification
            if ($this->username && $this->password) {
                $this->command($socket, "AUTH LOGIN", 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }

            // Envoi
            $this->command($socket, "MAIL FROM:<{$this->fromEmail}>", 250);
            $this->command($socket, "RCPT TO:<{$to}>", 250);
            $this->command($socket, "DATA", 354);

            // Construire le message
            $headers = $this->buildHeaders($to, $subject);
            $message = $headers . "\r\n" . $htmlBody . "\r\n.";
            $this->command($socket, $message, 250);

            $this->command($socket, "QUIT", 221);

            return true;
        } catch (\RuntimeException $e) {
            // Log l'erreur si debug activé
            if (getenv('APP_DEBUG') === 'true') {
                error_log("Mailer error: " . $e->getMessage());
            }
            throw $e;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Ouvre la connexion au serveur SMTP.
     *
     * @return resource
     */
    private function connect()
    {
        $protocol = ($this->encryption === 'ssl' || $this->port === 465) ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $protocol . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            15, // timeout
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]])
        );

        if (!$socket) {
            throw new \RuntimeException("Impossible de se connecter au serveur SMTP {$this->host}:{$this->port} ({$errno}: {$errstr})");
        }

        stream_set_timeout($socket, 15);
        return $socket;
    }

    /**
     * Envoie une commande SMTP et vérifie le code de réponse.
     *
     * @param resource $socket
     */
    private function command($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCode);
    }

    /**
     * Lit la réponse du serveur et vérifie le code.
     *
     * @param resource $socket
     */
    private function expect($socket, int $expectedCode): string
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new \RuntimeException("Pas de réponse du serveur SMTP.");
            }
            $response .= $line;
            // La dernière ligne du SMTP a un espace après le code (ex: "250 OK")
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("Réponse SMTP inattendue: attendu {$expectedCode}, reçu {$code} – {$response}");
        }

        return $response;
    }

    /**
     * Construit les en-têtes du message.
     */
    private function buildHeaders(string $to, string $subject): string
    {
        $boundary = md5(uniqid());
        $date = date('r');

        return implode("\r\n", [
            "Date: {$date}",
            "From: {$this->fromName} <{$this->fromEmail}>",
            "To: {$to}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "X-Mailer: Scores-App/1.0",
        ]);
    }

    /**
     * Envoie un email à plusieurs destinataires en BCC (un seul mail SMTP).
     *
     * @param string[] $bccEmails Liste des adresses en BCC
     */
    public function sendBcc(array $bccEmails, string $subject, string $htmlBody): bool
    {
        $bccEmails = array_unique(array_filter($bccEmails));
        if (empty($bccEmails)) {
            return false;
        }

        $socket = $this->connect();

        try {
            $this->expect($socket, 220);
            $this->command($socket, "EHLO " . gethostname(), 250);

            if ($this->encryption === 'tls' && $this->port !== 465) {
                $this->command($socket, "STARTTLS", 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->command($socket, "EHLO " . gethostname(), 250);
            }

            if ($this->username && $this->password) {
                $this->command($socket, "AUTH LOGIN", 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }

            $this->command($socket, "MAIL FROM:<{$this->fromEmail}>", 250);

            foreach ($bccEmails as $email) {
                $this->command($socket, "RCPT TO:<{$email}>", 250);
            }

            $this->command($socket, "DATA", 354);

            $headers = $this->buildBccHeaders($subject);
            $message = $headers . "\r\n" . $htmlBody . "\r\n.";
            $this->command($socket, $message, 250);

            $this->command($socket, "QUIT", 221);

            return true;
        } catch (\RuntimeException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                error_log("Mailer BCC error: " . $e->getMessage());
            }
            throw $e;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Construit les en-têtes pour un envoi BCC (pas de To visible).
     */
    private function buildBccHeaders(string $subject): string
    {
        $date = date('r');

        return implode("\r\n", [
            "Date: {$date}",
            "From: {$this->fromName} <{$this->fromEmail}>",
            "To: undisclosed-recipients:;",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "X-Mailer: Scores-App/1.0",
        ]);
    }
}
