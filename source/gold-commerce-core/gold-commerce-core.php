<?php
/**
 * Plugin Name: Gold Commerce Core
 * Description: هسته مستقل فروش طلا، نرخ، مظنه و گزارش ووکامرس.
 * Version: 1.2.0
 * Requires PHP: 8.3
 * Requires at least: 6.8
 * Requires Plugins: woocommerce
 * Text Domain: gold-commerce
 * License: GPL-2.0-or-later
 */
declare(strict_types=1);
defined('ABSPATH') || exit;
define('GC_VERSION','1.2.0');
define('GC_FILE',__FILE__);
spl_autoload_register(static function(string $class):void{
    $prefix='GoldCommerce\\';
    if(str_starts_with($class,$prefix)){
        $part=substr($class,strlen($prefix));
        if(preg_match('/^[A-Za-z]+$/D',$part)&&is_file(__DIR__.'/src/'.$part.'.php'))require __DIR__.'/src/'.$part.'.php';
    }
});
register_activation_hook(__FILE__,[\GoldCommerce\Database::class,'install']);
add_action('before_woocommerce_init',static function(){
    if(class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)){
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks',__FILE__,false);
    }
});
add_action('plugins_loaded',static function(){
    if(class_exists('WooCommerce'))\GoldCommerce\Plugin::boot();
    else add_action('admin_notices',static function(){echo '<div class="notice notice-error"><p>Gold Commerce به WooCommerce نیاز دارد.</p></div>';});
});
