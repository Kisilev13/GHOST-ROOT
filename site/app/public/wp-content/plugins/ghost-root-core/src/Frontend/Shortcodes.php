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
        $genesis = $g['rarity_band'] === 'GENESIS';
        ob_start(); ?>
        <article class="gr-identity-card <?php echo $genesis ? 'gr-genesis-card' : ''; ?>">
            <a href="<?php echo esc_url(get_permalink($post)); ?>">
                <div class="gr-card-art"><?php echo self::portrait($post->ID); ?><span class="gr-card-band"><?php echo esc_html($g['rarity_band']); ?></span></div>
                <div class="gr-card-body"><div class="gr-card-title"><h3><?php echo esc_html($post->post_title); ?></h3><span aria-hidden="true">↗</span></div>
                <?php if ($genesis): ?><p class="gr-genesis-name"><?php echo esc_html(get_post_meta($post->ID, 'codename', true) ?: 'ORIGIN UNKNOWN'); ?></p><?php endif; ?>
                <dl class="gr-card-meta"><div><dt>ENTITY</dt><dd><?php echo esc_html($g['entity']); ?></dd></div><div><dt>ACCESS</dt><dd><?php echo esc_html($g['access']); ?></dd></div><div><dt>STATE</dt><dd><?php echo esc_html($g['state']); ?></dd></div><div><dt>SIGNAL</dt><dd><?php echo esc_html($g['signal']); ?></dd></div></dl></div>
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
        return '<div class="gr-terminal" data-endpoint="' . esc_url(rest_url('ghost-root/v1/terminal')) . '"><div class="gr-window-bar"><span>ROOT / RECOVERY CONSOLE</span><span>GUEST SESSION</span></div><div class="gr-terminal-output" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"><p>GHOST//ROOT [RECOVERY INTERFACE]</p><p>Connection established. Access level: USER.</p><p>Type help to view available commands.</p></div><form class="gr-terminal-form"><label for="' . esc_attr($id) . '">guest@root:~$</label><input id="' . esc_attr($id) . '" name="command" aria-label="Terminal command" maxlength="256" autocomplete="off" autocapitalize="off" spellcheck="false" required><button type="submit">EXECUTE ↵</button></form><p class="gr-terminal-help">↑ ↓ Command history · Enter to execute · Simulated recovery console</p></div>';
    }
    public function network(): string {
        wp_enqueue_script('ghost-root-network');
        return '<div class="gr-network-map" data-endpoint="' . esc_url(rest_url('ghost-root/v1/identities?per_page=48')) . '"><div class="gr-window-bar"><span>ROOT NETWORK / SAMPLED IDENTITIES</span><span class="gr-network-count" role="status">ACQUIRING SIGNAL</span></div><canvas aria-label="Sample of recovered identity nodes; accessible links follow" role="img"></canvas><div class="gr-network-legend"><span>○ DORMANT</span><span>◇ ACTIVE</span><span>× COMPROMISED</span><span>□ ROOTED</span></div><details class="gr-node-list"><summary>Inspect sampled identities</summary><ul></ul></details></div>';
    }
    public function mint(): string {
        wp_enqueue_script('ghost-root-mint');
        $c = Config::mint_config();
        ob_start(); ?>
        <section class="gr-mint" data-endpoint="<?php echo esc_url(rest_url('ghost-root/v1/mint-config')); ?>"><p class="gr-eyebrow">INITIALIZATION PROTOCOL</p><h2>AN IDENTITY.<br>NOT YET YOURS.</h2><dl class="gr-data-list"><div><dt>NETWORK</dt><dd>SOLANA</dd></div><div><dt>TOTAL IDENTITIES</dt><dd>3,333</dd></div><div><dt>PROPOSED MINT PRICE</dt><dd data-mint-price><?php echo esc_html((string)$c['price']); ?> SOL</dd></div><div><dt>MAXIMUM PER WALLET</dt><dd data-mint-max><?php echo (int)$c['maxPerWallet']; ?></dd></div><div><dt>STATUS</dt><dd data-mint-state><?php echo esc_html($c['state']); ?></dd></div></dl><button class="gr-button gr-button-primary" data-wallet disabled>CONNECT WALLET ↗</button><p data-mint-notice role="status"><?php echo $c['deployed'] ? 'WALLET INTEGRATION PENDING' : 'NOT YET DEPLOYED'; ?></p><p class="gr-muted">Initialization will open after deployment and verification. No wallet connection or payment is required to explore.</p></section>
        <?php return ob_get_clean();
    }
    public function incidents($atts = []): string {
        $atts = shortcode_atts(['limit' => 3], $atts);
        $q = new \WP_Query(['post_type' => 'incident', 'post_status' => 'publish', 'posts_per_page' => min(50, max(1, (int)$atts['limit'])), 'no_found_rows' => true]);
        $out = '<div class="gr-incident-list">';
        foreach ($q->posts as $p) {
            $out .= '<a class="gr-incident-row" href="' . esc_url(get_permalink($p)) . '"><span class="gr-incident-number">' . esc_html(get_post_meta($p->ID, 'incident_id', true)) . '</span><div><h3>' . esc_html(preg_replace('/^INCIDENT \/\/ \d+ — /u', '', $p->post_title)) . '</h3><p>' . esc_html(get_post_meta($p->ID, 'summary', true)) . '</p></div><span class="gr-status-label">' . esc_html(get_post_meta($p->ID, 'status', true)) . '</span><span aria-hidden="true">↗</span></a>';
        }
        return $out . '</div>';
    }
    public function telemetry(): string {
        return '<dl class="gr-telemetry"><div><dt>NETWORK</dt><dd>ROOT</dd></div><div><dt>STATUS</dt><dd>' . esc_html(Config::get('network_status')) . '</dd></div><div><dt>RECORDS RECOVERED</dt><dd>' . (int) wp_count_posts('ghost_identity')->publish . ' / 3333</dd></div><div><dt>ACTIVE NODES</dt><dd>' . (int)Config::get('active_nodes') . '</dd></div><div><dt>SIGNAL STRENGTH</dt><dd>' . (int)Config::get('signal_strength') . '%</dd></div></dl>';
    }
    public function arg(): string {
        wp_enqueue_script('ghost-root-arg');
        $id = wp_unique_id('gr-answer-');
        return '<section class="gr-arg" data-endpoint="' . esc_url(rest_url('ghost-root/v1/arg/incident-31')) . '"><p class="gr-eyebrow">RECOVER THE TRANSMISSION</p><h2>Incident 31</h2><p data-arg-stage role="status">ACQUIRING FRAGMENT</p><pre data-arg-prompt></pre><div data-arg-hints></div><form><label for="' . esc_attr($id) . '">Decoded answer</label><div class="gr-input-row"><input id="' . esc_attr($id) . '" name="answer" maxlength="256" required autocomplete="off"><button type="submit" class="gr-button">VERIFY →</button></div></form><p data-arg-message role="status"></p><p class="gr-muted">A fictional puzzle within this archive. All clues are here; no external systems are involved. Completion grants a narrative acknowledgment only.</p></section>';
    }
}
