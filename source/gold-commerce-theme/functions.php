<?php
declare(strict_types=1);
defined('ABSPATH')||exit;
add_filter('locale',static fn($locale)=>get_theme_mod('gc_default_persian',true)?'fa_IR':$locale);
add_action('after_setup_theme',static function(){
    global $wp_locale;if(str_starts_with(get_locale(),'fa'))$wp_locale->text_direction='rtl';
    load_theme_textdomain('gold-commerce',get_template_directory().'/languages');
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('custom-logo',['height'=>120,'width'=>320,'flex-height'=>true,'flex-width'=>true]);
    add_theme_support('woocommerce');add_theme_support('wc-product-gallery-zoom');add_theme_support('wc-product-gallery-lightbox');add_theme_support('wc-product-gallery-slider');add_theme_support('responsive-embeds');add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    register_nav_menus(['primary'=>'منوی اصلی','footer'=>'منوی پایین']);
    add_image_size('gc-card',600,720,true);
});
add_filter('woocommerce_placeholder_img_src',static fn($src)=>get_template_directory_uri().'/assets/jewel.svg');
// Apply the visitor's saved appearance before styles render. New visitors always start in light mode.
add_action('wp_head',static function(){
    echo '<script id="gc-visitor-init">(function(){var r=document.documentElement,p={};try{p=JSON.parse(localStorage.getItem("gc-personalize")||"{}")||{};if(!p.theme){p.theme=localStorage.getItem("gc-theme")||"light";}}catch(e){p={theme:"light"};}r.dataset.theme=p.theme==="dark"?"dark":"light";if(/^#[0-9A-F]{6}$/i.test(p.accent||"")){r.style.setProperty("--accent",p.accent);}if(p.font==="system"){r.classList.add("gc-system-font");}if(p.size>=90&&p.size<=130&&p.size!==100){r.style.fontSize=p.size+"%";}try{var a=JSON.parse(localStorage.getItem("gc-a11y")||"[]");if(Array.isArray(a)&&a.length){r.dataset.a11y=a.join(" ");}}catch(e){}})();</script>';
},1);
add_action('wp_enqueue_scripts',static function(){
    $uri=get_template_directory_uri();$version=wp_get_theme()->get('Version');wp_enqueue_style('gc-theme',$uri.'/assets/theme.css',[],$version);wp_enqueue_style('gc-a11y',$uri.'/assets/accessibility.css',['gc-theme'],$version);wp_enqueue_script('gc-theme',$uri.'/assets/theme.js',[],$version,true);
    $accent=sanitize_hex_color(get_theme_mod('gc_accent','#a77735'))?:'#a77735';$background=sanitize_hex_color(get_theme_mod('gc_background','#faf8f3'))?:'#faf8f3';
    wp_add_inline_style('gc-theme',':root{--brand-accent:'.$accent.';--brand-paper:'.$background.'}');
    $font=get_theme_mod('gc_font_id',0);if($font&&get_post_mime_type((int)$font)==='font/woff2')wp_add_inline_style('gc-theme','@font-face{font-family:GoldLocal;src:url("'.esc_url(wp_get_attachment_url((int)$font)).'") format("woff2");font-display:swap}body{font-family:GoldLocal,Tahoma,sans-serif}');
});
add_filter('upload_mimes',static function($mimes){if(current_user_can('manage_options'))$mimes['woff2']='font/woff2';return $mimes;});
add_action('customize_register',static function($c){
    $c->add_panel('gc_design',['title'=>'طراحی فروشگاه طلا','priority'=>30]);
    $c->add_section('gc_identity',['title'=>'هویت و رنگ','panel'=>'gc_design']);
    $c->add_setting('gc_default_persian',['default'=>true,'sanitize_callback'=>static fn($v)=>(bool)$v]);$c->add_control('gc_default_persian',['label'=>'فارسی و راست‌به‌چپ پیش‌فرض','type'=>'checkbox','section'=>'gc_identity']);
    foreach(['gc_accent'=>['رنگ تأکیدی','#a77735'],'gc_background'=>['رنگ زمینه','#faf8f3']] as $id=>[$label,$default]){$c->add_setting($id,['default'=>$default,'sanitize_callback'=>'sanitize_hex_color','transport'=>'postMessage']);$c->add_control(new WP_Customize_Color_Control($c,$id,['label'=>$label,'section'=>'gc_identity']));}
    $c->add_setting('gc_font_id',['default'=>0,'sanitize_callback'=>'absint']);$c->add_control(new WP_Customize_Media_Control($c,'gc_font_id',['label'=>'فونت فارسی WOFF2 مجاز','section'=>'gc_identity','mime_type'=>'font/woff2']));
    $c->add_setting('gc_tagline',['default'=>'ارزش ماندگار، انتخاب آگاهانه','sanitize_callback'=>'sanitize_text_field']);$c->add_control('gc_tagline',['label'=>'شعار برند','section'=>'gc_identity']);
    $c->add_section('gc_hero',['title'=>'بخش آغازین','panel'=>'gc_design']);
    foreach(['gc_hero_title'=>['عنوان','زیبایی، در جزئیات ماندگار است.'],'gc_hero_text'=>['توضیح','مجموعه‌ای برای لحظه‌های ارزشمند شما؛ با جزئیات روشن قیمت و انتخابی مطمئن.'],'gc_hero_label'=>['برچسب','مجموعه منتخب']] as $id=>[$label,$default]){$c->add_setting($id,['default'=>$default,'sanitize_callback'=>'sanitize_text_field','transport'=>'postMessage']);$c->add_control($id,['label'=>$label,'section'=>'gc_hero','type'=>'textarea']);}
    $c->add_setting('gc_hero_image',['sanitize_callback'=>'absint']);$c->add_control(new WP_Customize_Media_Control($c,'gc_hero_image',['label'=>'تصویر اصلی','section'=>'gc_hero','mime_type'=>'image']));
    foreach(gc_modules() as $id=>$label){
        $c->add_section('gc_module_'.$id,['title'=>$label,'panel'=>'gc_design']);
        foreach(['enabled'=>['نمایش','checkbox',true],'count'=>['تعداد','number',4],'position'=>['ترتیب','number',array_search($id,array_keys(gc_modules()),true)*10],'title'=>['عنوان','text',$label],'layout'=>['چیدمان','select','grid']] as $field=>[$title,$type,$default]){
            $key='gc_'.$id.'_'.$field;$sanitize=$type==='checkbox'?static fn($v)=>(bool)$v:($type==='number'?static fn($v)=>min(30,max(0,absint($v))):($field==='layout'?static fn($v)=>in_array($v,['grid','slider'],true)?$v:'grid':'sanitize_text_field'));
            $c->add_setting($key,['default'=>$default,'sanitize_callback'=>$sanitize,'transport'=>$field==='position'?'refresh':'postMessage']);$args=['label'=>$title,'type'=>$type,'section'=>'gc_module_'.$id];if($type==='select')$args['choices']=['grid'=>'شبکه‌ای','slider'=>'ردیف پیمایشی'];$c->add_control($key,$args);
        }
        if(isset($c->selective_refresh))$c->selective_refresh->add_partial('gc_module_'.$id,['settings'=>array_map(static fn($f)=>'gc_'.$id.'_'.$f,['enabled','count','title','layout']),'selector'=>'#gc-module-'.$id,'container_inclusive'=>true,'render_callback'=>static fn()=>gc_render_module($id)]);
    }
    if(isset($c->selective_refresh))$c->selective_refresh->add_partial('gc_hero_title',['selector'=>'.gc-hero-copy','settings'=>['gc_hero_title','gc_hero_text','gc_hero_label'],'render_callback'=>'gc_hero_copy']);
});
add_action('customize_preview_init',static function(){wp_enqueue_script('gc-preview',get_template_directory_uri().'/assets/customizer.js',['customize-preview'],wp_get_theme()->get('Version'),true);});
function gc_modules():array{return ['categories'=>'دسته‌بندی‌ها','new'=>'تازه‌های مجموعه','bestsellers'=>'انتخاب‌های محبوب','offers'=>'پیشنهادهای ویژه','trust'=>'با اطمینان انتخاب کنید','journal'=>'مجله و راهنمای خرید'];}
function gc_shop_url():string{return function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/');}
function gc_hero_copy():void{echo '<p class="gc-eyebrow">'.esc_html(get_theme_mod('gc_hero_label','مجموعه منتخب')).'</p><h1>'.esc_html(get_theme_mod('gc_hero_title','زیبایی، در جزئیات ماندگار است.')).'</h1><p class="gc-hero-description">'.esc_html(get_theme_mod('gc_hero_text','مجموعه‌ای برای لحظه‌های ارزشمند شما؛ با جزئیات روشن قیمت و انتخابی مطمئن.')).'</p><a class="gc-button" href="'.esc_url(gc_shop_url()).'">تماشای مجموعه <span aria-hidden="true">↗</span></a>';}
function gc_render_module(string $id):void{
    $modules=gc_modules();if(!isset($modules[$id]))return;
    $enabled=get_theme_mod('gc_'.$id.'_enabled',true);$count=min(24,max(1,(int)get_theme_mod('gc_'.$id.'_count',4)));$layout=get_theme_mod('gc_'.$id.'_layout','grid')==='slider'?'slider':'grid';
    echo '<section id="gc-module-'.esc_attr($id).'" class="gc-section '.(!$enabled?'gc-hidden':'').'"'.(!$enabled?' hidden':'').'><header class="gc-section-heading"><div><p class="gc-eyebrow">'.esc_html(get_bloginfo('name')).'</p><h2>'.esc_html(get_theme_mod('gc_'.$id.'_title',$modules[$id])).'</h2></div><a href="'.esc_url($id==='journal'?get_post_type_archive_link('post')?:home_url('/?s='):gc_shop_url()).'">مشاهده همه <span aria-hidden="true">←</span></a></header>';
    if($id==='trust'){
        echo '<div class="gc-trust-grid"><article><span aria-hidden="true">◇</span><h3>قیمت شفاف</h3><p>جزئیات اصل طلا، اجرت و مالیات پیش از پرداخت.</p></article><article><span aria-hidden="true">↔</span><h3>تحویل قابل پیگیری</h3><p>روش‌های تحویل و شرایط فروش را پیش از خرید ببینید.</p></article><article><span aria-hidden="true">✦</span><h3>همراهی در انتخاب</h3><p>برای انتخاب وزن، اندازه و مدل دلخواه استعلام بگیرید.</p></article></div>';
    }elseif($id==='categories'&&taxonomy_exists('product_cat')){
        $terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>true,'number'=>$count]);echo '<div class="gc-categories gc-'.$layout.'">';if(!is_wp_error($terms))foreach($terms as $term)echo '<a href="'.esc_url(get_term_link($term)).'"><span aria-hidden="true">◇</span><h3>'.esc_html($term->name).'</h3><span>'.esc_html((string)$term->count).' انتخاب</span></a>';echo '</div>';
    }elseif($id==='journal'){
        $query=new WP_Query(['post_type'=>'post','posts_per_page'=>$count,'ignore_sticky_posts'=>true]);echo '<div class="gc-journal-grid">';while($query->have_posts()){$query->the_post();echo '<article>';if(has_post_thumbnail())the_post_thumbnail('medium_large',['loading'=>'lazy']);echo '<p class="gc-eyebrow">'.esc_html(get_the_date()).'</p><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><p>'.esc_html(wp_trim_words(get_the_excerpt(),24)).'</p></article>';}wp_reset_postdata();echo '</div>';
    }elseif(function_exists('wc_get_products')){
        $args=['status'=>'publish','visibility'=>'visible','limit'=>$count,'orderby'=>$id==='bestsellers'?'popularity':'date','order'=>'DESC'];if($id==='offers')$args['include']=wc_get_product_ids_on_sale();
        $products=($id==='offers'&&empty($args['include']))?[]:wc_get_products($args);echo '<div class="gc-product-grid gc-'.$layout.'">';
        foreach($products as $product){echo '<article class="gc-product-card"><a class="gc-product-image" href="'.esc_url($product->get_permalink()).'">';echo $product->get_image('gc-card',['loading'=>'lazy','alt'=>$product->get_name()]);echo '</a><div class="gc-product-info"><span class="gc-eyebrow">'.esc_html($product->get_sku()).'</span><h3><a href="'.esc_url($product->get_permalink()).'">'.esc_html($product->get_name()).'</a></h3><div>'.wp_kses_post($product->get_price_html()).'</div></div></article>';}
        if(!$products)echo '<p>محصولات این بخش به‌زودی توسط فروشگاه تکمیل می‌شوند.</p>';echo '</div>';
    }echo '</section>';
}
// Nonsecret brand portability: explicitly invoked by an authorized administrator.
add_action('admin_post_gc_design_export',static function(){if(!current_user_can('edit_theme_options'))wp_die('forbidden',403);check_admin_referer('gc_design');$mods=array_filter(get_theme_mods()?:[],static fn($k)=>str_starts_with((string)$k,'gc_'),ARRAY_FILTER_USE_KEY);unset($mods['gc_font_id'],$mods['gc_hero_image']);nocache_headers();header('Content-Type: application/json');header('Content-Disposition: attachment; filename="gold-design.json"');echo wp_json_encode(['schema_version'=>'1','design'=>$mods],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;});
add_action('admin_menu',static function(){add_theme_page('انتقال طراحی','انتقال طراحی','edit_theme_options','gc-design',static function(){echo '<div class="wrap"><h1>انتقال تنظیمات طراحی</h1><p>کلیدهای API و اطلاعات مشتری در این خروجی وجود ندارند. تصاویر و فونت جداگانه منتقل می‌شوند.</p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=gc_design_export'),'gc_design')).'">دریافت JSON</a><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="gc_design_import">';wp_nonce_field('gc_design');echo '<input type="file" name="design" accept="application/json" required><button>درون‌ریزی طراحی</button></form></div>';});});
add_action('admin_post_gc_design_import',static function(){if(!current_user_can('edit_theme_options'))wp_die('forbidden',403);check_admin_referer('gc_design');$f=$_FILES['design']??[];if(($f['error']??1)!==0||($f['size']??0)>32768||!is_uploaded_file($f['tmp_name']??''))wp_die('invalid_upload');$data=json_decode(file_get_contents($f['tmp_name']),true);if(($data['schema_version']??'')!=='1')wp_die('invalid_schema');foreach((array)($data['design']??[]) as $key=>$value){if(in_array($key,['gc_accent','gc_background'],true))set_theme_mod($key,sanitize_hex_color((string)$value));elseif(preg_match('/^gc_(categories|new|bestsellers|offers|trust|journal)_(enabled|count|position|title|layout)$/D',$key,$m)){set_theme_mod($key,match($m[2]){'enabled'=>(bool)$value,'count','position'=>min(30,max(0,absint($value))),'layout'=>in_array($value,['grid','slider'],true)?$value:'grid',default=>sanitize_text_field((string)$value)});}elseif(in_array($key,['gc_tagline','gc_hero_title','gc_hero_text','gc_hero_label'],true))set_theme_mod($key,sanitize_text_field((string)$value));}wp_safe_redirect(admin_url('themes.php?page=gc-design'));exit;});
