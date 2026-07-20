<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Repositories\VendorRepository;

/**
 * Class VirtualStore
 *
 * Description of administrative platform component VirtualStore.
 *
 * @package vendor-marketplace
 */
class VirtualStore
{
    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public static function init(): void
    {
        global $wp_query;

        // استخدام $wp_query مباشرة لتجنب استدعاء get_query_var() قبل تهيئته
        $vendor_slug = $wp_query->get('vendor_store');
        if (empty($vendor_slug)) {
            return;
        }

        $vendor_repo = Container::getInstance()->make(VendorRepository::class);
        $vendor = $vendor_repo->findBySlug($vendor_slug);

        if (!$vendor || $vendor->status !== 'approved') {
            self::handle404();
            return;
        }

        self::setupVirtualPage($vendor, $vendor_slug);
    }

    /**
     * SetupVirtualPage functionality helper.
     *
     * @param object $vendor Description index.
     * @param string $slug Description index.
     * @return void Output payload.
     */
    private static function setupVirtualPage(object $vendor, string $slug): void
    {
        global $wp_query, $post;

        $dummy_post = new \stdClass();
        $dummy_post->ID = -999;
        $dummy_post->post_author = 1;
        $dummy_post->post_date = current_time('mysql');
        $dummy_post->post_date_gmt = current_time('mysql', 1);
        $dummy_post->post_content = '[vmp_vendor_store slug="' . esc_attr($slug) . '"]';
        $dummy_post->post_title = sprintf(__('متجر %s', 'vmp'), $vendor->store_name);
        $dummy_post->post_status = 'publish';
        $dummy_post->comment_status = 'closed';
        $dummy_post->ping_status = 'closed';
        $dummy_post->post_name = 'vendor-store-' . $slug;
        $dummy_post->post_type = 'page';
        $dummy_post->filter = 'raw';

        $wp_post = new \WP_Post($dummy_post);

        // تعيين جميع متغيرات الاستعلام
        $wp_query->post = $wp_post;
        $wp_query->posts = [$wp_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $wp_post->ID;
        $wp_query->is_page = true;
        $wp_query->is_single = false;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_404 = false;
        $wp_query->max_num_pages = 1;

        $post = $wp_post;
        $GLOBALS['post'] = $wp_post;
        $GLOBALS['wp_the_query'] = $wp_query;

        setup_postdata($wp_post);
        $GLOBALS['vmp_current_vendor'] = $vendor;

        add_filter('the_content', function ($content) use ($slug) {
            if (get_query_var('vendor_store')) {
                return do_shortcode('[vmp_vendor_store slug="' . esc_attr($slug) . '"]');
            }
            return $content;
        }, 10, 1);

        add_action('wp_footer', function () {
            if (get_query_var('vendor_store')) {
                wp_reset_postdata();
            }
        }, 999);
    }

    /**
     * Handle404 functionality helper.
     *
     * @return void Output payload.
     */
    private static function handle404(): void
    {
        global $wp_query, $post;

        $wp_query->set_404();
        status_header(404);

        $dummy_post = new \stdClass();
        $dummy_post->ID = 0;
        $dummy_post->post_type = 'page';
        $dummy_post->post_title = '404';
        $dummy_post->post_status = 'publish';
        $dummy_post->filter = 'raw';

        $wp_post = new \WP_Post($dummy_post);
        $post = $wp_post;
        $GLOBALS['post'] = $wp_post;
        $wp_query->post = $wp_post;
        $wp_query->posts = [$wp_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $wp_post->ID;
        $wp_query->is_404 = true;
        $wp_query->is_page = false;
        $wp_query->is_singular = false;
        $wp_query->max_num_pages = 1;

        setup_postdata($wp_post);
    }
}

namespace VMP;

if (!function_exists('VMP\setup_virtual_store_page')) {
    /**
     * Setup Virtual Store Page functionality helper.
     *
     * @return void Output payload.
     */
    function setup_virtual_store_page(): void
    {
        \VMP\Support\VirtualStore::init();
    }
}