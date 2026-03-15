# Step Options

A simple and flexible WordPress plugin for creating global site options (similar to ACF Options Pages), with the ability to dynamically add fields directly from the admin panel.

Fields are not tied to posts or pages — all values are stored in the `wp_options` table and can be accessed site-wide.

**Current features:**
- Dynamically add / remove fields from the admin interface
- Supported field types: text, textarea, wysiwyg (visual editor), image
- Easy output via the `get_step_option()` function or shortcode
- HTML and automatic paragraph support in wysiwyg fields

## Installation

1. Download the plugin archive or clone the repository
2. Upload the `step-options` folder to `/wp-content/plugins/`
3. Activate the plugin in the WordPress admin under **Plugins**

After activation, a new menu item **Step Options** will appear in the sidebar.

## Usage

### 1. Adding Fields

1. Go to **Step Options → Manage Fields**
2. Fill in:
   - **Field Key** — unique identifier (latin letters, numbers, underscore only)
   - **Field Label** — display name shown in the admin
   - **Field Type** — choose from: text, textarea, wysiwyg, image
3. Click **Add Field**

### 2. Filling Values

1. Go to **Step Options** (main settings page)
2. Fill in the newly created fields
3. Click **Save Changes**

### 3. Output Values in Theme Templates

All values are accessible via the `get_step_option()` function:

```php
<?php
// Simple text, textarea, wysiwyg
echo get_step_option('site_slogan');                    // outputs the value (with wpautop for wysiwyg/textarea)

// With default fallback
echo get_step_option('footer_text', '© 2026 My Site');

// For images — returns attachment ID
$logo_id = get_step_option('site_logo');
if ($logo_id) {
    echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'site-logo']);
}

// Convenient image helper functions
echo get_step_option_image_url('site_logo', 'full');     // full image URL
echo get_step_option_image_url('site_logo');     // full is the default value

echo get_step_option_image('site_logo', 'thumbnail');    // ready <img> tag
?>
```
### 4. Output via Shortcode

```
[step_option key="site_slogan"]
[step_option key="about_text"]
[step_option key="site_logo"]   // outputs the attachment ID (not the image!)
```

### 5. Helpful Image Functions

```php
<?php
// Image URL (sizes: thumbnail, medium, large, full)
get_step_option_image_url('header_background', 'large');

// Ready <img> tag with custom attributes
get_step_option_image('site_logo', 'medium', [
    'class'   => 'logo',
    'alt'     => 'Company Logo',
    'loading' => 'lazy'
]);
?>
```