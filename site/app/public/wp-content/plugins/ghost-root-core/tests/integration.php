<?php
/**
 * Local-only integration checks: wp eval-file path/to/tests/integration.php
 * Disposable records, users and puzzle actors are removed in finally.
 */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local CLI only.'); }
use GhostRoot\Importer\Importer;
use GhostRoot\Terminal\Terminal;
use GhostRoot\Arg\Challenges;
use GhostRoot\Arg\Verifier;
use GhostRoot\Support\RateLimiter;
use GhostRoot\Metadata\Portrait;
use GhostRoot\Metadata\MetaBox;
use GhostRoot\State\StateManager;
use GhostRoot\Settings\Settings;
use GhostRoot\Settings\Config;
use GhostRoot\Support\Vocab;

$results = []; $users = []; $posts = []; $dir = sys_get_temp_dir().'/gr-tests-'.wp_generate_uuid4();
mkdir($dir,0700,true);
$original_user = get_current_user_id(); $original_ip = $_SERVER['REMOTE_ADDR'] ?? null;
$check = static function(string $name, bool $ok) use (&$results) {
    $results[] = ['test'=>$name,'pass'=>$ok];
    WP_CLI::log(($ok ? 'PASS ' : 'FAIL ').$name);
};
$call = static function(string $method,string $route,array $params=[]) {
    $r = new WP_REST_Request($method,$route); $r->set_body_params($params);
    return rest_do_request($r);
};
global $wpdb;
$actor_ips = ['192.0.2.231','192.0.2.232']; $actor_hashes=[];
foreach($actor_ips as $ip) { $_SERVER['REMOTE_ADDR']=$ip; $actor_hashes[]=RateLimiter::actor_hash('arg:incident-31'); }
try {
    $fixture = wp_insert_post(['post_type'=>'ghost_identity','post_status'=>'draft','post_title'=>'QA disposable identity'],true); $posts[]=$fixture;
    update_post_meta($fixture,'state','DORMANT');
    foreach(['subscriber','editor','administrator'] as $role) {
        $id=wp_insert_user(['user_login'=>'gr_qa_'.str_replace('-','',wp_generate_uuid4()),'user_pass'=>wp_generate_password(32,true,true),'role'=>$role]);
        if(is_wp_error($id)) throw new RuntimeException('Unable to create test user');
        $users[$role]=$id;
    }
    wp_set_current_user(0);
    $check('REST anonymous mutation denied',$call('POST','/ghost-root/v1/state/transition',['post_id'=>$fixture,'to'=>'ACTIVE'])->get_status()===401);
    foreach(['subscriber','editor'] as $role) {
        wp_set_current_user($users[$role]);
        $check('REST '.$role.' mutation denied',$call('POST','/ghost-root/v1/state/transition',['post_id'=>$fixture,'to'=>'ACTIVE'])->get_status()===403);
    }
    wp_set_current_user($users['administrator']);
    $check('Administrator one-step transition succeeds',$call('POST','/ghost-root/v1/state/transition',['post_id'=>$fixture,'to'=>'ACTIVE'])->get_status()===200 && StateManager::current($fixture)==='ACTIVE');
    $check('State history records prior and next state',count(StateManager::history($fixture))===1 && StateManager::history($fixture)[0]['from']==='DORMANT');
    $check('State skip is rejected',is_wp_error(StateManager::transition($fixture,'ROOTED')));
    $check('Forced skip is rejected',is_wp_error(StateManager::transition($fixture,'ROOTED',[],true)));
    $check('State rewind is rejected',is_wp_error(StateManager::transition($fixture,'DORMANT')));
    $_POST=['gr_meta_nonce'=>wp_create_nonce('gr_save_meta'),'gr_meta'=>['_ghost_state_history'=>'overwrite','arbitrary_key'=>'overwrite','entity'=>'SYNTHETIC']];
    (new MetaBox())->save($fixture,get_post($fixture));
    $check('Admin metabox ignores unlisted fields',get_post_meta($fixture,'arbitrary_key',true)==='' && count(StateManager::history($fixture))===1);
    $_POST=[];
    $command=new Terminal();
    $check('Terminal allowlisted command works',str_contains(implode("\n",$command->run('help')['lines']),'AVAILABLE COMMANDS'));
    foreach(['status; id', '$'.'(whoami)', '../../wp-config.php','<?php phpinfo(); ?>','status extra','identity 1 OR 1=1'] as $payload) {
        $out=implode("\n",$command->run($payload)['lines']);
        $check('Terminal rejects '.substr($payload,0,30),str_contains($out,'not found') || str_contains($out,'rejected'));
    }
    $check('Terminal rejects oversized input',str_contains(implode("\n",$command->run(str_repeat('a',257))['lines']),'exceeds'));
    $check('REST rejects array command',$call('POST','/ghost-root/v1/terminal',['command'=>['status']])->get_status()===400);
    $check('REST rejects long command',$call('POST','/ghost-root/v1/terminal',['command'=>str_repeat('a',257)])->get_status()===400);
    $cross=new WP_REST_Request('POST','/ghost-root/v1/terminal');$cross->set_header('Origin','https://untrusted.invalid');$cross->set_body_params(['command'=>'status']);
    $check('Cross-origin interaction denied',rest_do_request($cross)->get_status()===403);
    $view=Challenges::public_view('incident-31',1);
    $check('ARG public view excludes answer hashes',!str_contains(wp_json_encode($view),'answer_hash'));
    $_SERVER['REMOTE_ADDR']=$actor_ips[0];
    $check('ARG stage skipping rejected',!Verifier::verify('incident-31',3,'secret')['ok']);
    $check('Unreleased stage prompt hidden',Challenges::public_view('incident-31',3)===null);
    $check('Wrong ARG answer rejected',!Verifier::verify('incident-31',1,'wrong')['ok']);
    $check('Hint ladder advances',count(Challenges::public_view('incident-31',1)['hints'])===2);
    $check('Correct stage 1 accepted',Verifier::verify('incident-31',1,'node thirty one')['status']==='advance');
    $check('Correct stage 2 accepted',Verifier::verify('incident-31',2,'root port')['status']==='advance');
    $check('Correct stage 3 completes',Verifier::verify('incident-31',3,'secret')['status']==='complete');
    $_SERVER['REMOTE_ADDR']=$actor_ips[1];
    for($i=0;$i<6;$i++) Verifier::verify('incident-31',1,'wrong');
    $check('ARG cooldown enforced',Verifier::verify('incident-31',1,'node thirty one')['status']==='cooldown');
    $key='qa-'.wp_generate_uuid4();
    $check('Rate limiter permits first request',RateLimiter::allow($key,2,60));
    $check('Rate limiter permits second request',RateLimiter::allow($key,2,60));
    $check('Rate limiter denies excess request',!RateLimiter::allow($key,2,60));
    $traits=['ghost_id'=>'1842','fingerprint'=>'AABBCCDD','entity'=>'SYNTHETIC','face'=>'CHROME','eyes'=>'VOID','access'=>'USER'];
    $check('Procedural portraits deterministic',Portrait::svg($traits)===Portrait::svg($traits));
    $traits2=$traits;$traits2['ghost_id']='1843';
    $check('Different identities get different portraits',Portrait::svg($traits)!==Portrait::svg($traits2));
    // --- portrait diversity + robustness (Visual System V2) ---
    $bands=['GENESIS','ROOT','ADMIN','CORRUPTED','STANDARD','STANDARD','STANDARD','STANDARD','STANDARD','STANDARD','STANDARD','STANDARD'];
    $faces=['PORCELAIN','CHROME','CARBON','CERAMIC','RECONSTRUCTED','BIO-SYNTH'];
    $eyeset=['THERMAL','VOID','BIOMETRIC','FRACTURED','OPTICAL ARRAY','SIGNAL-BURN','NULL APERTURE','REVOCATION LENS','WITNESS ARRAY'];
    $svgs=[];$sil=[];
    foreach($bands as $i=>$b){
        $tt=['ghost_id'=>(string)(4000+$i),'fingerprint'=>strtoupper(substr(md5((string)$i),0,12)),'entity'=>['HUMAN','SYNTHETIC','HOLLOW','SPECTER'][$i%4],'face'=>$faces[$i%6],'eyes'=>$eyeset[$i%9],'mask'=>['NONE','FORENSIC PLATE','RESPIRATOR','SKELETAL INTERFACE','NEURAL VEIL','NULL MASK'][$i%6],'implant'=>['NEURAL CABLE','ANTENNA','OPTICAL ARRAY','SPINAL BUS','SIGNAL CROWN','ROOT PORT'][$i%6],'corruption'=>['GLITCH','BURN','SIGNAL LOSS','FRAGMENTATION','PACKET GHOSTING','MEMORY BLEED','NONE'][$i%7],'access'=>['USER','OPERATOR','ADMIN','SYSTEM','ROOT'][$i%5],'background'=>'ARCHIVE','archetype'=>'THE OBSERVER','rarity_band'=>$b,'state'=>'DORMANT'];
        $s=Portrait::svg($tt);$svgs[]=$s;
        if(preg_match('/<clipPath[^>]*><path d="(M[^"]+Z)"/',$s,$m)) $sil[]=sha1(preg_replace_callback('/-?\d+\.?\d*/',fn($x)=>(string)(round(((float)$x[0])/16)*16),$m[1]));
    }
    $check('Portrait sample has no exact duplicates',count(array_unique($svgs))===count($svgs));
    $check('Portrait silhouettes are ≥83% unique',count(array_unique($sil))>=10);
    $ok=true;$big=0;
    foreach(Vocab::TRAITS as $type=>$vals){ foreach($vals as $v){
        $tt=['ghost_id'=>'7','fingerprint'=>'DEADBEEF01','entity'=>'HUMAN','face'=>'PORCELAIN','eyes'=>'VOID','mask'=>'NONE','implant'=>'ANTENNA','corruption'=>'NONE','access'=>'USER','background'=>'SERVER','archetype'=>'','rarity_band'=>'STANDARD','state'=>'DORMANT'];
        $key=strtolower(str_replace(' ','_',$type)); if(isset($tt[$key])) $tt[$key]=$v; elseif('rarity band'===strtolower($type)) $tt['rarity_band']=$v;
        $s=@Portrait::svg($tt);
        if(!str_starts_with($s,'<svg ')||!str_ends_with($s,'</svg>')||strpos($s,'viewBox="0 0 640 640"')===false){$ok=false;}
        if(strlen($s)>18000)$big++;
    }}
    $check('Portrait renders every trait value as valid SVG',$ok);
    $check('Portrait output stays under 18KB for all trait values',$big===0);
    $before=Config::all(); $safe=(new Settings())->sanitize(['treasury_address'=>'seed phrase should never be stored','unexpected_private_key'=>'test secret','mint_max_per_wallet'=>2,'signal_strength'=>['invalid']]);
    $check('Settings refuse secret-shaped input',$safe['treasury_address']===$before['treasury_address'] && !isset($safe['unexpected_private_key']));
    $check('Malformed setting rejected safely',$safe['signal_strength']===$before['signal_strength']);
    $check('Mint config has no signing fields',!preg_match('/private|secret|mnemonic|seed/i',wp_json_encode(Config::mint_config())));
    $source=dirname(ABSPATH,2).'/Ghost/ghost-root-nft-builder-full-demo/metadata/0001.json';
    $good=file_get_contents($source);file_put_contents($dir.'/0001.json',$good);
    $r=(new Importer())->run($dir,['dry_run'=>true]);
    $check('Importer accepts valid metadata',$r['status']==='PASS' && $r['totals']['valid']===1);
    $r=(new Importer())->run($dir);
    $check('Importer skips existing identity',$r['totals']['skipped']===1 && $r['totals']['created']===0);
    file_put_contents($dir.'/0001.json','{bad json');
    $check('Importer rejects malformed JSON',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    $bad=json_decode($good,true);$bad['attributes'][0]['value']='INVALID';file_put_contents($dir.'/0001.json',wp_json_encode($bad));
    $check('Importer rejects unknown traits',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    $bad=json_decode($good,true);$bad['attributes'][]=$bad['attributes'][0];file_put_contents($dir.'/0001.json',wp_json_encode($bad));
    $check('Importer rejects duplicate attributes',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    $bad=json_decode($good,true);$bad['name']=['invalid'];file_put_contents($dir.'/0001.json',wp_json_encode($bad));
    $check('Importer rejects structured name',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    $bad=json_decode($good,true);$bad['image']='javascript:alert(1)';file_put_contents($dir.'/0001.json',wp_json_encode($bad));
    $check('Importer rejects unsafe image URI',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    unlink($dir.'/0001.json');symlink($source,$dir.'/0001.json');
    $check('Importer rejects symlink escape',(new Importer())->run($dir,['dry_run'=>true])['status']==='FAIL');
    unlink($dir.'/0001.json');
    wp_set_current_user(0);
    $read=$call('GET','/ghost-root/v1/identity/1');
    $check('Public identity read works',$read->get_status()===200);
    $check('Private identity inaccessible',$call('GET','/ghost-root/v1/identity/0')->get_status()===404);
    $check('Protocol records are not public REST content',!get_post_type_object('protocol_record')->show_in_rest);

    // --- OpenSea integration -------------------------------------------------
    wp_set_current_user(0);
    $os_status = $call('GET','/ghost-root/v1/opensea/status');
    $check('OpenSea status endpoint is public + 200',$os_status->get_status()===200);
    $status_body = $os_status->get_data();
    $check('OpenSea status never leaks the API key',
        !str_contains(strtolower(wp_json_encode($status_body)),'x-api-key') &&
        !array_key_exists('key_source',(array)$status_body) &&
        (!\GhostRoot\OpenSea\Config::has_api_key() || strpos(wp_json_encode($status_body), \GhostRoot\OpenSea\Config::api_key())===false));
    $check('OpenSea status reports a known state',
        in_array($status_body['status'] ?? '',['AWAITING_SOLANA_COLLECTION_DEPLOYMENT','NOT_INDEXED','PENDING','VERIFIED','PARTIAL','MISMATCH','ERROR'],true));
    $os_stats = $call('GET','/ghost-root/v1/opensea/stats');
    $check('OpenSea stats fails open (200, never 500)',$os_stats->get_status()===200);
    $stats_body = (array)$os_stats->get_data();
    $check('OpenSea stats never fabricates a zero when there is no market',
        !isset($stats_body['source']) || $stats_body['source']!=='opensea' || array_key_exists('floor_price',$stats_body));
    $check('OpenSea verification endpoint public + 200',$call('GET','/ghost-root/v1/opensea/verification')->get_status()===200);
    $check('OpenSea refresh requires auth',$call('POST','/ghost-root/v1/opensea/refresh')->get_status()===401 || $call('POST','/ghost-root/v1/opensea/refresh')->get_status()===403);
    $check('Marketplace shortcode renders + carries no secret',
        !str_contains(do_shortcode('[ghost_root_marketplace]'),\GhostRoot\OpenSea\Config::api_key() ?: '__none__') &&
        str_contains(do_shortcode('[ghost_root_marketplace]'),'SECONDARY MARKET'));
} finally {
    $_POST=[];
    wp_set_current_user($original_user);
    require_once ABSPATH.'wp-admin/includes/user.php';
    foreach($users as $id) wp_delete_user($id);
    foreach($posts as $id) { wp_delete_post($id,true); $wpdb->delete($wpdb->prefix.'ghost_root_events',['subject_id'=>(string)$id]); }
    foreach($actor_hashes as $hash) { $wpdb->delete($wpdb->prefix.'ghost_root_attempts',['ip_hash'=>$hash]); $wpdb->delete($wpdb->prefix.'ghost_root_events',['ip_hash'=>$hash]); }
    if(isset($key)) $wpdb->delete($wpdb->prefix.'ghost_root_limits',['bucket_key'=>hash('sha256',RateLimiter::actor_hash($key).':'.(int)floor(time()/60))]);
    if($original_ip===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$original_ip;
    foreach(glob($dir.'/*')?:[] as $file) unlink($file);rmdir($dir);
}
$failed=count(array_filter($results,static fn($r)=>!$r['pass']));
echo wp_json_encode(['checks'=>count($results),'failed'=>$failed,'results'=>$results],JSON_PRETTY_PRINT)."\n";
if($failed) WP_CLI::error($failed.' integration checks failed.');
WP_CLI::success(count($results).' integration checks passed; fixtures cleaned up.');
