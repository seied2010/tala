<?php
declare(strict_types=1);
namespace GoldCommerce;

/**
 * بخش اختصاصی طلا برای افزونهٔ «اتصال آگاه». بدون آن افزونه هیچ اثری ندارد.
 * - gc_mode با حالت اتصال یکی می‌شود؛ اگر افزونه از کار بیفتد gc_mode متصل می‌ماند و قیمت‌گذاری و پیش‌فاکتور بسته می‌شود (نگهبان).
 * - وزن، عیار، اجرت، سود و اجزای دیگر مدل قیمت از مشخصات کالای آگاه خوانده می‌شود؛ موتور قیمت همین افزونه مبلغ نهایی را حساب می‌کند.
 * - صدور پیش‌فاکتور در آگاه رزرو می‌کند و انقضا یا شکست آن رزرو را آزاد می‌کند.
 * - در حالت متصل واحد نمایش از آگاه خوانده می‌شود.
 */
final class AgahAdapter {
    /** کلیدهای مجاز مدل قیمت که از آگاه پذیرفته می‌شوند؛ مقدارها رشته‌اند و موتور قیمت دوباره اعتبارسنجی‌شان می‌کند. */
    public const MODEL_KEYS=['category','price_type','net_weight','gross_weight','stone_weight','purity','stone_value','services','fixed_price','making_mode','making_value','profit_mode','profit_value','supply_status'];

    public static function boot():void{
        add_action('agah_connect_mode',static function(string $mode):void{update_option('gc_mode',$mode==='connected'?'agah_connected':'standalone',false);Database::audit('authority.changed','store',['mode'=>$mode]);});
        add_filter('gc_agah_ready',static fn():bool=>class_exists('Agah_Connect')&&\Agah_Connect::connected()&&\Agah_Connect::ready());
        add_filter('gc_agah_status',static fn():string=>!class_exists('Agah_Connect')?'NOT_INSTALLED':(\Agah_Connect::ready()?'CONNECTED':'UNREACHABLE'));
        add_filter('gc_settings',static function(array $s):array{if(Plugin::mode()==='agah_connected'&&class_exists('Agah_Connect'))$s['display_currency']=\Agah_Connect::display_unit()==='rial'?'IRR':'IRT';return $s;});
        add_action('gc_agah_reserve',static function(\WC_Order $order):void{if(class_exists('Agah_Connect_Orders'))\Agah_Connect_Orders::reserve($order,false);});
        add_action('gc_agah_release',static function(\WC_Order $order):void{if(class_exists('Agah_Connect_Orders'))\Agah_Connect_Orders::release($order);});
        add_action('agah_connect_apply_item',[self::class,'applyItem'],10,2);
        add_action('admin_notices',static function():void{
            if(class_exists('GoldAgah\\Connector')&&class_exists('Agah_Connect')&&current_user_can('manage_woocommerce'))echo '<div class="notice notice-error"><p>افزونهٔ آزمایشی «Gold Agah Connector» (شبیه‌ساز) را غیرفعال کنید؛ اتصال واقعی با افزونهٔ «اتصال آگاه» انجام می‌شود.</p></div>';
        });
    }

    /** مشخصات کالای آگاه روی مدل قیمت محصول نوشته می‌شود؛ فقط کلیدهای مجاز و فقط محصول طلا (دارای مدل یا نوع قیمت). */
    public static function applyItem(\WC_Product $product,array $item):void{
        $attrs=is_array($item['attrs']??null)?$item['attrs']:[];
        $values=array_intersect_key($attrs,array_flip(self::MODEL_KEYS));
        if(!$values)return;
        $model=Plugin::model($product);
        foreach($values as $key=>$value)$model[$key]=(string)$value;
        $product->update_meta_data('_gc_product',$model);
        $product->save();
    }
}
