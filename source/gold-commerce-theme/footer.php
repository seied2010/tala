<footer class="gc-footer">
    <div class="gc-footer-top">
        <div><h2><?php bloginfo('name'); ?></h2><p><?php echo esc_html(get_theme_mod('gc_tagline','ارزش ماندگار، انتخاب آگاهانه')); ?></p><p><?php echo esc_html(get_option('gc_contact_text','اطلاعات تماس از بخش تنظیمات فروشگاه تکمیل می‌شود.')); ?></p></div>
        <nav aria-label="اطلاعات فروشگاه"><?php if(has_nav_menu('footer'))wp_nav_menu(['theme_location'=>'footer','container'=>false]);else{echo '<ul>';foreach((array)get_option('gc_pages',[]) as $key=>$id)if(in_array($key,['about','contact','shipping','returns','privacy','terms','faq','tracking'],true))echo '<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></li>';echo '</ul>';} ?></nav>
    </div>
    <div class="gc-footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span><a href="#main-content">بازگشت به بالا ↑</a></div>
</footer>

<div class="gc-floating-tool gc-floating-personalize">
    <button id="gc-personalize-toggle" class="gc-floating-button" type="button" aria-expanded="false" aria-controls="gc-personalize-panel" title="شخصی‌سازی صفحه">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 3v7m0 4v7m8-18v5m0 4v9m8-18v9m0 4v5M1 10h6m2-2h6m2 4h6"/></svg><span class="screen-reader-text">شخصی‌سازی صفحه</span>
    </button>
    <div id="gc-personalize-panel" class="gc-floating-panel" role="dialog" aria-modal="false" aria-labelledby="gc-personalize-title" hidden>
        <div class="gc-panel-head"><h2 id="gc-personalize-title">شخصی‌سازی صفحه</h2><button class="gc-panel-close" type="button" aria-label="بستن">×</button></div>
        <fieldset><legend>حالت نمایش</legend><div class="gc-segmented"><button id="gc-theme-light" type="button" data-theme-value="light" aria-pressed="true">روشن</button><button id="gc-theme-toggle" type="button" data-theme-value="dark" aria-pressed="false">تیره</button></div></fieldset>
        <fieldset><legend>رنگ اصلی</legend><div class="gc-color-options"><button type="button" data-accent="" aria-label="رنگ پیش‌فرض" title="رنگ پیش‌فرض">پیش‌فرض</button><button type="button" data-accent="#8A642F" style="--gc-swatch:#8A642F" aria-label="طلایی"></button><button type="button" data-accent="#315B4A" style="--gc-swatch:#315B4A" aria-label="سبز یشمی"></button><button type="button" data-accent="#6E3445" style="--gc-swatch:#6E3445" aria-label="زرشکی"></button></div></fieldset>
        <fieldset><legend>فونت</legend><div class="gc-segmented"><button type="button" data-font-value="site" aria-pressed="true">فونت سایت</button><button type="button" data-font-value="system" aria-pressed="false">فونت خوانا</button></div></fieldset>
        <fieldset><legend>اندازه متن</legend><div class="gc-size-control"><button id="gc-text-minus" type="button" aria-label="کوچک‌تر">−</button><output id="gc-text-size" aria-live="polite">۱۰۰٪</output><button id="gc-text-plus" type="button" aria-label="بزرگ‌تر">+</button></div></fieldset>
        <button id="gc-personalize-reset" class="gc-reset-button" type="button">بازگشت به تنظیمات پیش‌فرض</button>
    </div>
</div>

<div class="gc-floating-tool gc-floating-access">
    <button id="gc-access-toggle" class="gc-floating-button" type="button" aria-expanded="false" aria-controls="gc-access-panel" title="دسترس‌پذیری">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="4.5" r="2"/><path d="M5 8h14M12 8v6m0 0-3 7m3-7 3 7"/></svg><span class="screen-reader-text">دسترس‌پذیری</span>
    </button>
    <div id="gc-access-panel" class="gc-floating-panel gc-access-panel" role="dialog" aria-modal="false" aria-labelledby="gc-access-title" hidden>
        <div class="gc-panel-head"><h2 id="gc-access-title">دسترس‌پذیری</h2><button class="gc-panel-close" type="button" aria-label="بستن">×</button></div>
        <div class="gc-access-options"><button id="gc-contrast" type="button" data-a11y-value="contrast" aria-pressed="false">کنتراست بالا</button><button type="button" data-a11y-value="spacing" aria-pressed="false">فاصله بیشتر متن</button><button type="button" data-a11y-value="motion" aria-pressed="false">کاهش حرکت</button><button type="button" data-a11y-value="font" aria-pressed="false">فونت خوانا</button></div>
        <button id="gc-access-reset" class="gc-reset-button" type="button">بازنشانی دسترس‌پذیری</button>
    </div>
</div>

<button id="gc-back-to-top" class="gc-back-to-top" type="button" aria-label="رفتن به بالای صفحه" hidden><span aria-hidden="true">↑</span></button>
<?php wp_footer(); ?>
</body></html>
