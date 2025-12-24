<?php
/**
 * Plugin Name: Post Positions
 * Description: Manage and reorder featured posts in the "latest" category.
 * Version: 1.1.0
 * Author: Ghulam Mustafa
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu (submenu under Posts)
 */
function pp_register_admin_menu() {
    add_submenu_page(
        'edit.php',
        'Post Positions',
        'Post Positions',
        'edit_others_posts',
        'post-positions',
        'pp_settings_page'
    );
}
add_action('admin_menu', 'pp_register_admin_menu');


/**
 * Settings page: list latest posts sortable UI skeleton
 */
function pp_settings_page() {
    if (!current_user_can('edit_others_posts')) {
        wp_die('Insufficient permissions');
    }

$args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'category_name'  => 'latest',
    'posts_per_page' => 11,
    'orderby'        => 'meta_value_num date',
    'meta_key'       => 'featured_position',
    'order'          => 'ASC',
    'no_found_rows'  => true,
    'meta_query'     => array(
        array(
            'key'     => 'featured_position',
            'value'   => 0,
            'compare' => '!=',
            'type'    => 'NUMERIC',
        ),
    ),
    'date_query'     => array(
        array(
            'after'     => date('Y-m-d', strtotime('-2 days')),
            'inclusive' => true,
        ),
    ),
);


    $query = new WP_Query($args);
    ?>
    <div class="wrap">
        <h1>Post Positions</h1>

        <p>Drag and drop posts to reorder.</p>

        <?php if ($query->have_posts()) : ?>
            <form id="pp-order-form" method="post" action="">
				<input type="hidden" name="pp_nonce" value="<?php echo esc_attr(wp_create_nonce('pp-save-order')); ?>">

				<ul id="pp-sortable">
					<?php while ($query->have_posts()) : $query->the_post();
						$pos = intval(get_post_meta(get_the_ID(), 'featured_position', true));
						$edit_link = get_edit_post_link(get_the_ID());
						$date_time = get_the_date('M d, Y g:i A'); // Example: Oct 20, 2025 10:32 AM
					?>
						<li class="pp-item" data-id="<?php the_ID(); ?>">
							<span class="pp-handle">☰</span>
							<span class="pp-title"><?php the_title(); ?></span>
							
							<span class="pp-date" style="margin-left:10px; color:#777;">
								<?php echo esc_html($date_time); ?>
							</span>
							<a href="<?php echo esc_url($edit_link); ?>" 
							   class="button button-small" 
							   style="margin-left:auto;">Edit</a>
							   <span class="pp-pos pp-meta">(<?php echo esc_html($pos); ?>)</span>
						</li>
					<?php endwhile; ?>
				</ul>

				<p>
					<button id="pp-save-order" type="button" class="button button-primary">Save order</button>
					<span id="pp-save-status" style="margin-left:15px;"></span>
				</p>
			</form>
        <?php else: ?>
            <p>No latest posts found.</p>
        <?php endif;
        wp_reset_postdata();
        ?>
    </div>
    <?php
}


/**
 * Add metabox on post edit
 */
function pp_add_featured_position_metabox() {
    add_meta_box('pp_featured_position', 'Featured Position (Top Stories)', 'pp_render_metabox', 'post', 'side', 'high');
}
add_action('add_meta_boxes', 'pp_add_featured_position_metabox');

/**
 * Metabox HTML
 */
/*
function pp_render_metabox($post) {
    wp_nonce_field('pp_save_metabox', 'pp_metabox_nonce');
    $value = intval(get_post_meta($post->ID, 'featured_position', true));
    ?>
    <div id="pp-metabox-wrap">
        <p>This input is used only for posts in the <strong>latest</strong> category. If the post is not in that category, the position will be set to 0 on save.</p>
        <p>
            <label for="pp_featured_position">Position (0 for none):</label><br />
            <input type="number" id="pp_featured_position" name="pp_featured_position" value="<?php echo esc_attr($value); ?>" min="0" max="5" step="1" style="width:100%;" />
        </p>
    </div>
    <?php
}*/
function pp_render_metabox($post) {
    wp_nonce_field('pp_save_metabox', 'pp_metabox_nonce');
    $value = intval(get_post_meta($post->ID, 'featured_position', true));
    ?>
    <div id="pp-metabox-wrap">
        <p>This input is used only for posts in the <strong>latest</strong> category. 
        If the post is not in that category, the position will be set to 0 on save.</p>
        <p>
            <label for="pp_featured_position">Position (0 for none):</label><br />
            <select id="pp_featured_position" name="pp_featured_position" style="width:100%;">
                <?php
                for ($i = 0; $i <= 11; $i++) {
                    echo '<option value="' . $i . '"' . selected($value, $i, false) . '> Position ' . $i . '</option>';
                }
                ?>
            </select>
        </p>
    </div>
    <?php
}


/**
 * Save featured position on post save
 */
function pp_save_featured_position($post_id, $post) {
    // avoid autosave/revision and check permissions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['pp_metabox_nonce']) || !wp_verify_nonce($_POST['pp_metabox_nonce'], 'pp_save_metabox')) return;

    // categories (slugs)
    $categories = wp_get_post_categories($post_id, array('fields' => 'slugs'));
    $has_top = in_array('latest', $categories, true);

    $new_pos = isset($_POST['pp_featured_position']) ? intval($_POST['pp_featured_position']) : 0;
    $old_pos = intval(get_post_meta($post_id, 'featured_position', true));

    if (!$has_top) {
        // removed from latest -> set to 0 and reorder others
        if ($old_pos > 0) {
            pp_decrement_positions_after($old_pos, $post_id);
        }
        update_post_meta($post_id, 'featured_position', 0);
        return;
    }

    if ($new_pos <= 0) {
        // removing position
        if ($old_pos > 0) {
            pp_decrement_positions_after($old_pos, $post_id);
        }
        update_post_meta($post_id, 'featured_position', 0);
        return;
    }

    if ($old_pos === $new_pos) {
        update_post_meta($post_id, 'featured_position', $new_pos);
        return;
    }

    if ($old_pos === 0) {
        // inserting new positioned post
        pp_increment_positions_from($new_pos);
        update_post_meta($post_id, 'featured_position', $new_pos);
        return;
    }

    // moving an existing positioned post
    if ($old_pos < $new_pos) {
        pp_decrement_positions_range($old_pos + 1, $new_pos, $post_id);
    } else {
        pp_increment_positions_range($new_pos, $old_pos - 1, $post_id);
    }
    update_post_meta($post_id, 'featured_position', $new_pos);
}
add_action('save_post', 'pp_save_featured_position', 10, 2);

/**
 * Ensure default meta for new posts
 */
function pp_ensure_default_position($post_id, $post, $update) {
    if ($post->post_type !== 'post') return;
    $existing = get_post_meta($post_id, 'featured_position', true);
    if ($existing === '') {
        update_post_meta($post_id, 'featured_position', 0);
    }
}
add_action('wp_insert_post', 'pp_ensure_default_position', 10, 3);

/**
 * Helper DB update functions
 * These use direct SQL for bulk updates (be careful; tested for typical setups).
 */

function pp_increment_positions_from($from) {
    global $wpdb;
    $meta_key = 'featured_position';
    $sql = $wpdb->prepare(
        "UPDATE {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         SET pm.meta_value = CAST(pm.meta_value AS SIGNED) + 1
         WHERE pm.meta_key = %s
           AND CAST(pm.meta_value AS SIGNED) >= %d
           AND p.post_status = 'publish'
           AND p.post_type = 'post'",
        $meta_key, $from
    );
    $wpdb->query($sql);
}

function pp_increment_positions_range($from, $to, $exclude_id = 0) {
    global $wpdb;
    $meta_key = 'featured_position';
    $sql = "UPDATE {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         SET pm.meta_value = CAST(pm.meta_value AS SIGNED) + 1
         WHERE pm.meta_key = %s
           AND CAST(pm.meta_value AS SIGNED) BETWEEN %d AND %d
           AND p.post_status = 'publish'
           AND p.post_type = 'post'";

    if ($exclude_id) {
        $sql .= $wpdb->prepare(' AND p.ID <> %d', $exclude_id);
        $wpdb->query($wpdb->prepare($sql, $meta_key, $from, $to));
    } else {
        $wpdb->query($wpdb->prepare($sql, $meta_key, $from, $to));
    }
}

function pp_decrement_positions_after($pos, $exclude_id = 0) {
    global $wpdb;
    $meta_key = 'featured_position';
    $sql = "UPDATE {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         SET pm.meta_value = CAST(pm.meta_value AS SIGNED) - 1
         WHERE pm.meta_key = %s
           AND CAST(pm.meta_value AS SIGNED) > %d
           AND p.post_status = 'publish'
           AND p.post_type = 'post'";

    if ($exclude_id) {
        $sql .= $wpdb->prepare(' AND p.ID <> %d', $exclude_id);
        $wpdb->query($wpdb->prepare($sql, $meta_key, $pos));
    } else {
        $wpdb->query($wpdb->prepare($sql, $meta_key, $pos));
    }
}

function pp_decrement_positions_range($from, $to, $exclude_id = 0) {
    global $wpdb;
    $meta_key = 'featured_position';
    $sql = "UPDATE {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         SET pm.meta_value = CAST(pm.meta_value AS SIGNED) - 1
         WHERE pm.meta_key = %s
           AND CAST(pm.meta_value AS SIGNED) BETWEEN %d AND %d
           AND p.post_status = 'publish'
           AND p.post_type = 'post'";

    if ($exclude_id) {
        $sql .= $wpdb->prepare(' AND p.ID <> %d', $exclude_id);
        $wpdb->query($wpdb->prepare($sql, $meta_key, $from, $to));
    } else {
        $wpdb->query($wpdb->prepare($sql, $meta_key, $from, $to));
    }
}


add_action('admin_enqueue_scripts', function($hook) {
    //  Must match your admin page hook suffix
    if ($hook === 'posts_page_post-positions') {

        // Load jQuery UI sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Your JS
        wp_enqueue_script(
            'pp-admin-js',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            '1.1',
            true
        );

        // Localize script (MUST match handle)
        wp_localize_script('pp-admin-js', 'pp_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pp_nonce'),
        ]);

        // Your CSS
        wp_enqueue_style(
            'pp-admin-css',
            plugin_dir_url(__FILE__) . 'assets/admin.css'
        );
    }
});



add_action('wp_ajax_pp_save_order', function() {
    check_ajax_referer('pp_nonce', 'nonce'); //  must match wp_localize_script

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied.');
    }

    if (empty($_POST['order']) || !is_array($_POST['order'])) {
        wp_send_json_error('Invalid order data.');
    }

    $position = 1;
    foreach ($_POST['order'] as $post_id) {
        update_post_meta(intval($post_id), 'featured_position', $position);
        $position++;
    }

    wp_send_json_success(['message' => 'Order saved successfully.']);
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('jquery-ui-sortable');
});

?>
