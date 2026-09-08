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
        <?php foreach (['identities' => 'IDENTITIES', 'incidents' => 'INCIDENT LOG', 'signal' => 'SIGNAL', 'archive' => 'ARCHIVE', 'terminal' => 'TERMINAL', 'protocol' => 'PROTOCOL', 'verify' => 'VERIFY'] as $slug => $label): ?>
        <a href="<?php echo esc_url(home_url('/' . $slug . '/')); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url(home_url('/initialize/')); ?>" class="gr-nav-cta">INITIALIZE <span aria-hidden="true">↗</span></a>
    </nav>
</header>
<main id="main" tabindex="-1">
