<?php
// lib/SimpleSMTP.php

class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;
    public $error = '';

    public function __construct($host, $user, $pass, $port = 587) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
    }

    public function send($to, $subject, $body, $fromName) {
        // Detect if running on localhost (XAMPP/Laragon/Local) or Production
        $whitelist = ['127.0.0.1', '::1', 'localhost'];
        // Check if domain ends with .test (Laragon default)
        $isLocal = in_array($_SERVER['SERVER_NAME'], $whitelist) || substr($_SERVER['SERVER_NAME'], -5) === '.test';

        $contextOptions = [];
        if ($isLocal) {
            $contextOptions['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ];
        }

        $context = stream_context_create($contextOptions);

        // Determine protocol based on port
        $protocol = 'ssl://';
        if ($this->port == 25 || $this->port == 587) {
            $protocol = 'tcp://'; // Use plain TCP for 25/587 (Internal or STARTTLS)
        }

        $maxRetries = 3;
        $attempt = 0;
        $socket = false;
        
        while($attempt < $maxRetries) {
            $attempt++;
            $socket = @stream_socket_client($protocol . $this->host . ":" . $this->port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            if ($socket) break;
            sleep(1); // Wait 1s before retry
        }

        if (!$socket) {
            $this->error = "Connect Failed after $maxRetries attempts: $errstr ($errno)";
            error_log("SMTP Connect Failed: $errstr ($errno)");
            return false;
        }

        if (!$this->serverCmd($socket, "220")) return false;

        $serverName = $_SERVER['SERVER_NAME'] ?? 'duongbau.vn';
        if (empty($serverName) || $serverName === 'localhost' || $serverName === '127.0.0.1' || $serverName === '::1') {
            $serverName = 'duongbau.vn';
        }

        $this->sendCmd($socket, "EHLO " . $serverName);
        if (!$this->serverCmd($socket, "250")) return false;

        $this->sendCmd($socket, "AUTH LOGIN");
        if (!$this->serverCmd($socket, "334")) return false;

        $this->sendCmd($socket, base64_encode($this->user));
        if (!$this->serverCmd($socket, "334")) return false;

        $this->sendCmd($socket, base64_encode($this->pass));
        if (!$this->serverCmd($socket, "235")) {
             $this->error = "Auth Failed (Pass): " . $this->error; 
             return false; 
        }

        $this->sendCmd($socket, "MAIL FROM: <" . $this->user . ">");
        if (!$this->serverCmd($socket, "250")) return false;

        $this->sendCmd($socket, "RCPT TO: <" . $to . ">");
        if (!$this->serverCmd($socket, "250")) return false;

        $this->sendCmd($socket, "DATA");
        if (!$this->serverCmd($socket, "354")) return false;

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date("r") . "\r\n"; // RFC 2822
        $domain = (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '::1' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') ? $_SERVER['SERVER_NAME'] : 'duongbau.vn';
        $headers .= "Message-ID: <" . uniqid() . "@" . $domain . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $this->user . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";

        $this->sendCmd($socket, $headers . "\r\n" . $body . "\r\n.");
        if (!$this->serverCmd($socket, "250")) return false;

        $this->sendCmd($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function sendCmd($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
    }

    private function serverCmd($socket, $expected) {
        $response = "";
        while($str = fgets($socket, 515)) {
            $response .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        // Check if response code matches expected
        if (substr($response, 0, 3) != $expected) {
            $this->error = "SMTP Error: Expected $expected, got $response";
            return false;
        }
        return true;
    }
}
