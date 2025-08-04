<?php
/**
 * Plugin Name: Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 4.0
 * Author: Darren Kandekore
 * Author URI: https://github.com/dkandekore    
 * Text Domain: duplicate-post-manager
 * @package DuplicatePostManager
 * @since 2.0
 */

if (!defined('ABSPATH')) exit;

// Enqueue admin JS
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_duplicate-post-manager') {
        wp_enqueue_script('dpm-js', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], null, true);
    }
});

// Admin menu
add_action('admin_menu', function() {
    add_menu_page('Duplicate Post Manager', 'Duplicate Post Manager', 'manage_options', 'duplicate-post-manager', 'dpm_admin_page', 'dashicons-admin-page', 80);
});

// Validate URL (not 404)
function dpm_validate_url($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '404') === false;
}

function dpm_admin_page() {
    echo '<div class="wrap"><h1>Duplicate Post Manager</h1>';

    global $wpdb;
    $post_type = 'post';
    $duplicates = [];

    // Find duplicates by title or slug
    $duplicate_titles = $wpdb->get_results("SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' GROUP BY post_title HAVING COUNT(*) > 1");
    $duplicate_slugs = $wpdb->get_results("SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' GROUP BY post_name HAVING COUNT(*) > 1");

    $grouped = [];

    foreach ($duplicate_titles as $dup) {
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'title' => $dup->post_title]);
        if (count($posts) > 1) $grouped[] = $posts;
    }

    foreach ($duplicate_slugs as $dup) {
        $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish'", $dup->post_name));
        if (count($posts) > 1) $grouped[] = $posts;
    }

    if (!empty($grouped)) {
        echo '<form method="post">';
        wp_nonce_field('dpm_bulk_action', 'dpm_nonce');
        echo '<table class="widefat fixed striped"><thead><tr><th>Delete</th><th>Title</th><th>Slug</th><th>Redirect To</th><th>Custom URL</th></tr></thead><tbody>';

        foreach ($grouped as $group) {
            foreach ($group as $post) {
                $others = array_filter($group, fn($p) => $p->ID !== $post->ID);
                echo '<tr>';
                echo '<td><input type="checkbox" name="delete_ids[]" value="' . esc_attr($post->ID) . '"></td>';
                echo '<td>' . esc_html($post->post_title) . '</td>';
                echo '<td>' . esc_html($post->post_name) . '</td>';
                echo '<td><select name="redirect_select[' . esc_attr($post->ID) . ']"><option value="">-- Select --</option>';
                foreach ($others as $target) {
                    $url = get_permalink($target->ID);
                    echo '<option value="' . esc_url($url) . '">' . esc_html($target->post_title) . '</option>';
                }
                echo '</select></td>';
                echo '<td><input type="text" name="redirect_manual[' . esc_attr($post->ID) . ']" placeholder="https://..." style="width:100%"></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<br><label><input type="checkbox" name="delete_permanently" value="1"> Delete Permanently</label>';
        echo '<br><br><button type="submit" class="button button-primary" name="process_redirects">Delete Selected & Redirect</button>';
        echo '</form>';
    } else {
        echo '<p>No duplicate posts found.</p>';
    }

    // Handle form submission
    if (!empty($_POST['process_redirects'])) {
        if (!isset($_POST['dpm_nonce']) || !wp_verify_nonce($_POST['dpm_nonce'], 'dpm_bulk_action')) {
            wp_die('Security check failed');
        }

        $redirects = get_option('dpm_redirects', []);
        $ids = $_POST['delete_ids'] ?? [];

        foreach ($ids as $id) {
            $id = intval($id);
            $manual = trim($_POST['redirect_manual'][$id] ?? '');
            $select = trim($_POST['redirect_select'][$id] ?? '');
            $redirect_to = esc_url_raw($manual ?: $select);

            if (!$redirect_to || !dpm_validate_url($redirect_to)) {
                echo '<div class="error"><p>Invalid or missing redirect for post ID ' . $id . '</p></div>';
                continue;
            }

            $slug = get_post_field('post_name', $id);
            $redirects[] = ['from' => "/$slug", 'to' => $redirect_to];

            if (!empty($_POST['delete_permanently'])) {
                wp_delete_post($id, true);
            } else {
                wp_trash_post($id);
            }
        }

        update_option('dpm_redirects', $redirects);
        echo '<div class="updated"><p>Redirects saved and posts deleted/trashed.</p></div>';
    }

    // Show .htaccess output
    $redirects = get_option('dpm_redirects', []);
    if (!empty($redirects)) {
        echo '<h2>.htaccess Redirect Rules</h2><textarea rows="10" style="width:100%;font-family:monospace;">';
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 {$rule['from']} {$rule['to']}\n";
        }
        echo "# END Post Redirects";
        echo '</textarea>';
        echo '<form method="post"><button type="submit" name="download_htaccess" class="button">Download .htaccess</button></form>';
    }

    if (!empty($_POST['download_htaccess'])) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename=\"redirects.htaccess\"');
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 {$rule['from']} {$rule['to']}\n";
        }
        echo "# END Post Redirects";
        exit;
    }

    echo '</div>';
}
📁 New File: assets/admin.js
This will enhance UI behavior (optional but helpful):

js
Copy
Edit
jQuery(document).ready(function($) {
    $('input[name^=\"redirect_manual\"]').on('input', function() {
        let row = $(this).closest('tr');
        if ($(this).val().trim() !== '') {
            row.find('select[name^=\"redirect_select\"]').prop('disabled', true);
        } else {
            row.find('select[name^=\"redirect_select\"]').prop('disabled', false);
        }
    });
});