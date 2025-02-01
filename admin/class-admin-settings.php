<?php
namespace Website_Ai_Assistant\Admin;

use Website_Ai_Assistant\Models\OpenAI_Service;
use Website_Ai_Assistant\Models\Gemini_Service;
use Website_Ai_Assistant\Models\Deepseek_Service;

class Admin_Settings {
    private const OPTION_GROUP = 'waa_options';
    private const SETTINGS_PAGE = 'website-ai-assistant';
    private const TEST_PAGE = 'website-ai-assistant-test';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_waa_fetch_models', [$this, 'handle_fetch_models']);
        add_action('wp_ajax_waa_update_model', [$this, 'handle_update_model']);

        // Log the AJAX actions setup
        waa_debug_log('Setting up AJAX actions');
        
        // Initialize admin settings values and log current settings
        add_action('admin_init', function() {
            $options = get_option('waa_options', []);
            waa_debug_log('Current admin settings:', [
                'provider' => $options['ai_provider'] ?? 'not set',
                'model' => $options[$options['ai_provider'] . '_model'] ?? 'not set',
                'has_api_key' => !empty($options[$options['ai_provider'] . '_api_key']) ? 'yes' : 'no'
            ]);
        });
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
public function render_text_field($args): void {
    if (empty($args['field'])) {
        waa_debug_log("Error: Missing field name in render_text_field", $args);
        return;
    }

    $options = get_option('waa_options', []);
    $field_name = $args['field'];
    $field_type = $args['type'] ?? 'text';
    $value = $options[$field_name] ?? $args['default'] ?? '';
    $class = $args['class'] ?? '';
    
    waa_debug_log("Rendering field: $field_name", [
        'type' => $field_type,
        'value' => $value,
        'class' => $class
    ]);
    
    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <?php if ($field_type === 'select'): ?>
            <select
                name="waa_options[<?php echo esc_attr($field_name); ?>]"
                id="waa_options_<?php echo esc_attr($field_name); ?>"
                class="regular-text"
                <?php
                if (!empty($args['data'])) {
                    foreach ($args['data'] as $key => $val) {
                        echo ' data-' . esc_attr($key) . '="' . esc_attr($val) . '"';
                    }
                }
                ?>
            >
                <?php if (!empty($args['options'])): ?>
                    <?php foreach ($args['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        <?php else: ?>
            <input
                type="<?php echo esc_attr($field_type); ?>"
                id="waa_options_<?php echo esc_attr($field_name); ?>"
                name="waa_options[<?php echo esc_attr($field_name); ?>]"
                value="<?php echo esc_attr($value); ?>"
                class="regular-text"
            />
        <?php endif; ?>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

public function render_textarea_field(array $args): void {
    $options = get_option('waa_options');
    $field = $args['field'];
    $value = $options[$field] ?? '';
    echo '<textarea name="waa_options[' . esc_attr($field) . ']" class="large-text" rows="4">' . esc_textarea($value) . '</textarea>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

public function render_select_field(array $args): void {
    $options = get_option('waa_options');
    $field = $args['field'];
    $value = $options[$field] ?? $args['default'] ?? '';
    $class = $args['class'] ?? '';
    
    // Build data attributes
    $data_attrs = '';
    if (!empty($args['data'])) {
        foreach ($args['data'] as $key => $val) {
            $data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($val) . '"';
        }
    }
    
    $id = $args['id'] ?? 'waa_options_' . esc_attr($field);
    
    echo '<select name="waa_options[' . esc_attr($field) . ']" id="' . esc_attr($id) . '" class="' . esc_attr($class) . '"' . $data_attrs . '>';
    foreach ($args['options'] as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>';
        echo esc_html($label);
        echo '</option>';
    }
    echo '</select>';
    
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

public function register_settings(): void {
    // Register the settings group
    register_setting(
        'waa_options', // Option group
        'waa_options', // Option name in the database
        ['sanitize_callback' => [$this, 'sanitize_options']]
    );

    // Register settings sections
    $this->register_ai_settings();        // AI provider and model settings
    $this->register_api_settings();       // API credentials for all providers
    $this->register_search_settings();    // Search configuration
    $this->register_display_settings();   // Display options
    $this->register_lead_settings();      // Lead generation settings

    // Log registered settings for debugging
    waa_debug_log('Settings registered with the following data:', [
        'option_group' => 'waa_options',
        'sections' => [
            'ai_settings' => 'website-ai-assistant',
            'api_credentials' => 'website-ai-assistant',
            'search_settings' => 'website-ai-assistant',
            'display_settings' => 'website-ai-assistant',
            'lead_settings' => 'website-ai-assistant'
        ]
    ]);
}

private function register_lead_settings(): void {
    add_settings_section(
        'waa_lead_settings',
        __('Lead Generation Settings', 'website-ai-assistant'),
        [$this, 'render_lead_section'],
        'website-ai-assistant'
    );

    // Enable lead collection
    add_settings_field(
        'enable_lead_collection',
        __('Enable Lead Collection', 'website-ai-assistant'),
        [$this, 'render_checkbox_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'enable_lead_collection',
            'description' => __('Show lead collection form in chat widget', 'website-ai-assistant')
        ]
    );

    // Lead collection timing
    add_settings_field(
        'lead_collection_timing',
        __('When to Show Form', 'website-ai-assistant'),
        [$this, 'render_select_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'lead_collection_timing',
            'options' => [
                'immediate' => __('Immediately', 'website-ai-assistant'),
                'after_first' => __('After First Message', 'website-ai-assistant'),
                'after_two' => __('After Two Messages', 'website-ai-assistant'),
                'end' => __('After Three Messages', 'website-ai-assistant')
            ],
            'default' => 'immediate',
            'description' => __('Choose when to display the lead collection form', 'website-ai-assistant')
        ]
    );

    // Form heading
    add_settings_field(
        'lead_collection_heading',
        __('Form Heading', 'website-ai-assistant'),
        [$this, 'render_text_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'lead_collection_heading',
            'type' => 'text',
            'default' => __('Please share your contact info to continue', 'website-ai-assistant'),
            'description' => __('Heading text displayed at the top of the form', 'website-ai-assistant')
        ]
    );

    // Form description
    add_settings_field(
        'lead_collection_description',
        __('Form Description', 'website-ai-assistant'),
        [$this, 'render_text_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'lead_collection_description',
            'type' => 'text',
            'description' => __('Optional description text below the heading', 'website-ai-assistant')
        ]
    );

    // FluentCRM List selector (populated dynamically)
    add_settings_field(
        'fluentcrm_list_id',
        __('FluentCRM List', 'website-ai-assistant'),
        [$this, 'render_select_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'fluentcrm_list_id',
            'options' => $this->get_fluentcrm_lists(),
            'description' => __('Select the FluentCRM list to add contacts to', 'website-ai-assistant')
        ]
    );

    // FluentCRM Tag selector (populated dynamically)
    add_settings_field(
        'fluentcrm_tag_id',
        __('FluentCRM Tag', 'website-ai-assistant'),
        [$this, 'render_select_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'fluentcrm_tag_id',
            'options' => $this->get_fluentcrm_tags(),
            'description' => __('Select the FluentCRM tag to apply to contacts', 'website-ai-assistant')
        ]
    );

    // Contact Status
    add_settings_field(
        'fluentcrm_status',
        __('Contact Status', 'website-ai-assistant'),
        [$this, 'render_select_field'],
        'website-ai-assistant',
        'waa_lead_settings',
        [
            'field' => 'fluentcrm_status',
            'options' => [
                'subscribed' => __('Subscribed', 'website-ai-assistant'),
                'pending' => __('Pending', 'website-ai-assistant'),
                'unsubscribed' => __('Unsubscribed', 'website-ai-assistant')
            ],
            'default' => 'subscribed',
            'description' => __('Select the default status for new contacts', 'website-ai-assistant')
        ]
    );
}

public function render_lead_section(): void {
    echo '<p>' . esc_html__('Configure lead generation settings:', 'website-ai-assistant') . '</p>';
    echo '<ul class="waa-section-info">';
    echo '<li>' . esc_html__('Enable/disable lead collection form', 'website-ai-assistant') . '</li>';
    echo '<li>' . esc_html__('Choose when to show the form (immediately, after first message, etc.)', 'website-ai-assistant') . '</li>';
    echo '<li>' . esc_html__('Customize form heading and description', 'website-ai-assistant') . '</li>';
    echo '<li>' . esc_html__('Connect to FluentCRM for lead storage (make sure FluentCRM is installed)', 'website-ai-assistant') . '</li>';
    echo '<li>' . esc_html__('Select FluentCRM list and tag for organizing contacts', 'website-ai-assistant') . '</li>';
    echo '</ul>';
}


    private function register_ai_settings(): void {
        /* Original AI settings section
        add_settings_section(
            'waa_ai_settings',
            __('AI Settings', 'website-ai-assistant'),
            [$this, 'render_ai_section'],
            'website-ai-assistant'
        );

        // AI Provider selector
        add_settings_field(
            'ai_provider',
            __('AI Provider', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'ai_provider',
                'options' => $this->get_ai_provider_options(),
                'default' => 'gemini'
            ]
        );

        // AI Model selector
        add_settings_field(
            'ai_model',
            __('AI Model', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'ai_model',
                'options' => []
            ]
        );

        // System Message
        add_settings_field(
            'system_message',
            __('System Message', 'website-ai-assistant'),
            [$this, 'render_textarea_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            ['field' => 'system_message']
        );
        */

        add_settings_section(
            'waa_ai_settings',
            __('AI Settings', 'website-ai-assistant'),
            [$this, 'render_ai_section'],
            'website-ai-assistant'
        );

        // AI Provider selector
        add_settings_field(
            'ai_provider',
            __('AI Provider', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'ai_provider',
                'id' => 'waa_options_ai_provider',
                'options' => $this->get_ai_provider_options(),
                'default' => 'gemini',
                'description' => __('Select your AI provider', 'website-ai-assistant')
            ]
        );

        // OpenAI Model Selector - populated dynamically via AJAX
        add_settings_field(
            'openai_model',
            __('OpenAI Model', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'openai_model',
                'id' => 'waa_options_openai_model',
                'class' => 'openai-field model-field',
                'data' => [
                    'current-value' => get_option('waa_options')['openai_model'] ?? '',
                    'provider' => 'openai'
                ],
                'options' => array_merge(
                    ['' => __('Select OpenAI model', 'website-ai-assistant')],
                    get_option('waa_openai_models', [])
                ),
                'description' => __('Select OpenAI model', 'website-ai-assistant')
            ]
        );

        // Gemini Model Selector with predefined options
        add_settings_field(
            'gemini_model',
            __('Gemini Model', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'gemini_model',
                'id' => 'waa_options_gemini_model',
                'class' => 'gemini-field model-field',
                'data' => [
                    'current-value' => get_option('waa_options')['gemini_model'] ?? '',
                    'provider' => 'gemini'
                ],
                'options' => array_merge(
                    ['' => __('Select Gemini model', 'website-ai-assistant')],
                    \Website_Ai_Assistant\Models\Gemini_Service::AVAILABLE_MODELS
                ),
                'description' => __('Select a Gemini model to use', 'website-ai-assistant')
            ]
        );

        // Deepseek Model Selector - populated dynamically via AJAX
        add_settings_field(
            'deepseek_model',
            __('Deepseek Model', 'website-ai-assistant'),
            [$this, 'render_select_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'deepseek_model',
                'id' => 'waa_options_deepseek_model',
                'class' => 'deepseek-field model-field',
                'data' => [
                    'current-value' => get_option('waa_options')['deepseek_model'] ?? '',
                    'provider' => 'deepseek'
                ],
                'options' => array_merge(
                    ['' => __('Select Deepseek model', 'website-ai-assistant')],
                    get_option('waa_deepseek_models', [])
                ),
                'description' => __('Select Deepseek model', 'website-ai-assistant')
            ]
        );

        add_settings_field(
            'system_message',
            __('System Message', 'website-ai-assistant'),
            [$this, 'render_textarea_field'],
            'website-ai-assistant',
            'waa_ai_settings',
            [
                'field' => 'system_message',
                'description' => __('This message sets the AI\'s behavior and tone. Leave blank for default.', 'website-ai-assistant')
            ]
        );
    }

    private function register_api_settings(): void {
        // API Credentials Section
        add_settings_section(
           'api_credentials',
           __('API Credentials', 'website-ai-assistant'),
           [$this, 'render_api_section'],
           self::SETTINGS_PAGE
       );

        // OpenAI Credentials
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'api_credentials',
            [
                'field' => 'openai_api_key',
                'type' => 'password',
                'class' => 'openai-field waa-api-key-field',
                'description' => __('Enter your API key from OpenAI dashboard', 'website-ai-assistant')
            ]
        );

        // OpenAI Cache Control
        add_settings_field(
            'openai_disable_cache',
            __('Model Cache', 'website-ai-assistant'),
            [$this, 'render_checkbox_field'],
            'website-ai-assistant',
            'api_credentials',
            [
                'field' => 'openai_disable_cache',
                'class' => 'openai-field',
                'description' => __('Disable caching to fetch fresh model list from OpenAI API each time', 'website-ai-assistant')
            ]
        );

        // Deepseek API Key
        add_settings_field(
            'deepseek_api_key',
            __('Deepseek API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'api_credentials',
            [
                'field' => 'deepseek_api_key',
                'type' => 'password',
                'class' => 'deepseek-field waa-api-key-field',
                'description' => __('Enter your API key from Deepseek dashboard', 'website-ai-assistant')
            ]
        );

        // Deepseek API Endpoint
        add_settings_field(
            'deepseek_endpoint',
            __('Deepseek Endpoint', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'api_credentials',
            [
                'field' => 'deepseek_endpoint',
                'type' => 'text',
                'class' => 'deepseek-field waa-api-key-field',
                'default' => 'https://api.deepseek.com/v1/',
                'description' => __('API endpoint for Deepseek service (default: https://api.deepseek.com/v1/)', 'website-ai-assistant')
            ]
        );

        // Gemini API Key
        add_settings_field(
            'gemini_api_key',
            __('Gemini API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'website-ai-assistant',
            'api_credentials',
            [
                'field' => 'gemini_api_key',
                'type' => 'password',
                'class' => 'waa-api-key-field',
                'description' => __('Enter your API key from Google AI Studio', 'website-ai-assistant')
            ]
        );

        // Keep existing fields commented for reference
        /* Original Gemini field
        add_settings_field(
            'gemini_api_key',
            __('Gemini API Key', 'website-ai-assistant'),
            [$this, 'render_text_field'],
            'waa_api_credentials',
            'waa_api_credentials',
            [
                'field' => 'gemini_api_key',
                'type' => 'password',
                'class' => 'gemini-field waa-api-key-field'
            ]
        );
        */

    }

    private function register_search_settings(): void {
        // Search Settings Section
        add_settings_section(
            'waa_search_settings',
            __('Search Settings', 'website-ai-assistant'),
            [$this, 'render_search_section'],
            'website-ai-assistant'
        );

        // Define all search fields
        $search_fields = [
            // Search Provider
            [
                'id' => 'search_provider',
                'title' => __('Search Provider', 'website-ai-assistant'),
                'callback' => 'render_select_field',
                'args' => [
                    'field' => 'search_provider',
                    'options' => [
                        'google' => __('Google Custom Search', 'website-ai-assistant'),
                        'algolia' => __('Algolia', 'website-ai-assistant')
                    ],
                    'default' => 'google',
                    'description' => __('Select your preferred search provider', 'website-ai-assistant')
                ]
            ],
            // Google Search Settings
            [
                'id' => 'google_search_api_key',
                'title' => __('Google Search API Key', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'google_search_api_key',
                    'type' => 'password',
                    'class' => 'google-search-field waa-api-key-field',
                    'description' => __('Enter your Google Custom Search API Key', 'website-ai-assistant')
                ]
            ],
            [
                'id' => 'search_engine_id',
                'title' => __('Google Search Engine ID', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'search_engine_id',
                    'type' => 'text',
                    'class' => 'google-search-field waa-api-key-field',
                    'description' => __('Enter your Google Custom Search Engine ID', 'website-ai-assistant')
                ]
            ],
            // Algolia Settings
            [
                'id' => 'algolia_app_id',
                'title' => __('Algolia Application ID', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'algolia_app_id',
                    'type' => 'text',
                    'class' => 'algolia-field waa-api-key-field',
                    'description' => __('Enter your Algolia Application ID', 'website-ai-assistant')
                ]
            ],
            [
                'id' => 'algolia_search_key',
                'title' => __('Algolia Search API Key', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'algolia_search_key',
                    'type' => 'password',
                    'class' => 'algolia-field waa-api-key-field',
                    'description' => __('Enter your Algolia Search-Only API Key', 'website-ai-assistant')
                ]
            ],
            [
                'id' => 'algolia_admin_key',
                'title' => __('Algolia Admin API Key', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'algolia_admin_key',
                    'type' => 'password',
                    'class' => 'algolia-field waa-api-key-field',
                    'description' => __('Enter your Algolia Admin API Key', 'website-ai-assistant')
                ]
            ],
            [
                'id' => 'algolia_index',
                'title' => __('Algolia Index Name', 'website-ai-assistant'),
                'callback' => 'render_text_field',
                'args' => [
                    'field' => 'algolia_index',
                    'type' => 'text',
                    'class' => 'algolia-field waa-api-key-field',
                    'description' => __('Enter your Algolia Index Name (e.g., website_contentsearchable_posts)', 'website-ai-assistant')
                ]
            ]
        ];

        // Register all search fields
        foreach ($search_fields as $field) {
            add_settings_field(
                $field['id'],
                $field['title'],
                [$this, $field['callback']],
                'website-ai-assistant',
                'waa_search_settings',
                $field['args']
            );
        }
    }

    private function register_display_settings(): void {
        add_settings_section(
            'waa_display_settings',
            __('Display Settings', 'website-ai-assistant'),
            [$this, 'render_display_section'],
            'website-ai-assistant'
        );

        add_settings_field(
            'display_locations',
            __('Show Chat Widget On', 'website-ai-assistant'),
            [$this, 'render_display_locations_field'],
            'website-ai-assistant',
            'waa_display_settings'
        );

        add_settings_field(
            'enable_debug',
            __('Enable Debug Logging', 'website-ai-assistant'),
            [$this, 'render_checkbox_field'],
            'website-ai-assistant',
            'waa_display_settings',
            [
                'field' => 'enable_debug',
                'description' => __('Log debug information', 'website-ai-assistant')
            ]
        );
    }

    private function get_ai_provider_options(): array {
        return [
            'gemini' => __('Google Gemini AI', 'website-ai-assistant'),
            'openai' => __('OpenAI', 'website-ai-assistant'),
            'deepseek' => __('Deepseek', 'website-ai-assistant')
        ];
    }

    private function get_fluentcrm_lists(): array {
        if (!function_exists('FluentCrmApi')) {
            return ['' => __('FluentCRM not installed', 'website-ai-assistant')];
        }

        try {
            $listApi = FluentCrmApi('lists');
            if (!$listApi) {
                return ['' => __('FluentCRM API not available', 'website-ai-assistant')];
            }

            $lists = $listApi->all();
            if (empty($lists)) {
                return ['' => __('No lists found', 'website-ai-assistant')];
            }

            $options = ['' => __('Select a list', 'website-ai-assistant')];
            foreach ($lists as $list) {
                $options[$list->id] = esc_html($list->title);
            }
            return $options;
        } catch (\Exception $e) {
            error_log('Website AI Assistant Error: ' . $e->getMessage());
            return ['' => __('Error loading lists', 'website-ai-assistant')];
        }
    }

    private function get_fluentcrm_tags(): array {
        if (!function_exists('FluentCrmApi')) {
            return ['' => __('FluentCRM not installed', 'website-ai-assistant')];
        }

        try {
            $tagApi = FluentCrmApi('tags');
            if (!$tagApi) {
                return ['' => __('FluentCRM API not available', 'website-ai-assistant')];
            }

            $tags = $tagApi->all();
            if (empty($tags)) {
                return ['' => __('No tags found', 'website-ai-assistant')];
            }

            $options = ['' => __('Select a tag', 'website-ai-assistant')];
            foreach ($tags as $tag) {
                $options[$tag->id] = esc_html($tag->title);
            }
            return $options;
        } catch (\Exception $e) {
            error_log('Website AI Assistant Error: ' . $e->getMessage());
            return ['' => __('Error loading tags', 'website-ai-assistant')];
        }
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once WAA_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_ai_section(): void {
        echo '<p>' . esc_html__('Configure your AI settings below:', 'website-ai-assistant') . '</p>';
        echo '<ul class="waa-section-info">';
        echo '<li>' . esc_html__('Select your preferred AI provider', 'website-ai-assistant') . '</li>';
        echo '<li>' . esc_html__('Enter the required API credentials', 'website-ai-assistant') . '</li>';
        echo '<li>' . esc_html__('Choose the model you want to use', 'website-ai-assistant') . '</li>';
        echo '</ul>';
    }

    public function render_api_section(): void {
        echo '<p>' . esc_html__('API Credentials:', 'website-ai-assistant') . '</p>';
        echo '<ul class="waa-section-info">';
        echo '<li>' . esc_html__('Gemini: Get your API key from Google AI Studio', 'website-ai-assistant') . '</li>';
        echo '<li>' . esc_html__('OpenAI: Get your API key from OpenAI dashboard', 'website-ai-assistant') . '</li>';
        echo '<li>' . esc_html__('Deepseek: Get your API key from Deepseek platform', 'website-ai-assistant') . '</li>';
        echo '</ul>';
    }

    public function render_search_section(): void {
        echo '<p>' . esc_html__('Search Provider Settings:', 'website-ai-assistant') . '</p>';
        echo '<ul class="waa-section-info">';
        echo '<li>' . esc_html__('Google: Custom Search requires API key and Engine ID', 'website-ai-assistant') . '</li>';
        echo '<li>' . esc_html__('Algolia: Uses existing WordPress Algolia plugin settings', 'website-ai-assistant') . '</li>';
        echo '</ul>';
    }

    public function render_display_section(): void {
        echo '<p>' . esc_html__('Configure where the chat widget should appear.', 'website-ai-assistant') . '</p>';
    }


    /* Commented out duplicate method - preserved for reference
    public function render_textarea_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $value = $options[$field] ?? '';
        echo '<textarea name="waa_options[' . esc_attr($field) . ']" class="large-text" rows="4">' . esc_textarea($value) . '</textarea>';
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    */

    public function handle_fetch_models(): void {
        error_log('Starting handle_fetch_models...');
        check_ajax_referer('waa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('Unauthorized access attempt in handle_fetch_models');
            wp_send_json_error(['message' => __('Unauthorized access', 'website-ai-assistant')]);
            return;
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        waa_debug_log('handle_fetch_models called', [
            'provider' => $provider,
            'has_api_key' => !empty($api_key),
            'api_key_length' => strlen($api_key),
            'post_data' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]);
        
        $models = [];
        
        switch ($provider) {
            case 'gemini':
                $models = \Website_Ai_Assistant\Models\Gemini_Service::AVAILABLE_MODELS;
                update_option('waa_gemini_models', $models);
                waa_debug_log('Using static Gemini models');
                break;
            case 'openai':
                try {
                    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
                    waa_debug_log('OpenAI: Processing model fetch request');
                    
                    waa_debug_log('OpenAI: Processing fetch models request', [
                        'has_api_key' => !empty($api_key),
                        'api_key_length' => strlen($api_key),
                        'api_key_preview' => substr($api_key, 0, 10) . '...'
                    ]);

                    // Check for cached models first
                    $cached_models = get_option('waa_openai_models', []);
                    $cached_key_hash = get_option('waa_openai_key_hash', '');
                    $current_key_hash = md5($api_key);
        
                    // Use cached models if available and key matches
                    if (!empty($cached_models) && $cached_key_hash === $current_key_hash) {
                        $models = $cached_models;
                        waa_debug_log('OpenAI: Using cached models');
                        break;
                    }
        
                    if (empty($api_key)) {
                        throw new \Exception(__('API key is required', 'website-ai-assistant'));
                    }
        
                    // If no cache hit, determine whether to use cache for fetching
                    $options = get_option('waa_options', []);
                    $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
                    $use_cache = !$force_refresh && empty($options['openai_disable_cache']);

                    waa_debug_log('OpenAI: Cache Settings:', [
                        'use_cache' => $use_cache,
                        'force_refresh' => $force_refresh ? 'yes' : 'no',
                        'disable_cache_setting' => !empty($options['openai_disable_cache']) ? 'yes' : 'no'
                    ]);
                    
                    if ($use_cache) {
                        $cached_models = get_option('waa_openai_models', []);
                        $cached_key_hash = get_option('waa_openai_key_hash', '');
                        $current_key_hash = md5($api_key);

                        waa_debug_log('OpenAI: Cache status', [
                            'has_cached_models' => !empty($cached_models),
                            'models_count' => count($cached_models),
                            'cache_key_match' => $cached_key_hash === $current_key_hash,
                            'current_key_hash' => $current_key_hash
                        ]);

                        // Use cached models if available and key matches
                        if ($cached_models && $cached_key_hash === $current_key_hash) {
                            $models = $cached_models;
                            waa_debug_log('OpenAI: Using cached models');
                            break;
                        }
                    }
    
                    // If we get here, either cache is disabled or we need fresh models
                    waa_debug_log('OpenAI: Fetching fresh models from API');
                    $openai_service = new OpenAI_Service($api_key);
                    $models = $openai_service->get_available_models();
    
                    // Cache the models unless this was a forced refresh
                    if (!$force_refresh && !empty($models)) {
                        update_option('waa_openai_models', $models);
                        update_option('waa_openai_key_hash', md5($api_key));
                        waa_debug_log('OpenAI: Cached new models');
                    }

                    // Set default model if none selected
                    if (!empty($models)) {
                        $options = get_option('waa_options', []);
                        if (empty($options['openai_model'])) {
                            $first_model = array_key_first($models);
                            $options['openai_model'] = $first_model;
                            update_option('waa_options', $options);
                            waa_debug_log('OpenAI: Set default model:', $first_model);
                        }
                    }
                } catch (\Exception $e) {
                    wp_send_json_error(['message' => $e->getMessage()]);
                    return;
                }
                break;
            case 'deepseek':
                // Check for cached models first
                $cached_models = get_option('waa_deepseek_models', []);
                $cached_key_hash = get_option('waa_deepseek_key_hash', '');
                $api_key = sanitize_text_field($_POST['api_key'] ?? '');
                $current_key_hash = md5($api_key);

                // Use cached models if available and key matches
                if (!empty($cached_models) && $cached_key_hash === $current_key_hash) {
                    $models = $cached_models;
                    waa_debug_log('Deepseek: Using cached models');
                    break;
                }

                // If no cache hit, validate API key and fetch models
                if (empty($api_key)) {
                    throw new \Exception(__('API key is required', 'website-ai-assistant'));
                }

                try {
                    $deepseek_service = new Deepseek_Service($api_key, Deepseek_Service::API_ENDPOINT);
                    $models = $deepseek_service->get_available_models();
                    
                    // Cache the models and API key hash
                    update_option('waa_deepseek_models', $models);
                    update_option('waa_deepseek_key_hash', $current_key_hash);
                    waa_debug_log('Deepseek: Cached new models');
                } catch (\Exception $e) {
                    wp_send_json_error(['message' => $e->getMessage()]);
                    return;
                }
                break;
            default:
                wp_send_json_error(['message' => __('Invalid provider', 'website-ai-assistant')]);
                return;
        }
        
        wp_send_json_success(['models' => $models]);
    }

    /* Commented out duplicate method - preserved for reference
    public function render_select_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $value = $options[$field] ?? $args['default'] ?? '';
        
        echo '<select name="waa_options[' . esc_attr($field) . ']" id="waa_options_' . esc_attr($field) . '">';
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    */

    public function render_checkbox_field(array $args): void {
        $options = get_option('waa_options');
        $field = $args['field'];
        $checked = isset($options[$field]) && $options[$field] ? 'checked' : '';
        
        echo '<input type="checkbox" name="waa_options[' . esc_attr($field) . ']" ' . $checked . ' value="1">';
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
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
                <?php esc_html_e('Home Page', 'website-ai-assistant'); ?>
            </label><br>
            <label>
                <input type="checkbox" name="waa_options[display_locations][]"
                       value="posts" <?php checked(in_array('posts', $locations)); ?>>
                <?php esc_html_e('Blog Posts', 'website-ai-assistant'); ?>
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
        <?php
    }

    public function enqueue_admin_assets($hook): void {
        // Only load on our settings page
        if ('settings_page_website-ai-assistant' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'waa-admin-styles',
            WAA_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            WAA_VERSION
        );

        wp_enqueue_script(
            'waa-admin-scripts',
            WAA_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery'],
            WAA_VERSION,
            true
        );

        wp_localize_script('waa-admin-scripts', 'waaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('waa_admin_nonce'),
            'i18n' => [
                'loadingModels' => __('Loading available models...', 'website-ai-assistant'),
                'enterApiKey' => __('Please enter API key first', 'website-ai-assistant'),
                'selectModel' => __('Select a model', 'website-ai-assistant'),
                'fetchError' => __('Failed to fetch models', 'website-ai-assistant'),
                'noModelsFound' => __('No available models found', 'website-ai-assistant'),
                'fetchingModels' => __('Fetching available models...', 'website-ai-assistant')
            ]
        ]);
    }

    public function sanitize_options($input): array {
        waa_debug_log('Sanitizing input options:', [
            'ai_provider' => $input['ai_provider'] ?? 'not set',
            'search_provider' => $input['search_provider'] ?? 'not set',
            'fields_present' => array_keys($input)
        ]);

        // Get existing options to preserve any values not in current input
        $existing = get_option('waa_options', []);
        $sanitized = $existing;

        // Handle search provider changes
        if (isset($input['search_provider'])) {
            $search_provider = sanitize_text_field($input['search_provider']);
            if (in_array($search_provider, ['google', 'algolia'])) {
                $old_provider = $existing['search_provider'] ?? 'google';
                $sanitized['search_provider'] = $search_provider;
                
                waa_debug_log('Search provider updated:', [
                    'from' => $old_provider,
                    'to' => $search_provider
                ]);

                // Only clear settings if they're explicitly empty in the input
                if ($search_provider === 'google') {
                    // When switching to Google, preserve existing Algolia settings
                    $sanitized['google_search_api_key'] = $input['google_search_api_key'] ?? $existing['google_search_api_key'] ?? '';
                    $sanitized['search_engine_id'] = $input['search_engine_id'] ?? $existing['search_engine_id'] ?? '';
                } else if ($search_provider === 'algolia') {
                    // When switching to Algolia, preserve existing Google settings
                    $sanitized['algolia_app_id'] = $input['algolia_app_id'] ?? $existing['algolia_app_id'] ?? '';
                    $sanitized['algolia_search_key'] = $input['algolia_search_key'] ?? $existing['algolia_search_key'] ?? '';
                    $sanitized['algolia_admin_key'] = $input['algolia_admin_key'] ?? $existing['algolia_admin_key'] ?? '';
                    $sanitized['algolia_index'] = $input['algolia_index'] ?? $existing['algolia_index'] ?? '';
                }
                
                waa_debug_log('Settings after provider change:', [
                    'provider' => $search_provider,
                    'has_google_key' => isset($sanitized['google_search_api_key']),
                    'has_algolia_key' => isset($sanitized['algolia_search_key'])
                ]);
            }
        }
        
        $field_types = [
            'url' => ['deepseek_endpoint'],
            'key' => [
                'gemini_api_key', 'openai_api_key', 'deepseek_api_key',
                'google_search_api_key', 'search_engine_id',
                'algolia_app_id', 'algolia_search_key', 'algolia_admin_key'
            ],
            'text' => ['algolia_index']
        ];

        // Process each field type
        foreach ($field_types as $type => $fields) {
            foreach ($fields as $field) {
                if (!isset($input[$field])) continue;

                switch ($type) {
                    case 'url':
                        $sanitized[$field] = esc_url_raw(rtrim($input[$field], '/') . '/');
                        break;
                    case 'key':
                    case 'text':
                        $sanitized[$field] = sanitize_text_field($input[$field]);
                        if ($type === 'text') {
                            waa_debug_log("Sanitizing text field: $field", [
                                'input_value' => $input[$field],
                                'sanitized_value' => $sanitized[$field]
                            ]);
                        }
                        break;
                }
            }
        }

        // Provider and model fields
        $provider_model_fields = [
            'ai_provider',
            'openai_model',
            'gemini_model',
            'deepseek_model',
            'search_provider',
            'system_message'
        ];

        foreach ($provider_model_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
                waa_debug_log("Sanitized $field:", $sanitized[$field]);
            } else {
                waa_debug_log("Field not found in input: $field");
                $sanitized[$field] = '';
            }
        }

        // Add special handling for provider-specific model fields
        $provider = $sanitized['ai_provider'] ?? '';
        if ($provider) {
            $model_field = $provider . '_model';
            waa_debug_log("Checking provider-specific model field: $model_field", [
                'value' => $sanitized[$model_field] ?? 'not set',
                'provider' => $provider
            ]);
        }

        // Handle display locations array
        if (isset($input['display_locations']) && is_array($input['display_locations'])) {
            $sanitized['display_locations'] = array_map('sanitize_text_field', $input['display_locations']);
        } else {
            $sanitized['display_locations'] = ['all']; // Default to all pages if not set
        }

        // Handle debug option
        $sanitized['enable_debug'] = isset($input['enable_debug']) ? 1 : 0;

        // Sanitize lead generation fields
        $lead_fields = [
            'enable_lead_collection' => 'bool',
            'lead_collection_timing' => 'text',
            'lead_collection_heading' => 'text',
            'lead_collection_description' => 'text',
            'fluentcrm_list_id' => 'int',
            'fluentcrm_tag_id' => 'int',
            'fluentcrm_status' => 'text'
        ];

        foreach ($lead_fields as $field => $type) {
            if (isset($input[$field])) {
                switch ($type) {
                    case 'bool':
                        $sanitized[$field] = (bool) $input[$field];
                        break;
                    case 'int':
                        $sanitized[$field] = absint($input[$field]);
                        break;
                    case 'text':
                        $sanitized[$field] = sanitize_text_field($input[$field]);
                        break;
                }
            }
        }

        return $sanitized;
    }

}
