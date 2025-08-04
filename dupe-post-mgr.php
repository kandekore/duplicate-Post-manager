<?php
/**
 * Plugin Name: Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Duplicate Post Manager',
        'Duplicate Post Manager',
        'manage_options',
        'duplicate-post-manager',
        'dpm_admin_page',
        'dashicons-admin-page',
        80
    );
});

// Admin page callback
function dpm_admin_page() {
    echo '<div class="wrap"><h1>Duplicate Post Manager</h1>';
    echo '<form method="post">';
    submit_button('Scan for Duplicates');
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        global $wpdb;

        $duplicates = $wpdb->get_results("SELECT post_title, post_name, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post' AND post_status = 'publish'
            GROUP BY post_title, post_name
            HAVING count > 1
            ORDER BY count DESC");

        if ($duplicates) {
            echo '<h2>Duplicate Posts</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Title</th><th>Slug</th><th>Count</th><th>Actions</th></tr></thead><tbody>';

            foreach ($duplicates as $dup) {
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_title = %s AND post_name = %s AND post_type = 'post' AND post_status = 'publish'",
                    $dup->post_title, $dup->post_name
                ));

                foreach ($posts as $post) {
                    echo '<tr>';
                    echo '<td>' . esc_html($post->post_title) . '</td>';
                    echo '<td>' . esc_html($post->post_name) . '</td>';
                    echo '<td>' . esc_html($dup->count) . '</td>';
                    echo '<td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="delete_id" value="' . esc_attr($post->ID) . '">
                            <input type="text" name="redirect_to" placeholder="Redirect URL" required style="width:300px">
                            <button type="submit" class="button button-primary">Delete & Redirect</button>
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

    // Handle deletion and .htaccess generation
    if (!empty($_POST['delete_id']) && !empty($_POST['redirect_to'])) {
        $delete_id = intval($_POST['delete_id']);
        $redirect_to = esc_url_raw($_POST['redirect_to']);
        $old_slug = get_post_field('post_name', $delete_id);

        // Delete post
        wp_delete_post($delete_id, true);

        // Store redirect rule in a transient for now
        $redirects = get_option('dpm_redirects', []);
        $redirects[] = [
            'from' => "/$old_slug",
            'to' => $redirect_to
        ];
        update_option('dpm_redirects', $redirects);

        echo '<div class="updated"><p>Post deleted and redirect rule stored.</p></div>';
    }

    // Display stored .htaccess rules
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
