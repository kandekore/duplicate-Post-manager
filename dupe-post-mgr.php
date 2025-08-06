<?php
/**
 * Plugin Name: Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 1.2
 * Author: Darren Kandekore
 * License: GPL2
 * Text Domain: duplicate-post-manager
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('Duplicate Post Manager', 'Duplicate Post Manager', 'manage_options', 'duplicate-post-manager', 'dpm_admin_page', 'dashicons-admin-page', 80);
});

function dpm_admin_page() {
    echo '<div class="wrap"><h1>Duplicate Post Manager</h1>';

    // Bulk action
    if (!empty($_POST['bulk_delete_ids']) && isset($_POST['dpm_bulk_action'])) {
        check_admin_referer('dpm_bulk_action', 'dpm_bulk_action');

        $redirects = get_option('dpm_redirects', []);
        $post_ids_to_delete = array_map('intval', wp_unslash($_POST['bulk_delete_ids']));

        foreach ($post_ids_to_delete as $post_id) {
            $manual = isset($_POST['redirect_manual'][$post_id]) ? trim(wp_unslash($_POST['redirect_manual'][$post_id])) : '';
            $selected = isset($_POST['redirect_select'][$post_id]) ? trim(wp_unslash($_POST['redirect_select'][$post_id])) : '';
            $redirect_to = esc_url_raw($manual ?: $selected);

            // Convert full URL to relative if local
            if (strpos($redirect_to, home_url()) === 0) {
                $redirect_to = wp_make_link_relative($redirect_to);
            }

            // Validate URL
            $headers = @get_headers($redirect_to);
            if (!$redirect_to || strpos($headers[0], '404') !== false) {
                // Escaping the post_id for safe output.
                echo '<div class="error"><p>Invalid or missing redirect for post ID ' . esc_html($post_id) . '. Skipping.</p></div>';
                continue;
            }

            // Save redirect
            $old_slug = get_post_field('post_name', $post_id);
            $redirects[] = ['from' => "/$old_slug", 'to' => $redirect_to];
            wp_trash_post($post_id);
        }
        update_option('dpm_redirects', $redirects);
        echo '<div class="updated"><p>Posts moved to trash and redirects saved.</p></div>';
    }

    // Start duplicate scan
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['bulk_delete_ids'])) {
        global $wpdb;

        // Note: Direct DB queries are used for performance here, which is acceptable in an admin context.
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

            // Group by title
            foreach ($duplicate_titles as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_title
                ));
                if (count($posts) < 2) continue;
                $output_posts[] = $posts;
            }

            // Group by slug
            foreach ($duplicate_slugs as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_name
                ));
                if (count($posts) < 2) continue;
                $output_posts[] = $posts;
            }

            // Flatten and render
            foreach ($output_posts as $group) {
                foreach ($group as $post) {
                    $others = array_filter($group, fn($p) => $p->ID !== $post->ID);
                    echo '<tr>';
                    echo '<td><input type="checkbox" class="dpm-check" name="bulk_delete_ids[]" value="' . esc_attr($post->ID) . '"></td>';
                    echo '<td>' . esc_html($post->post_title) . '</td>';
                    echo '<td>' . esc_html($post->post_name) . '</td>';
                    echo '<td><select name="redirect_select[' . esc_attr($post->ID) . ']"><option value="">-- Select --</option>';
                    foreach ($others as $target) {
                        $url = get_permalink($target->ID);
                        $relative = wp_make_link_relative($url);
                        echo '<option value="' . esc_url($relative) . '">' . esc_html($target->post_name) . '</option>';
                    }
                    echo '</select></td>';
                    echo '<td><input type="text" name="redirect_manual[' . esc_attr($post->ID) . ']" placeholder="https://..." style="width:100%"></td>';
                    echo '</tr>';
                }
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

    // Show .htaccess block
    $redirects = get_option('dpm_redirects', []);
    if (!empty($redirects)) {
        echo '<h2>.htaccess Redirect Rules</h2><textarea readonly rows="10" style="width:100%;font-family:monospace;">';
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            // Escaping the rule data for safe output.
            echo "Redirect 301 " . esc_html($rule['from']) . " " . esc_html($rule['to']) . "\n";
        }
        echo "# END Post Redirects";
        echo '</textarea>';
    }

    echo '</div>';
}