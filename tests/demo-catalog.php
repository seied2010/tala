<?php
/** Integration lifecycle test. Run only on a disposable WordPress + WooCommerce test store. */
if (!defined('ABSPATH')) exit(1);
if (!class_exists('Tala_Demo_Catalog')) throw new RuntimeException('demo_plugin_not_active');

$before = Tala_Demo_Catalog::count();
$preexisting = get_posts(['post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]);
$ids = Tala_Demo_Catalog::import();
if (count($ids) !== 27 || Tala_Demo_Catalog::count() !== 27) throw new RuntimeException('demo_import_count');
$again = Tala_Demo_Catalog::import();
if (count($again) !== 27 || Tala_Demo_Catalog::count() !== 27) throw new RuntimeException('demo_import_not_idempotent');
foreach ($again as $id) {
    $product = wc_get_product($id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) throw new RuntimeException('demo_not_purchasable:' . $id);
    if ($product->get_meta('_tala_demo_catalog') !== Tala_Demo_Catalog::VERSION) throw new RuntimeException('demo_marker_missing:' . $id);
    if ((float)$product->get_price() <= 0) throw new RuntimeException('demo_price_invalid:' . $id);
}
$deleted = Tala_Demo_Catalog::remove();
if (count($deleted) !== 27 || Tala_Demo_Catalog::count() !== 0) throw new RuntimeException('demo_remove_failed');
$attachments = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => '_tala_demo_catalog', 'meta_value' => Tala_Demo_Catalog::VERSION]);
if ($attachments) throw new RuntimeException('demo_attachment_remove_failed');
foreach ($preexisting as $id) if (!get_post((int)$id)) throw new RuntimeException('existing_product_changed:' . $id);
if ($before !== 0) Tala_Demo_Catalog::import();
echo wp_json_encode(['status' => 'PASS', 'imported' => 27, 'idempotent' => true, 'purchasable' => true, 'removed' => 27, 'attachments_removed' => true, 'preexisting_products_preserved' => count($preexisting)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

