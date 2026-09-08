<?php
/**
 * Standalone unit checks for the GHOST//ROOT OpenSea module — no WordPress
 * bootstrap required. Run:  php tests/opensea-unit.php
 *
 * Covers the pure logic: config resolution, stats normalisation (never a fake
 * zero), collection shaping, the verification state machine, the SWR cache,
 * secret redaction, and that shortcodes never emit the API key.
 *
 * Full REST + auth behaviour is checked by tests/integration.php under wp-cli.
 */

error_reporting(E_ALL);
define('ABSPATH', sys_get_temp_dir() . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('GHOST_ROOT_DIR', dirname(__DIR__) . '/');
define('GHOST_ROOT_VERSION', 'test');

$GLOBALS['__t'] = [];
$GLOBALS['__opt'] = ['ghost_root_settings' => []];

function apply_filters($t, $v, ...$a) { return $v; }
function add_filter(...$a) {} function add_action(...$a) {} function add_shortcode(...$a) {}
function do_action(...$a) {}
function get_transient($k) { return $GLOBALS['__t'][$k] ?? false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['__t'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['__t'][$k]); return true; }
function get_option($k, $d = false) { return $GLOBALS['__opt'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['__opt'][$k] = $v; return true; }
function wp_parse_args($a, $d) { return array_merge($d, is_array($a) ? $a : []); }
function wp_next_scheduled(...$a) { return false; }
function wp_schedule_single_event(...$a) { return true; }
function wp_schedule_event(...$a) { return true; }
function wp_unschedule_event(...$a) { return true; }
function wp_clear_scheduled_hook(...$a) {}
function shortcode_atts($d, $a) { return array_merge($d, is_array($a) ? $a : []); }
function wp_enqueue_style(...$a) {} function wp_enqueue_script(...$a) {}
function do_shortcode($s) { return "[$s]"; }
function sanitize_title($s) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $s), '-')); }
function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $s)); }
function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))); }
function wp_kses_post($s) { return (string) $s; }
function esc_url_raw($s) { return filter_var($s, FILTER_VALIDATE_URL) ? $s : ''; }
function esc_url($s) { return (string) $s; } function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function home_url($p = '/') { return 'https://ghostroot.site' . $p; }
function rest_url($p = '') { return 'https://ghostroot.site/wp-json/' . $p; }
function add_query_arg($a, $u) { return $u . '?' . http_build_query($a); }
function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function wp_json_encode($d) { return json_encode($d); }
function current_user_can($c) { return false; }
function wp_verify_nonce(...$a) { return false; }
function is_wp_error($t) { return $t instanceof WP_Error; }
function register_rest_route(...$a) {}
class WP_Error {
    public function __construct(private $code = '', private $msg = '', private $data = '') {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->msg; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
class WP_REST_Request {
    public function __construct(private $m = 'GET', private $r = '') {}
    public function get_header($k) { return ''; }
    public function offsetExists($k) { return false; } public function offsetGet($k) { return null; }
    public function offsetSet($k, $v): void {} public function offsetUnset($k): void {}
}

spl_autoload_register(function ($class) {
    $p = 'GhostRoot\\';
    if (strpos($class, $p) !== 0) return;
    $f = GHOST_ROOT_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($p))) . '.php';
    if (is_readable($f)) require_once $f;
});

use GhostRoot\OpenSea\Config;
use GhostRoot\OpenSea\Stats;
use GhostRoot\OpenSea\Collection;
use GhostRoot\OpenSea\Verification;
use GhostRoot\OpenSea\Cache;
use GhostRoot\OpenSea\Client;
use GhostRoot\OpenSea\Service;

$pass = 0; $fail = 0;
function ok(string $name, bool $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS  $name\n"; }
    else { $fail++; echo "FAIL  $name\n"; }
}

/* --- Config --------------------------------------------------------------- */
ok('Config: inert without an API key', !Config::has_api_key());
ok('Config: status = AWAITING when no Solana address', Config::status() === Config::STATE_AWAITING);
ok('Config: chain is solana', Config::chain() === 'solana');
ok('Config: TTLs match the caching policy', Config::ttl('collection') === 3 * 3600 && Config::ttl('activity') === 90);
$snap = Config::public_snapshot();
ok('Config: public snapshot carries no key material', strpos(json_encode($snap), 'api_key') === false && !isset($snap['api_key']));

/* --- Stats normalisation ------------------------------------------------- */
$s = Stats::normalise(['total' => ['volume' => 37.4, 'volume_symbol' => 'SOL', 'num_owners' => 1204, 'floor_price' => null],
    'intervals' => [['interval' => 'one_day', 'sales' => 3, 'volume' => 4.1]]]);
ok('Stats: absent floor stays null (no fake zero)', $s['floor_price'] === null);
ok('Stats: present 24h volume is kept', $s['volume_24h'] === 4.1);
ok('Stats: owners parsed', $s['num_owners'] === 1204);
ok('Stats: source flagged opensea', $s['source'] === 'opensea');
ok('Stats: empty payload => source none', Stats::normalise([])['source'] === 'none');
ok('Stats::fmt(null) is an em dash', Stats::fmt(null, null) === '—');
ok('Stats::fmt formats sub-1 values with precision', Stats::fmt(0.185, 'SOL') === '0.185 SOL');

/* --- Collection shaping ------------------------------------------------- */
$c = Collection::shape(['collection' => 'ghost-root', 'name' => 'GHOST_ROOT',
    'contracts' => [['address' => '0x3ad41f', 'chain' => 'ethereum']], 'total_supply' => 19,
    'fees' => [['fee' => 5.0, 'recipient' => 'x', 'required' => false]]]);
ok('Collection: preserves chain + supply for the mismatch check', $c['contracts'][0]['chain'] === 'ethereum' && $c['total_supply'] === 19);

/* --- Verification state machine --------------------------------------- */
ok('Verify: AWAITING with no on-chain address', Verification::compute()['state'] === Config::STATE_AWAITING);

/* --- Cache SWR -------------------------------------------------------- */
$r1 = Cache::remember('u1', 100, fn() => ['n' => 1]);
$r2 = Cache::remember('u1', 100, fn() => ['n' => 2]);
ok('Cache: fresh hit does not re-run the producer', $r2['state'] === 'fresh' && $r2['data']['n'] === 1);
$GLOBALS['__t']['gr_os_u2'] = ['t' => time() - 9999, 'd' => ['stale' => 1]];
$r3 = Cache::remember('u2', 10, fn() => new WP_Error('down'));
ok('Cache: serves STALE copy on producer error', $r3['state'] === 'stale' && $r3['data']['stale'] === 1);
$r4 = Cache::remember('u3', 10, fn() => new WP_Error('down'));
ok('Cache: unavailable (null) when there is nothing cached', $r4['state'] === 'unavailable' && $r4['data'] === null);

/* --- Redaction ------------------------------------------------------- */
// Synthetic 32-hex value shaped like a key — NOT a real credential.
$red = Client::redact('deadbeefcafef00d0123456789abcdef0');
ok('Client::redact never returns the full value', strpos($red, 'deadbeef') === false && str_contains($red, '…'));

/* --- Shortcodes never leak the key --------------------------------- */
$svc = new Service();
$mk = $svc->marketplace_shortcode([]);
ok('Marketplace shortcode renders the SECONDARY MARKET module', str_contains($mk, 'SECONDARY MARKET'));
ok('Marketplace shortcode shows AWAITING copy pre-deployment', str_contains($mk, 'AWAITING DEPLOYMENT'));
ok('Marketplace shortcode contains no "x-api-key" / key string', stripos($mk, 'x-api-key') === false && stripos($mk, 'OPENSEA_API_KEY') === false);
$ms = $svc->market_state_shortcode(['ghost_id' => 31]);
ok('Market-state shortcode renders MARKET STATE', str_contains($ms, 'MARKET STATE'));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
