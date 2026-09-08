<?php
namespace GhostRoot\Frontend;
defined('ABSPATH') || exit;

final class Assets {
    public function hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'register']);
    }
    public function register(): void {
        wp_register_style('ghost-root-components', GHOST_ROOT_URL . 'assets/components.css', [], GHOST_ROOT_VERSION);
        foreach (['terminal', 'network', 'mint', 'arg', 'market'] as $name) {
            wp_register_script('ghost-root-' . $name, GHOST_ROOT_URL . 'assets/' . $name . '.js', [], GHOST_ROOT_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        }
        // Also covers Elementor shortcodes rendered after wp_head.
        wp_enqueue_style('ghost-root-components');
    }
}
