<?php
/**
 * Plugin Name: Gold Agah Connector
 * Description: اتصال اختیاری آگاه؛ قرارداد پیشنهادی و آزمون MOCK_ONLY، بدون اتصال عملیاتی به alpha12.
 * Version: 1.1.0
 * Requires PHP: 8.3
 * Requires Plugins: gold-commerce-core, woocommerce
 * License: GPL-2.0-or-later
 */
declare(strict_types=1);
defined('ABSPATH')||exit;
spl_autoload_register(static function(string $class){$prefix='GoldAgah\\';if(str_starts_with($class,$prefix)){$part=substr($class,strlen($prefix));if(preg_match('/^[A-Za-z]+$/D',$part)&&is_file(__DIR__.'/src/'.$part.'.php'))require __DIR__.'/src/'.$part.'.php';}});
add_action('plugins_loaded',static function(){if(class_exists(\GoldCommerce\Plugin::class))\GoldAgah\Connector::boot();else add_action('admin_notices',static function(){echo '<div class="notice notice-error"><p>اتصال آگاه به Gold Commerce Core نیاز دارد.</p></div>';});},20);
register_deactivation_hook(__FILE__,static function(){if(get_option('gc_mode','standalone')!=='standalone')update_option('gc_mode','agah_degraded');});
