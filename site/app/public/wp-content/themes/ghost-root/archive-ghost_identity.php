<?php defined('ABSPATH') || exit; get_header(); ?>
<section class="gr-section">
<?php gr_page_head('ROOT NETWORK / IDENTITY INDEX', 'RECOVERED IDENTITIES.', 'Every record is a fragment. Every face is a question.'); ?>
<?php if (class_exists('\GhostRoot\Support\Vocab')) gr_filters(['entity' => \GhostRoot\Support\Vocab::ENTITY, 'access' => \GhostRoot\Support\Vocab::ACCESS, 'state' => \GhostRoot\Support\Vocab::GHOST_STATES, 'rarity' => array_keys(\GhostRoot\Support\Vocab::RARITY_BANDS), 'signal' => \GhostRoot\Support\Vocab::SIGNAL], '/identities/'); ?>
<div class="gr-results-bar"><span><?php global $wp_query; echo (int)$wp_query->found_posts; ?> MATCHING RECORDS</span><span>RECONSTRUCTION // PRELIMINARY</span></div>
<div class="gr-identity-grid">
<?php while (have_posts()): the_post(); if (class_exists('\GhostRoot\Frontend\Shortcodes')) echo \GhostRoot\Frontend\Shortcodes::card(get_post()); endwhile; ?>
</div>
<?php if (!$wp_query->found_posts) echo '<p class="gr-empty">NO SIGNAL. No records match these filters.</p>'; gr_pagination(); ?>
</section><?php get_footer(); ?>
