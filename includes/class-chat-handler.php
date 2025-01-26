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

    public function __construct() {
        // Get plugin options
        $options = get_option('waa_options', []);
        $this->gemini_api_key = $options['gemini_api_key'] ?? '';
        $this->search_api_key = $options['google_search_api_key'] ?? '';
        $this->search_engine_id = $options['search_engine_id'] ?? '';
        $this->search_provider = $options['search_provider'] ?? 'google';
        $this->system_message = ($options['system_message'] ?? '') . "\n\n" .
            "Please format your responses in HTML. For links, use: " .
            "<a href='URL' target='_blank' rel='noopener noreferrer'>TITLE</a>";
        $this->max_history = $options['max_history'] ?? 10;

        // Log initialization settings
        waa_debug_log('Chat Handler initialized with:');
        waa_debug_log('- Search Provider: ' . $this->search_provider);
        waa_debug_log('- Google Search API Key Set: ' . (!empty($this->search_api_key) ? 'Yes' : 'No'));
        waa_debug_log('- Search Engine ID Set: ' . (!empty($this->search_engine_id) ? 'Yes' : 'No'));
        
        // Validate Google Search settings if that's the selected provider
        if ($this->search_provider === 'google') {
            if (empty($this->search_api_key) || empty($this->search_engine_id)) {
                waa_debug_log('WARNING: Google Search selected but credentials are missing');
            }
        }

        // Lazy initialize Algolia service when needed
        $this->algolia_service = null;

        // Add AJAX handlers
        add_action('wp_ajax_waa_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_waa_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_waa_clear_history', [$this, 'clear_chat_history']);
        add_action('wp_ajax_nopriv_waa_clear_history', [$this, 'clear_chat_history']);
    }

    private function get_search_results(string $query): string {
        if ($this->search_provider === 'algolia') {
            // Initialize Algolia service if not already done
            if (!$this->algolia_service) {
                $this->algolia_service = new Algolia_Service();
            }

            // Use Algolia search
            $this->search_results = $this->algolia_service->get_search_results($query);
            
            if (empty($this->search_results)) {
                return "No relevant content found on this website. Please respond with: 'I apologize, but I'm specifically designed to help with questions about this website's content.'";
            }
        } else {
            // Use Google search (existing functionality)
            if (empty($this->search_api_key) || empty($this->search_engine_id)) {
                waa_debug_log('Google Search: Missing API key or Search Engine ID');
                return '';
            }

            try {
                waa_debug_log('Google Search: Searching for query: ' . $query);
                $url = add_query_arg([
                    'key' => $this->search_api_key,
                    'cx' => $this->search_engine_id,
                    'q' => $query,
                    'num' => 5 // Number of results to return
                ], 'https://www.googleapis.com/customsearch/v1');

                $response = wp_remote_get($url);
                waa_debug_log('Google Search: Raw response: ' . print_r(wp_remote_retrieve_body($response), true));
                
                if (is_wp_error($response)) {
                    waa_debug_log('Google Search: WP Error: ' . $response->get_error_message());
                    throw new \Exception($response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                waa_debug_log('Google Search: Decoded response: ' . print_r($body, true));
                
                if (isset($body['error'])) {
                    waa_debug_log('Google Search: API Error: ' . $body['error']['message']);
                    throw new \Exception($body['error']['message']);
                }

                if (!isset($body['items']) || empty($body['items'])) {
                    waa_debug_log('Google Search: No results found');
                    $this->search_results = [];
                    return "No relevant content found on this website. Please respond with: 'I apologize, but I'm specifically designed to help with questions about this website's content.'";
                }

                // Store search results for later use
                $this->search_results = $body['items'];
                waa_debug_log('Google Search: Found ' . count($this->search_results) . ' results');
                
                // Map Google results to match Algolia format
                $this->search_results = array_map(function($item) {
                    $result = [
                        'title' => $item['title'],
                        'snippet' => $item['snippet'],
                        'link' => $item['link']
                    ];
                    waa_debug_log('Google Search: Formatted result: ' . print_r($result, true));
                    return $result;
                }, $this->search_results);
            } catch (\Exception $e) {
                waa_debug_log('Website AI Assistant Search Error: ' . $e->getMessage());
                return '';
            }
        }
            
        // Format the context with the search results, regardless of provider
        $context = "Here are relevant articles from our website. Provide your response in HTML format. " .
                   "For links, use: <a href='URL' target='_blank' rel='noopener noreferrer'>TITLE</a>\n\n";

        waa_debug_log('Formatting context with ' . count($this->search_results) . ' search results');
        
        foreach ($this->search_results as $index => $item) {
            $article_context = "Article {$index}:\n";
            $article_context .= "Title: {$item['title']}\n";
            $article_context .= "Snippet: {$item['snippet']}\n";
            $article_context .= "URL: {$item['link']}\n\n";
            
            $context .= $article_context;
            waa_debug_log('Added article to context: ' . print_r($item, true));
        }

        waa_debug_log('Final context length: ' . strlen($context));
        return $context;
    }

    public function handle_chat_message(): void {
        check_ajax_referer('waa_chat_nonce', 'nonce');

        waa_debug_log("Chat Handler: Processing message");

        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($message)) {
            wp_send_json_error(['message' => __('Message cannot be empty.', 'website-ai-assistant')]);
        }

        if (empty($this->gemini_api_key)) {
            waa_debug_log("Chat Handler: Gemini API key not configured");
            wp_send_json_error(['message' => __('Gemini API key is not configured.', 'website-ai-assistant')]);
        }

        try {
            // Initialize conversation
            $this->conversation = new Conversation($session_id, $this->max_history);

            // Get search results for context
            $search_context = $this->get_search_results($message);

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

            // Prepare the API request
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $this->gemini_api_key;

            $request_body = [
                'contents' => $this->conversation->get_messages(),
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ],
            ];

            waa_debug_log("Chat Handler: Sending request to Gemini API");
            waa_debug_log("Request body: " . print_r($request_body, true));

            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($request_body),
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            waa_debug_log("Chat Handler: Response code: {$response_code}");
            waa_debug_log("Chat Handler: Response body: {$response_body}");

            $body = json_decode($response_body, true);
            
            if (isset($body['error'])) {
                throw new \Exception($body['error']['message']);
            }

            if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                throw new \Exception(__('Invalid response from Gemini API', 'website-ai-assistant'));
            }

            $ai_response = $body['candidates'][0]['content']['parts'][0]['text'];
            
            // Format response with links
            $formatted_response = $this->format_response_with_links($ai_response);
            
            // Add AI response to conversation history
            $this->conversation->add_message('model', $ai_response);
            
            waa_debug_log("Chat Handler: Success - Got response from API");
            wp_send_json_success([
                'message' => $formatted_response,
                'hasLinks' => !empty($this->search_results)
            ]);

        } catch (\Exception $e) {
            waa_debug_log("Chat Handler Error: " . $e->getMessage());
            waa_debug_log("Stack trace: " . $e->getTraceAsString());
            
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
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
        }
        
        wp_send_json_success();
    }

    private function convert_markdown_to_html(string $markdown): string {
        // First, normalize line endings
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        
        // Clean up any HTML attributes that might have slipped through
        $markdown = preg_replace('/"?\s*target="_blank"\s*rel="noopener noreferrer"/', '', $markdown);
        
        // Convert bold
        $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $markdown);
        
        // Convert italic
        $html = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $html);
        
        // Convert headers (h1 to h4)
        $html = preg_replace('/####\s*(.*?)\n/s', '<h4>$1</h4>', $html);
        $html = preg_replace('/###\s*(.*?)\n/s', '<h3>$1</h3>', $html);
        $html = preg_replace('/##\s*(.*?)\n/s', '<h2>$1</h2>', $html);
        $html = preg_replace('/#\s*(.*?)\n/s', '<h1>$1</h1>', $html);

        // Handle markdown links [text](url)
        $html = preg_replace(
            '/\[([^\]]+)\]\(([^\)]+)\)/',
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            $html
        );

        // Handle any remaining bare URLs
        $html = preg_replace_callback(
            '/(?<![\(\[])(https?:\/\/[^\s<\)]+)/',
            function($matches) {
                $url = $matches[1];
                $post_id = url_to_postid($url);
                if ($post_id) {
                    $title = get_the_title($post_id);
                    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a>';
                }
                return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
            },
            $html
        );

        // Split content into blocks
        $blocks = preg_split('/\n\n+/', $html);
        $output = [];

        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            $first_line = trim($lines[0]);
            
            // Check if this block is a list
            if (preg_match('/^(?:[-*+]|\d+\.)\s/', $first_line)) {
                // Determine list type
                $is_ordered = preg_match('/^\d+\./', $first_line);
                $list_items = [];
                $current_indent = 0;
                $list_stack = [];
                
                foreach ($lines as $line) {
                    if (trim($line) === '') continue;
                    
                    // Calculate indentation level
                    $indent = strspn($line, ' ') / 2;
                    $line = ltrim($line);
                    
                    // Remove list markers
                    $line = preg_replace('/^[-*+]\s*/', '', $line);  // Unordered list
                    $line = preg_replace('/^\d+\.\s*/', '', $line);  // Ordered list
                    
                    if ($indent > $current_indent) {
                        // Start a new nested list
                        $new_list_type = $is_ordered ? 'ol' : 'ul';
                        $list_stack[] = "<{$new_list_type}>";
                        $current_indent = $indent;
                    } elseif ($indent < $current_indent) {
                        // Close nested lists
                        while ($indent < $current_indent) {
                            $list_stack[] = '</li>' . array_pop($list_stack);
                            $current_indent--;
                        }
                    }
                    
                    $list_items[] = "<li>{$line}";
                }
                
                // Close any remaining lists
                while (!empty($list_stack)) {
                    $list_items[] = '</li>' . array_pop($list_stack);
                }
                
                $list_type = $is_ordered ? 'ol' : 'ul';
                $output[] = "<{$list_type}>" . implode('</li>', $list_items) . "</{$list_type}>";
            }
            // Check if this block is a code block
            elseif (preg_match('/^```(.*?)```$/s', $block, $matches)) {
                $code = trim(preg_replace('/^```|```$/s', '', $matches[0]));
                $output[] = "<pre><code>{$code}</code></pre>";
            }
            // Regular paragraph
            else {
                $block = trim($block);
                if (!empty($block)) {
                    // Don't wrap already-wrapped content in <p> tags
                    if (!preg_match('/^<[a-z].*>.*<\/[a-z].*>$/s', $block)) {
                        $output[] = "<p>{$block}</p>";
                    } else {
                        $output[] = $block;
                    }
                }
            }
        }

        // Join blocks with appropriate spacing
        $html = implode("\n", $output);
        
        // Convert inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // Remove any "Related Articles" section that might be added
        $message_content = implode("\n", $output);
        $message_content = preg_replace('/��\s*Related Articles:?.*$/s', '', $message_content);
        $message_content = preg_replace('/Related Articles:?.*$/s', '', $message_content);

        return $message_content;
    }

    private function format_response_with_links(string $response): string {
        waa_debug_log('Formatting response with links. Raw response: ' . $response);
        
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
        
        waa_debug_log('Formatted response: ' . $response);
        return $response;
    }
} 