<?php
/**
 * Plugin Name: Tala Demo Catalog
 * Description: کاتالوگ نمایشی قابل حذف برای فروشگاه طلا؛ شامل ۲۷ محصول، قیمت و تصاویر ساختگی.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Requires Plugins: woocommerce
 * Author: Tala Project
 * License: GPL-2.0-or-later
 * Text Domain: tala-demo-catalog
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Tala_Demo_Catalog {
    public const VERSION = '1.0.0';
    private const META = '_tala_demo_catalog';
    private const IMAGE_META = '_tala_demo_image_key';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_tala_demo_import', [self::class, 'handleImport']);
        add_action('admin_post_tala_demo_remove', [self::class, 'handleRemove']);
        add_action('admin_notices', [self::class, 'notice']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [self::class, 'links']);
    }

    public static function links(array $links): array {
        if (class_exists('WooCommerce')) {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=tala-demo-catalog')) . '">مدیریت کاتالوگ نمونه</a>');
        }
        return $links;
    }

    public static function menu(): void {
        if (!class_exists('WooCommerce')) return;
        add_submenu_page('woocommerce', 'کاتالوگ نمونه طلا', 'کاتالوگ نمونه طلا', 'manage_woocommerce', 'tala-demo-catalog', [self::class, 'page']);
    }

    public static function notice(): void {
        if (!current_user_can('activate_plugins') || class_exists('WooCommerce')) return;
        echo '<div class="notice notice-error"><p>افزونه Tala Demo Catalog برای کارکردن به WooCommerce فعال نیاز دارد.</p></div>';
    }

    public static function page(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی کافی نیست.');
        $count = self::count();
        $message = isset($_GET['tala_demo_message']) ? sanitize_key(wp_unslash($_GET['tala_demo_message'])) : '';
        echo '<div class="wrap" dir="rtl"><h1>کاتالوگ نمونه طلا</h1>';
        if ($message === 'imported') echo '<div class="notice notice-success"><p>کاتالوگ نمونه با موفقیت وارد یا تکمیل شد.</p></div>';
        if ($message === 'removed') echo '<div class="notice notice-success"><p>محصولات و تصاویر متعلق به کاتالوگ نمونه حذف شدند.</p></div>';
        if ($message === 'failed') echo '<div class="notice notice-error"><p>عملیات کامل نشد؛ گزارش خطای PHP/WooCommerce را بررسی کنید.</p></div>';
        echo '<p>این بسته ۲۷ محصول کاملاً ساختگی در ۹ گروه می‌سازد. قیمت‌ها، وزن‌ها، موجودی و تصاویر صرفاً برای نمایش ظاهر سایت هستند.</p>';
        echo '<p><strong>وضعیت فعلی:</strong> ' . esc_html((string)$count) . ' محصول نمونه شناسایی شد.</p>';
        echo '<p>محصولات مانند کالاهای عادی WooCommerce قابل افزودن به سبد و پرداخت‌اند. این افزونه هیچ درگاه، سفارش یا تنظیم مالی را تغییر نمی‌دهد.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tala_demo_import">';
        wp_nonce_field('tala_demo_import');
        submit_button($count ? 'تکمیل یا بازسازی موارد مفقود' : 'ورود ۲۷ محصول نمونه', 'primary', 'submit', false);
        echo '</form><hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'تمام محصولات نمونه این افزونه حذف شوند؟\')"><input type="hidden" name="action" value="tala_demo_remove">';
        wp_nonce_field('tala_demo_remove');
        submit_button('حذف کامل محصولات نمونه', 'delete', 'submit', false, $count ? [] : ['disabled' => 'disabled']);
        echo '</form><p><small>حذف فقط رکوردهای دارای شناسه داخلی این افزونه را هدف می‌گیرد و محصولات واقعی فروشگاه را تغییر نمی‌دهد.</small></p></div>';
    }

    public static function handleImport(): void {
        self::authorize('tala_demo_import');
        try {
            self::import();
            self::redirect('imported');
        } catch (Throwable $e) {
            error_log('Tala Demo Catalog import: ' . $e->getMessage());
            self::redirect('failed');
        }
    }

    public static function handleRemove(): void {
        self::authorize('tala_demo_remove');
        try {
            self::remove();
            self::redirect('removed');
        } catch (Throwable $e) {
            error_log('Tala Demo Catalog remove: ' . $e->getMessage());
            self::redirect('failed');
        }
    }

    private static function authorize(string $action): void {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی کافی نیست.', '', ['response' => 403]);
        check_admin_referer($action);
        if (!class_exists('WooCommerce') || !class_exists('WC_Product_Simple')) wp_die('WooCommerce فعال نیست.', '', ['response' => 409]);
    }

    private static function redirect(string $message): never {
        wp_safe_redirect(add_query_arg(['page' => 'tala-demo-catalog', 'tala_demo_message' => $message], admin_url('admin.php')));
        exit;
    }

    public static function import(): array {
        if (!class_exists('WC_Product_Simple')) throw new RuntimeException('woocommerce_required');
        @set_time_limit(180);
        $parent = self::term('محصولات نمونه', 'sample-products', 0);
        $tag = self::term('نمونه آزمایشی', 'demo-sample', 0, 'product_tag');
        $images = [];
        $ids = [];
        foreach (self::groups() as $groupKey => $group) {
            $category = self::term($group['category'], 'sample-' . $groupKey, $parent);
            $images[$groupKey] = self::image($groupKey, $group['category']);
            foreach ($group['items'] as $index => $item) {
                $sku = 'TALA-DEMO-' . strtoupper($groupKey) . '-' . sprintf('%02d', $index + 1);
                $existingId = wc_get_product_id_by_sku($sku);
                $product = $existingId ? wc_get_product($existingId) : new WC_Product_Simple();
                if (!$product) throw new RuntimeException('product_load_failed:' . $sku);
                if ($existingId && $product->get_meta(self::META) !== self::VERSION) continue;
                $product->set_name($item['name'] . ' — نمونه');
                $product->set_slug(sanitize_title($sku . '-' . $item['name']));
                $product->set_status('publish');
                if (!$existingId) $product->set_sku($sku);
                $product->set_catalog_visibility('visible');
                $product->set_regular_price($item['price']);
                $product->set_price($item['price']);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($item['stock']);
                $product->set_stock_status('instock');
                $product->set_backorders('no');
                $product->set_sold_individually(false);
                $product->set_virtual(false);
                $product->set_tax_status('none');
                $product->set_category_ids([$parent, $category]);
                $product->set_tag_ids([$tag]);
                $product->set_image_id($images[$groupKey]);
                $product->set_featured($index === 0);
                $product->set_short_description('محصول نمایشی · ' . $item['weight'] . ' گرم · عیار ' . $item['purity'] . ' · قیمت ساختگی');
                $product->set_description('<p><strong>این یک محصول نمونه و اطلاعات آن ساختگی است.</strong></p><p>' . esc_html($group['description']) . '</p><ul><li>وزن تقریبی نمونه: ' . esc_html($item['weight']) . ' گرم</li><li>عیار نمونه: ' . esc_html($item['purity']) . '</li><li>قیمت و موجودی صرفاً برای نمایش و آزمون مسیر فروشگاه است.</li></ul>');
                $product->set_attributes([
                    self::attribute('عیار', [$item['purity'] . ' (نمونه)'], 0),
                    self::attribute('وزن تقریبی', [$item['weight'] . ' گرم (نمونه)'], 1),
                    self::attribute('وضعیت داده', ['آزمایشی و ساختگی'], 2),
                ]);
                // Gold Commerce 1.2 casts missing meta to an array. Persisting an explicit empty
                // model keeps these ordinary WooCommerce demo products outside its gold-pricing path.
                $product->update_meta_data('_gc_product', []);
                $product->update_meta_data(self::META, self::VERSION);
                $product->update_meta_data('_tala_demo_group', $groupKey);
                $product->update_meta_data('_tala_demo_disclaimer', 'تمام مشخصات و قیمت ساختگی است.');
                $product->save();
                $ids[] = $product->get_id();
            }
        }
        update_option('tala_demo_catalog_last_import', ['version' => self::VERSION, 'count' => count($ids), 'time' => time()], false);
        return $ids;
    }

    private static function attribute(string $name, array $options, int $position): WC_Product_Attribute {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name($name);
        $attribute->set_options($options);
        $attribute->set_position($position);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        return $attribute;
    }

    private static function term(string $name, string $slug, int $parent = 0, string $taxonomy = 'product_cat'): int {
        $found = term_exists($slug, $taxonomy);
        if (!$found) {
            $found = wp_insert_term($name, $taxonomy, ['slug' => $slug, 'parent' => $parent, 'description' => 'دسته یا برچسب ساختگی متعلق به افزونه Tala Demo Catalog']);
            if (is_wp_error($found)) throw new RuntimeException($found->get_error_message());
            $id = (int)$found['term_id'];
            update_term_meta($id, self::META, self::VERSION);
            return $id;
        }
        return (int)(is_array($found) ? $found['term_id'] : $found);
    }

    private static function image(string $key, string $title): int {
        $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => self::IMAGE_META, 'meta_value' => $key]);
        if ($found) return (int)$found[0];
        $path = plugin_dir_path(__FILE__) . 'assets/images/' . $key . '.png';
        if (!is_readable($path)) throw new RuntimeException('image_missing:' . $key);
        $upload = wp_upload_bits('tala-demo-' . $key . '.png', null, file_get_contents($path));
        if (!empty($upload['error'])) throw new RuntimeException((string)$upload['error']);
        $attachment = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'تصویر ساختگی ' . $title,
            'post_content' => 'تصویر تولیدشده برای کاتالوگ نمایشی؛ فاقد محصول یا نشان تجاری واقعی.',
            'post_excerpt' => 'تصویر نمونه ' . $title,
            'post_status' => 'inherit',
        ], $upload['file']);
        if (is_wp_error($attachment)) throw new RuntimeException($attachment->get_error_message());
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata((int)$attachment, $upload['file']);
        if ($metadata) wp_update_attachment_metadata((int)$attachment, $metadata);
        update_post_meta((int)$attachment, self::IMAGE_META, $key);
        update_post_meta((int)$attachment, self::META, self::VERSION);
        update_post_meta((int)$attachment, '_wp_attachment_image_alt', 'تصویر نمونه ' . $title . ' با اطلاعات ساختگی');
        return (int)$attachment;
    }

    public static function count(): int {
        $query = new WP_Query(['post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => self::META, 'meta_value' => self::VERSION, 'no_found_rows' => false]);
        return (int)$query->found_posts;
    }

    public static function remove(): array {
        if (!class_exists('WC_Product_Simple')) throw new RuntimeException('woocommerce_required');
        @set_time_limit(180);
        $ids = get_posts(['post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => self::META, 'meta_value' => self::VERSION]);
        $deleted = [];
        foreach ($ids as $id) {
            $product = wc_get_product((int)$id);
            if ($product && $product->get_meta(self::META) === self::VERSION) {
                $product->delete(true);
                $deleted[] = (int)$id;
            }
        }
        $attachments = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => self::META, 'meta_value' => self::VERSION]);
        foreach ($attachments as $id) wp_delete_attachment((int)$id, true);
        $terms = get_terms(['taxonomy' => ['product_cat', 'product_tag'], 'hide_empty' => false, 'meta_key' => self::META, 'meta_value' => self::VERSION]);
        if (!is_wp_error($terms)) foreach ($terms as $term) wp_delete_term((int)$term->term_id, $term->taxonomy);
        delete_option('tala_demo_catalog_last_import');
        return $deleted;
    }

    private static function groups(): array {
        return [
            'ring' => ['category' => 'انگشترهای نمونه', 'description' => 'سه مدل نمایشی انگشتر طلای زرد با طراحی مینیمال و کلاسیک.', 'items' => [
                ['name' => 'انگشتر طلای ماهور', 'weight' => '۲٫۸', 'purity' => '۱۸ عیار', 'price' => '185000000', 'stock' => 4],
                ['name' => 'انگشتر طلای آوین', 'weight' => '۳٫۴', 'purity' => '۱۸ عیار', 'price' => '229000000', 'stock' => 3],
                ['name' => 'انگشتر طلای هلیا', 'weight' => '۴٫۱', 'purity' => '۱۸ عیار', 'price' => '278000000', 'stock' => 2],
            ]],
            'necklace' => ['category' => 'گردنبندهای نمونه', 'description' => 'گردنبندهای ظریف نمایشی با زنجیر طلایی و آویزهای ساده.', 'items' => [
                ['name' => 'گردنبند طلای هلال', 'weight' => '۵٫۲', 'purity' => '۱۸ عیار', 'price' => '358000000', 'stock' => 3],
                ['name' => 'گردنبند طلای روژان', 'weight' => '۶٫۱', 'purity' => '۱۸ عیار', 'price' => '419000000', 'stock' => 2],
                ['name' => 'گردنبند طلای پریماه', 'weight' => '۷٫۳', 'purity' => '۱۸ عیار', 'price' => '498000000', 'stock' => 2],
            ]],
            'bracelet' => ['category' => 'دستبندهای نمونه', 'description' => 'مجموعه نمایشی دستبند و النگوی طلای زرد برای چیدمان فروشگاه.', 'items' => [
                ['name' => 'دستبند طلای تابان', 'weight' => '۶٫۸', 'purity' => '۱۸ عیار', 'price' => '465000000', 'stock' => 3],
                ['name' => 'النگوی طلای مهتاب', 'weight' => '۹٫۵', 'purity' => '۱۸ عیار', 'price' => '648000000', 'stock' => 2],
                ['name' => 'دستبند طلای سرو', 'weight' => '۱۲٫۲', 'purity' => '۱۸ عیار', 'price' => '829000000', 'stock' => 2],
            ]],
            'earrings' => ['category' => 'گوشواره‌های نمونه', 'description' => 'سه جفت گوشواره نمایشی با فرم حلقه‌ای و قطره‌ای.', 'items' => [
                ['name' => 'گوشواره طلای باران', 'weight' => '۳٫۱', 'purity' => '۱۸ عیار', 'price' => '214000000', 'stock' => 4],
                ['name' => 'گوشواره طلای ترنج', 'weight' => '۴٫۳', 'purity' => '۱۸ عیار', 'price' => '294000000', 'stock' => 3],
                ['name' => 'گوشواره طلای پرنیان', 'weight' => '۵٫۶', 'purity' => '۱۸ عیار', 'price' => '382000000', 'stock' => 2],
            ]],
            'gemstone' => ['category' => 'طلای سنگ‌دار نمونه', 'description' => 'زیورآلات نمایشی با نگین سبز مصنوعی؛ ارزش سنگ و طلا واقعی نیست.', 'items' => [
                ['name' => 'انگشتر نگین‌دار زمردین', 'weight' => '۴٫۲', 'purity' => '۱۸ عیار', 'price' => '335000000', 'stock' => 2],
                ['name' => 'آویز نگین‌دار سبزآرا', 'weight' => '۵٫۴', 'purity' => '۱۸ عیار', 'price' => '426000000', 'stock' => 2],
                ['name' => 'نیم‌ست نگین‌دار یشمی', 'weight' => '۸٫۸', 'purity' => '۱۸ عیار', 'price' => '695000000', 'stock' => 1],
            ]],
            'coin' => ['category' => 'سکه‌های نمونه', 'description' => 'سکه‌های کاملاً خیالی و بدون وابستگی به ضرابخانه یا نشان واقعی.', 'items' => [
                ['name' => 'سکه طلای نماد یک‌گرمی', 'weight' => '۱', 'purity' => '۲۴ عیار', 'price' => '98000000', 'stock' => 8],
                ['name' => 'سکه طلای نماد دوگرمی', 'weight' => '۲', 'purity' => '۲۴ عیار', 'price' => '193000000', 'stock' => 6],
                ['name' => 'سکه طلای نماد پنج‌گرمی', 'weight' => '۵', 'purity' => '۲۴ عیار', 'price' => '478000000', 'stock' => 4],
            ]],
            'bullion' => ['category' => 'شمش‌های نمونه', 'description' => 'شمش‌های نمایشی بدون سریال، گواهی یا برند واقعی.', 'items' => [
                ['name' => 'شمش طلای نمایشی ۵ گرمی', 'weight' => '۵', 'purity' => '۲۴ عیار', 'price' => '475000000', 'stock' => 5],
                ['name' => 'شمش طلای نمایشی ۱۰ گرمی', 'weight' => '۱۰', 'purity' => '۲۴ عیار', 'price' => '945000000', 'stock' => 3],
                ['name' => 'شمش طلای نمایشی ۲۰ گرمی', 'weight' => '۲۰', 'purity' => '۲۴ عیار', 'price' => '1880000000', 'stock' => 2],
            ]],
            'usedgold' => ['category' => 'طلای دست‌دوم نمونه', 'description' => 'مدل‌های ساختگی طلای کارکرده؛ وضعیت و قیمت فقط برای نمایش است.', 'items' => [
                ['name' => 'زنجیر طلای دست‌دوم آرا', 'weight' => '۷٫۹', 'purity' => '۱۸ عیار', 'price' => '512000000', 'stock' => 1],
                ['name' => 'دستبند طلای دست‌دوم بافت', 'weight' => '۱۰٫۶', 'purity' => '۱۸ عیار', 'price' => '688000000', 'stock' => 1],
                ['name' => 'گردنبند طلای دست‌دوم کلاسیک', 'weight' => '۱۴٫۲', 'purity' => '۱۸ عیار', 'price' => '918000000', 'stock' => 1],
            ]],
            'custom' => ['category' => 'سفارش‌های اختصاصی نمونه', 'description' => 'پلاک‌های سفارشی خیالی؛ نام‌ها و طرح‌ها به سفارش واقعی مربوط نیستند.', 'items' => [
                ['name' => 'پلاک سفارشی نقش مهر', 'weight' => '۴٫۸', 'purity' => '۱۸ عیار', 'price' => '346000000', 'stock' => 3],
                ['name' => 'پلاک سفارشی خط آفتاب', 'weight' => '۶٫۵', 'purity' => '۱۸ عیار', 'price' => '469000000', 'stock' => 2],
                ['name' => 'گردنبند سفارشی نقش پارسی', 'weight' => '۹٫۲', 'purity' => '۱۸ عیار', 'price' => '668000000', 'stock' => 1],
            ]],
        ];
    }
}

add_action('plugins_loaded', [Tala_Demo_Catalog::class, 'boot']);

