<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('gr-site'); ?>><?php wp_body_open(); ?>
<a class="gr-skip-link" href="#main">Skip to content</a>
<header class="gr-header">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="gr-logo" aria-label="GHOST ROOT home"><span class="gr-logo-mark" aria-hidden="true">[<i>+</i>]</span>GHOST<span>//</span>ROOT</a>
    <button class="gr-menu-toggle" aria-expanded="false" aria-controls="gr-navigation">MENU <span aria-hidden="true">+</span></button>
    <nav id="gr-navigation" class="gr-nav" aria-label="Primary navigation">
        <?php
        $gr_nav_active = static function (string $slug): bool {
            return is_page($slug)
                || ($slug === 'identities' && (is_post_type_archive('ghost_identity') || is_singular('ghost_identity')))
                || ($slug === 'incidents' && (is_post_type_archive('incident') || is_singular('incident')))
                || ($slug === 'archive' && (is_post_type_archive('archive_record') || is_singular(['archive_record', 'transmission'])));
        };
        foreach (['identities' => 'IDENTITIES', 'incidents' => 'INCIDENTS', 'archive' => 'ARCHIVE', 'signal' => 'SIGNAL', 'protocol' => 'PROTOCOL'] as $slug => $label): ?>
        <a href="<?php echo esc_url(home_url('/' . $slug . '/')); ?>"<?php if($gr_nav_active($slug)) echo ' aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
        <span class="gr-nav-divider" aria-hidden="true"></span>
        <?php foreach (['terminal' => 'TERMINAL', 'verify' => 'VERIFY'] as $slug => $label): ?>
        <a class="gr-nav-secondary" href="<?php echo esc_url(home_url('/' . $slug . '/')); ?>"<?php if($gr_nav_active($slug)) echo ' aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url(home_url('/initialize/')); ?>" class="gr-nav-cta"<?php if(is_page('initialize')) echo ' aria-current="page"'; ?>>PRE-MINT <span aria-hidden="true">//</span> LOCKED</a>
    </nav>
</header>
<main id="main" tabindex="-1">
