<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Stock {
    public static function reserve(\WC_Order $order,int $seconds):void{
        if(get_option('woocommerce_manage_stock','yes')!=='yes')throw new \DomainException('stock_management_required');
        foreach($order->get_items() as $item){$p=$item->get_product();if(!$p)throw new \DomainException('product_missing');if(Plugin::model($p)&&(!$p->managing_stock()||$p->backorders_allowed()))throw new \DomainException('finite_stock_management_required');}
        // Keep issued quotes in Woo pending status so Woo's native held-stock query counts them.
        if(!$order->has_status('pending')){$order->set_status('pending');$order->save();}
        global $wpdb;$expected=[];$covered=true;
        foreach($order->get_items() as $item){$p=$item->get_product();$id=$p->get_stock_managed_by_id();$expected[$id]=($expected[$id]??0)+(int)$item->get_quantity();}
        foreach($expected as $id=>$qty){
            $held=$wpdb->get_row($wpdb->prepare('SELECT stock_quantity,expires FROM '.$wpdb->prefix.'wc_reserved_stock WHERE order_id=%d AND product_id=%d',$order->get_id(),$id),ARRAY_A);
            $p=wc_get_product($id);
            if(!$held||(int)$held['stock_quantity']!==$qty||strtotime($held['expires'].' UTC')<time()+$seconds||$qty+(int)wc_get_held_stock_quantity($p,$order->get_id())>(int)$p->get_stock_quantity()){$covered=false;break;}
        }
        // Native INSERT ... ON DUPLICATE reports zero affected rows for an unchanged hold.
        // Reuse a sufficient existing hold instead of incorrectly treating that as unavailable stock.
        if($covered&&$expected)return;
        (new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock())->reserve_stock_for_order($order,max(1,intdiv($seconds+59,60)));
    }
    public static function release(\WC_Order $order):void{(new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock())->release_stock_for_order($order);if(Plugin::mode()==='agah_connected')do_action('gc_agah_release',$order);}
}
