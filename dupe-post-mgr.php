<?php
/**
 * Plugin Name: Kandeshop Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 1.3
 * Author: Darren Kandekore
 * License: GPL2
 * Text Domain: kandeshop-duplicate-post-manager
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('Duplicate Post Manager', 'Duplicate Post Manager', 'manage_options', 'duplicate-post-manager', 'dpm_admin_page', 'dashicons-admin-page', 80);
});

function dpm_admin_page() {
    echo '<div class="wrap"><h1>Duplicate Post Manager</h1>';

    // Handle saving rules to .htaccess
    if (isset($_POST['save_htaccess']) && isset($_POST['dpm_htaccess_nonce'])) {
        check_admin_referer('dpm_save_htaccess_action', 'dpm_htaccess_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to perform this action.');
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once (ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $redirects = get_option('dpm_redirects', []);
        $htaccess_file = get_home_path() . '.htaccess';

        if (!$wp_filesystem->exists($htaccess_file) && !$wp_filesystem->is_writable(get_home_path())) {
             echo '<div class="error"><p>.htaccess file does not exist and the directory is not writable. Please create the file or make the directory writable.</p></div>';
        } elseif ($wp_filesystem->is_writable($htaccess_file) || (!$wp_filesystem->exists($htaccess_file) && $wp_filesystem->is_writable(get_home_path()))) {
            require_once(ABSPATH . 'wp-admin/includes/misc.php');

            $rules = [];
            foreach ($redirects as $rule) {
                $rules[] = "Redirect 301 " . $rule['from'] . " " . $rule['to'];
            }

            $insertion_result = insert_with_markers($htaccess_file, 'Duplicate Post Manager', $rules);

            if ($insertion_result) {
                echo '<div class="updated"><p>.htaccess file updated successfully.</p></div>';
            } else {
                 echo '<div class="error"><p>Could not write to the .htaccess file. Please check file permissions.</p></div>';
            }

        } else {
            echo '<div class="error"><p>.htaccess file is not writable. Please update it manually or check file permissions.</p></div>';
        }
    }


    // Bulk delete action
    if (!empty($_POST['bulk_delete_ids']) && isset($_POST['dpm_bulk_action'])) {
        check_admin_referer('dpm_bulk_action', 'dpm_bulk_action');

        $redirects = get_option('dpm_redirects', []);
        $post_ids_to_delete = array_map('intval', wp_unslash($_POST['bulk_delete_ids']));

        foreach ($post_ids_to_delete as $post_id) {
            $manual = isset($_POST['redirect_manual'][$post_id]) ? sanitize_text_field(wp_unslash($_POST['redirect_manual'][$post_id])) : '';
            $selected = isset($_POST['redirect_select'][$post_id]) ? sanitize_text_field(wp_unslash($_POST['redirect_select'][$post_id])) : '';
            $redirect_to = $manual ?: $selected;

            if (empty($redirect_to)) {
                echo '<div class="error"><p>Missing redirect for post ID ' . esc_html($post_id) . '. Skipping.</p></div>';
                continue;
            }

            // Create a full URL for validation, even if the source is relative
            $validation_url = $redirect_to;
            if (substr($validation_url, 0, 1) === '/') {
                $validation_url = home_url($validation_url);
            }

            $headers = @get_headers(esc_url_raw($validation_url));
            if (!$headers || strpos($headers[0], '404') !== false) {
                echo '<div class="error"><p>Invalid redirect for post ID ' . esc_html($post_id) . '. The URL might be broken. Skipping.</p></div>';
                continue;
            }

            $old_slug = get_post_field('post_name', $post_id);
            $final_redirect_path = esc_url_raw($redirect_to);

            if (strpos($final_redirect_path, home_url()) === 0) {
                $final_redirect_path = wp_make_link_relative($final_redirect_path);
            }
            
            $redirects[] = ['from' => "/$old_slug", 'to' => $final_redirect_path];
            wp_trash_post($post_id);
        }
        update_option('dpm_redirects', $redirects);
        echo '<div class="updated"><p>Posts moved to trash and redirects saved.</p></div>';
    }

    // Scan for duplicates
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['bulk_delete_ids']) && !isset($_POST['save_htaccess'])) {
        global $wpdb;

        $duplicate_titles = $wpdb->get_results("
            SELECT post_title, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post' AND post_status = 'publish'
            GROUP BY post_title
            HAVING count > 1
        ");

        $duplicate_slugs = $wpdb->get_results("
            SELECT post_name, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post' AND post_status = 'publish'
            GROUP BY post_name
            HAVING count > 1
        ");

        if ($duplicate_titles || $duplicate_slugs) {
            echo '<form method="post">';
            wp_nonce_field('dpm_bulk_action', 'dpm_bulk_action');
            echo '<h2>Duplicate Posts</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th><input type="checkbox" onclick="jQuery(\'.dpm-check\').prop(\'checked\', this.checked);"></th><th>Title</th><th>Slug</th><th>Redirect To</th><th>Custom URL</th></tr></thead><tbody>';

            $output_posts = [];
            $processed_ids = [];

            foreach ($duplicate_titles as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_title
                ));
                if (count($posts) < 2) continue;

                foreach($posts as $post) {
                    if (in_array($post->ID, $processed_ids)) continue;
                    $output_posts[$post->ID] = $posts;
                    $processed_ids[] = $post->ID;
                }
            }

            foreach ($duplicate_slugs as $dup) {
                 $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_name
                ));
                if (count($posts) < 2) continue;

                foreach($posts as $post) {
                    if (in_array($post->ID, $processed_ids)) continue;
                    $output_posts[$post->ID] = $posts;
                    $processed_ids[] = $post->ID;
                }
            }

            foreach ($output_posts as $main_post_id => $group) {
                $post = get_post($main_post_id);
                $others = array_filter($group, fn($p) => $p->ID !== $post->ID);
                if(empty($others)) continue;

                echo '<tr>';
                echo '<td><input type="checkbox" class="dpm-check" name="bulk_delete_ids[]" value="' . esc_attr($post->ID) . '"></td>';
                echo '<td>' . esc_html($post->post_title) . '</td>';
                echo '<td>' . esc_html($post->post_name) . '</td>';
                echo '<td><select name="redirect_select[' . esc_attr($post->ID) . ']"><option value="">-- Select --</option>';
                foreach ($others as $target) {
                    $url = get_permalink($target->ID);
                    $relative = wp_make_link_relative($url);
                    echo '<option value="' . esc_attr($relative) . '">' . esc_html($target->post_name) . '</option>';
                }
                echo '</select></td>';
                echo '<td><input type="text" name="redirect_manual[' . esc_attr($post->ID) . ']" placeholder="https://..." style="width:100%"></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<br><button type="submit" class="button button-primary">Delete Selected & Redirect</button>';
            echo '</form>';
        } else {
            echo '<p>No duplicate posts found.</p>';
        }
    } else {
        echo '<form method="post"><button type="submit" class="button button-primary">Scan for Duplicates</button></form>';
    }

    // Show .htaccess rules and save button
    $redirects = get_option('dpm_redirects', []);
    if (!empty($redirects)) {
        echo '<h2>.htaccess Redirect Rules</h2>';
        echo '<p>Copy the rules below, or use the button to save them directly to your .htaccess file.</p>';
        echo '<textarea readonly rows="10" style="width:100%;font-family:monospace;">';
        echo "# BEGIN Duplicate Post Manager\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 " . esc_html($rule['from']) . " " . esc_html($rule['to']) . "\n";
        }
        echo "# END Duplicate Post Manager";
        echo '</textarea>';

        echo '<form method="post" style="margin-top: 10px;">';
        wp_nonce_field('dpm_save_htaccess_action', 'dpm_htaccess_nonce');
        echo '<input type="submit" name="save_htaccess" class="button button-primary" value="Save Rules to .htaccess">';
        echo '</form>';
    }

    echo '</div>';
}