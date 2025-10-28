<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrations settings page
 *
 * @package RM_PagBank/Admin/Settings
 */

// Enqueue integrations CSS
wp_enqueue_style(
    'pagbank-connect-integrations',
    plugins_url('public/css/integrations.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
    [],
    WC_PAGSEGURO_CONNECT_VERSION
);

// Load integrations settings fields
$integrations_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/dokan-split-fields.php';

// Get saved options
$integrations_options = get_option('woocommerce_rm-pagbank-integrations_settings', []);

// Display the settings form
?>
<form method="post" id="mainform" action="" enctype="multipart/form-data">
    <?php wp_nonce_field('woocommerce-settings'); ?>
    <table class="form-table">
        <?php
        foreach ($integrations_fields as $key => $field) {
            $field['id'] = $key;
            $field_value = isset($integrations_options[$key]) ? $integrations_options[$key] : (isset($field['default']) ? $field['default'] : '');
            
            switch ($field['type']) {
                case 'title':
                    ?>
                    <tr>
                        <td colspan="2">
                            <h2><?php echo esc_html($field['title']); ?></h2>
                            <?php if (!empty($field['desc'])): ?>
                                <p><?php echo wp_kses_post($field['desc']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    break;
                    
                case 'checkbox':
                    ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['title']); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr($field['type']); ?>">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php echo esc_html($field['title']); ?></span></legend>
                                <label for="<?php echo esc_attr($key); ?>">
                                    <input 
                                        type="checkbox" 
                                        class="<?php echo esc_attr(isset($field['class']) ? $field['class'] : ''); ?>" 
                                        name="<?php echo esc_attr($key); ?>" 
                                        id="<?php echo esc_attr($key); ?>" 
                                        value="1" 
                                        <?php checked($field_value, 'yes'); ?> 
                                    />
                                    <?php echo wp_kses_post(isset($field['description']) ? $field['description'] : ''); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <?php
                    break;
                    
                case 'text':
                case 'number':
                    ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['title']); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr($field['type']); ?>">
                            <input 
                                type="<?php echo esc_attr($field['type']); ?>" 
                                name="<?php echo esc_attr($key); ?>" 
                                id="<?php echo esc_attr($key); ?>" 
                                value="<?php echo esc_attr($field_value); ?>" 
                                class="<?php echo esc_attr(isset($field['class']) ? $field['class'] : ''); ?>"
                                <?php if (isset($field['custom_attributes']) && is_array($field['custom_attributes'])) {
                                    foreach ($field['custom_attributes'] as $attr => $attr_value) {
                                        echo esc_attr($attr) . '="' . esc_attr($attr_value) . '" ';
                                    }
                                } ?>
                            />
                            <?php if (!empty($field['description'])): ?>
                                <p class="description"><?php echo wp_kses_post($field['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    break;
                    
                case 'select':
                    ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['title']); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr($field['type']); ?>">
                            <select 
                                name="<?php echo esc_attr($key); ?>" 
                                id="<?php echo esc_attr($key); ?>" 
                                class="<?php echo esc_attr(isset($field['class']) ? $field['class'] : ''); ?>"
                            >
                                <?php foreach ($field['options'] as $option_key => $option_value): ?>
                                    <option value="<?php echo esc_attr($option_key); ?>" <?php selected($field_value, $option_key); ?>>
                                        <?php echo esc_html($option_value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($field['description'])): ?>
                                <p class="description"><?php echo wp_kses_post($field['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    break;
            }
        }
        ?>
    </table>
    
    <p class="submit">
        <button name="save" class="button-primary woocommerce-save-button" type="submit" value="<?php echo esc_attr__('Salvar alterações', 'pagbank-connect'); ?>">
            <?php echo esc_html__('Salvar alterações', 'pagbank-connect'); ?>
        </button>
    </p>
</form>

