<?php
/*
Plugin Name: WP Subdomain Distributor Safe
Plugin URI: 
Description: Safe multisite subdomain distributor without crash.
Version: 3.0
Author: Beny Maylili
*/

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| GET SUBDOMAIN LIST SAFELY
|--------------------------------------------------------------------------
*/

if (!function_exists('wpsd_get_subdomains')) {

    function wpsd_get_subdomains() {

        // Cache ringan
        $cached = get_option('wpsd_cached_subdomains');

        if (!empty($cached) && is_array($cached)) {
            return $cached;
        }

        $subdomains = [];

        // Hanya jalan jika multisite aktif
        if (!is_multisite()) {
            return [];
        }

        // Ambil data site secara aman
        global $wpdb;

        $blogs = $wpdb->get_results("
            SELECT blog_id, domain, path
            FROM {$wpdb->blogs}
            WHERE site_id = 1
            AND spam = 0
            AND deleted = 0
            AND archived = 0
        ");

        if (empty($blogs)) {
            return [];
        }

        foreach ($blogs as $blog) {

            // Hindari site utama
            if ((int)$blog->blog_id === 1) {
                continue;
            }

            $url = 'https://' . $blog->domain;

            $subdomains[] = untrailingslashit($url);
        }

        // Simpan cache
        update_option('wpsd_cached_subdomains', $subdomains);

        return $subdomains;
    }
}

/*
|--------------------------------------------------------------------------
| MANUAL SYNC SUBDOMAIN
|--------------------------------------------------------------------------
*/

function wpsd_sync_subdomains() {

    if (!current_user_can('manage_network')) {
        return;
    }

    delete_option('wpsd_cached_subdomains');

    wpsd_get_subdomains();
}

/*
|--------------------------------------------------------------------------
| GENERATE LINKS
|--------------------------------------------------------------------------
*/

if (!function_exists('wpsd_generate_links')) {

    function wpsd_generate_links($post_id) {

        $url = get_permalink($post_id);

        if (!$url) {
            return '';
        }

        $subs = wpsd_get_subdomains();

        if (empty($subs)) {
            return '';
        }

        $links = [];

        foreach ($subs as $sub) {

            $links[] = esc_url(
                str_replace(
                    untrailingslashit(home_url()),
                    $sub,
                    $url
                )
            );
        }

        return implode("\n", array_unique($links));
    }
}

/*
|--------------------------------------------------------------------------
| META BOX
|--------------------------------------------------------------------------
*/

function wpsd_add_metabox() {

    add_meta_box(
        'wpsd_box',
        'Link Subdomain Terdistribusi',
        'wpsd_metabox_callback',
        'post',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'wpsd_add_metabox');

/*
|--------------------------------------------------------------------------
| METABOX CONTENT
|--------------------------------------------------------------------------
*/

function wpsd_metabox_callback($post) {

    $links = wpsd_generate_links($post->ID);

    ?>

    <textarea
        id="wpsd-links"
        style="width:100%;height:260px;font-size:13px;"
        readonly
    ><?php echo esc_textarea($links); ?></textarea>

    <p style="margin-top:10px;">

        <button
            type="button"
            class="button button-primary"
            onclick="wpsdCopyLinks()"
        >
            Copy Semua Link
        </button>

        <button
            type="button"
            class="button"
            onclick="location.href='<?php echo admin_url('?wpsd_sync=1'); ?>'"
        >
            Sync Subdomain
        </button>

    </p>

    <p>
        Total Link:
        <strong>
            <?php echo count(array_filter(explode("\n", $links))); ?>
        </strong>
    </p>

    <?php
}

/*
|--------------------------------------------------------------------------
| SHORTCODE
|--------------------------------------------------------------------------
*/

function wpsd_shortcode() {

    if (!is_singular()) {
        return '';
    }

    global $post;

    $links = wpsd_generate_links($post->ID);

    ob_start();

    ?>

    <textarea
        id="wpsd-frontend-links"
        style="width:100%;height:260px;"
        readonly
    ><?php echo esc_textarea($links); ?></textarea>

    <p style="margin-top:10px;">

        <button
            type="button"
            onclick="wpsdCopyFrontendLinks()"
        >
            Copy Link
        </button>

    </p>

    <?php

    return ob_get_clean();
}

add_shortcode('subdomain_links', 'wpsd_shortcode');

/*
|--------------------------------------------------------------------------
| HANDLE MANUAL SYNC
|--------------------------------------------------------------------------
*/

function wpsd_handle_sync() {

    if (
        isset($_GET['wpsd_sync']) &&
        $_GET['wpsd_sync'] == 1 &&
        current_user_can('manage_network')
    ) {

        wpsd_sync_subdomains();

        wp_safe_redirect(remove_query_arg('wpsd_sync'));
        exit;
    }
}

add_action('admin_init', 'wpsd_handle_sync');

/*
|--------------------------------------------------------------------------
| SCRIPTS
|--------------------------------------------------------------------------
*/

function wpsd_scripts() {
?>

<script>

function wpsdCopyLinks() {

    const textarea = document.getElementById('wpsd-links');

    if (!textarea) return;

    textarea.select();

    textarea.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(textarea.value)
    .then(() => {

        alert('Semua link berhasil disalin');

    })
    .catch(() => {

        alert('Gagal menyalin link');

    });
}

function wpsdCopyFrontendLinks() {

    const textarea = document.getElementById('wpsd-frontend-links');

    if (!textarea) return;

    textarea.select();

    textarea.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(textarea.value)
    .then(() => {

        alert('Link berhasil disalin');

    })
    .catch(() => {

        alert('Gagal menyalin link');

    });
}

</script>

<?php
}

add_action('admin_footer', 'wpsd_scripts');
add_action('wp_footer', 'wpsd_scripts');
