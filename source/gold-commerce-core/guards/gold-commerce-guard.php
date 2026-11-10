<?php
/** Plugin Name: Gold Commerce persistent financial guard */
defined('ABSPATH')||exit;
add_filter('woocommerce_is_purchasable',static function($yes,$product){if(!class_exists('GoldCommerce\\Plugin')&&($product->get_meta('_gc_product')||($product->get_parent_id()&&wc_get_product($product->get_parent_id())?->get_meta('_gc_product'))))return false;return $yes;},999,2);
add_filter('woocommerce_variation_is_purchasable',static function($yes,$product){if(!class_exists('GoldCommerce\\Plugin')&&($product->get_meta('_gc_product')||($product->get_parent_id()&&wc_get_product($product->get_parent_id())?->get_meta('_gc_product'))))return false;return $yes;},999,2);
add_filter('woocommerce_valid_order_statuses_for_payment',static function($statuses,$order){if(!class_exists('GoldCommerce\\Plugin')&&$order->get_meta('_gc_specialized')==='yes')return [];return $statuses;},999,2);
add_action('woocommerce_check_cart_items',static function(){if(!class_exists('GoldCommerce\\Plugin')&&WC()->cart)foreach(WC()->cart->get_cart() as $row)if($row['data']->get_meta('_gc_product')){wc_add_notice('هستهٔ طلا غیرفعال است؛ خرید تخصصی موقتاً متوقف شده است.','error');break;}});
add_action('woocommerce_pre_payment_complete',static function($id){if(!class_exists('GoldCommerce\\Plugin')&&wc_get_order($id)?->get_meta('_gc_specialized')==='yes')throw new Exception('gold_core_inactive_payment_review_required');},1);
