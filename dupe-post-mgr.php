<?php
/**
 * Plugin Name: Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 1.1
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
    echo '<form method="post">';
    submit_button('Scan for Duplicates');
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['delete_id'])) {
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
            echo '<h2>Duplicate Posts</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Title</th><th>Slug</th><th>Actions</th></tr></thead><tbody>';

            // Posts with duplicate titles
            foreach ($duplicate_titles as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts}
                     WHERE post_title = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_title
                ));
                if (count($posts) < 2) continue;

                foreach ($posts as $post) {
                    $other_posts = array_filter($posts, fn($p) => $p->ID !== $post->ID);
                    echo '<tr>';
                    echo '<td>' . esc_html($post->post_title) . '</td>';
                    echo '<td>' . esc_html($post->post_name) . '</td>';
                    echo '<td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="delete_id" value="' . esc_attr($post->ID) . '">
                            <select name="redirect_select" style="width:300px;">
                                <option value="">-- Select redirect target --</option>';
                    foreach ($other_posts as $target) {
                        $url = get_permalink($target->ID);
                        echo '<option value="' . esc_url($url) . '">' . esc_html($target->post_title) . '</option>';
                    }
                    echo '</select><br><input type="text" name="redirect_to" placeholder="Or enter custom URL" style="width:300px;margin-top:5px">
                            <button type="submit" class="button button-primary" style="margin-top:5px;">Delete & Redirect</button>
                        </form>
                    </td>';
                    echo '</tr>';
                }
            }

            // Posts with duplicate slugs
            foreach ($duplicate_slugs as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts}
                     WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_name
                ));
                if (count($posts) < 2) continue;

                foreach ($posts as $post) {
                    $other_posts = array_filter($posts, fn($p) => $p->ID !== $post->ID);
                    echo '<tr>';
                    echo '<td>' . esc_html($post->post_title) . '</td>';
                    echo '<td>' . esc_html($post->post_name) . '</td>';
                    echo '<td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="delete_id" value="' . esc_attr($post->ID) . '">
                            <select name="redirect_select" style="width:300px;">
                                <option value="">-- Select redirect target --</option>';
                    foreach ($other_posts as $target) {
                        $url = get_permalink($target->ID);
                        echo '<option value="' . esc_url($url) . '">' . esc_html($target->post_title) . '</option>';
                    }
                    echo '</select><br><input type="text" name="redirect_to" placeholder="Or enter custom URL" style="width:300px;margin-top:5px">
                            <button type="submit" class="button button-primary" style="margin-top:5px;">Delete & Redirect</button>
                        </form>
                    </td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No duplicates found.</p>';
        }
    }

    // Handle deletion and .htaccess rule storing
    if (!empty($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);
        $manual_url = trim($_POST['redirect_to'] ?? '');
        $selected_url = trim($_POST['redirect_select'] ?? '');
        $redirect_to = esc_url_raw($manual_url ?: $selected_url);

        if (!$redirect_to || strpos(@get_headers($redirect_to)[0], '404') !== false) {
            echo '<div class="error"><p>Invalid or missing redirect URL. Action aborted.</p></div>';
        } else {
            $old_slug = get_post_field('post_name', $delete_id);
            wp_trash_post($delete_id); // Use trash instead of permanent delete

            $redirects = get_option('dpm_redirects', []);
            $redirects[] = ['from' => "/$old_slug", 'to' => $redirect_to];
            update_option('dpm_redirects', $redirects);

            echo '<div class="updated"><p>Post moved to trash and redirect rule stored.</p></div>';
        }
    }

    // Display .htaccess rules
    $redirects = get_option('dpm_redirects', []);
    if (!empty($redirects)) {
        echo '<h2>.htaccess Redirect Rules</h2><textarea rows="10" style="width:100%;font-family:monospace;">';
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 {$rule['from']} {$rule['to']}\n";
        }
        echo "# END Post Redirects";
        echo '</textarea>';
    }

    echo '</div>';
}
