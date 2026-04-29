<?php
// Ensure secure session is started
require_once __DIR__ . '/../../config/constants.php';

class RateLimitService {
    /**
     * Check rate limit
     * @param string $key Identifier (e.g., 'login_ip')
     * @param int $maxRequests Max attempts
     * @param int $periodSeconds Time window
     * @return bool True if allowed, False if limit reached
     */
    public static function check($key, $maxRequests = 5, $periodSeconds = 60) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $sessionKey = 'rate_limit_' . $key . '_' . md5($ip);
        
        $current = $_SESSION[$sessionKey] ?? ['count' => 0, 'start_time' => time()];
        
        // Reset if period expired
        if (time() - $current['start_time'] > $periodSeconds) {
            $current = ['count' => 0, 'start_time' => time()];
        }
        
        // Increment
        $current['count']++;
        $_SESSION[$sessionKey] = $current;
        
        if ($current['count'] > $maxRequests) {
            return false;
        }
        
        return true;
    }
}
