<?php
declare(strict_types=1);

/**
 * Plugin Name: Website AI Assistant
 * Plugin URI:
 * Description: An AI-powered chat assistant for WordPress websites using Google's Gemini API
 * Version: 3.0.0
 * Author: Gerald Haygood
 * Author URI:
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: website-ai-assistant
 * Domain Path: /languages
 *
 * @package WebsiteAiAssistant
 */

namespace Website_Ai_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

define('WAA_VERSION', '2.2.5');
define('WAA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WAA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Website_Ai_Assistant\\';
    
    // Check if the class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = str_replace('\\', '/', $relative_class);

    // Map namespace parts to directories
    if (strpos($file, 'Admin/') === 0) {
        $file = WAA_PLUGIN_DIR . 'admin/class-' . strtolower(str_replace('Admin/', '', $file)) . '.php';
    } elseif (strpos($file, 'Models/') === 0) {
        $file = WAA_PLUGIN_DIR . 'includes/models/class-' . strtolower(str_replace('Models/', '', $file)) . '.php';
    } else {
        $file = WAA_PLUGIN_DIR . 'includes/class-' . strtolower($file) . '.php';
    }

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load activation files
if (!class_exists('Website_Ai_Assistant\Activator')) {
    require_once WAA_PLUGIN_DIR . 'includes/class-activator.php';
}
if (!class_exists('Website_Ai_Assistant\Deactivator')) {
    require_once WAA_PLUGIN_DIR . 'includes/class-deactivator.php';
}

// Then the activation hooks
register_activation_hook(__FILE__, ['Website_Ai_Assistant\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Website_Ai_Assistant\Deactivator', 'deactivate']);

// Load Debug_Logger first since it's used by functions.php
require_once WAA_PLUGIN_DIR . 'includes/class-debug-logger.php';
require_once WAA_PLUGIN_DIR . 'includes/functions.php';

/**
 * Main plugin class
 */
final class Website_AI_Assistant {
    private static ?Website_AI_Assistant $instance = null;
    
    private function __construct() {
        waa_debug_log("Constructor called");
        
        // Load plugin dependencies
        $this->load_dependencies();
        
        // Initialize admin settings if in admin area
        if (is_admin()) {
            waa_debug_log("Initializing admin settings");
            $admin_settings = new Admin\Admin_Settings();
        }

        try {
            // Initialize chat handler
            new Chat_Handler();

            // Initialize lead handler
            new Lead_Handler();
        } catch (\Exception $e) {
            waa_debug_log('Error initializing handlers: ' . $e->getMessage());
            // Continue plugin initialization despite Algolia error
        }

        // Add actions
        add_action('plugins_loaded', [$this, 'load_plugin_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('wp_footer', [$this, 'render_chat_widget']);
    }

    public static function get_instance(): Website_AI_Assistant {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
private function load_dependencies(): void {
    // Core utilities
    require_once WAA_PLUGIN_DIR . 'includes/class-debug-logger.php';
    
    // Admin classes
    require_once WAA_PLUGIN_DIR . 'admin/class-admin-settings.php';
    
    // Model classes
    require_once WAA_PLUGIN_DIR . 'includes/models/class-base-model-service.php';
    require_once WAA_PLUGIN_DIR . 'includes/models/class-openai-service.php';
    require_once WAA_PLUGIN_DIR . 'includes/models/class-gemini-service.php';
    require_once WAA_PLUGIN_DIR . 'includes/models/class-deepseek-service.php';
    
    // Service classes
    require_once WAA_PLUGIN_DIR . 'includes/class-algolia-service.php';
    require_once WAA_PLUGIN_DIR . 'includes/class-chat-handler.php';
    
    // Lead handler
    require_once WAA_PLUGIN_DIR . 'includes/class-lead-handler.php';
        require_once WAA_PLUGIN_DIR . 'includes/class-lead-handler.php';
    }

    public function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            'website-ai-assistant',
            false,
            dirname(WAA_PLUGIN_BASENAME) . '/languages/'
        );
    }

    public function enqueue_public_assets(): void {
        wp_enqueue_style(
            'waa-chat-interface',
            WAA_PLUGIN_URL . 'public/css/chat-interface.css',
            [],
            WAA_VERSION
        );

        wp_enqueue_script(
            'waa-chat-interface',
            WAA_PLUGIN_URL . 'public/js/chat-interface.js',
            ['jquery'],
            WAA_VERSION,
            true
        );

        $options = get_option('waa_options', []);
        waa_debug_log('Lead Collection Settings: ' . print_r([
            'enabled' => !empty($options['enable_lead_collection']),
            'timing' => $options['lead_collection_timing'] ?? 'immediate'
        ], true));

        wp_localize_script('waa-chat-interface', 'waaData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('waa_chat_nonce'),
            'enableLeadCollection' => !empty($options['enable_lead_collection']),
            'leadCollectionTiming' => $options['lead_collection_timing'] ?? 'immediate',
            'emailRequired' => __('Please enter your email address.', 'website-ai-assistant'),
            'ajaxError' => __('An error occurred. Please try again.', 'website-ai-assistant'),
            'privacyUrl' => $options['privacy_page_url'] ?? '',
            'privacyText' => $options['privacy_text'] ?? __('By continuing, you agree to our {privacy_policy}.', 'website-ai-assistant')
        ]);
    }

    public function render_chat_widget(): void {
        // Get display settings
        $options = get_option('waa_options', []);
        waa_debug_log('Privacy Settings: ' . print_r([
            'privacy_url' => $options['privacy_page_url'] ?? 'not set',
            'privacy_text' => $options['privacy_text'] ?? 'not set'
        ], true));

        $locations = $options['display_locations'] ?? ['all'];

        // Check if we should display the widget
        if (!$this->should_display_chat($locations)) {
            return;
        }

        require_once WAA_PLUGIN_DIR . 'public/templates/chat-widget.php';
    }

    private function should_display_chat(array $locations): bool {
        // If 'all' is selected, always show
        if (in_array('all', $locations)) {
            return true;
        }

        // Check specific locations
        if (in_array('home', $locations) && is_front_page()) {
            return true;
        }

        if (in_array('posts', $locations) && is_single() && !is_singular('product')) {
            return true;
        }

        if (in_array('pages', $locations) && is_page()) {
            return true;
        }

        // Check for WooCommerce product pages
        if (in_array('products', $locations) && is_singular('product')) {
            return true;
        }

        return false;
    }
}

// Initialize the plugin
Website_AI_Assistant::get_instance(); 

add_action('wp_ajax_waa_test_api', function() {
    check_ajax_referer('waa_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access', 'website-ai-assistant')]);
    }
    
    $api_type = sanitize_text_field($_POST['api_type'] ?? '');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    
    if ($api_type === 'gemini') {
        // Test Gemini API
        try {
            // Make a simple test call to Gemini API
            $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'contents' => [
                        ['parts' => [['text' => 'Say "Hello, testing Gemini API!"']]]
                    ]
                ])
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['error'])) {
                throw new Exception($body['error']['message']);
            }
            
            wp_send_json_success(['message' => __('Gemini API connection successful!', 'website-ai-assistant')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    } elseif ($api_type === 'search') {
        // Test Search API
        $search_engine_id = sanitize_text_field($_POST['search_engine_id'] ?? '');
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            $url = add_query_arg([
                'key' => $api_key,
                'cx' => $search_engine_id,
                'q' => $query
            ], 'https://www.googleapis.com/customsearch/v1');
            
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['error'])) {
                throw new Exception($body['error']['message']);
            }
            
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: 1: search query, 2: number of results */
                    __('Search API connection successful! Found %2$d results for "%1$s".', 'website-ai-assistant'),
                    $query,
                    $body['searchInformation']['totalResults'] ?? 0
                )
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    } else {
        wp_send_json_error(['message' => __('Invalid API type', 'website-ai-assistant')]);
    }
}); 
