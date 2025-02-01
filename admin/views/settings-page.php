<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <?php settings_errors(); ?>
    <div id="waa-settings-notices"></div>

    <form action="options.php" method="post" id="waa-settings-form">
        <?php 
        // Output nonce, action, and option_page fields
        settings_fields('waa_options');
        
        // Log current options for debugging
        $options = get_option('waa_options', []);
        waa_debug_log('Current settings:', $options);

        // Show all sections from the settings page
        do_settings_sections('website-ai-assistant');
        ?>

        <!-- Added API Key Test Buttons -->
        <div class="api-key-test-buttons" style="margin: 20px 0;">
            <button type="button" id="test-gemini-api" class="button">Test Gemini API Key</button>
            <button type="button" id="test-deepseek-api" class="button">Test DeepSeek API Key</button>
            <div id="api-test-response" style="margin-top: 10px; font-style: italic;"></div>
        </div>

        <div class="submit-wrapper">
            <?php submit_button(null, 'primary', 'submit', true, ['id' => 'waa-save-settings']); ?>
            <div class="model-fetch-status" style="display: none;"></div>
        </div>
    </form>
</div>