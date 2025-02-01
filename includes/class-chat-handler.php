<?php
namespace Website_Ai_Assistant;

class Chat_Handler {
    private $gemini_api_key;
    private $search_api_key;
    private $search_engine_id;
    private $search_provider;
    private $system_message;
    private $max_history;
    private $conversation;
    private $search_results = [];
    private $algolia_service;
    private $lead_handler;
    private $logger;

    public function __construct() {
        $this->logger = Debug_Logger::get_instance()->set_context('Chat');
        $this->lead_handler = new Lead_Handler();
        
        // Get plugin options
        $options = get_option('waa_options', []);
        $this->logger->info('Chat Handler options:', [
            'search_provider' => $options['search_provider'] ?? 'not set',
            'has_google_key' => !empty($options['google_search_api_key']),
            'has_search_engine_id' => !empty($options['search_engine_id']),
            'has_algolia_app_id' => !empty($options['algolia_app_id']),
            'has_algolia_search_key' => !empty($options['algolia_search_key'])
        ]);

        // Initialize based on selected provider
        $this->search_provider = $options['search_provider'] ?? 'google';
        $this->search_api_key = $options['google_search_api_key'] ?? '';
        $this->search_engine_id = $options['search_engine_id'] ?? '';
        $this->system_message = ($options['system_message'] ?? '') . "\n\n" .
            "Please format your responses in HTML. For links, use: " .
            "<a href='URL' target='_blank' rel='noopener noreferrer'>TITLE</a>";
        $this->max_history = $options['max_history'] ?? 10;

        // Initialize credentials
        $this->algolia_service = null;
        $this->validate_search_settings();

        // Log initialization settings
        $this->logger->section('INITIALIZATION');
        $this->logger->info('Search settings:', [
            'provider' => $this->search_provider,
            'google_key_set' => !empty($this->search_api_key),
            'engine_id_set' => !empty($this->search_engine_id)
        ]);

        if ($this->search_provider === 'algolia') {
            $this->algolia_service = new Algolia_Service();
            $this->logger->info('Algolia service initialized');
        }

        // Add AJAX handlers
        add_action('wp_ajax_waa_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_waa_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_waa_clear_history', [$this, 'clear_chat_history']);
        add_action('wp_ajax_nopriv_waa_clear_history', [$this, 'clear_chat_history']);
    }

    private function validate_search_settings(): void {
        if ($this->search_provider === 'google') {
            if (empty($this->search_api_key) || empty($this->search_engine_id)) {
                $this->logger->error('Google Search credentials missing');
            }
        } elseif ($this->search_provider === 'algolia') {
            $options = get_option('waa_options', []);
            if (empty($options['algolia_app_id']) || empty($options['algolia_search_key'])) {
                $this->logger->error('Algolia credentials missing');
            }
        }
    }

    private function get_search_results(string $query): string {
        if ($this->search_provider === 'algolia') {
            // Validate Algolia credentials
            $options = get_option('waa_options', []);
            if (empty($options['algolia_app_id']) || empty($options['algolia_search_key'])) {
                $this->logger->error('Algolia credentials missing');
                return '';
            }

            // Initialize Algolia service if not already done
            if (!$this->algolia_service) {
                $this->algolia_service = new Algolia_Service();
            }

            $this->logger->info('Searching with Algolia:', ['query' => $query]);
            $this->search_results = $this->algolia_service->get_search_results($query);
            
            if (empty($this->search_results)) {
                $this->logger->info('No Algolia results found');
                return "No relevant content found on this website. Please respond with: 'I apologize, but I'm specifically designed to help with questions about this website's content.'";
            }

            $this->logger->info('Algolia search complete', ['results_count' => count($this->search_results)]);
        } else {
            // Use Google search
            if (empty($this->search_api_key) || empty($this->search_engine_id)) {
                $this->logger->error('Google Search credentials missing');
                return '';
            }

            try {
                $this->logger->info('Searching with Google:', ['query' => $query]);
                $url = add_query_arg([
                    'key' => $this->search_api_key,
                    'cx' => $this->search_engine_id,
                    'q' => $query,
                    'num' => 5
                ], 'https://www.googleapis.com/customsearch/v1');

                $response = wp_remote_get($url);
                $this->logger->debug('Google Search raw response:', [
                    'body' => wp_remote_retrieve_body($response)
                ]);
                
                if (is_wp_error($response)) {
                    throw new \Exception($response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($body['error'])) {
                    throw new \Exception($body['error']['message']);
                }

                if (!isset($body['items']) || empty($body['items'])) {
                    $this->logger->info('No Google results found');
                    $this->search_results = [];
                    return "No relevant content found on this website. Please respond with: 'I apologize, but I'm specifically designed to help with questions about this website's content.'";
                }

                // Store search results for later use
                $this->search_results = array_map(function($item) {
                    return [
                        'title' => $item['title'],
                        'snippet' => $item['snippet'],
                        'link' => $item['link']
                    ];
                }, $body['items']);

                $this->logger->info('Google search complete', ['results_count' => count($this->search_results)]);
            } catch (\Exception $e) {
                $this->logger->exception($e, 'Google search error');
                return '';
            }
        }
            
        // Format the context with the search results
        $context = "Here are relevant articles from our website. Provide your response in HTML format. " .
                   "For links, use: <a href='URL' target='_blank' rel='noopener noreferrer'>TITLE</a>\n\n";

        $this->logger->debug('Building context', ['results_count' => count($this->search_results)]);
        
        foreach ($this->search_results as $index => $item) {
            $article_context = "Article {$index}:\n";
            $article_context .= "Title: {$item['title']}\n";
            $article_context .= "Snippet: {$item['snippet']}\n";
            $article_context .= "URL: {$item['link']}\n\n";
            
            $context .= $article_context;
            $this->logger->debug('Added article', [
                'index' => $index,
                'title' => $item['title']
            ]);
        }

        $this->logger->debug('Context built', ['length' => strlen($context)]);
        return $context;
    }

    public function handle_chat_message(): void {
        check_ajax_referer('waa_chat_nonce', 'nonce');

        $this->logger->info('Processing message');

        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($message)) {
            wp_send_json_error(['message' => __('Message cannot be empty.', 'website-ai-assistant')]);
        }

        $options = get_option('waa_options', []);
        $provider = $options['ai_provider'] ?? 'gemini';
        
        $this->logger->info('AI provider settings:', [
            'provider' => $provider,
            'available_options' => array_keys($options),
            'has_model' => isset($options[$provider . '_model']),
            'model' => $options[$provider . '_model'] ?? 'not set',
            'has_key' => isset($options[$provider . '_api_key']),
            'all_settings' => $options
        ]);
        
        // Validate API key based on provider
        $api_key = $options[$provider . '_api_key'] ?? '';
        if (empty($api_key)) {
            $this->logger->error($provider . ' API key not configured');
            wp_send_json_error(['message' => __($provider . ' API key is not configured.', 'website-ai-assistant')]);
        }

        try {
            try {
                // Initialize conversation
                $this->conversation = new Conversation($session_id, $this->max_history);
                $this->logger->debug('Conversation initialized:', [
                    'session_id' => $session_id,
                    'max_history' => $this->max_history
                ]);
                
                // Get search results for context
                $this->logger->debug('Getting search results for:', [
                    'query' => $message,
                    'search_provider' => $this->search_provider
                ]);
                
                $search_context = $this->get_search_results($message);
                
                $this->logger->debug('Search results retrieved:', [
                    'has_results' => !empty($search_context),
                    'context_length' => strlen($search_context)
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error('Setup error:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            // Add system message with specific instructions
            $system_message = $this->system_message . "\n\n" .
                "When providing answers:\n" .
                "1. ONLY use the exact URLs provided in the search results - never create or modify URLs\n" .
                "2. If search results are available, incorporate the exact article links naturally in your response\n" .
                "3. If no search results are found, explain that you can only help with website-related questions\n" .
                "4. Be conversational and helpful while staying strictly within the provided content\n";

            $this->conversation->add_message('model', $system_message);

            // Add search context if available
            if (!empty($search_context)) {
                $this->conversation->add_message('model', $search_context);
            }

            // Add user message
            $this->conversation->add_message('user', $message);

            // Get the appropriate service based on provider
            switch ($provider) {
                case 'openai':
                    $service = new \Website_Ai_Assistant\Models\OpenAI_Service($api_key);
                    break;
                case 'gemini':
                    $service = new \Website_Ai_Assistant\Models\Gemini_Service($api_key);
                    break;
                case 'deepseek':
                    $endpoint = $options['deepseek_endpoint'] ?? \Website_Ai_Assistant\Models\Deepseek_Service::API_ENDPOINT;
                    $service = new \Website_Ai_Assistant\Models\Deepseek_Service($api_key, $endpoint);
                    break;
                default:
                    throw new \Exception(__('Invalid AI provider', 'website-ai-assistant'));
            }

            // Set the model for the service
            $model = $options[$provider . '_model'] ?? '';
            if (empty($model)) {
                throw new \Exception(__('No model selected for ' . $provider, 'website-ai-assistant'));
            }
            $service->set_model($model);

            $this->logger->debug('Using AI provider:', [
                'provider' => $provider,
                'model' => $model
            ]);

            // Generate response using the selected service
            $this->logger->debug('Generating AI response with:', [
                'provider' => $provider,
                'model' => $model,
                'messages_count' => count($this->conversation->get_messages())
            ]);
            
            try {
                $ai_response = $service->generate_response($this->conversation->get_messages());
            } catch (\Exception $e) {
                $this->logger->error('AI service error:', [
                    'provider' => $provider,
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            // Format response with links
            $formatted_response = $this->format_response_with_links($ai_response);
            
            // Add AI response to conversation history
            $this->conversation->add_message('model', $ai_response);
            
            $this->logger->info('Successfully got API response');
            wp_send_json_success([
                'message' => $formatted_response,
                'hasLinks' => !empty($this->search_results)
            ]);

        } catch (\Exception $e) {
            $this->logger->exception($e, 'Chat handler error');
            wp_send_json_error([
                'message' => sprintf(
                    __('Error: %s', 'website-ai-assistant'),
                    $e->getMessage()
                )
            ]);
        }
    }

    public function clear_chat_history(): void {
        check_ajax_referer('waa_chat_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        if ($session_id) {
            $conversation = new Conversation($session_id);
            $conversation->clear_history();
            $this->logger->info('Cleared chat history', ['session_id' => $session_id]);
        }
        
        wp_send_json_success();
    }

    private function format_response_with_links(string $response): string {
        $this->logger->debug('Formatting response with links');
        
        // Since response is already in HTML, just clean it up
        $response = wp_kses($response, [
            'a' => [
                'href' => [],
                'target' => [],
                'rel' => []
            ],
            'p' => [],
            'br' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'blockquote' => []
        ]);

        // Ensure links have target and rel attributes
        $response = preg_replace(
            '/<a([^>]+)href=[\'"](.*?)[\'"]([^>]*)>/i',
            '<a$1href="$2"$3 target="_blank" rel="noopener noreferrer">',
            $response
        );
        
        $this->logger->debug('Response formatting complete');
        return $response;
    }
}
