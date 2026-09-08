<?php
namespace GhostRoot\Frontend;
use GhostRoot\Support\Vocab;
defined('ABSPATH') || exit;

final class Queries {
    public static function param(string $key): string {
        $value = $_GET[$key] ?? '';
        return is_string($value) ? substr(sanitize_text_field(wp_unslash($value)), 0, 100) : '';
    }
    public static function identity_args(array $filters): array {
        $meta = [];
        $allowed = ['entity' => Vocab::ENTITY, 'access' => Vocab::ACCESS, 'state' => Vocab::GHOST_STATES, 'rarity' => array_keys(Vocab::RARITY_BANDS), 'signal' => Vocab::SIGNAL];
        foreach ($allowed as $key => $values) {
            $value = strtoupper((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $meta[] = ['key' => $key === 'rarity' ? 'rarity_band' : $key, 'value' => in_array($value, $values, true) ? $value : '__INVALID__'];
            }
        }
        return ['post_type' => 'ghost_identity', 'post_status' => 'publish', 'posts_per_page' => 24, 'meta_key' => 'ghost_id', 'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_query' => $meta];
    }
    public function hooks(): void { add_action('pre_get_posts', [$this, 'archives']); }
    public function archives(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) { return; }
        if ($query->is_post_type_archive('ghost_identity')) {
            $filters = [];
            foreach (['entity', 'access', 'state', 'rarity', 'signal'] as $key) { $filters[$key] = self::param($key); }
            foreach (self::identity_args($filters) as $key => $value) { $query->set($key, $value); }
        }
        if ($query->is_post_type_archive('archive_record')) {
            $query->set('posts_per_page', 12);
            $meta = [];
            foreach (['node', 'access', 'status'] as $key) {
                if (self::param($key) !== '') { $meta[] = ['key' => 'record_' . $key, 'value' => self::param($key)]; }
            }
            $query->set('meta_query', $meta);
            if (self::param('type') !== '') { $query->set('tax_query', [['taxonomy' => 'archive_type', 'field' => 'name', 'terms' => self::param('type')]]); }
            $date = self::param('date');
            if ($date !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $query->set('date_query', [['after' => $date, 'before' => $date . ' 23:59:59', 'inclusive' => true]]); }
                else { $query->set('post__in', [0]); }
            }
        }
    }
}
