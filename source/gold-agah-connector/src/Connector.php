<?php
declare(strict_types=1);
namespace GoldAgah;
use GoldCommerce\Database;
use GoldCommerce\Plugin;
use GoldCommerce\Decimal;
final class Connector {
    public static function boot():void{
        add_filter('gc_agah_status',static fn()=>'MOCK_ONLY');
        add_filter('gc_authority_mode',static fn($mode)=>$mode==='standalone'?'standalone':'agah_degraded');
        add_action('admin_menu',static function(){add_submenu_page('gold-commerce','اتصال آگاه','اتصال آگاه — MOCK_ONLY','gc_manage_integrations','gc-agah',[self::class,'page']);});
        add_action('gc_queue_tick',[self::class,'drain']);
        add_action('gc_verified_paid',[self::class,'paid']);
        add_action('gc_refund_event',[self::class,'refunded'],10,2);
        add_action('admin_post_gc_agah_retry',static function(){
            if(!current_user_can('gc_manage_integrations'))wp_die('forbidden',403);check_admin_referer('gc_agah_retry');$id=(int)($_POST['id']??0);global $wpdb;
            $wpdb->query($wpdb->prepare('UPDATE '.Database::table('integration_outbox')." SET state='retry',next_try=%d WHERE id=%d AND store_uuid=%s AND company_id=%s AND state IN ('retry','dead')",time(),$id,get_option('gc_store_uuid'),self::provider()->company));Database::audit('agah.retry_requested',(string)$id);wp_safe_redirect(admin_url('admin.php?page=gc-agah'));exit;
        });
        add_action('rest_api_init',static function(){register_rest_route('gold-agah/v1','/webhook',['methods'=>'POST','permission_callback'=>[self::class,'authenticate'],'callback'=>static function($r){try{return self::inbox((array)$r->get_json_params());}catch(\Throwable $e){return new \WP_Error('webhook_rejected','رویداد رد شد',['status'=>409]);}}]);});
        add_filter('woocommerce_can_reduce_order_stock',static fn($yes,$order)=>$order->get_meta('_gc_authority_agah')==='yes'?false:$yes,10,2);
        add_filter('woocommerce_can_restore_order_stock',static fn($yes,$order)=>$order->get_meta('_gc_authority_agah')==='yes'?false:$yes,10,2);
    }
    public static function provider():MockProvider{return new MockProvider((string)get_option('gc_store_uuid'),(string)get_option('gc_agah_company','mock-company'));}
    public static function page():void{
        if(!current_user_can('gc_manage_integrations'))wp_die('forbidden');
        global $wpdb;$rows=$wpdb->get_results('SELECT event_uuid,event_type,state,attempts,next_try,last_error_code FROM '.Database::table('integration_outbox').' ORDER BY id DESC LIMIT 100',ARRAY_A);
        echo '<div class="wrap" dir="rtl"><h1>اتصال آگاه — MOCK_ONLY</h1><p>API عمومی و رزرو واقعی در alpha12 وجود ندارد. این افزونه تنها با شبیه‌ساز آزموده می‌شود. فروش آگاه‌محور عملیاتی غیرفعال است. فروش مستقل از WooCommerce استفاده می‌کند.</p><p>اطلاعات احراز هویت در wp-config یا متغیر محیطی نگهداری می‌شود.</p><h2>صف و مغایرت‌ها</h2><pre dir="ltr">'.esc_html(wp_json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="gc_agah_retry">';wp_nonce_field('gc_agah_retry');echo '<label>شناسه ردیف صف برای تلاش مجدد با همان کلید <input type="number" name="id" min="1" required></label><button>ثبت تلاش مجدد</button></form></div>';
    }
    public static function switchAuthority(string $target,array $reconciliation):void{
        if(!current_user_can('gc_manage_integrations'))throw new \DomainException('forbidden');
        Database::lock('authority-switch',static function()use($target,$reconciliation){
            if(!in_array($target,['standalone','agah_connected'],true)||empty($reconciliation['approved'])||empty($reconciliation['mapping_verified'])||empty($reconciliation['balances_verified']))throw new \DomainException('reconciliation_required');
            $open=wc_get_orders(['limit'=>1,'status'=>['pending','on-hold','gc-await-quote','gc-quote-sent'],'return'=>'ids']);if($open)throw new \DomainException('open_orders_lock');
            if($target==='agah_connected')throw new \DomainException('BLOCKED_EXTERNAL_real_reservation_api_required');
            update_option('gc_mode','standalone');Database::audit('authority.changed','store',['mode'=>'standalone','reconciliation'=>$reconciliation]);
        });
    }
    public static function map(int $local,string $remote,string $company,string $type='product'):void{
        if(!current_user_can('gc_manage_integrations'))throw new \DomainException('forbidden');global $wpdb;$store=(string)get_option('gc_store_uuid');
        if($company!==self::provider()->company||!in_array($type,['product','variation'],true)||$local<1||$remote==='')throw new \DomainException('mapping_invalid');
        Database::lock('mapping:'.$type.':'.$local,static function()use($wpdb,$store,$company,$type,$local,$remote){
            $table=Database::table('external_mapping');$old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE store_uuid=%s AND company_id=%s AND local_type=%s AND local_id=%d",$store,$company,$type,$local),ARRAY_A);
            $history=$old?json_decode($old['history'],true):[];if($old)$history[]=['remote_id'=>$old['remote_id'],'version'=>(int)$old['version'],'changed_at'=>time()];
            $row=['store_uuid'=>$store,'company_id'=>$company,'local_type'=>$type,'local_id'=>$local,'remote_id'=>$remote,'version'=>$old?(int)$old['version']+1:1,'history'=>wp_json_encode($history)];
            $ok=$old?$wpdb->update($table,$row,['id'=>$old['id']]):$wpdb->insert($table,$row);if($ok===false)throw new \DomainException('mapping_conflict');Database::audit('mapping.updated',(string)$local,['version'=>$row['version']]);
        });
    }
    public static function enqueue(string $event,string $type,string $order,array $payload):void{
        global $wpdb;$store=(string)get_option('gc_store_uuid');$company=self::provider()->company;
        $payload+=['store_uuid'=>$store,'company_id'=>$company,'schema_version'=>'1','idempotency_key'=>$event];
        if($payload['store_uuid']!==$store||$payload['company_id']!==$company)throw new \DomainException('company_scope_denied');
        $encoded=wp_json_encode($payload);$hash=hash('sha256',$encoded);$table=Database::table('integration_outbox');
        $old=$wpdb->get_var($wpdb->prepare("SELECT payload_hash FROM $table WHERE event_uuid=%s",$event));if($old){if(!hash_equals($old,$hash))throw new \DomainException('idempotency_payload_conflict');return;}
        $ok=$wpdb->insert($table,['event_uuid'=>$event,'store_uuid'=>$store,'company_id'=>$company,'event_type'=>$type,'aggregate_id'=>$order,'payload'=>$encoded,'payload_hash'=>$hash,'state'=>'pending','next_try'=>time()]);
        if(!$ok)throw new \RuntimeException('outbox_failed');
    }
    public static function paid(\WC_Order $order):void{
        if($order->get_meta('_gc_authority_agah')!=='yes')return;
        $event='paid:'.$order->get_id().':'.$order->get_meta('_gc_intent');$payload=(array)$order->get_meta('_gc_agah_event_payload');
        $payload+=['order_uuid'=>(string)$order->get_meta('_gc_order_uuid'),'amount_minor'=>Decimal::mul(Decimal::money((string)$order->get_total()),'100'),'currency'=>'IRR','scale'=>2];
        self::enqueue($event,'sale',(string)$order->get_id(),$payload);$order->update_meta_data('_gc_agah_sync','pending — MOCK_ONLY');$order->save();
    }
    public static function refunded(int $orderId,int $refundId):void{
        $order=wc_get_order($orderId);if(!$order||$order->get_meta('_gc_authority_agah')!=='yes')return;
        self::enqueue('refund:'.$refundId,'refund',(string)$orderId,['invoice_id'=>(string)$order->get_meta('_gc_agah_invoice')]);
    }
    public static function drain():void{
        global $wpdb;Database::lock('agah-outbox',static function()use($wpdb){
            $table=Database::table('integration_outbox');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE state IN ('pending','retry') AND next_try<=%d ORDER BY id LIMIT 30",time()),ARRAY_A);
            foreach($rows as $row){$p=json_decode($row['payload'],true);try{
                $provider=self::provider();if($row['store_uuid']!==$provider->store||$row['company_id']!==$provider->company)throw new \DomainException('company_scope_denied');
                $response=match($row['event_type']){'sale'=>$provider->sale($p),'refund'=>$provider->refund($p),'release'=>$provider->release($p),default=>throw new \DomainException('event_type_invalid')};
                $wpdb->update($table,['state'=>'mock_delivered','attempts'=>(int)$row['attempts']+1,'last_error_code'=>''],['id'=>$row['id']]);
                if($o=wc_get_order((int)$row['aggregate_id'])){$o->update_meta_data('_gc_agah_sync','MOCK_ONLY delivered');if(isset($response['invoice_id']))$o->update_meta_data('_gc_agah_invoice',$response['invoice_id']);$o->save();}
            }catch(\Throwable $e){$attempt=(int)$row['attempts']+1;$code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'provider_failed';$terminal=$e instanceof \DomainException||$attempt>=8;
                $wpdb->update($table,['state'=>$terminal?'dead':'retry','attempts'=>$attempt,'next_try'=>time()+min(7200,30*(2**$attempt)),'last_error_code'=>$code],['id'=>$row['id']]);Database::audit('agah.delivery_failed',$row['event_uuid'],['code'=>$code,'attempt'=>$attempt]);\GoldCommerce\Notifications::enqueue('sync-problem:'.$row['event_uuid'],get_option('admin_email'),'اشکال در همگام‌سازی آگاه — MOCK_ONLY','رویداد '.$row['event_uuid'].' نیازمند بررسی است. کد: '.$code);}
            }
        });
    }
    public static function authenticate(\WP_REST_Request $r):bool|\WP_Error{
        $secret=defined('GC_AGAH_WEBHOOK_SECRET')?(string)GC_AGAH_WEBHOOK_SECRET:(string)getenv('GC_AGAH_WEBHOOK_SECRET');
        if(strlen($secret)<32)return new \WP_Error('not_configured','BLOCKED_EXTERNAL',['status'=>503]);
        $ts=$r->get_header('X-GC-Timestamp');$sig=$r->get_header('X-GC-Signature');
        if(!ctype_digit($ts)||abs(time()-(int)$ts)>300||!hash_equals(hash_hmac('sha256',$ts.'.'.$r->get_body(),$secret),$sig))return new \WP_Error('unauthorized','رد شد',['status'=>401]);return true;
    }
    public static function inbox(array $p):array{
        global $wpdb;$store=(string)get_option('gc_store_uuid');$company=self::provider()->company;
        if(($p['store_uuid']??'')!==$store||($p['company_id']??'')!==$company||($p['schema_version']??'')!=='1'||empty($p['event_id'])){Database::audit('webhook.scope_denied','agah');throw new \DomainException('webhook_scope_denied');}
        return Database::lock('inbox:'.$p['event_id'],static function()use($wpdb,$p,$store,$company){
            $hash=hash('sha256',wp_json_encode($p));$table=Database::table('integration_inbox');$old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE provider='agah' AND store_uuid=%s AND company_id=%s AND remote_event_id=%s",$store,$company,$p['event_id']),ARRAY_A);
            if($old){if($old['payload_hash']!==$hash)throw new \DomainException('webhook_payload_conflict');return ['status'=>'duplicate','mode'=>'MOCK_ONLY'];}
            // No mock payload is allowed to overwrite live Woo inventory or pricing.
            if(!in_array($p['type']??'',['catalog_changed','price_changed','inventory_changed','sync_notice'],true))throw new \DomainException('event_type_invalid');
            $result=['status'=>'recorded','mode'=>'MOCK_ONLY'];$wpdb->insert($table,['provider'=>'agah','store_uuid'=>$store,'company_id'=>$company,'remote_event_id'=>$p['event_id'],'payload_hash'=>$hash,'result'=>wp_json_encode($result),'processed_at'=>time()]);Database::audit('webhook.recorded',(string)$p['event_id']);return $result;
        });
    }
}
