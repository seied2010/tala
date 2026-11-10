<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Privacy {
    public static function boot():void{
        add_filter('wp_privacy_personal_data_exporters',static function($list){$list['gold-commerce']=['exporter_friendly_name'=>'Gold Commerce support','callback'=>[self::class,'export']];return $list;});
        add_filter('wp_privacy_personal_data_erasers',static function($list){$list['gold-commerce']=['eraser_friendly_name'=>'Gold Commerce support','callback'=>[self::class,'erase']];return $list;});
    }
    public static function export(string $email,int $page=1):array{
        global $wpdb;$user=get_user_by('email',$email);$data=[];
        if($user){$rows=$wpdb->get_results($wpdb->prepare('SELECT id,order_id,message,kind,status,created_at FROM '.Database::table('support').' WHERE user_id=%d ORDER BY id LIMIT 50 OFFSET %d',$user->ID,max(0,$page-1)*50),ARRAY_A);
            foreach($rows as $row){$fields=[];foreach($row as $key=>$value)$fields[]=['name'=>(string)$key,'value'=>(string)$value];$data[]=['group_id'=>'gold-support','group_label'=>'درخواست‌های پشتیبانی طلا','item_id'=>'gold-support-'.$row['id'],'data'=>$fields];}
        }return ['data'=>$data,'done'=>count($data)<50];
    }
    public static function erase(string $email,int $page=1):array{
        $data=self::export($email,$page);return ['items_removed'=>false,'items_retained'=>!empty($data['data']),'messages'=>$data['data']?['سوابق مالی و درخواست‌های وابسته به سفارش طبق سیاست نگهداری فروشگاه نیازمند بازبینی مدیر هستند.']:[],'done'=>$data['done']];
    }
    public static function cleanup():void{
        global $wpdb;$days=(int)(Plugin::settings()['log_retention_days']??0);if($days<1)return;
        $cut=time()-$days*86400;
        // Remove only delivered/failed notification bodies beyond explicit retention. Never delete receipts or invoices.
        $wpdb->query($wpdb->prepare('UPDATE '.Database::table('notifications')." SET payload='' WHERE state IN ('sent','dead') AND next_try<%d",$cut));
    }
}
