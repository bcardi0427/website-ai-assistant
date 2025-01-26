<?php
namespace Website_Ai_Assistant\Admin;

class Admin_Settings {
    private const OPTION_GROUP = 'waa_options';
    private const SETTINGS_PAGE = 'website-ai-assistant';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_settings_page(): void {
        add_options_page(
            __('Website AI Assistant Settings', 'website-ai-assistant'),
            __('Website AI Assistant', 'website-ai-assistant'),
            'manage_options',
            self::SETTINGS_PAGE,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'waa_options',
            'waa_options',
            [
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => []
            ]
        );

        // API Settings section
        add_settings_section(
            'waa_api_settings',
            __('API Settings', 'website-ai-assistant'),
            [$this, 'render_api_section'],
            'website-ai-assistant'
        );

        // Search Provider selector
        add_settings_field(
            'search_provider',
            __('Search Provider', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'field' => 'search_provider',
                'options' => [
                    'google' => __('Google Custom Search', 'website-ai-assistant'),
                    'algolia' => __('Algolia (WordPress Plugin)', 'website-ai-assistant')
                ],
                'default' => 'google',
                'description' => __('Select which search provider to use. Algolia requires the Algolia Search plugin to be installed and configured.', 'website-ai-assistant')
            ]
        );

        // Gemini API Key field
        add_settings_field(
            'gemini_api_key',
            __('Gemini API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'gemini_api_key',
                'field_name' => 'gemini_api_key',
                'type' => 'password'
            ]
        );

        // Google Search Fields
        add_settings_field(
            'google_search_api_key',
            __('Google Search API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'google_search_api_key',
                'field_name' => 'google_search_api_key',
                'type' => 'password',
                'class' => 'google-search-field'
            ]
        );

        add_settings_field(
            'search_engine_id',
            __('Search Engine ID', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'search_engine_id',
                'field_name' => 'search_engine_id',
                'type' => 'text',
                'class' => 'google-search-field'
            ]
        );

        // Algolia Fields
        add_settings_field(
            'algolia_app_id',
            __('Algolia Application ID', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'algolia_app_id',
                'field_name' => 'algolia_app_id',
                'type' => 'text',
                'class' => 'algolia-field'
            ]
        );

        add_settings_field(
            'algolia_search_key',
            __('Algolia Search API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'algolia_search_key',
                'field_name' => 'algolia_search_key',
                'type' => 'password',
                'class' => 'algolia-field'
            ]
        );

        add_settings_field(
            'algolia_admin_key',
            __('Algolia Admin API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'waa_api_settings',
            [
                'label_for' => 'algolia_admin_key',
                'field_name' => 'algolia_admin_key',
                'type' => 'password',
                'class' => 'algolia-field'
            ]
        );

        add_settings_field(
            'system_message',
            __('System Message', 'website-ai-assistant'),
            [$this, 'render_textarea_field'],
            self::SETTINGS_PAGE,
            'waa_api_settings',
            ['field' => 'system_message']
        );

        add_settings_field(
            'max_history',
            __('Max Conversation History', 'website-ai-assistant'),
            [$this, 'render_number_field'],
            self::SETTINGS_PAGE,
            'waa_api_settings',
            ['field' => 'max_history']
        );

        // Add Website Topics field after system message
        add_settings_field(
            'website_topics',
            __('Website Topics', 'website-ai-assistant'),
            [$this, 'render_textarea_field'],
            self::SETTINGS_PAGE,
            'waa_api_settings',
            [
                'field' => 'website_topics',
                'description' => __('Enter the main topics or subjects your website covers (e.g., "hydroponics, indoor gardening, plant care"). This helps the AI understand which questions it should answer in detail.', 'website-ai-assistant')
            ]
        );

        // Add new section for Display Settings
        add_settings_section(
            'waa_display_settings',
            __('Display Settings', 'website-ai-assistant'),
            [$this, 'render_display_section'],
            self::SETTINGS_PAGE
        );

        // Add display location field
        add_settings_field(
            'display_locations',
            __('Show Chat Widget On', 'website-ai-assistant'),
            [$this, 'render_display_locations_field'],
            self::SETTINGS_PAGE,
            'waa_display_settings'
        );

        // Add new section for Lead Collection Settings
        add_settings_section(
            'waa_lead_settings',
            __('Lead Collection Settings', 'website-ai-assistant'),
            [$this, 'render_lead_section'],
            self::SETTINGS_PAGE
        );

        // Add lead collection fields
        add_settings_field(
            'enable_lead_collection',
            __('Enable Lead Collection', 'website-ai-assistant'),
            [$this, 'render_checkbox_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            ['field' => 'enable_lead_collection']
        );

        add_settings_field(
            'lead_collection_timing',
            __('Ask for Contact Info', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'lead_collection_timing',
                'options' => [
                    'immediate' => __('At the start of conversation', 'website-ai-assistant'),
                    'after_first' => __('After first message', 'website-ai-assistant'),
                    'after_two' => __('After two messages', 'website-ai-assistant'),
                    'end' => __('At end of conversation', 'website-ai-assistant')
                ]
            ]
        );

        // FluentCRM List field with priority 10
        add_settings_field(
            'fluentcrm_list_id',
            __('FluentCRM List', 'website-ai-assistant'),
            [$this, 'render_fluentcrm_lists_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'fluentcrm_list_id',
                'priority' => 10
            ]
        );

        // FluentCRM Tag field with priority 11
        add_settings_field(
            'fluentcrm_tag_id',
            __('FluentCRM Tag', 'website-ai-assistant'),
            [$this, 'render_fluentcrm_tags_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'fluentcrm_tag_id',
                'priority' => 11
            ]
        );

        // Add after the FluentCRM tag field
        add_settings_field(
            'fluentcrm_status',
            __('Contact Status', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'fluentcrm_status',
                'options' => [
                    'subscribed' => __('Subscribed', 'website-ai-assistant'),
                    'pending' => __('Pending', 'website-ai-assistant')
                ],
                'description' => __('Status for new contacts in FluentCRM', 'website-ai-assistant'),
                'default' => 'subscribed'
            ]
        );

        // Add lead collection message fields
        add_settings_field(
            'lead_collection_heading',
            __('Lead Collection Heading', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'lead_collection_heading',
                'description' => __('The heading shown above the contact form', 'website-ai-assistant'),
                'default' => __('Please share your contact info to continue', 'website-ai-assistant')
            ]
        );

        add_settings_field(
            'lead_collection_description',
            __('Lead Collection Description', 'website-ai-assistant'),
            [$this, 'render_textarea_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'lead_collection_description',
                'description' => __('Optional description text shown below the heading', 'website-ai-assistant')
            ]
        );

        add_settings_field(
            'privacy_page_url',
            __('Privacy Policy URL', 'website-ai-assistant'),
            [$this, 'render_privacy_page_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'description' => __('Select your privacy policy page or enter a custom URL', 'website-ai-assistant')
            ]
        );

        add_settings_field(
            'privacy_text',
            __('Privacy Notice Text', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            self::SETTINGS_PAGE,
            'waa_lead_settings',
            [
                'field' => 'privacy_text',
                'description' => __('Text shown with privacy policy link. Use {privacy_policy} for the link.', 'website-ai-assistant'),
                'default' => __('By continuing, you agree to our {privacy_policy}.', 'website-ai-assistant')
            ]
        );

        // Add in register_settings() method after other settings
        add_settings_field(
            'enable_debug',
            __('Enable Debug Logging', 'website-ai-assistant'),
            [$this, 'render_checkbox_field'],
            self::SETTINGS_PAGE,
            'waa_api_settings',
            [
                'field' => 'enable_debug',
                'description' => __('Log debug information to wp-content/debug.log', 'website-ai-assistant')
            ]
        );
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once WAA_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function enqueue_admin_assets($hook): void {
        if ("settings_page_" . self::SETTINGS_PAGE !== $hook) {
            return;
        }

        wp_enqueue_style(
            'waa-admin-settings',
            WAA_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            WAA_VERSION
        );

        wp_enqueue_script(
            'waa-admin-settings',
            WAA_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery'],
            WAA_VERSION,
            true
        );

        // Add AJAX data for the test buttons
        wp_localize_script('waa-admin-settings', 'waaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('waa_admin_nonce'),
            'testGemini' => __('Testing Gemini API...', 'website-ai-assistant'),
            'testSearch' => __('Testing Search API...', 'website-ai-assistant'),
            'enterQuery' => __('Please enter a search query to test.', 'website-ai-assistant'),
            'ajaxError' => __('Network error occurred. Please try again.', 'website-ai-assistant'),
            'missingSearchCreds' => __('Please enter both Google Search API key and Search Engine ID.', 'website-ai-assistant'),
        ]);
    }

    /**
     * Renders a text field for the settings page
     *
     * @param array $args Field arguments
     */
    public function render_text_field($args): void {
        $options = get_option('waa_options', []);
        $field_name = $args['field'] ?? $args['field_name'] ?? '';
        $field_type = $args['type'] ?? 'text';
        $value = $options[$field_name] ?? $args['default'] ?? '';
        ?>
        <input 
            type="<?php echo esc_attr($field_type); ?>"
            id="<?php echo esc_attr($field_name); ?>"
            name="waa_options[<?php echo esc_attr($field_name); ?>]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
        />
        <?php
    }

    /**
     * Renders a textarea field for the settings page
     *
     * @param array $args Field arguments
     */
    public function render_textarea_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $value = $options[$field] ?? '';
        echo '<textarea name="waa_options[' . esc_attr($field) . ']" class="large-text" rows="4">' . esc_textarea($value) . '</textarea>';
        if ($field === 'system_message') {
            echo '<p class="description">' . esc_html__('This message sets the AI\'s behavior and tone. Leave blank for default.', 'website-ai-assistant') . '</p>';
        }
    }

    /**
     * Renders a number field for the settings page
     *
     * @param array $args Field arguments
     */
    public function render_number_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $value = $options[$field] ?? 10;
        echo '<input type="number" name="waa_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="small-text" min="1">';
        if ($field === 'max_history') {
            echo '<p class="description">' . esc_html__('Maximum number of messages to keep in conversation history. Default is 10.', 'website-ai-assistant') . '</p>';
        }
    }

    /**
     * Renders the API settings section description
     */
    public function render_api_section(): void {
        echo '<p>' . esc_html__('Configure your AI Assistant settings below. You\'ll need a Gemini API key to use this plugin.', 'website-ai-assistant') . '</p>';
    }

    /**
     * Validates and sanitizes the options before saving
     *
     * @param array $input The raw input array
     * @return array The sanitized output array
     */
    public function sanitize_options($input) {
        // Debug logging
        error_log('Sanitizing options - Input: ' . print_r($input, true));
        
        $sanitized = [];
        
        // Handle API keys
        // Process API keys
        $api_fields = ['gemini_api_key', 'google_search_api_key', 'search_engine_id'];
        foreach ($api_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? sanitize_text_field($input[$field]) : '';
        }

        // Process lead collection settings
        $lead_fields = [
            'enable_lead_collection' => 'bool',
            'lead_collection_timing' => 'text',
            'fluentcrm_list_id' => 'int',
            'fluentcrm_tag_id' => 'int',
            'fluentcrm_status' => 'text',
            'lead_collection_heading' => 'text',
            'lead_collection_description' => 'textarea',
            'privacy_page_url' => 'url',
            'privacy_text' => 'text'
        ];

        foreach ($lead_fields as $field => $type) {
            switch ($type) {
                case 'bool':
                    $sanitized[$field] = isset($input[$field]) ? 1 : 0;
                    break;
                case 'int':
                    $sanitized[$field] = isset($input[$field]) ? absint($input[$field]) : 0;
                    break;
                case 'url':
                    $sanitized[$field] = isset($input[$field]) ? esc_url_raw($input[$field]) : '';
                    break;
                case 'textarea':
                    $sanitized[$field] = isset($input[$field]) ? sanitize_textarea_field($input[$field]) : '';
                    break;
                default:
                    $sanitized[$field] = isset($input[$field]) ? sanitize_text_field($input[$field]) : '';
            }
        }

        // Merge with existing options while preserving unrelated settings
        $existing = get_option('waa_options', []);
        // Merge with new sanitized values taking priority over existing ones
        $sanitized = array_merge($existing, $sanitized);
        $sanitized = array_merge($sanitized, $input);
        
        // Debug logging
        error_log('Sanitized options: ' . print_r($sanitized, true));
        
        return $sanitized;
    }

    public function render_display_section(): void {
        echo '<p>' . esc_html__('Configure where the chat widget should appear on your website.', 'website-ai-assistant') . '</p>';
    }

    public function render_display_locations_field(): void {
        $options = get_option('waa_options', []);
        $locations = $options['display_locations'] ?? ['all'];
        ?>
        <fieldset>
            <label>
                <input type="checkbox" name="waa_options[display_locations][]" 
                       value="all" <?php checked(in_array('all', $locations)); ?>>
                <?php esc_html_e('All Pages', 'website-ai-assistant'); ?>
            </label><br>
            
            <label>
                <input type="checkbox" name="waa_options[display_locations][]" 
                       value="home" <?php checked(in_array('home', $locations)); ?>>
                <?php esc_html_e('Homepage Only', 'website-ai-assistant'); ?>
            </label><br>
            
            <label>
                <input type="checkbox" name="waa_options[display_locations][]" 
                       value="posts" <?php checked(in_array('posts', $locations)); ?>>
                <?php esc_html_e('Posts', 'website-ai-assistant'); ?>
            </label><br>
            
            <label>
                <input type="checkbox" name="waa_options[display_locations][]" 
                       value="pages" <?php checked(in_array('pages', $locations)); ?>>
                <?php esc_html_e('Pages', 'website-ai-assistant'); ?>
            </label><br>
            
            <label>
                <input type="checkbox" name="waa_options[display_locations][]" 
                       value="products" <?php checked(in_array('products', $locations)); ?>>
                <?php esc_html_e('Products', 'website-ai-assistant'); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Select where you want the chat widget to appear. "All Pages" will override other selections.', 'website-ai-assistant'); ?>
        </p>
        <?php
    }

    public function render_lead_section(): void {
        echo '<p>' . esc_html__('Configure settings for collecting user contact information.', 'website-ai-assistant') . '</p>';
    }

    public function render_checkbox_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $checked = (isset($options[$field]) && $options[$field] == 1) ? 'checked' : '';
        
        echo '<input type="checkbox" name="waa_options[' . esc_attr($field) . ']" ' . $checked . ' value="1">';
    }

    public function render_select_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $value = $options[$field] ?? '';
        
        echo '<select name="waa_options[' . esc_attr($field) . ']">';
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    }

    /**
     * Renders the FluentCRM lists dropdown
     */
    public function render_fluentcrm_lists_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $selected_list = $options[$field] ?? '';
        
        if (!function_exists('FluentCrmApi')) {
            echo '<p class="description error">' . 
                 esc_html__('FluentCRM is not installed or activated.', 'website-ai-assistant') . 
                 '</p>';
            return;
        }

        try {
            $lists = FluentCrmApi('lists')->all();
            
            if (empty($lists)) {
                echo '<p class="description">' . 
                     esc_html__('No lists found in FluentCRM. Please create a list first.', 'website-ai-assistant') . 
                     '</p>';
                return;
            }

            echo '<select name="waa_options[fluentcrm_list_id]" class="regular-text">';
            echo '<option value="">' . esc_html__('Select a list...', 'website-ai-assistant') . '</option>';
            
            foreach ($lists as $list) {
                echo sprintf(
                    '<option value="%s" %s>%s (%d subscribers)</option>',
                    esc_attr($list->id),
                    selected($selected_list, $list->id, false),
                    esc_html($list->title),
                    intval($list->subscribers_count)
                );
            }
            
            echo '</select>';
            echo '<p class="description">' . 
                 esc_html__('Select the FluentCRM list where leads will be added.', 'website-ai-assistant') . 
                 '</p>';

        } catch (\Exception $e) {
            echo '<p class="description error">' . 
                 esc_html__('Error loading FluentCRM lists. Please check if FluentCRM is configured correctly.', 'website-ai-assistant') . 
                 '</p>';
        }
    }

    /**
     * Renders the FluentCRM tags dropdown
     */
    public function render_fluentcrm_tags_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $selected_tag = $options[$field] ?? '';
        
        if (!function_exists('FluentCrmApi')) {
            echo '<p class="description error">' . 
                 esc_html__('FluentCRM is not installed or activated.', 'website-ai-assistant') . 
                 '</p>';
            return;
        }

        try {
            $tags = FluentCrmApi('tags')->all();
            
            if (empty($tags)) {
                echo '<p class="description">' . 
                     esc_html__('No tags found in FluentCRM. Please create a tag first.', 'website-ai-assistant') . 
                     '</p>';
                return;
            }

            echo '<select name="waa_options[fluentcrm_tag_id]" class="regular-text">';
            echo '<option value="">' . esc_html__('Select a tag...', 'website-ai-assistant') . '</option>';
            
            foreach ($tags as $tag) {
                echo sprintf(
                    '<option value="%s" %s>%s (%d subscribers)</option>',
                    esc_attr($tag->id),
                    selected($selected_tag, $tag->id, false),
                    esc_html($tag->title),
                    intval($tag->subscribers_count)
                );
            }
            
            echo '</select>';
            echo '<p class="description">' . 
                 esc_html__('Select the FluentCRM tag to apply to new contacts.', 'website-ai-assistant') . 
                 '</p>';

        } catch (\Exception $e) {
            echo '<p class="description error">' . 
                 esc_html__('Error loading FluentCRM tags.', 'website-ai-assistant') . 
                 '</p>';
        }
    }

    /**
     * Renders the privacy page selector
     */
    public function render_privacy_page_field(array $args): void {
        $options = get_option('waa_options');
        $current_url = $options['privacy_page_url'] ?? '';
        
        // Get list of pages
        $pages = get_pages(['sort_column' => 'post_title']);
        
        echo '<select id="waa_privacy_page_select" name="waa_options[privacy_page_url]" class="regular-text">';
        echo '<option value="">' . esc_html__('Select a page...', 'website-ai-assistant') . '</option>';
        
        foreach ($pages as $page) {
            $page_url = get_permalink($page->ID);
            echo sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($page_url),
                selected($current_url, $page_url, false),
                esc_html($page->post_title)
            );
        }
        echo '</select>';
        
        // Custom URL input
        echo '<div style="margin-top: 10px;">';
        echo '<input type="text" id="waa_privacy_page_custom" ';
        echo 'name="waa_options[privacy_page_url_custom]" ';
        echo 'value="' . esc_attr($current_url) . '" ';
        echo 'class="regular-text" placeholder="' . esc_attr__('Or enter custom URL', 'website-ai-assistant') . '">';
        echo '</div>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Updates the system message to include website topics
     */
    private function get_system_message(): string {
        $options = get_option('waa_options', []);
        $topics = $options['website_topics'] ?? '';
        
        $system_message = __(
            'You are an AI assistant specifically focused on helping with questions about this website. ',
            'website-ai-assistant'
        );
        
        if (!empty($topics)) {
            $system_message .= sprintf(
                __('This website focuses on the following topics: %s. ', 'website-ai-assistant'),
                $topics
            );
        }
        
        $system_message .= __(
            'For questions directly related to these topics and website content, provide detailed answers with relevant links. ' .
            'For unrelated questions, politely explain that you are specifically here to help with website-related questions and provide only a brief, general response without links.',
            'website-ai-assistant'
        );
        
        return $system_message;
    }
} 