<?php
namespace Website_Ai_Assistant;

class Activator {
    public static function activate(): void {
        waa_debug_log("Website AI Assistant: Activation started");
        
        try {
            waa_debug_log("Class namespace: " . __NAMESPACE__);
            
            // Get existing options
            $existing_options = get_option('waa_options', []);
            waa_debug_log("Existing options: " . print_r($existing_options, true));
            
            // Add or update default options
            if (true) {
                waa_debug_log("Adding default options");
                
                $default_options = [
                    'ai_provider' => 'gemini',
                    'system_message' => __(
                        'You are an AI assistant specifically focused on helping with questions about this website. ' .
                        'For questions directly related to the website content, provide detailed answers with relevant links. ' .
                        'For unrelated questions, politely explain that you are specifically here to help with website-related questions and provide only a brief, general response without links. ' .
                        'Provide direct, concise answers without suggesting related articles or links unless specifically relevant to the website content.',
                        'website-ai-assistant'
                    ),
                    'max_history' => 10,
                    'enable_debug' => false,
                    'search_provider' => 'google',
                    
                    // API Keys
                    'gemini_api_key' => '',
                    'openai_api_key' => '',
                    'deepseek_api_key' => '',
                    'google_search_api_key' => '',
                    'search_engine_id' => '',
                    'algolia_app_id' => '',
                    'algolia_search_key' => '',
                    'algolia_admin_key' => '',
                    'algolia_index' => '',
                    
                    // Model Defaults
                    'gemini_model' => 'gemini-1.5-flash',
                    'openai_model' => '',
                    'deepseek_model' => '',
                    
                    // Display Settings
                    'display_locations' => ['all'],
                    
                    // Lead Generation
                    'enable_lead_collection' => false,
                    'lead_collection_timing' => 'after_first',
                    'fluentcrm_list_id' => 0,
                    'fluentcrm_tag_id' => 0
                ];

                // Store default Gemini models
                $gemini_models = [
                    'gemini-2.0-flash-exp' => __('Gemini 2.0 Flash Experimental', 'website-ai-assistant'),
                    'gemini-1.5-flash' => __('Gemini 1.5 Flash', 'website-ai-assistant'),
                    'gemini-1.5-flash-8b' => __('Gemini 1.5 Flash 8B', 'website-ai-assistant'),
                    'gemini-1.5-pro' => __('Gemini 1.5 Pro', 'website-ai-assistant'),
                    'gemini-1.0-pro' => __('Gemini 1.0 Pro', 'website-ai-assistant'),
                    'text-embedding-004' => __('Text Embedding 004', 'website-ai-assistant')
                ];
                update_option('waa_gemini_models', $gemini_models);
                
                // Merge existing options with defaults
                $merged_options = array_merge($default_options, $existing_options);
                $result = update_option('waa_options', $merged_options);
                waa_debug_log("Add option result: " . var_export($result, true));
            }

            // Flush rewrite rules
            flush_rewrite_rules();
            waa_debug_log("Website AI Assistant: Activation completed");
            
        } catch (\Exception $e) {
            waa_debug_log("Website AI Assistant Activation Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
} 