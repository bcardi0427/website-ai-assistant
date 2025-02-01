<?php
namespace Website_Ai_Assistant\Models;

/**
 * Deepseek AI model service
 */
class Deepseek_Service extends Base_Model_Service {
    /**
     * Deepseek API endpoint
     * @var string
     */
    public const API_ENDPOINT = 'https://api.deepseek.ai/v1/';

    /**
     * Logger instance
     * @var Debug_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param string $api_key The API key
     * @param string|null $endpoint Optional custom endpoint
     */
    public function __construct(string $api_key, ?string $endpoint = null) {
        parent::__construct($api_key);
        $this->logger = \Website_Ai_Assistant\Debug_Logger::get_instance()->set_context('Deepseek');
        
        if ($endpoint) {
            $this->api_url = rtrim($endpoint, '/') . '/';
            $this->logger->info('Using custom endpoint: ' . $this->api_url);
        }
    }

    /**
     * Fallback models if API fetch fails
     * @var array
     */
    private const FALLBACK_MODELS = [
        'deepseek-chat' => 'Deepseek Chat',
        'deepseek-coder' => 'Deepseek Coder',
        'deepseek-math' => 'Deepseek Math'
    ];

    /**
     * Cached models
     * @var array|null
     */
    private $cached_models = null;

    /**
     * Get the API URL for Deepseek
     *
     * @return string
     */
    protected function get_api_url(): string {
        $endpoint = $this->api_url ?? self::API_ENDPOINT;
        $this->logger->debug('Using API endpoint:', ['url' => $endpoint]);
        return $endpoint;
    }

    protected function make_request(string $endpoint, array $data = [], string $method = 'POST'): array {
        $url = $this->get_api_url() . $endpoint;
        
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

        // Log request details
        $this->logger->debug('Making request to Deepseek API:', [
            'url' => $url,
            'method' => $method,
            'endpoint' => $endpoint,
            'headers' => array_keys($args['headers']),
            'has_body' => isset($args['body'])
        ]);

        $raw_response = wp_remote_request($url, $args);

        if (is_wp_error($raw_response)) {
            $this->logger->error('Request failed:', [
                'error' => $raw_response->get_error_message(),
                'code' => $raw_response->get_error_code()
            ]);
            throw new \Exception($raw_response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($raw_response);
        $body = wp_remote_retrieve_body($raw_response);
        
        $this->logger->debug('Response received:', [
            'status_code' => $response_code,
            'body_length' => strlen($body)
        ]);

        $response_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('JSON parse error:', [
                'error' => json_last_error_msg(),
                'body_preview' => substr($body, 0, 100)
            ]);
            throw new \Exception('Invalid JSON response from Deepseek API');
        }

        if (isset($response_data['error'])) {
            $error_message = $response_data['error']['message'] ?? 'Unknown Deepseek API error';
            $this->logger->error('API error:', [
                'message' => $error_message,
                'response' => $response_data['error']
            ]);
            throw new \Exception($error_message);
        }

        return $response_data;
    }

    /**
     * Get available Deepseek models
     *
     * @return array
     */
    public function get_available_models(): array {
        if ($this->cached_models !== null) {
            return $this->cached_models;
        }

        try {
            $response = $this->make_request('models', [], 'GET');
            
            $models = [];
            foreach ($response['data'] as $model) {
                if ($model['object'] === 'model' && strpos($model['id'], 'deepseek') === 0) {
                    $models[$model['id']] = $this->format_model_name($model['id']);
                }
            }

            $this->cached_models = $models;
            return $models;

        } catch (\Exception $e) {
            waa_debug_log('Deepseek models fetch error: ' . $e->getMessage());
            return self::FALLBACK_MODELS;
        }
    }

    /**
     * Generate a response using Deepseek
     *
     * @param array $messages Array of conversation messages
     * @param array $config Optional configuration parameters
     * @return string
     * @throws \Exception
     */
    public function generate_response(array $messages, array $config = []): string {
        $this->logger->debug('Preparing Deepseek request:', [
            'model' => $this->model,
            'messages_count' => count($messages),
            'config' => $config,
            'api_url' => $this->api_url
        ]);

        try {
            $data = [
                'model' => $this->model,
                'messages' => $this->format_messages($messages),
                'temperature' => $config['temperature'] ?? 0.7,
                'max_tokens' => $config['max_tokens'] ?? 1000,
                'top_p' => $config['top_p'] ?? 1,
                'stop' => $config['stop'] ?? null
            ];

            $this->logger->debug('Making Deepseek API request to chat/completions');
            $response = $this->make_request('chat/completions', $data);
            $this->logger->debug('Successfully received Deepseek response');
            
            return $this->parse_response($response);
        } catch (\Exception $e) {
            $this->logger->error('Deepseek API error:', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'endpoint' => $this->api_url . 'chat/completions',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
            'stop' => [
                'type' => 'text',
                'default' => '',
                'description' => __('Optional sequence where the API will stop generating', 'website-ai-assistant')
            ]
        ];
    }

    /**
     * Get the authorization header for Deepseek
     *
     * @return string
     */
    protected function get_auth_header(): string {
        return 'Bearer ' . $this->api_key;
    }

    /**
     * Format messages for Deepseek API
     *
     * @param array $messages
     * @return array
     */
    protected function format_messages(array $messages): array {
        $formatted = [];
        foreach ($messages as $message) {
            // Map 'model' role to 'assistant' for Deepseek
            $role = $message['role'] === 'model' ? 'assistant' : $message['role'];
            $formatted[] = [
                'role' => $role,
                'content' => $message['content']
            ];
        }
        return $formatted;
    }

    /**
     * Parse Deepseek API response
     *
     * @param array $response
     * @return string
     * @throws \Exception
     */
    protected function parse_response(array $response): string {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception(__('Invalid response from Deepseek API', 'website-ai-assistant'));
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
        // Convert "deepseek-chat" to "Deepseek Chat"
        return ucwords(str_replace('-', ' ', $model_id));
    }
}