<?php
defined('ABSPATH') || exit;
get_header();
if (!class_exists('\GhostRoot\Frontend\Shortcodes')) {
    echo '<section class="gr-section"><h1>GHOST//ROOT</h1><p>Recovery systems are offline.</p></section>';
    get_footer(); return;
}
$featured = get_posts(['post_type' => 'ghost_identity', 'post_status' => 'publish', 'meta_key' => 'ghost_id', 'meta_value' => 1842, 'numberposts' => 1]);
if (!$featured) $featured = get_posts(['post_type' => 'ghost_identity', 'post_status' => 'publish', 'numberposts' => 1]);
$post_id = $featured[0]->ID ?? 0;
?>
<section class="gr-hero">
    <div class="gr-hero-top gr-mono"><span><i class="gr-dot"></i> ROOT NETWORK — SIGNAL DETECTED</span><span>RECOVERY SESSION / 031</span></div>
    <div class="gr-hero-copy"><p class="gr-eyebrow">AUTONOMOUS IDENTITIES. UNKNOWN ORIGIN.</p><h1>YOU WERE<br>NEVER SUPPOSED<br>TO <span>FIND THEM.</span></h1><p class="gr-hero-description">3,333 identities. A network that disappeared.<br>A signal that never stopped.</p><div class="gr-actions"><a class="gr-button gr-button-primary" href="<?php echo esc_url(home_url('/identities/')); ?>">EXPLORE THE ARCHIVE <span aria-hidden="true">↗</span></a><a class="gr-text-link" href="<?php echo esc_url(home_url('/initialize/')); ?>">PROJECT STATUS <span aria-hidden="true">→</span></a></div></div>
    <div class="gr-hero-portrait" aria-label="Recovered identity preview">
        <?php if ($post_id) echo \GhostRoot\Frontend\Shortcodes::portrait($post_id, true); ?>
        <div class="gr-scanline" aria-hidden="true"></div><span class="gr-art-label">RECONSTRUCTION // PRELIMINARY</span>
        <div class="gr-crosshair gr-crosshair-tl" aria-hidden="true"></div><div class="gr-crosshair gr-crosshair-br" aria-hidden="true"></div>
        <div class="gr-hero-dossier"><p class="gr-eyebrow">IDENTITY RECOVERED</p><strong>GHOST//<?php echo esc_html(str_pad(gr_meta('ghost_id', $post_id), 4, '0', STR_PAD_LEFT)); ?></strong><dl><div><dt>STATUS</dt><dd><?php echo esc_html(gr_meta('state', $post_id)); ?></dd></div><div><dt>ACCESS</dt><dd><?php echo esc_html(gr_meta('access', $post_id)); ?></dd></div><div><dt>SIGNAL</dt><dd><?php echo (int)\GhostRoot\Settings\Config::get('signal_strength'); ?>%</dd></div></dl></div>
    </div>
    <div class="gr-hero-bottom"><span class="gr-mono">[ SCROLL TO INVESTIGATE ] ↓</span><span class="gr-mono">SOURCE: [REDACTED] <span class="gr-hero-coordinate">/ 0x31.ROOT</span></span></div>
</section>
<dl class="gr-sysinfo" aria-label="Recovered collection metadata">
    <div><dt>COLLECTION</dt><dd>3,333 IDENTITIES</dd></div>
    <div><dt>NETWORK</dt><dd>SOLANA</dd></div>
    <div><dt>STATE</dt><dd>PRE_MINT</dd></div>
    <div><dt>ARTIFACT</dt><dd>GENERATIVE DIGITAL IDENTITY</dd></div>
</dl>
<div class="gr-alert-strip"><span class="gr-dot"></span><span>NODE 31 IS RESPONDING.</span><span class="gr-muted">THIS SHOULD NOT BE POSSIBLE.</span><a href="<?php echo esc_url(home_url('/incident/31/')); ?>">READ INCIDENT ↗</a></div>
<section class="gr-section gr-discovery"><div><p class="gr-eyebrow">01 / THE DISCOVERY</p><h2>THE NETWORK<br>WAS SUPPOSED<br>TO BE DEAD.</h2></div><div class="gr-discovery-copy"><p>A distributed autonomous security network disappeared without explanation.</p><p>Years later, fragments began responding.<br>Inside them were 3,333 identities.</p><p>Nobody knows who created them.<br>Nobody knows why they survived.</p><p class="gr-statement">Some are still dormant.<br>Some are waking up.</p></div></section>
<div class="gr-telemetry-wrap"><?php echo do_shortcode('[ghost_root_telemetry]'); ?></div>
<section class="gr-section"><div class="gr-section-heading"><div><p class="gr-eyebrow">02 / RECOVERED IDENTITIES</p><h2>FACES WITHOUT<br>A PAST.</h2></div><a class="gr-text-link" href="<?php echo esc_url(home_url('/identities/')); ?>">EXPLORE THE INDEX ↗</a></div><p class="gr-muted gr-caption">A sample of recovered records from the identity index.</p><?php echo do_shortcode('[ghost_root_identity_grid limit="6"]'); ?></section>
<section class="gr-section gr-incident-section"><div class="gr-section-heading"><div><p class="gr-eyebrow">03 / INCIDENT LOG</p><h2>NOTHING IS<br>STAYING DORMANT.</h2></div><a class="gr-text-link" href="<?php echo esc_url(home_url('/incidents/')); ?>">ALL INCIDENTS ↗</a></div><?php echo do_shortcode('[ghost_root_incidents]'); ?></section>
<section class="gr-section gr-signal-section"><div><p class="gr-eyebrow">04 / SIGNAL MAP</p><h2>STILL HERE.<br>STILL LISTENING.</h2><p class="gr-intro">Fragments of the same network.<br>Each point, a recovered identity.</p><a class="gr-text-link" href="<?php echo esc_url(home_url('/signal/')); ?>">INSPECT THE NETWORK ↗</a></div><?php echo do_shortcode('[ghost_root_network]'); ?></section>
<section class="gr-section gr-terminal-teaser"><div><p class="gr-eyebrow">05 / DIRECT ACCESS</p><h2>THE SYSTEM<br>IS LISTENING.</h2><p class="gr-intro">Ask it what happened.</p></div><?php echo do_shortcode('[ghost_root_terminal]'); ?></section>
<section class="gr-section gr-endnote"><p class="gr-eyebrow">TRUST NOTHING. VERIFY EVERYTHING.</p><h2>ROOT KNOWS.</h2><div class="gr-actions"><a class="gr-button" href="<?php echo esc_url(home_url('/protocol/')); ?>">READ THE PROTOCOL →</a><a class="gr-text-link" href="<?php echo esc_url(home_url('/verify/')); ?>">VERIFY THE SOURCE ↗</a></div></section>
<?php get_footer(); ?>
