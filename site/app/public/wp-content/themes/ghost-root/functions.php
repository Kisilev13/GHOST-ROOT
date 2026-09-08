<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', static function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    register_nav_menus(['primary' => 'Primary navigation']);
});
add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('ghost-root', get_theme_file_uri('assets/css/site.css'), [], (string) filemtime(get_theme_file_path('assets/css/site.css')));
    wp_enqueue_script('ghost-root-site', get_theme_file_uri('assets/js/site.js'), [], '1.0.0', ['in_footer' => true, 'strategy' => 'defer']);
});
// Preload the two above-the-fold webfonts (subset WOFF2) so the display face
// swaps in fast. No other fonts are preloaded.
add_action('wp_head', static function () {
    foreach (['gr-display', 'gr-text'] as $font) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url(get_theme_file_uri("assets/fonts/{$font}.woff2"))
        );
    }
}, 1);
function gr_meta(string $key, ?int $id = null): string {
    return (string) get_post_meta($id ?? get_the_ID(), $key, true);
}
function gr_label(string $text): string { return strtoupper(str_replace('_', ' ', $text)); }
function gr_page_head(string $eyebrow, string $title, string $description = ''): void {
    echo '<div class="gr-page-head"><p class="gr-eyebrow">' . esc_html($eyebrow) . '</p><h1>' . esc_html($title) . '</h1>';
    if ($description) echo '<p class="gr-intro">' . esc_html($description) . '</p>';
    echo '</div>';
}
function gr_filters(array $filters, string $action): void {
    echo '<form class="gr-filters" action="' . esc_url(home_url($action)) . '" method="get">';
    foreach ($filters as $key => $values) {
        $value = class_exists('\GhostRoot\Frontend\Queries') ? \GhostRoot\Frontend\Queries::param($key) : '';
        echo '<label for="gr-filter-' . esc_attr($key) . '">' . esc_html(gr_label($key)) . '<select name="' . esc_attr($key) . '" id="gr-filter-' . esc_attr($key) . '"><option value="">ALL</option>';
        foreach ($values as $option) { echo '<option value="' . esc_attr($option) . '" ' . selected($value, $option, false) . '>' . esc_html($option) . '</option>'; }
        echo '</select></label>';
    }
    if ($action === '/archive/') echo '<label for="gr-filter-date">DATE<input type="date" id="gr-filter-date" name="date" value="' . esc_attr(\GhostRoot\Frontend\Queries::param('date')) . '"></label>';
    echo '<button class="gr-button" type="submit">FILTER ↓</button><a class="gr-filter-reset" href="' . esc_url(home_url($action)) . '">RESET</a></form>';
}
function gr_pagination(): void {
    $links = paginate_links(['type' => 'list', 'prev_text' => '← PREVIOUS', 'next_text' => 'NEXT →']);
    if ($links) echo '<nav class="gr-pagination" aria-label="Pagination">' . wp_kses_post($links) . '</nav>';
}
function gr_description(): string {
    if (is_front_page()) return 'You were never supposed to find them. Explore recovered identities, incident logs and fragments from the ROOT network.';
    return wp_strip_all_tags(is_singular() ? get_the_excerpt() : 'Recovered identities and classified records from the GHOST//ROOT network.');
}
add_filter('document_title_parts', static function ($parts) {
    $parts['site'] = 'GHOST//ROOT';
    if (is_front_page()) $parts['title'] = 'GHOST//ROOT — Recovered Identities';
    return $parts;
});
add_filter('wpseo_title', static function ($title) { return is_front_page() ? 'GHOST//ROOT — Recovered Identities' : str_replace(get_bloginfo('name'), 'GHOST//ROOT', $title); });
add_filter('wpseo_metadesc', static function ($description) { return $description ?: gr_description(); });
add_action('wp_head', static function () {
    if (defined('WPSEO_VERSION')) return;
    $url = is_singular() ? get_permalink() : home_url('/');
    echo '<meta name="description" content="' . esc_attr(gr_description()) . '">';
    echo '<meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '"><meta property="og:description" content="' . esc_attr(gr_description()) . '"><meta property="og:url" content="' . esc_url($url) . '"><meta property="og:type" content="website"><meta name="twitter:card" content="summary">';
    if (!is_singular()) echo '<link rel="canonical" href="' . esc_url($url) . '">';
}, 5);
