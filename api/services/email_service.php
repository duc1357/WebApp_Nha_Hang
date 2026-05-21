<?php
require_once __DIR__ . '/../../config/mail_config.php';
require_once __DIR__ . '/../../lib/SimpleSMTP.php';

class EmailService {
    /**
     * Send an email
     * @param string $to Recipient email
     * @param string $subject Subject
     * @param string $body HTML Body
     * @return bool
     */
    public static function send($to, $subject, $body) {
        $smtp = new SimpleSMTP(MAIL_HOST, MAIL_USER, MAIL_PASS, MAIL_PORT);
        // SimpleSMTP handles the connection inside send method usually or via connect,
        // my SimpleSMTP implementation in lib/SimpleSMTP.php does everything in send().

        try {
            return $smtp->send($to, $subject, $body, MAIL_FROM_NAME);
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
            return false;
        }
    }
}
