<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Cache {
    public static function boot():void{
        add_action('template_redirect',static function(){
            if(is_front_page()||is_shop()||is_product()||is_product_taxonomy()){
                // Dynamic Gold prices must not outlive a source tick in a page/CDN cache.
                if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();
            }
        },0);
        add_action('gc_rate_changed',static function(){
            $ids=wc_get_products(['limit'=>-1,'status'=>'publish','return'=>'ids']);
            foreach($ids as $id){clean_post_cache($id);if(function_exists('rocket_clean_post'))rocket_clean_post($id);if(function_exists('w3tc_flush_post'))w3tc_flush_post($id);do_action('litespeed_purge_post',$id);}
            if(function_exists('wp_cache_clear_cache'))wp_cache_clear_cache();
            do_action('gc_public_price_cache_purge');
        });
    }
}
