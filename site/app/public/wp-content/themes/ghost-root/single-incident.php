<?php defined('ABSPATH') || exit; get_header(); while (have_posts()): the_post();
$gr_id     = gr_meta('incident_id');
$gr_sev    = strtoupper(gr_meta('severity')) ?: 'STANDARD';
$gr_status = strtoupper(gr_meta('status')) ?: 'OPEN';
$gr_title  = preg_replace('/^INCIDENT \/\/ \d+ — /u', '', get_the_title());
$gr_summary = gr_meta('summary');
?>
<article class="gr-section gr-incident-detail" data-severity="<?php echo esc_attr($gr_sev); ?>">
    <a class="gr-back" href="<?php echo esc_url(home_url('/incidents/')); ?>">← INCIDENT REGISTER</a>
    <header class="gr-incident-head">
        <p class="gr-eyebrow">INCIDENT // <?php echo esc_html($gr_id); ?></p>
        <p class="gr-redaction-bar" aria-hidden="true"></p>
        <h1><?php echo esc_html($gr_title); ?></h1>
        <?php if ($gr_summary): ?><p class="gr-incident-statement"><?php echo esc_html($gr_summary); ?></p><?php endif; ?>
    </header>
    <dl class="gr-evidence-panel">
        <div><dt>NODE</dt><dd><?php echo esc_html(gr_meta('affected_nodes') ?: '—'); ?></dd></div>
        <div><dt>SEVERITY</dt><dd><?php echo esc_html($gr_sev); ?></dd></div>
        <div><dt>STATUS</dt><dd><?php echo esc_html($gr_status); ?></dd></div>
        <div><dt>TX</dt><dd><?php echo esc_html(gr_meta('transmission_id') ?: '—'); ?></dd></div>
        <div><dt>DATE</dt><dd><?php echo esc_html(gr_meta('incident_date') ?: '—'); ?></dd></div>
    </dl>
    <div class="gr-document-layout">
        <div class="gr-document gr-prose"><p class="gr-document-stamp">CLASSIFIED / RECOVERED RECORD</p><?php the_content(); ?></div>
        <div class="gr-prose gr-incident-evidence"><p class="gr-eyebrow">EVIDENCE / CHAIN OF CUSTODY</p><?php echo wp_kses_post(wpautop(gr_meta('evidence') ?: 'No evidence recovered.')); ?></div>
    </div>
    <?php if ((int) $gr_id === 31) echo do_shortcode('[ghost_root_arg]'); ?>
    <p class="gr-caption gr-muted">Fictional incident record / GHOST//ROOT narrative archive.</p>
</article>
<?php endwhile; get_footer(); ?>
