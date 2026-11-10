<?php
declare(strict_types=1);
namespace GoldCommerce;

final class Database {
    public static function table(string $name): string {global $wpdb;if(!preg_match('/^[a-z_]+$/D',$name))throw new \DomainException('table_invalid');return $wpdb->prefix.'gc_'.$name;}
    public static function install(): void {
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$collate=$wpdb->get_charset_collate();
        $guardDir=WP_CONTENT_DIR.'/mu-plugins';
        if(!wp_mkdir_p($guardDir)||!copy(dirname(__DIR__).'/guards/gold-commerce-guard.php',$guardDir.'/gold-commerce-guard.php'))throw new \RuntimeException('persistent_guard_install_failed');
        $schemas=[
            'quotes'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, uuid varchar(36) NOT NULL, order_id bigint unsigned NOT NULL, version int NOT NULL, status varchar(32) NOT NULL, token_hash char(64) NOT NULL, snapshot longtext NOT NULL, amount_irr varchar(32) NOT NULL, issued_at bigint NOT NULL, expires_at bigint NOT NULL, started_at bigint NULL, settlement_deadline bigint NULL, intent varchar(64) NULL, transaction_id varchar(191) NULL, PRIMARY KEY  (id), UNIQUE KEY uuid (uuid), UNIQUE KEY order_version (order_id,version), KEY expiry (status,expires_at)",
            'rate_sources'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(64) NOT NULL, config longtext NOT NULL, health varchar(32) NOT NULL, last_success bigint NULL, PRIMARY KEY  (id), UNIQUE KEY name (name)",
            'rate_ticks'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, source_id varchar(64) NOT NULL, version varchar(64) NOT NULL, payload longtext NOT NULL, valid_until bigint NOT NULL, created_at bigint NOT NULL, PRIMARY KEY  (id), UNIQUE KEY version (version), KEY expiry (valid_until)",
            'quote_events'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, quote_uuid varchar(36) NOT NULL, event_type varchar(64) NOT NULL, metadata longtext NOT NULL, created_at bigint NOT NULL, PRIMARY KEY  (id), KEY quote (quote_uuid)",
            'security_audit'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, actor bigint unsigned NOT NULL, action varchar(80) NOT NULL, object_id varchar(80) NOT NULL, metadata longtext NOT NULL, created_at bigint NOT NULL, previous_hash char(64) NOT NULL, event_hash char(64) NOT NULL, PRIMARY KEY  (id), KEY object_id (object_id)",
            'integration_outbox'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, event_uuid varchar(64) NOT NULL, store_uuid varchar(36) NOT NULL, company_id varchar(64) NOT NULL, event_type varchar(64) NOT NULL, aggregate_id varchar(64) NOT NULL, payload longtext NOT NULL, payload_hash char(64) NOT NULL, state varchar(24) NOT NULL, attempts int NOT NULL DEFAULT 0, next_try bigint NOT NULL, last_error_code varchar(64) NOT NULL DEFAULT '', PRIMARY KEY  (id), UNIQUE KEY event_uuid (event_uuid), KEY ready (state,next_try)",
            'integration_inbox'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, provider varchar(32) NOT NULL, store_uuid varchar(36) NOT NULL, company_id varchar(64) NOT NULL, remote_event_id varchar(128) NOT NULL, payload_hash char(64) NOT NULL, result longtext NOT NULL, processed_at bigint NOT NULL, PRIMARY KEY  (id), UNIQUE KEY dedup (provider,store_uuid,company_id,remote_event_id)",
            'external_mapping'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, store_uuid varchar(36) NOT NULL, company_id varchar(64) NOT NULL, local_type varchar(32) NOT NULL, local_id bigint unsigned NOT NULL, remote_id varchar(128) NOT NULL, version int NOT NULL, history longtext NOT NULL, PRIMARY KEY  (id), UNIQUE KEY local_entity (store_uuid,company_id,local_type,local_id), UNIQUE KEY remote_entity (store_uuid,company_id,local_type,remote_id)",
            'notifications'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, dedup_key varchar(128) NOT NULL, payload longtext NOT NULL, state varchar(24) NOT NULL, attempts int NOT NULL DEFAULT 0, next_try bigint NOT NULL, PRIMARY KEY  (id), UNIQUE KEY dedup_key (dedup_key), KEY ready (state,next_try)",
            'support'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, order_id bigint unsigned NOT NULL DEFAULT 0, user_id bigint unsigned NOT NULL, kind varchar(24) NOT NULL, status varchar(24) NOT NULL, message text NOT NULL, created_at bigint NOT NULL, PRIMARY KEY  (id), KEY owner (user_id,order_id)"
            ,'payment_receipts'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, provider varchar(32) NOT NULL, transaction_id varchar(191) NOT NULL, order_id bigint unsigned NOT NULL, amount_irr varchar(32) NOT NULL, intent varchar(64) NOT NULL, status varchar(24) NOT NULL, received_at bigint NOT NULL, PRIMARY KEY  (id), UNIQUE KEY receipt (provider,transaction_id), KEY order_id (order_id)"
        ];
        foreach($schemas as $name=>$cols){
            $wpdb->last_error='';
            dbDelta('CREATE TABLE '.self::table($name)." (\n".str_replace(', ',",\n",$cols)."\n) ENGINE=InnoDB $collate;");
            if($wpdb->last_error!=='')throw new \RuntimeException('schema_migration_failed:'.$name);
        }
        add_option('gc_store_uuid',wp_generate_uuid4());add_option('gc_mode','standalone');add_option('gc_schema','1');
        add_option('gc_settings',['pricing_strategy'=>'REQUEST_QUOTE','quote_ttl'=>180,'payment_grace'=>900,'rate_ttl'=>60,'guest_quotes'=>false,'shipping_configured'=>false,'policy'=>['version'=>'unconfigured','tax_percent'=>'0','tax_components'=>[],'rounding'=>'half_up'],'finance_configured'=>false,'display_currency'=>'IRR','feature_flags'=>[]]);
        self::roles();self::pages();update_option('gc_schema','4');
        add_filter('cron_schedules',static function($s){$s['gc_minute']=['interval'=>60,'display'=>'Gold Commerce every minute'];return $s;});
        if(!wp_next_scheduled('gc_maintenance'))wp_schedule_event(time()+60,'gc_minute','gc_maintenance');
    }
    public static function roles(): void {
        $roles=[
            'gc_pricer'=>['کارشناس قیمت',['gc_issue_quotes','gc_manage_rates','gc_view_reports']],
            'gc_fulfilment'=>['کارشناس ارسال',['gc_fulfil_orders','gc_support']],
            'gc_auditor'=>['حسابرس',['gc_view_reports']],
            'gc_content'=>['مدیر محتوا',['edit_posts','publish_posts','upload_files','edit_pages']],
            'gc_sales'=>['مدیر فروش',['edit_products','publish_products','read_private_products','edit_shop_orders','gc_issue_quotes','gc_fulfil_orders','gc_support','gc_view_reports']]
        ];
        foreach($roles as $id=>[$label,$caps]){add_role($id,$label,['read'=>true]);$role=get_role($id);foreach($caps as $cap)$role?->add_cap($cap);}
        foreach(['administrator','shop_manager'] as $id){$r=get_role($id);foreach(['gc_issue_quotes','gc_manage_rates','gc_fulfil_orders','gc_support','gc_view_reports'] as $cap)$r?->add_cap($cap);}
        foreach(['gc_manage_settings','gc_manage_integrations'] as $cap)get_role('administrator')?->add_cap($cap);
    }
    public static function pages(): void {
        $defs=['home'=>['خانه',''], 'shop'=>['فروشگاه',''], 'cart'=>['سبد خرید','[woocommerce_cart]'],'checkout'=>['تسویه حساب','[woocommerce_checkout]'],'myaccount'=>['حساب من','[woocommerce_my_account]'],'tracking'=>['پیگیری سفارش','[gc_tracking]'],'about'=>['درباره ما',''],'contact'=>['تماس با ما','[gc_contact]'],'shipping'=>['ارسال',''],'returns'=>['مرجوعی',''],'faq'=>['سؤالات متداول',''],'terms'=>['شرایط خرید',''],'privacy'=>['حریم خصوصی','']];
        $map=get_option('gc_pages',[]);
        foreach($defs as $key=>[$title,$content]){
            $wc=in_array($key,['shop','cart','checkout','myaccount','terms'],true)?(int)get_option('woocommerce_'.$key.'_page_id'):0;
            if($wc&&get_post($wc)){$map[$key]=$wc;continue;}
            if($key==='privacy'&&($existing=(int)get_option('wp_page_for_privacy_policy'))&&get_post($existing)){$map[$key]=$existing;continue;}
            if(!empty($map[$key])&&get_post($map[$key]))continue;
            $found=get_page_by_path(sanitize_title($title));
            $map[$key]=$found?$found->ID:wp_insert_post(['post_title'=>$title,'post_name'=>sanitize_title($title),'post_content'=>$content,'post_type'=>'page','post_status'=>'publish']);
            if($wc===0&&in_array($key,['shop','cart','checkout','myaccount','terms'],true))update_option('woocommerce_'.$key.'_page_id',$map[$key]);
        }
        update_option('gc_pages',$map);
        if(!get_option('page_on_front')){update_option('show_on_front','page');update_option('page_on_front',$map['home']);}
        if(!get_option('wp_page_for_privacy_policy'))update_option('wp_page_for_privacy_policy',$map['privacy']);
    }
    public static function lock(string $key,callable $callback): mixed {
        global $wpdb;$name='gc:'.substr(hash('sha256',DB_NAME.':'.$wpdb->prefix.':'.$key),0,56);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$name))!==1)throw new \RuntimeException('resource_busy');
        try{return $callback();}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));}
    }
    public static function audit(string $action,string $object,array $metadata=[]):void{
        global $wpdb;
        self::lock('audit',static function()use($wpdb,$action,$object,$metadata){
            $table=self::table('security_audit');$prev=$wpdb->get_var("SELECT event_hash FROM $table ORDER BY id DESC LIMIT 1")?:str_repeat('0',64);
            $safe=[];foreach($metadata as $key=>$value){if(!preg_match('/token|secret|password|email|phone|address|key|card/i',(string)$key))$safe[$key]=is_scalar($value)?$value:hash('sha256',wp_json_encode($value));}
            $row=['actor'=>get_current_user_id(),'action'=>$action,'object_id'=>$object,'metadata'=>wp_json_encode($safe),'created_at'=>time(),'previous_hash'=>$prev];
            $row['event_hash']=hash('sha256',wp_json_encode($row));$wpdb->insert($table,$row);
        });
    }
}
