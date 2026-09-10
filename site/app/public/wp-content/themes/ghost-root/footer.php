<?php defined('ABSPATH') || exit; ?>
</main>
<footer class="gr-footer">
    <div class="gr-footer-brand"><a href="<?php echo esc_url(home_url('/')); ?>" class="gr-logo">GHOST//ROOT</a><p>YOU WERE NEVER HERE.</p><p class="gr-footer-context">Fictional network archive and digital collectible project. Interactive story — not a real system.</p></div>
    <dl class="gr-footer-sys">
        <div><dt>NETWORK</dt><dd>SOLANA</dd></div>
        <div><dt>STATUS</dt><dd>PRE_MINT</dd></div>
        <div><dt>SUPPLY</dt><dd>3,333</dd></div>
        <div><dt>WALLET</dt><dd>DISABLED</dd></div>
        <?php
        $gr_osv = class_exists('\GhostRoot\OpenSea\Verification') ? \GhostRoot\OpenSea\Verification::quick() : null;
        if (is_array($gr_osv)) {
            $gr_ms = [
                'AWAITING_SOLANA_COLLECTION_DEPLOYMENT' => 'AWAITING DEPLOYMENT',
                'NOT_INDEXED' => 'NOT INDEXED',
                'VERIFIED' => 'ACTIVE',
                'PARTIAL' => 'INDEXING',
                'MISMATCH' => 'UNVERIFIED',
                'PENDING' => 'PENDING',
                'ERROR' => 'UNAVAILABLE',
            ][$gr_osv['state']] ?? 'PENDING';
            echo '<div><dt>MARKET</dt><dd>' . esc_html($gr_ms) . '</dd></div>';
        }
        ?>
    </dl>
    <nav class="gr-footer-links" aria-label="Footer">
        <a href="<?php echo esc_url(home_url('/protocol/')); ?>">PROTOCOL</a>
        <a href="<?php echo esc_url(home_url('/initialize/')); ?>">PROJECT STATUS</a>
        <a href="<?php echo esc_url(home_url('/verify/')); ?>">VERIFY THE SOURCE <span aria-hidden="true">↗</span></a>
        <?php
        if (is_array($gr_osv) && in_array($gr_osv['state'], ['VERIFIED', 'PARTIAL'], true) && !empty($gr_osv['opensea_url'])) {
            echo '<a href="' . esc_url($gr_osv['opensea_url']) . '" target="_blank" rel="noopener nofollow">OPENSEA <span aria-hidden="true">↗</span></a>';
        }
        ?>
    </nav>
</footer>
<?php wp_footer(); ?>
</body></html>
