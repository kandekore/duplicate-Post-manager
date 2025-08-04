<?php
/**
 * Plugin Name: Duplicate Post Manager
 * Description: Find and manage duplicate posts by title or slug. Allows deletion and 301 redirection with .htaccess code generation.
 * Version: 2.0
 * Author: Darren Kandekore
 * Author URI: https://github.com/dkandekore    
 * Text Domain: duplicate-post-manager
 * @package DuplicatePostManager
 * @since 2.0
 */

if (!defined('ABSPATH')) exit;

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

function dpm_validate_url($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '404') === false;
}

function dpm_admin_page() {
    echo '<div class="wrap"><h1>Duplicate Post Manager</h1>';
    echo '<form method="post">';
    submit_button('Scan for Duplicates');
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['bulk_delete'])) {
        global $wpdb;
        $duplicate_titles = $wpdb->get_results("SELECT post_title, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' GROUP BY post_title HAVING count > 1");
        $duplicate_slugs = $wpdb->get_results("SELECT post_name, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' GROUP BY post_name HAVING count > 1");

        if ($duplicate_titles || $duplicate_slugs) {
            $all_posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1]);
            echo '<form method="post">';
            echo '<h2>Duplicate Posts</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th><input type="checkbox" onclick="jQuery(\'.dpm-check\').prop(\'checked\', this.checked);"></th><th>Title</th><th>Slug</th></tr></thead><tbody>';

            $printed = [];
            foreach (array_merge($duplicate_titles, $duplicate_slugs) as $dup) {
                $field = isset($dup->post_title) ? 'post_title' : 'post_name';
                $value = $dup->$field;
                if (in_array($value, $printed)) continue;
                $printed[] = $value;
                $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE {$field} = %s AND post_type = 'post' AND post_status = 'publish'", $value));
                if (count($posts) < 2) continue;
                foreach ($posts as $post) {
                    echo '<tr>';
                    echo '<td><input type="checkbox" class="dpm-check" name="bulk_ids[]" value="' . esc_attr($post->ID) . '"></td>';
                    echo '<td>' . esc_html($post->post_title) . '</td>';
                    echo '<td>' . esc_html($post->post_name) . '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
            echo '<br><label><strong>Redirect To:</strong></label><br>';
            echo '<select name="redirect_select" style="width:300px"><option value="">-- Select a redirect target --</option>';
            foreach ($all_posts as $target) {
                $url = get_permalink($target->ID);
                echo '<option value="' . esc_url($url) . '">' . esc_html($target->post_title) . '</option>';
            }
            echo '</select><br><input type="text" name="redirect_manual" placeholder="Or enter custom URL" style="width:300px;margin-top:5px"><br><br>';
            echo '<button type="submit" name="bulk_delete" class="button button-primary">Delete Selected & Redirect</button>';
            echo '</form>';
        } else {
            echo '<p>No duplicates found.</p>';
        }
    }

    // Handle bulk delete
    if (!empty($_POST['bulk_delete']) && !empty($_POST['bulk_ids'])) {
        $redirect_to = esc_url_raw($_POST['redirect_manual']) ?: esc_url_raw($_POST['redirect_select']);
        if (!$redirect_to || !dpm_validate_url($redirect_to)) {
            echo '<div class="error"><p>Redirect URL is required and must not return 404.</p></div>';
        } else {
            $redirects = get_option('dpm_redirects', []);
            foreach ($_POST['bulk_ids'] as $id) {
                $id = intval($id);
                $old_slug = get_post_field('post_name', $id);
                wp_delete_post($id, true);
                $redirects[] = ['from' => "/$old_slug", 'to' => $redirect_to];
            }
            update_option('dpm_redirects', $redirects);
            echo '<div class="updated"><p>Posts deleted and redirect rules stored.</p></div>';
        }
    }

    // Display stored .htaccess rules
    $redirects = get_option('dpm_redirects', []);
    if (!empty($redirects)) {
        echo '<h2>.htaccess Redirect Rules</h2>';
        echo '<textarea rows="10" style="width:100%;font-family:monospace;">';
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 {$rule['from']} {$rule['to']}\n";
        }
        echo "# END Post Redirects";
        echo '</textarea>';
        echo '<form method="post"><button type="submit" name="download_htaccess" class="button">Download .htaccess</button></form>';
    }

    // Handle .htaccess file download
    if (!empty($_POST['download_htaccess'])) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="redirects.htaccess"');
        echo "# BEGIN Post Redirects\n";
        foreach ($redirects as $rule) {
            echo "Redirect 301 {$rule['from']} {$rule['to']}\n";
        }
        echo "# END Post Redirects";
        exit;
    }

    echo '</div>';
}
