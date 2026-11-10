<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Catalog {
    public static function boot():void{
        add_action('woocommerce_before_product_object_save',[self::class,'index']);
        add_action('woocommerce_before_shop_loop',[self::class,'filters'],12);
        add_action('woocommerce_product_query',[self::class,'query']);
        add_action('woocommerce_after_shop_loop_item',[self::class,'compareLink'],15);
        add_shortcode('gc_compare',[self::class,'compare']);
        add_action('woocommerce_product_after_variable_attributes',[self::class,'variationFields'],10,3);
        add_action('woocommerce_save_product_variation',[self::class,'variationSave'],10,2);
    }
    public static function index(\WC_Product $p):void{
        $m=Plugin::model($p);if(!$m)return;
        foreach(['purity','net_weight','category'] as $key)if(isset($m[$key]))$p->update_meta_data('_gc_'.$key,(string)$m[$key]);
    }
    public static function filters():void{
        echo '<details class="gc-catalog-filters"><summary>فیلتر و مقایسه محصولات</summary><form method="get"><label>عیار <input name="gc_purity" inputmode="decimal" value="'.esc_attr(sanitize_text_field($_GET['gc_purity']??'')).'"></label><label>حداقل وزن (گرم) <input name="gc_min_weight" inputmode="decimal" value="'.esc_attr(sanitize_text_field($_GET['gc_min_weight']??'')).'"></label><label>حداکثر وزن <input name="gc_max_weight" inputmode="decimal" value="'.esc_attr(sanitize_text_field($_GET['gc_max_weight']??'')).'"></label><label>حداقل قیمت معتبر (ریال) <input name="gc_min_price" inputmode="numeric" value="'.esc_attr(sanitize_text_field($_GET['gc_min_price']??'')).'"></label><label>حداکثر قیمت معتبر (ریال) <input name="gc_max_price" inputmode="numeric" value="'.esc_attr(sanitize_text_field($_GET['gc_max_price']??'')).'"></label><label><input name="gc_in_stock" type="checkbox" value="1" '.checked(!empty($_GET['gc_in_stock']),true,false).'> فقط موجود</label>';
        foreach(['pa_color'=>'رنگ','pa_style'=>'سبک','pa_size'=>'اندازه'] as $tax=>$label)if(taxonomy_exists($tax)){echo '<label>'.esc_html($label).'<select name="gc_attr['.esc_attr($tax).']"><option value="">همه</option>';foreach(get_terms(['taxonomy'=>$tax,'hide_empty'=>true]) as $term)echo '<option value="'.esc_attr($term->slug).'" '.selected(sanitize_title($_GET['gc_attr'][$tax]??''),$term->slug,false).'>'.esc_html($term->name).'</option>';echo '</select></label>';}
        echo '<button>اعمال فیلتر</button></form><p>برای مقایسه، شناسه حداکثر چهار محصول را در صفحه مقایسه وارد کنید.</p></details>';
    }
    public static function query(\WP_Query $query):void{
        $meta=(array)$query->get('meta_query');
        foreach(['gc_purity'=>['purity','='],'gc_min_weight'=>['net_weight','>='],'gc_max_weight'=>['net_weight','<=']] as $param=>[$field,$op])if(isset($_GET[$param])&&$_GET[$param]!==''){
            try{Decimal::parse(wp_unslash($_GET[$param]));$meta[]=['key'=>'_gc_'.$field,'value'=>(string)$_GET[$param],'compare'=>$op,'type'=>'DECIMAL(20,4)'];}catch(\Throwable $e){$query->set('post__in',[0]);}
        }
        if(!empty($_GET['gc_in_stock']))$meta[]=['key'=>'_stock_status','value'=>'instock'];$query->set('meta_query',$meta);
        $tax=(array)$query->get('tax_query');foreach((array)($_GET['gc_attr']??[]) as $name=>$slug)if(in_array($name,['pa_color','pa_style','pa_size'],true)&&taxonomy_exists($name)&&$slug!=='')$tax[]=['taxonomy'=>$name,'field'=>'slug','terms'=>sanitize_title($slug)];$query->set('tax_query',$tax);
        if(!empty($_GET['gc_min_price'])||!empty($_GET['gc_max_price'])){
            try{$min=Decimal::money(wp_unslash($_GET['gc_min_price']??'0'));$max=isset($_GET['gc_max_price'])&&$_GET['gc_max_price']!==''?Decimal::money(wp_unslash($_GET['gc_max_price'])):'9000000000000000';$ids=[];
                foreach(wc_get_products(['status'=>'publish','visibility'=>'visible','limit'=>-1]) as $p){try{$s=Plugin::price($p);if(Decimal::cmp($s['amount_irr'],$min)>=0&&Decimal::cmp($s['amount_irr'],$max)<=0)$ids[]=$p->get_id();}catch(\Throwable $e){}}
                $existing=$query->get('post__in');if($existing)$ids=array_intersect($ids,$existing);$query->set('post__in',$ids?:[0]);
            }catch(\Throwable $e){$query->set('post__in',[0]);}
        }
    }
    public static function compareLink():void{global $product;if($product)echo '<p class="gc-compare-label">شناسه مقایسه: '.esc_html((string)$product->get_id()).'</p>';}
    public static function compare():string{
        $input=explode(',',sanitize_text_field($_GET['gc_compare']??''));
        $ids=array_slice(array_values(array_unique(array_filter(array_map('absint',$input)))),0,4);$products=[];
        foreach($ids as $id){$p=wc_get_product($id);if($p&&$p->get_status()==='publish'&&$p->get_catalog_visibility()!=='hidden')$products[]=$p;}
        ob_start();echo '<form method="get"><label>شناسه محصولات (با کاما، حداکثر چهار مورد) <input name="gc_compare" value="'.esc_attr(implode(',',$ids)).'"></label><button>مقایسه</button></form><div class="gc-compare-table"><table><tbody>';
        foreach(['name'=>'محصول','purity'=>'عیار','net_weight'=>'وزن خالص','category'=>'گروه','price'=>'قیمت معتبر'] as $field=>$label){echo '<tr><th>'.esc_html($label).'</th>';foreach($products as $p){$m=Plugin::model($p);echo '<td>'.($field==='price'?wp_kses_post($p->get_price_html()):esc_html($field==='name'?$p->get_name():(string)($m[$field]??'—'))).'</td>';}echo '</tr>';}echo '</tbody></table></div>';return ob_get_clean();
    }
    public static function variationFields(int $loop,array $data,\WP_Post $variation):void{
        $p=wc_get_product($variation->ID);$m=$p?Plugin::model($p):[];
        foreach(['net_weight'=>'وزن خالص طلا','gross_weight'=>'وزن کل','fixed_price'=>'قیمت ثابت'] as $key=>$label)woocommerce_wp_text_input(['id'=>'gc_variation_'.$key.'_'.$loop,'name'=>'gc_variation['.$loop.']['.$key.']','label'=>$label,'value'=>$m[$key]??'','wrapper_class'=>'form-row form-row-full']);
    }
    public static function variationSave(int $id,int $loop):void{
        if(!current_user_can('edit_post',$id))return;$p=wc_get_product($id);if(!$p||!isset($_POST['gc_variation'][$loop]))return;$m=Plugin::model($p);
        foreach(['net_weight','gross_weight','fixed_price'] as $key){$value=(string)wp_unslash($_POST['gc_variation'][$loop][$key]??'');if($value!==''){Decimal::parse($value);$m[$key]=$value;}}
        $p->update_meta_data('_gc_product',$m);$p->save();Database::audit('variation.updated',(string)$id);
    }
}
