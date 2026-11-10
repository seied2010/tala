<?php
declare(strict_types=1);
namespace GoldCommerce;
final class ClassicCheckout {
    public static function configure(bool $approved):void{
        if(!$approved||!current_user_can('gc_manage_settings'))throw new \DomainException('explicit_admin_approval_required');
        $backup=get_option('gc_checkout_page_backup',[]);
        foreach(['cart'=>'[woocommerce_cart]','checkout'=>'[woocommerce_checkout]'] as $key=>$shortcode){
            $id=wc_get_page_id($key);$page=get_post($id);if(!$page)throw new \DomainException('checkout_page_missing');
            if(!isset($backup[$id]))$backup[$id]=['content'=>$page->post_content,'at'=>time()];
            wp_update_post(['ID'=>$id,'post_content'=>$shortcode]);
        }
        update_option('gc_checkout_page_backup',$backup,false);Database::audit('checkout.classic_configured','store');
    }
    public static function guard(mixed $result,\WP_REST_Server $server,\WP_REST_Request $request):mixed{
        if($request->get_method()==='POST'&&preg_match('#^/wc/store/v\d+/checkout#',$request->get_route())&&WC()->cart){
            foreach(WC()->cart->get_cart() as $row)if(Plugin::model($row['data']))return new \WP_Error('gold_classic_checkout_required','برای خرید طلا از تسویه کلاسیک فروشگاه استفاده کنید.',['status'=>409]);
        }return $result;
    }
}
