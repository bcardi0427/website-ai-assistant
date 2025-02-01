<?php
namespace Website_Ai_Assistant\Models;

/**
 * Gemini AI model service
 */
class Gemini_Service extends Base_Model_Service {
    /**
     * Available Gemini models
     * @var array
     */
    public const AVAILABLE_MODELS = [
        'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-1.0-pro' => 'Gemini 1.0 Pro',
        'text-embedding-004' => 'Text Embedding 004'
    ];

    /**
     * Get the API URL for Gemini
     *
     * @return string
     */
    protected function get_api_url(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/';
    }

    protected function make_request(string $endpoint, array $data = [], string $method = 'POST'): array {
        // For Gemini, append API key as query parameter instead of using Authorization header
        $url = $this->api_url . 'models/' . $endpoint . '?key=' . $this->api_key;
        
        // Build request args
        $args = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'method' => $method,
            'timeout' => 30,
        ];

        if ($method === 'POST') {
            $args['body'] = json_encode($data);
        }

        // Log request details
        waa_debug_log("\n=== OUTGOING HTTP REQUEST TO GEMINI ===");
        waa_debug_log("$method $url HTTP/1.1");
        waa_debug_log("Content-Type: application/json");
        if (isset($args['body'])) {
            waa_debug_log("\n" . $args['body']);
        }
        waa_debug_log("=== END REQUEST ===\n");

        // Make the request
        $raw_response = wp_remote_request($url, $args);

        // Log response
        waa_debug_log("\n=== INCOMING HTTP RESPONSE FROM GEMINI ===");
        if (is_wp_error($raw_response)) {
            waa_debug_log("Error: " . $raw_response->get_error_message());
            throw new \Exception($raw_response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($raw_response);
        $body = wp_remote_retrieve_body($raw_response);
        
        waa_debug_log("HTTP/1.1 " . $response_code);
        waa_debug_log("\n" . $body);
        waa_debug_log("=== END RESPONSE ===\n");

        $response_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from Gemini API');
        }

        if (isset($response_data['error'])) {
            $error_message = $response_data['error']['message'] ?? 'Unknown Gemini API error';
            waa_debug_log('Gemini API error:', $response_data['error']);
            throw new \Exception($error_message);
        }

        return $response_data;
    }

    /**
     * Get available Gemini models
     *
     * @return array
     */
    public function get_available_models(): array {
        return self::AVAILABLE_MODELS;
    }

    /**
     * Generate a response using Gemini
     *
     * @param array $messages Array of conversation messages
     * @param array $config Optional configuration parameters
     * @return string
     * @throws \Exception
     */
    public function generate_response(array $messages, array $config = []): string {
        waa_debug_log('Gemini: Generating response', [
            'model' => $this->model,
            'message_count' => count($messages)
        ]);

        $formatted_messages = $this->format_messages($messages);

        $data = [
            'contents' => $formatted_messages,
            'generationConfig' => [
                'temperature' => $config['temperature'] ?? 0.7,
                'topK' => $config['topK'] ?? 40,
                'topP' => $config['topP'] ?? 0.95,
                'maxOutputTokens' => $config['maxOutputTokens'] ?? 1024,
            ]
        ];

        waa_debug_log('Gemini: Request data prepared', [
            'content_count' => count($formatted_messages),
            'has_config' => !empty($config)
        ]);

        $endpoint = $this->model . ':generateContent';
        $response = $this->make_request($endpoint, $data);

        waa_debug_log('Gemini: Response received', [
            'response_keys' => array_keys($response)
        ]);

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
                'max' => 1,
                'default' => 0.7,
                'description' => __('Controls randomness in the response', 'website-ai-assistant')
            ],
            'topK' => [
                'type' => 'number',
                'min' => 1,
                'max' => 100,
                'default' => 40,
                'description' => __('Limits vocabulary for each token generation', 'website-ai-assistant')
            ],
            'topP' => [
                'type' => 'number',
                'min' => 0,
                'max' => 1,
                'default' => 0.95,
                'description' => __('Nucleus sampling threshold', 'website-ai-assistant')
            ],
            'maxOutputTokens' => [
                'type' => 'number',
                'min' => 1,
                'max' => 2048,
                'default' => 1024,
                'description' => __('Maximum length of generated response', 'website-ai-assistant')
            ]
        ];
    }

    /**
     * Format messages for Gemini API
     *
     * @param array $messages
     * @return array
     */
    protected function format_messages(array $messages): array {
        waa_debug_log('Gemini: Starting message formatting', [
            'message_count' => count($messages)
        ]);

        $contents = [];
        foreach ($messages as $index => $message) {
            // Map roles to Gemini format
            // Gemini expects "user" or "model" roles
            $role = match($message['role']) {
                'user' => 'user',
                'model', 'assistant' => 'model',
                default => 'user'
            };

            // Extract content
            $text = '';
            if (isset($message['content'])) {
                $text = $message['content'];
                waa_debug_log('Gemini: Extracted content from content field', [
                    'length' => strlen($text)
                ]);
            } elseif (isset($message['parts'])) {
                if (is_array($message['parts'])) {
                    $parts = [];
                    foreach ($message['parts'] as $part) {
                        if (is_array($part) && isset($part['text'])) {
                            $parts[] = $part['text'];
                        } elseif (is_string($part)) {
                            $parts[] = $part;
                        }
                    }
                    $text = implode("\n", $parts);
                    waa_debug_log('Gemini: Extracted content from parts array', [
                        'parts_count' => count($parts),
                        'total_length' => strlen($text)
                    ]);
                } else {
                    $text = (string)$message['parts'];
                    waa_debug_log('Gemini: Using parts as string');
                }
            }

            if (!empty($text)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => $text]
                    ]
                ];
                waa_debug_log('Gemini: Added message', [
                    'index' => $index,
                    'role' => $role,
                    'text_length' => strlen($text)
                ]);
            }
        }

        waa_debug_log('Gemini: Message formatting complete', [
            'formatted_count' => count($contents)
        ]);

        return $contents;
    }

    /**
     * Parse Gemini API response
     *
     * @param array $response
     * @return string
     * @throws \Exception
     */
    protected function parse_response(array $response): string {
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception(__('Invalid response from Gemini API', 'website-ai-assistant'));
        }

        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Get the authorization header for Gemini
     * Note: Not used since Gemini uses API key in URL, but must implement abstract method
     *
     * @return string
     */
    protected function get_auth_header(): string {
        return '';
    }
}