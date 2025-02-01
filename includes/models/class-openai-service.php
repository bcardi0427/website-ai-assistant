<?php
namespace Website_Ai_Assistant\Models;

/**
 * OpenAI model service
 */
class OpenAI_Service extends Base_Model_Service {
    /**
     * OpenAI API endpoint
     * @var string
     */
    public const API_ENDPOINT = 'https://api.openai.com/v1/';

    /**
     * In-memory cache for models during request
     * @var array|null
     */
    private $cached_models = null;

    /**
     * Get the API URL for OpenAI
     *
     * @return string
     */
    protected function get_api_url(): string {
        waa_debug_log('OpenAI: Using API endpoint:', self::API_ENDPOINT);
        return rtrim(self::API_ENDPOINT, '/') . '/';
    }

    protected function make_request(string $endpoint, array $data = [], string $method = 'POST'): array {
        $url = $this->api_url . $endpoint;
        
        // Build request args
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->get_auth_header(),
            ],
            'method' => $method,
            'timeout' => 30,
        ];

        if ($method === 'POST') {
            $args['body'] = json_encode($data);
        }

        // Log exact HTTP request being sent
        waa_debug_log("\n=== OUTGOING HTTP REQUEST TO OPENAI ===");
        waa_debug_log("$method $url HTTP/1.1");
        waa_debug_log("Content-Type: application/json");
        waa_debug_log("Authorization: " . $args['headers']['Authorization']);
        if (isset($args['body'])) {
            waa_debug_log("\n" . $args['body']);
        }
        waa_debug_log("=== END REQUEST ===\n");

        // Make the request
        $raw_response = wp_remote_request($url, $args);

        // Log exact HTTP response received
        waa_debug_log("\n=== INCOMING HTTP RESPONSE FROM OPENAI ===");
        waa_debug_log("HTTP/1.1 " . wp_remote_retrieve_response_code($raw_response));
        $headers = wp_remote_retrieve_headers($raw_response);
        foreach ($headers as $key => $value) {
            waa_debug_log("$key: $value");
        }
        waa_debug_log("\n" . wp_remote_retrieve_body($raw_response));
        waa_debug_log("=== END RESPONSE ===\n");

        // Handle errors
        if (is_wp_error($raw_response)) {
            waa_debug_log("\n!!! OPENAI REQUEST FAILED !!!");
            waa_debug_log("Error: " . $raw_response->get_error_message());
            waa_debug_log("Code: " . $raw_response->get_error_code() . "\n");
            throw new \Exception($raw_response->get_error_message());
        }

        $body = wp_remote_retrieve_body($raw_response);
        $response_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            waa_debug_log("\n!!! INVALID JSON RESPONSE !!!");
            waa_debug_log("Error: " . json_last_error_msg());
            waa_debug_log("Raw Response: " . $body . "\n");
            throw new \Exception('Invalid JSON response from OpenAI API');
        }

        if (isset($response_data['error'])) {
            waa_debug_log("\n!!! OPENAI API ERROR !!!");
            waa_debug_log("Error: " . ($response_data['error']['message'] ?? 'Unknown error'));
            waa_debug_log("Type: " . ($response_data['error']['type'] ?? 'Unknown type') . "\n");
            throw new \Exception($response_data['error']['message'] ?? 'Unknown OpenAI API error');
        }

        return $response_data;
    }

    /**
     * Get available OpenAI models
     *
     * @return array
     */
    public function get_available_models(): array {
        try {
            waa_debug_log("\n=== OPENAI GET MODELS REQUEST ===");
            waa_debug_log("GET " . $this->api_url . "models");
            waa_debug_log("Authorization: Bearer " . $this->api_key);
            waa_debug_log("Content-Type: application/json");
            waa_debug_log("=== END REQUEST ===\n");
            
            $response = $this->make_request('models', [], 'GET');
            
            waa_debug_log("\n=== OPENAI GET MODELS RESPONSE ===");
            waa_debug_log("Raw Response Body:");
            waa_debug_log(json_encode($response, JSON_PRETTY_PRINT));
            waa_debug_log("=== END RESPONSE ===\n");
            
            if (!isset($response['data']) || !is_array($response['data'])) {
                waa_debug_log('OpenAI: Invalid response format', [
                    'parsed_data' => $response
                ]);
                throw new \Exception('Invalid response format from OpenAI API');
            }
            
            $models = [];
            foreach ($response['data'] as $model) {
                if (isset($model['id']) && strpos($model['id'], 'gpt') === 0) {
                    $models[$model['id']] = $this->format_model_name($model['id']);
                }
            }
            
            if (empty($models)) {
                waa_debug_log('OpenAI: No GPT models found in response');
                throw new \Exception('No GPT models found');
            }
            
            waa_debug_log('OpenAI: Successfully fetched models:', [
                'count' => count($models),
                'models' => array_keys($models)
            ]);
            
            return $models;
            
        } catch (\Exception $e) {
            waa_debug_log('OpenAI models fetch error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a response using OpenAI
     *
     * @param array $messages Array of conversation messages
     * @param array $config Optional configuration parameters
     * @return string
     * @throws \Exception
     */
    public function generate_response(array $messages, array $config = []): string {
        $data = [
            'model' => $this->model,
            'messages' => $this->format_messages($messages),
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 1000,
            'top_p' => $config['top_p'] ?? 1,
            'frequency_penalty' => $config['frequency_penalty'] ?? 0,
            'presence_penalty' => $config['presence_penalty'] ?? 0
        ];

        $response = $this->make_request('chat/completions', $data);
        return $this->parse_response($response);
    }

    /**
     * Get model-specific configuration options
     *
     * @return array
     */
    public function get_model_config(): array {
        return [
            'temperature' => [
                'type' => 'number',
                'min' => 0,
                'max' => 2,
                'default' => 0.7,
                'description' => __('Controls randomness in the response', 'website-ai-assistant')
            ],
            'max_tokens' => [
                'type' => 'number',
                'min' => 1,
                'max' => 4096,
                'default' => 1000,
                'description' => __('Maximum length of generated response', 'website-ai-assistant')
            ],
            'top_p' => [
                'type' => 'number',
                'min' => 0,
                'max' => 1,
                'default' => 1,
                'description' => __('Nucleus sampling threshold', 'website-ai-assistant')
            ],
            'frequency_penalty' => [
                'type' => 'number',
                'min' => -2,
                'max' => 2,
                'default' => 0,
                'description' => __('Decreases likelihood of repeating information', 'website-ai-assistant')
            ],
            'presence_penalty' => [
                'type' => 'number',
                'min' => -2,
                'max' => 2,
                'default' => 0,
                'description' => __('Encourages discussing new topics', 'website-ai-assistant')
            ]
        ];
    }

    /**
     * Get the authorization header for OpenAI
     *
     * @return string
     */
    protected function get_auth_header(): string {
        waa_debug_log('OpenAI: Generating auth header', [
            'has_api_key' => !empty($this->api_key),
            'api_key_length' => strlen($this->api_key),
            'api_key_preview' => substr($this->api_key, 0, 10) . '...',
            'is_trimmed' => $this->api_key === trim($this->api_key),
        ]);

        if (empty($this->api_key)) {
            waa_debug_log('OpenAI: No API key provided for authorization');
            throw new \Exception('API key is required for OpenAI API calls');
        }

        $auth_header = 'Bearer ' . trim($this->api_key);
        waa_debug_log('OpenAI: Auth header generated', [
            'header_length' => strlen($auth_header),
            'starts_with_bearer' => str_starts_with($auth_header, 'Bearer '),
            'token_preview' => substr($auth_header, 7, 10) . '...'
        ]);
        
        return $auth_header;
    }

    /**
     * Format messages for OpenAI API
     *
     * @param array $messages
     * @return array
     */
    protected function format_messages(array $messages): array {
        waa_debug_log('OpenAI: Formatting messages:', [
            'message_count' => count($messages),
            'sample_keys' => !empty($messages) ? array_keys($messages[0]) : []
        ]);

        $formatted = [];
        foreach ($messages as $index => $message) {
            // Map 'model' role to 'assistant' for OpenAI
            $role = $message['role'] === 'model' ? 'assistant' : $message['role'];
            
            waa_debug_log('OpenAI: Processing message ' . $index . ':', [
                'role' => $message['role'],
                'has_content' => isset($message['content']),
                'has_parts' => isset($message['parts']),
                'content_type' => isset($message['content']) ? gettype($message['content']) : 'not set',
                'parts_type' => isset($message['parts']) ? gettype($message['parts']) : 'not set'
            ]);

            // Handle both 'content' and 'parts' formats
            $content = '';
            if (isset($message['content'])) {
                $content = $message['content'];
                waa_debug_log('OpenAI: Using content field');
            } elseif (isset($message['parts'])) {
                if (is_array($message['parts'])) {
                    $texts = [];
                    foreach ($message['parts'] as $part) {
                        if (is_array($part) && isset($part['text'])) {
                            $texts[] = $part['text'];
                        } elseif (is_string($part)) {
                            $texts[] = $part;
                        }
                    }
                    $content = implode("\n", $texts);
                    waa_debug_log('OpenAI: Using parts field:', [
                        'parts_extracted' => $texts,
                        'parts_count' => count($texts)
                    ]);
                } else {
                    $content = $message['parts'];
                    waa_debug_log('OpenAI: Using parts field (non-array)', ['value' => $content]);
                }
            }
            
            if (!empty($content)) {
                $formatted[] = [
                    'role' => $role,
                    'content' => $content
                ];
                waa_debug_log('OpenAI: Added formatted message:', [
                    'role' => $role,
                    'content_length' => strlen($content)
                ]);
            } else {
                waa_debug_log('OpenAI: Skipped empty message', [
                    'original_message' => $message
                ]);
            }
        }
        waa_debug_log('OpenAI: Final formatted messages:', $formatted);
        return $formatted;
    }

    /**
     * Parse OpenAI API response
     *
     * @param array $response
     * @return string
     * @throws \Exception
     */
    protected function parse_response(array $response): string {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception(__('Invalid response from OpenAI API', 'website-ai-assistant'));
        }

        return $response['choices'][0]['message']['content'];
    }

    /**
     * Format model name for display
     *
     * @param string $model_id
     * @return string
     */
    private function format_model_name(string $model_id): string {
        // Convert "gpt-3.5-turbo" to "GPT-3.5 Turbo"
        $name = str_replace('-', ' ', $model_id);
        $name = ucwords($name);
        $name = str_replace('Gpt ', 'GPT-', $name);
        return $name;
    }
}