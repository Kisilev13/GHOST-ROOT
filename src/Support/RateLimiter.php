<?php
namespace GhostRoot\Support;
defined('ABSPATH') || exit;
/** Atomic fixed-window counters. No raw IPs or forwarded headers are stored. */
final class RateLimiter {
    public static function allow(string $endpoint, int $limit = 30, int $window = 60): bool {
        global $wpdb;
        $window = max(1, $window);
        $bucket = (int)floor(time() / $window);
        $key = hash('sha256', self::actor_hash($endpoint) . ':' . $bucket);
        $table = $wpdb->prefix . 'ghost_root_limits';
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (bucket_key, requests, expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE requests = LEAST(requests + 1, %d)",
            $key, ($bucket + 1) * $window, $limit + 1
        ));
        if ($ok === false) return false;
        $count = $wpdb->get_var($wpdb->prepare("SELECT requests FROM {$table} WHERE bucket_key = %s", $key));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %d LIMIT 100", time() - 86400));
        return $count !== null && (int)$count <= $limit;
    }
    public static function actor_hash(string $endpoint): string {
        return hash_hmac('sha256', self::client_ip() . '|' . $endpoint, wp_salt('auth'));
    }
    public static function client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) && filter_var($ip,FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
