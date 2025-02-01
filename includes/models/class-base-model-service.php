<?php
namespace Website_Ai_Assistant\Models;

/**
 * Abstract base class for AI model services
 */
abstract class Base_Model_Service {
    /**
     * The API endpoint URL
     * @var string
     */
    protected $api_url;

    /**
     * The API key
     * @var string
     */
    protected $api_key;

    /**
     * The selected model
     * @var string
     */
    protected $model;

    /**
     * Constructor
     *
     * @param string $api_key The API key
     * @param string $model The model to use
     */
    public function __construct(string $api_key, ?string $model = null) {
        waa_debug_log(get_class($this) . ': Constructor called with:', [
            'api_key_preview' => substr($api_key, 0, 10) . '...',
            'api_key_length' => strlen($api_key),
            'model' => $model ?? 'not set',
            'class' => get_class($this)
        ]);

        if (empty($api_key)) {
            waa_debug_log(get_class($this) . ': Empty API key provided');
            throw new \Exception('API key cannot be empty');
        }

        // Ensure we have the full API key
        $this->api_key = rtrim(trim($api_key)); // rtrim removes any trailing whitespace while preserving trailing characters
        if ($model) {
            $this->set_model($model);
        }
        $this->api_url = $this->get_api_url();

        waa_debug_log(get_class($this) . ': Initialized with:', [
            'api_key_preview' => substr($this->api_key, 0, 10) . '...',
            'api_key_length' => strlen($this->api_key),
            'has_model' => !empty($model),
            'api_url' => $this->api_url,
            'class' => get_class($this)
        ]);
    }

    /**
     * Get the API URL for this service
     *
     * @return string The API URL
     */
    abstract protected function get_api_url(): string;

    /**
     * Get available models from the API
     *
     * @return array Array of available models
     */
    abstract public function get_available_models(): array;

    /**
     * Generate a response from the model
     *
     * @param array $messages Array of conversation messages
     * @param array $config Optional configuration parameters
     * @return string The generated response
     */
    abstract public function generate_response(array $messages, array $config = []): string;

    /**
     * Get model-specific configuration options
     *
     * @return array Configuration options
     */
    abstract public function get_model_config(): array;

    /**
     * Make an API request
     *
     * @param string $endpoint The API endpoint
     * @param array $data The request data
     * @param string $method HTTP method (GET/POST)
     * @return array The response data
     * @throws \Exception if request fails
     */
    protected function make_request(string $endpoint, array $data = [], string $method = 'POST'): array {
        if (empty($this->model)) {
            waa_debug_log('API Request error:', [
                'error' => 'No model selected',
                'class' => get_class($this)
            ]);
            throw new \Exception('No AI model selected');
        }

        $url = $this->api_url . $endpoint;
        $request_id = uniqid('api_request_');
        
        waa_debug_log('[' . $request_id . '] Making API request:', [
            'class' => get_class($this),
            'url' => $url,
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $method === 'POST' ? $data : 'No body for GET request',
            'api_key_preview' => substr($this->api_key, 0, 10) . '...',
            'api_key_length' => strlen($this->api_key)
        ]);
        
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

        waa_debug_log('[' . $request_id . '] Full request details:', [
            'url' => $url,
            'headers' => $args['headers'],
            'method' => $args['method'],
            'timeout' => $args['timeout'],
            'has_body' => isset($args['body'])
        ]);

        // Log raw request to API
        $api_name = str_replace('_Service', '', str_replace('Website_Ai_Assistant\\Models\\', '', get_class($this)));
        waa_debug_log("\n=== RAW REQUEST TO " . $api_name . " API ===");
        waa_debug_log($method . " " . $url);
        waa_debug_log($data ? json_encode($data) : "(no body)");
        waa_debug_log("=== END REQUEST ===\n");
        
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            waa_debug_log('Request failed with WP_Error:', [
                'error' => $error_message,
                'wp_error_code' => $response->get_error_code()
            ]);
            throw new \Exception($error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log raw response from API
        $api_name = str_replace('_Service', '', str_replace('Website_Ai_Assistant\\Models\\', '', get_class($this)));
        waa_debug_log("\n=== RAW RESPONSE FROM " . $api_name . " API ===");
        waa_debug_log($response_body);
        waa_debug_log("=== END RESPONSE ===\n");
        
        waa_debug_log('[' . $request_id . '] Response received:', [
            'status_code' => $response_code,
            'headers' => $response_headers,
            'body_length' => strlen($response_body),
            'body_preview' => substr($response_body, 0, 1000) . (strlen($response_body) > 1000 ? '...' : '')
        ]);

        $body = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            waa_debug_log('[' . $request_id . '] JSON parse error:', [
                'error' => json_last_error_msg(),
                'raw_body' => substr($response_body, 0, 1000)
            ]);
            throw new \Exception('Invalid JSON response from API');
        }

        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            waa_debug_log('[' . $request_id . '] API returned error:', [
                'error' => $error_message,
                'full_error' => $body['error']
            ]);
            throw new \Exception($error_message);
        }

        waa_debug_log('[' . $request_id . '] Request successful:', [
            'response_structure' => array_keys($body),
            'data_type' => isset($body['data']) ? gettype($body['data']) : 'not set'
        ]);
        
        return $body;
    }

    /**
     * Get the authorization header value
     *
     * @return string The authorization header
     */
    abstract protected function get_auth_header(): string;

    /**
     * Format the chat messages for the API request
     *
     * @param array $messages Array of conversation messages
     * @return array Formatted messages
     */
    abstract protected function format_messages(array $messages): array;

    /**
     * Parse the API response to extract the generated text
     *
     * @param array $response The API response
     * @return string The generated text
     */
    abstract protected function parse_response(array $response): string;

    /**
     * Set the model to use for requests
     *
     * @param string $model The model identifier
     * @return void
     */
    public function set_model(string $model): void {
        waa_debug_log(get_class($this) . ': Setting model:', [
            'model' => $model,
            'class' => get_class($this),
            'previous_model' => $this->model ?? 'none'
        ]);
        
        if (empty($model)) {
            waa_debug_log(get_class($this) . ': Empty model name provided');
            throw new \Exception('Model name cannot be empty');
        }
        
        $this->model = $model;
        
        waa_debug_log(get_class($this) . ': Model set successfully:', [
            'model' => $this->model,
            'class' => get_class($this)
        ]);
    }
}