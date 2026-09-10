<?php
namespace GhostRoot\Frontend;
use GhostRoot\Metadata\Portrait;
use GhostRoot\Settings\Config;
defined('ABSPATH') || exit;

final class Shortcodes {
    public function hooks(): void {
        foreach (['identity_grid', 'terminal', 'network', 'mint', 'incidents', 'telemetry', 'arg'] as $name) { add_shortcode('ghost_root_' . $name, [$this, $name]); }
        (new Queries())->hooks();
    }
    public static function traits(int $id): array {
        $out = [];
        foreach (['ghost_id', 'entity', 'face', 'eyes', 'mask', 'implant', 'corruption', 'access', 'background', 'signal', 'state', 'fingerprint', 'rarity_band', 'archetype'] as $key) { $out[$key] = (string) get_post_meta($id, $key, true); }
        return $out;
    }
    public static function portrait(int $id, bool $hero = false): string {
        $traits = self::traits($id);
        if (has_post_thumbnail($id)) { return get_the_post_thumbnail($id, $hero ? 'large' : 'medium_large', ['alt' => get_the_title($id), 'loading' => $hero ? 'eager' : 'lazy']); }
        $uri = (string) get_post_meta($id, 'image_uri', true);
        if (preg_match('#^https?://#', $uri) && !str_contains($uri, 'EXAMPLE')) {
            return '<img src="' . esc_url($uri) . '" alt="' . esc_attr(get_the_title($id)) . '" width="640" height="640" loading="' . ($hero ? 'eager' : 'lazy') . '">';
        }
        // The data URI is created exclusively from our escaped SVG generator. It carries no
        // network cost, so lazy-loading only delays paint on fast scroll — always eager.
        return '<img src="' . esc_attr(Portrait::data_uri($traits, 640)) . '" alt="' . esc_attr(Portrait::alt_text($traits)) . '" width="640" height="640" decoding="async" loading="eager">';
    }
    public static function card(\WP_Post $post): string {
        $g = self::traits($post->ID);
        $rarity  = $g['rarity_band'] ?: 'STANDARD';
        $genesis = $rarity === 'GENESIS';
        $gid     = str_pad((string) ($g['ghost_id'] ?: '0'), 4, '0', STR_PAD_LEFT);
        $designation = $genesis
            ? (get_post_meta($post->ID, 'codename', true) ?: 'ORIGIN UNKNOWN')
            : ($g['archetype'] ?: '');
        $heading = is_post_type_archive('ghost_identity') ? 'h2' : 'h3';
        $state   = $g['state'] ?: 'UNKNOWN';
        ob_start(); ?>
        <article data-rarity="<?php echo esc_attr($rarity); ?>" class="gr-identity-card<?php echo $genesis ? ' gr-genesis-card' : ''; ?>">
            <a href="<?php echo esc_url(get_permalink($post)); ?>" aria-label="<?php echo esc_attr('GHOST//' . $gid . ($designation ? ' — ' . $designation : '')); ?>">
                <div class="gr-card-art">
                    <?php echo self::portrait($post->ID); ?>
                    <span class="gr-card-band"><?php echo esc_html($rarity); ?></span>
                    <span class="gr-card-signal" data-state="<?php echo esc_attr($state); ?>"><i aria-hidden="true"></i><?php echo esc_html($state); ?></span>
                </div>
                <div class="gr-card-body">
                    <?php echo '<' . $heading . ' class="gr-card-id">GHOST//' . esc_html($gid) . '<span aria-hidden="true">↗</span></' . $heading . '>'; ?>
                    <?php if ($designation): ?><p class="gr-card-designation"><?php echo esc_html($designation); ?></p><?php endif; ?>
                    <dl class="gr-card-meta">
                        <div><dt>ENTITY</dt><dd><?php echo esc_html($g['entity'] ?: 'UNKNOWN'); ?></dd></div>
                        <div><dt>ACCESS</dt><dd><?php echo esc_html($g['access'] ?: 'UNKNOWN'); ?></dd></div>
                        <div><dt>STATE</dt><dd><?php echo esc_html($state); ?></dd></div>
                        <div><dt>SIGNAL</dt><dd><?php echo esc_html($g['signal'] ?: 'UNKNOWN'); ?></dd></div>
                    </dl>
                </div>
            </a>
        </article>
        <?php return ob_get_clean();
    }
    public function identity_grid($atts = []): string {
        $atts = shortcode_atts(['limit' => 6, 'rarity' => '', 'page' => 1], $atts);
        $q = new \WP_Query(array_merge(Queries::identity_args(['rarity' => $atts['rarity']]), ['posts_per_page' => min(24, max(1, (int)$atts['limit'])), 'paged' => max(1, (int)$atts['page']), 'no_found_rows' => true]));
        return '<div class="gr-identity-grid">' . implode('', array_map([self::class, 'card'], $q->posts)) . '</div>';
    }
    public function terminal(): string {
        wp_enqueue_script('ghost-root-terminal');
        $id = wp_unique_id('gr-command-');
        return '<div class="gr-terminal" data-endpoint="' . esc_url(rest_url('ghost-root/v1/terminal')) . '"><div class="gr-window-bar"><span>ROOT / RECOVERY SIMULATION</span><span>SIMULATED · NO SYSTEM ACCESS <i class="gr-console-light" aria-hidden="true"></i></span></div><p class="gr-terminal-sim">SIMULATED NARRATIVE INTERFACE — NO COMMANDS ARE EXECUTED ON ANY SYSTEM.</p><div class="gr-terminal-output" role="log" aria-live="polite" aria-relevant="additions" aria-label="Recovery simulation output" tabindex="0"><p>GHOST//ROOT [RECOVERY SIMULATION]</p><p>Fictional archive interface. No system access. No commands run on any server.</p><p>Type help to view the available narrative queries.</p></div><form class="gr-terminal-form"><label for="' . esc_attr($id) . '">archive://guest&gt;</label><input id="' . esc_attr($id) . '" name="command" aria-label="Archive query" maxlength="256" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="' . esc_attr($id) . '-help" required><button type="submit">RUN QUERY</button></form><p class="gr-terminal-help" id="' . esc_attr($id) . '-help">↑ ↓ History · Enter Run · Esc Clear input · Tab Complete<br>Tab again to leave · Simulated narrative interface — nothing is executed</p><p class="gr-terminal-status" role="status" aria-atomic="true">CHANNEL READY</p></div>';
    }
    public function network(): string {
        wp_enqueue_script('ghost-root-network');
        return '<div class="gr-network-map" data-endpoint="' . esc_url(rest_url('ghost-root/v1/identities?per_page=48')) . '"><div class="gr-window-bar"><span>ROOT NETWORK / SAMPLED IDENTITIES</span><span class="gr-network-count" role="status">ACQUIRING SIGNAL</span></div><canvas aria-label="Sample of recovered identity nodes; accessible links follow" role="img"></canvas><div class="gr-network-legend" aria-label="States in the displayed sample"><span data-state="DORMANT"><i class="gr-node-symbol" aria-hidden="true">○</i>DORMANT <b data-state-count="DORMANT">—</b></span><span data-state="ACTIVE"><i class="gr-node-symbol" aria-hidden="true">◇</i>ACTIVE <b data-state-count="ACTIVE">—</b></span><span data-state="COMPROMISED"><i class="gr-node-symbol" aria-hidden="true">×</i>COMPROMISED <b data-state-count="COMPROMISED">—</b></span><span data-state="ROOTED"><i class="gr-node-symbol" aria-hidden="true">□</i>ROOTED <b data-state-count="ROOTED">—</b></span></div><p class="gr-network-note">Acquire a node to inspect it. Keyboard access is available in the record list.</p><details class="gr-node-list"><summary>Inspect sampled identities</summary><ul></ul></details></div>';
    }
    public function mint(): string {
        // Pre-mint: no wallet connection, no transaction, no purchase flow. Static status only.
        ob_start(); ?>
        <section class="gr-mint"><p class="gr-eyebrow">INITIALIZATION PROTOCOL</p><h2>AN IDENTITY.<br>NOT YET YOURS.</h2><div class="gr-initialization-closed"><strong>[ INITIALIZATION OFFLINE ]</strong><span>DEPLOYMENT STATUS</span><p>NOT YET DEPLOYED / NOT YET VERIFIED</p></div><dl class="gr-data-list"><div><dt>PROJECT PHASE</dt><dd>PRE-MINT ARCHIVE</dd></div><div><dt>WALLET CONNECTION</dt><dd>DISABLED</dd></div><div><dt>PLANNED NETWORK</dt><dd>SOLANA</dd></div><div><dt>ARCHIVE RECORDS</dt><dd>3,333</dd></div></dl><p class="gr-muted">No wallet connection is required to explore the archive. No payment is required to browse the site. Nothing on this page connects to a wallet, requests a signature, or requests a transaction.</p></section>
        <?php return ob_get_clean();
    }
    public function incidents($atts = []): string {
        $atts = shortcode_atts(['limit' => 3], $atts);
        $q = new \WP_Query(['post_type' => 'incident', 'post_status' => 'publish', 'posts_per_page' => min(50, max(1, (int)$atts['limit'])), 'no_found_rows' => true]);
        // Severity is a restrained visual system, not a per-status colour.
        $rank = ['STANDARD' => 'STANDARD', 'WARNING' => 'WARNING', 'CORRUPTED' => 'CORRUPTED', 'ROOT' => 'ROOT'];
        $out = '<ol class="gr-incident-list">';
        foreach ($q->posts as $p) {
            $id     = get_post_meta($p->ID, 'incident_id', true);
            $sev    = strtoupper((string) get_post_meta($p->ID, 'severity', true));
            $sev    = $rank[$sev] ?? 'STANDARD';
            $status = strtoupper((string) get_post_meta($p->ID, 'status', true)) ?: 'OPEN';
            $node   = get_post_meta($p->ID, 'affected_nodes', true) ?: '—';
            $title  = preg_replace('/^INCIDENT \/\/ \d+ — /u', '', $p->post_title);
            $summary = get_post_meta($p->ID, 'summary', true);
            $out .= '<li><a class="gr-incident-card" data-severity="' . esc_attr($sev) . '" href="' . esc_url(get_permalink($p)) . '">'
                . '<span class="gr-incident-number">' . esc_html($id) . '</span>'
                . '<span class="gr-incident-body">'
                  . '<span class="gr-incident-cls"><span>NODE // ' . esc_html($node) . '</span><span>SEVERITY // ' . esc_html($sev) . '</span></span>'
                  . '<h3 class="gr-incident-title">' . esc_html($title) . '</h3>'
                  . ($summary ? '<span class="gr-incident-summary">' . esc_html($summary) . '</span>' : '')
                  . '<span class="gr-incident-status">STATUS // ' . esc_html($status) . '</span>'
                . '</span>'
                . '<span class="gr-incident-arrow" aria-hidden="true">→</span>'
              . '</a></li>';
        }
        return $out . '</ol>';
    }
    public function telemetry(): string {
        return '<dl class="gr-telemetry"><div><dt>NETWORK</dt><dd>ROOT</dd></div><div><dt>STATUS</dt><dd>' . esc_html(Config::get('network_status')) . '</dd></div><div><dt>RECORDS RECOVERED</dt><dd>' . (int) wp_count_posts('ghost_identity')->publish . ' / 3333</dd></div><div><dt>ACTIVE NODES</dt><dd>' . (int)Config::get('active_nodes') . '</dd></div><div><dt>SIGNAL STRENGTH</dt><dd>' . (int)Config::get('signal_strength') . '%</dd></div></dl>';
    }
    public function arg(): string {
        wp_enqueue_script('ghost-root-arg');
        $id = wp_unique_id('gr-answer-');
        return '<section class="gr-arg" data-endpoint="' . esc_url(rest_url('ghost-root/v1/arg/incident-31')) . '"><p class="gr-eyebrow">RECOVER THE TRANSMISSION</p><h2>Incident 31</h2><p data-arg-stage role="status">ACQUIRING FRAGMENT</p><pre data-arg-prompt id="' . esc_attr($id) . '-prompt"></pre><div data-arg-hints></div><form><label for="' . esc_attr($id) . '">Decoded answer</label><div class="gr-input-row"><input id="' . esc_attr($id) . '" name="answer" maxlength="256" required autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="' . esc_attr($id) . '-prompt"><button type="submit" class="gr-button">VERIFY →</button></div></form><p data-arg-message role="status" aria-atomic="true" tabindex="-1"></p><p class="gr-muted">A fictional puzzle within this archive. All clues are here; no external systems are involved. Completion grants a narrative acknowledgment only.</p></section>';
    }
}
