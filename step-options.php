<?php
/*
Plugin Name: Step Options
Plugin URI:  https://webstep.top/cms/step-options-plugin-for-wordpress/
Description: Plugin for creating global site options
Version:     1.0.0
Author: Ihor Rybalko
Author URI: https://stringutils.online
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

if (!defined('ABSPATH')) {
    exit;
}

define('STEP_OPTIONS_VERSION', '1.0.0');
define('STEP_OPTIONS_DIR', plugin_dir_path(__FILE__));
define('STEP_OPTIONS_URL', plugin_dir_url(__FILE__));

function step_options_process_fields_actions() {
   
    if (! isset($_GET['page']) || $_GET['page'] !== 'step-fields') {
        return;
    }

    // Delete (GET)
    if (isset($_GET['delete_key']) && check_admin_referer('step_delete_field')) {
        $delete_key = sanitize_key($_GET['delete_key']);
        $fields = get_option('step_custom_fields', []);
        $new_fields = [];

        foreach ($fields as $f) {
            if ($f['key'] !== $delete_key) {
                $new_fields[] = $f;
            } else {
                delete_option("step_{$delete_key}");
            }
        }

        update_option('step_custom_fields', $new_fields);

        wp_safe_redirect(add_query_arg(
            ['page' => 'step-fields', 'message' => 'deleted'],
            admin_url('admin.php')
        ));
        exit;
    }

    // Add (POST)
    if (isset($_POST['add_field']) && check_admin_referer('step_add_field')) {
        $new_key   = sanitize_key($_POST['field_key']);
        $new_label = sanitize_text_field($_POST['field_label']);
        $new_type  = in_array($_POST['field_type'], ['text', 'textarea', 'wysiwyg', 'image']) ? $_POST['field_type'] : 'text';

        if (!empty($new_key) && !empty($new_label)) {
            $fields = get_option('step_custom_fields', []);
            $exists = false;
            foreach ($fields as $f) {
                if ($f['key'] === $new_key) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $fields[] = [
                    'key'   => $new_key,
                    'label' => $new_label,
                    'type'  => $new_type
                ];
                update_option('step_custom_fields', $fields);

                wp_safe_redirect(add_query_arg(
                    ['page' => 'step-fields', 'message' => 'added'],
                    admin_url('admin.php')
                ));
                exit;
            } else {
               
                add_settings_error(
                    'step_fields_messages',
                    'field_exists',
                    __('The key already exists', 'step-options'),
                    'error'
                );
            }
        }
    }
}
add_action('admin_init', 'step_options_process_fields_actions');

// Add pages to the menu: main and for managing fields
function step_options_add_admin_pages() {
    add_menu_page(
        __('Step Options', 'step-options'),
        __('Step Options', 'step-options'),
        'manage_options',
        'step-options',
        'step_options_page_html',
        'dashicons-admin-generic',
        80
    );

    add_submenu_page(
        'step-options',
        __('Manage Fields', 'step-options'),
        __('Manage Fields', 'step-options'),
        'manage_options',
        'step-fields',
        'step_fields_page_html'
    );
}
add_action('admin_menu', 'step_options_add_admin_pages');

// HTML of the main options page (where the values ​​are filled in)
function step_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error(
            'step_options_messages',
            'step_options_message',
            __('Settings saved', 'step-options'),
            'updated'
        );
    }

    settings_errors('step_options_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php 
        $fields = get_option('step_custom_fields', []);

        if (!empty($fields)){
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('step_options_group');
            do_settings_sections('step-options');
            submit_button(__('Save changes', 'step-options'));
            ?>
        </form>

        <?php } else { ?>
            <div>
                <h3><?php _e('Fields not added yet', 'step-options'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=step-fields'); ?>" class="button button-primary">
                    <?php _e('Add first field', 'step-options'); ?>
                </a>
            </div>
       <?php } ?>
    </div>
    <?php
}

// Registering dynamic settings (based on saved fields)
function step_options_register_settings() {
    $fields = get_option('step_custom_fields', []);

    if (!is_array($fields)) {
        $fields = [];
    }

    // Register each field dynamically
    foreach ($fields as $field) {
        $key = sanitize_key($field['key']);
        if (empty($key)) continue;

        $sanitize_func = 'sanitize_text_field';
        if (in_array($field['type'], ['textarea', 'wysiwyg'])) {
            $sanitize_func = 'step_sanitize_html';
        } elseif ($field['type'] === 'image') {
            $sanitize_func = 'absint';  // attachment ID
        }

        register_setting(
            'step_options_group',
            "step_{$key}",
            [
                'type'              => ($field['type'] === 'image') ? 'integer' : 'string',
                'sanitize_callback' => $sanitize_func,
                'default'           => ''
            ]
        );
    }

    // Section for dynamic fields
    add_settings_section(
        'step_main_section',
        __('Global settings', 'step-options'),
        null,
        'step-options'
    );

    // Adding fields to the section
    foreach ($fields as $field) {
        $key = sanitize_key($field['key']);
        $label = esc_html($field['label']);
        $type = esc_attr($field['type']);

        add_settings_field(
            "step_{$key}",
            $label,
            function() use ($key, $type) {
                step_render_field($key, $type);
            },
            'step-options',
            'step_main_section'
        );
    }
}
add_action('admin_init', 'step_options_register_settings');

function step_sanitize_html($value) {
    return wp_kses_post($value);
}

// Field render function
function step_render_field($key, $type) {
    $value = get_option("step_{$key}", '');
    $name = "step_{$key}";
    $id = "step_{$key}";

    switch ($type) {
        case 'image':
            $preview = '';
            $button_text = __('Select an image', 'step-options');
            $remove_style = 'display:none;';

            if (!empty($value)) {
                $img = wp_get_attachment_image_src($value, 'thumbnail');
                if ($img) {
                    $preview = '<img src="' . esc_url($img[0]) . '" alt="" style="max-width:200px; height:auto; display:block; margin:10px 0;">';
                    $button_text = __('Replace image', 'step-options');
                    $remove_style = '';
                }
            }

            ?>
            <div class="step-image-field">
                <input type="hidden"
                       name="<?php echo esc_attr($name); ?>"
                       id="<?php echo esc_attr($id); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       class="step-image-id">

                <div class="step-image-preview"><?php echo $preview; ?></div>

                <button type="button" class="button step-upload-image"><?php echo esc_html($button_text); ?></button>
                <button type="button" class="button step-remove-image" style="<?php echo esc_attr($remove_style); ?>">
                    <?php _e('Delete image', 'step-options'); ?>
                </button>

            </div>
            <?php
            break;
        case 'wysiwyg':
            $settings = [
                'textarea_name' => $name,        
                'editor_class'  => 'step-wysiwyg',
                'media_buttons' => true,           
                'textarea_rows' => 10,             
                'teeny'         => false,         
                'quicktags'     => true,
                'tinymce'       => true,            
            ];

            wp_editor($value, $id, $settings);
            break;
        case 'textarea':
            ?>
            <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <?php
            break;
        case 'text':
        default:
            ?>
            <input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <?php
            break;
    }
    ?>
    <p class="description"><?php printf(__('Key: %s', 'step-options'), esc_html($key)); ?></p>
    <?php
}

// HTML pages for managing fields (adding/removing)
function step_fields_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $messages = [
        'added'   => ['message' => __('The field was added successfully', 'step-options'), 'type' => 'updated'],
        'deleted' => ['message' => __('The field was successfully deleted', 'step-options'), 'type' => 'updated'],
    ];
    
    if (isset($_GET['message']) && array_key_exists($_GET['message'], $messages)) {
        add_settings_error(
            'step_fields_messages',
            'step_message',
            $messages[$_GET['message']]['message'],
            $messages[$_GET['message']]['type']
        );
    }

    settings_errors('step_fields_messages');
    
    step_options_process_fields_actions();

    $fields = get_option('step_custom_fields', []);
    ?>
    <div class="wrap">
        <h1><?php _e('Manage Fields', 'step-options'); ?></h1>

        <!-- Add field form -->
        <form method="post">
            <?php wp_nonce_field('step_add_field'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="field_label"><?php _e('Field label', 'step-options'); ?></label></th>
                    <td><input type="text" id="field_label" name="field_label" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="field_key"><?php _e('Field key (only Latin letters, numbers, _)', 'step-options'); ?></label></th>
                    <td><input type="text" id="field_key" name="field_key" class="regular-text" required pattern="[a-zA-Z0-9_]+"></td>
                </tr>
                <tr>
                    <th><label for="field_type"><?php _e('Field type', 'step-options'); ?></label></th>
                    <td>
                        <select id="field_type" name="field_type">
                            <option value="text"><?php _e('Text (input)', 'step-options'); ?></option>
                            <option value="textarea"><?php _e('Multiline text (textarea)', 'step-options'); ?></option>
                            <option value="wysiwyg"><?php _e('Visual editor (WYSIWYG)', 'step-options'); ?></option>
                            <option value="image"><?php _e('Image (media library)', 'step-options'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="submit" name="add_field" class="button button-primary" value="<?php _e('Add field', 'step-options'); ?>">
        </form>

        <!-- Field list -->
        <?php if (!empty($fields)) : ?>
            <h2><?php _e('Existing fields', 'step-options'); ?></h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Key', 'step-options'); ?></th>
                        <th><?php _e('Shortcode', 'step-options'); ?></th>
                        <th><?php _e('Label', 'step-options'); ?></th>
                        <th><?php _e('Type', 'step-options'); ?></th>
                        <th><?php _e('Actions', 'step-options'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field) : ?>
                        <tr>
                            <td><?php echo esc_html($field['key']); ?></td>
                            <td><?php if($field['type'] != 'image'){?>[step_option key="<?php echo esc_html($field['key']); ?>"] <?php } ?></td>
                            <td><?php echo esc_html($field['label']); ?></td>
                            <td><?php echo esc_html($field['type']); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=step-fields&delete_key=' . urlencode($field['key'])), 'step_delete_field'); ?>" class="button button-secondary" onclick="return confirm('<?php _e('Delete this field? The value will be erased.', 'step-options'); ?>');"><?php _e('Delete', 'step-options'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// The main function for getting the value by key
function get_step_option($key, $default = '') {
    $sanitized_key = sanitize_key($key);
    $value = get_option("step_{$sanitized_key}", $default);

    $value = wpautop($value);

    return $value;
}

/**
 * Returns the image ID by key
 */
function get_step_option_image_id($key, $default = 0) {
    $sanitized_key = sanitize_key($key);
    $value = get_option("step_{$sanitized_key}", $default);
    return (int) $value;
}

/**
 * Returns the URL of the image (you can specify the size: thumbnail, medium, large, full)
 */
function get_step_option_image_url($key, $size = 'full', $default = '') {
    $id = get_step_option_image_id($key);
    if (!$id) return $default;
    $src = wp_get_attachment_image_src($id, $size);
    return $src ? $src[0] : $default;
}

/**
 * Outputs the finished <img> tag
 */
function get_step_option_image($key, $size = 'full', $attr = [], $default = '') {
    $id = get_step_option_image_id($key);
    if (!$id) return $default;
    return wp_get_attachment_image($id, $size, false, $attr);
}

// Shortcode
function step_option_shortcode($atts) {
    $atts = shortcode_atts(['key' => ''], $atts);
    if (empty($atts['key'])) return '';
    return get_step_option($atts['key']);
}
add_shortcode('step_option', 'step_option_shortcode');

function step_options_enqueue_media() {
    $screen = get_current_screen();
    if (strpos($screen->id, 'step-options') === false && strpos($screen->id, 'step-fields') === false) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
        'step-options-admin-image',
        STEP_OPTIONS_URL . 'assets/js/admin-image-uploader.js',
        array('jquery'),             
        STEP_OPTIONS_VERSION,             
        true                              
    );

    wp_localize_script(
        'step-options-admin-image',
        'stepOptionsL10n',
        array(
            'title'   => esc_js(__('Select an image', 'step-options')),
            'button'  => esc_js(__('Use the image', 'step-options')),
            'replace' => esc_js(__('Replace image', 'step-options')),
            'select'  => esc_js(__('Select an image', 'step-options')),
        )
    );
}
add_action('admin_enqueue_scripts', 'step_options_enqueue_media');
