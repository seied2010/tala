<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Rates {
    public static function put(array $tick,string $source='manual'):array {
        global $wpdb;
        Decimal::parse($tick['value']??null);
        if(($tick['currency']??'')!=='IRR'||!in_array($tick['unit']??'',['IRR_PER_GRAM','IRR_PER_ITEM'],true))throw new \DomainException('rate_unit_invalid');
        $now=time();$until=(int)($tick['valid_until']??0);$ttl=(int)(Plugin::settings()['rate_ttl']??60);
        if($until<=$now||$until>$now+$ttl||($tick['effective_at']??$now)>$now)throw new \DomainException('rate_expiry_invalid');
        $tick+=['effective_at'=>$now,'fetched_at'=>$now,'reference_purity'=>'750'];$tick['version']=wp_generate_uuid4();$tick['source']=$source;
        $wpdb->insert(Database::table('rate_ticks'),['source_id'=>$source,'version'=>$tick['version'],'payload'=>wp_json_encode($tick),'valid_until'=>$until,'created_at'=>$now]);
        if($wpdb->last_error)throw new \RuntimeException('rate_persistence_failed');Database::audit('rate.updated',$tick['version'],['source'=>$source]);do_action('gc_rate_changed',$tick);return $tick;
    }
    public static function latest(string $source='manual'):array{
        global $wpdb;$json=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Database::table('rate_ticks').' WHERE source_id=%s ORDER BY id DESC LIMIT 1',$source));
        if(!$json)throw new \DomainException('rate_unavailable');$tick=json_decode($json,true,32,JSON_THROW_ON_ERROR);if((int)$tick['valid_until']<=time())throw new \DomainException('rate_stale');return $tick;
    }
    public static function refresh():void{
        global $wpdb;
        foreach($wpdb->get_results('SELECT * FROM '.Database::table('rate_sources').' ORDER BY id',ARRAY_A) as $source){
            try{
                $c=json_decode($source['config'],true,32,JSON_THROW_ON_ERROR);$headers=[];
                if(!empty($c['credential_env'])){$secret=getenv($c['credential_env']);if(!$secret)throw new \RuntimeException('rate_credentials_missing');$headers['Authorization']='Bearer '.$secret;}
                $tick=self::http($c['url'],$headers);$tick['source_version']=(string)($tick['version']??'');self::put($tick,$source['name']);
                $wpdb->update(Database::table('rate_sources'),['health'=>'ok','last_success'=>time()],['id'=>$source['id']]);
            }catch(\Throwable $e){$wpdb->update(Database::table('rate_sources'),['health'=>'unavailable'],['id'=>$source['id']]);Database::audit('rate.failed',$source['name'],['code'=>'provider_unavailable']);}
        }
        foreach((array)apply_filters('gc_rate_providers',[]) as $name=>$provider){
            try{self::put($provider(),(string)$name);}catch(\Throwable $e){Database::audit('rate.failed',(string)$name,['code'=>'provider_unavailable']);}
        }
    }
    public static function addSource(string $name,string $url,string $credentialEnv=''):void{
        if(!current_user_can('gc_manage_rates'))throw new \DomainException('forbidden');
        $name=sanitize_key($name);if(!$name||$name==='manual'||strlen($name)>64)throw new \DomainException('source_name_invalid');
        if(wp_parse_url($url,PHP_URL_SCHEME)!=='https'||wp_parse_url($url,PHP_URL_USER)||wp_parse_url($url,PHP_URL_PASS)||!wp_http_validate_url($url)||preg_match('/secret|token|api.?key|password/i',(string)wp_parse_url($url,PHP_URL_QUERY)))throw new \DomainException('unsafe_provider_url');
        if($credentialEnv!==''&&!preg_match('/^GC_RATE_[A-Z0-9_]{1,64}$/D',$credentialEnv))throw new \DomainException('credential_env_invalid');
        global $wpdb;$table=Database::table('rate_sources');$row=['name'=>$name,'config'=>wp_json_encode(['url'=>$url,'credential_env'=>$credentialEnv]),'health'=>'unconfigured'];
        $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s",$name));$ok=$id?$wpdb->update($table,$row,['id'=>$id]):$wpdb->insert($table,$row);if($ok===false)throw new \RuntimeException('source_save_failed');Database::audit('rate.source_updated',$name);
    }
    public static function resolve(string $source,array $fallbacks=[]):array{
        foreach(array_unique(array_merge([$source],$fallbacks)) as $name){try{return self::latest((string)$name);}catch(\DomainException $e){}}
        throw new \DomainException('rate_unavailable');
    }
    /** HTTPS only, fixed admin-defined provider host, no redirects or private destinations. */
    public static function http(string $url,array $headers=[]):array{
        if(wp_parse_url($url,PHP_URL_SCHEME)!=='https'||!wp_http_validate_url($url))throw new \DomainException('unsafe_provider_url');
        $response=wp_safe_remote_get($url,['headers'=>$headers,'timeout'=>10,'redirection'=>0,'limit_response_size'=>65536]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)throw new \RuntimeException('provider_unavailable');
        return json_decode(wp_remote_retrieve_body($response),true,32,JSON_THROW_ON_ERROR);
    }
}
