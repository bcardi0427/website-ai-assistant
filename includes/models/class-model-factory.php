<?php
namespace Website_Ai_Assistant\Models;

/**
 * Factory class for creating AI model services
 */
class Model_Factory {
    /**
     * Create an instance of the appropriate model service
     *
     * @param string $provider The AI provider (gemini, openai, or deepseek)
     * @param string $api_key The API key for the service
     * @param string $model The model to use
     * @return Base_Model_Service The model service instance
     * @throws \Exception if provider is invalid
     */
    public static function create(string $provider, string $api_key, string $model): Base_Model_Service {
        waa_debug_log("Creating model service for provider: $provider with model: $model");

        switch ($provider) {
            case 'gemini':
                return new Gemini_Service($api_key, $model);

            case 'openai':
                return new OpenAI_Service($api_key, $model);

            case 'deepseek':
                return new Deepseek_Service($api_key, $model);

            default:
                throw new \Exception(sprintf(
                    __('Invalid AI provider: %s', 'website-ai-assistant'),
                    $provider
                ));
        }
    }

    /**
     * Get available models for a provider
     *
     * @param string $provider The AI provider
     * @param string|null $api_key Optional API key for providers that need it to fetch models
     * @return array Array of available models
     */
    public static function get_available_models(string $provider, ?string $api_key = null): array {
        try {
            switch ($provider) {
                case 'gemini':
                    return (new Gemini_Service($api_key ?? '', ''))->get_available_models();

                case 'openai':
                    if (empty($api_key)) {
                        return OpenAI_Service::FALLBACK_MODELS;
                    }
                    return (new OpenAI_Service($api_key, ''))->get_available_models();

                case 'deepseek':
                    if (empty($api_key)) {
                        return Deepseek_Service::FALLBACK_MODELS;
                    }
                    return (new Deepseek_Service($api_key, ''))->get_available_models();

                default:
                    return [];
            }
        } catch (\Exception $e) {
            waa_debug_log("Error fetching models for $provider: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get configuration options for a specific model
     *
     * @param string $provider The AI provider
     * @param string $model The model name
     * @param string $api_key The API key
     * @return array Model configuration options
     */
    public static function get_model_config(string $provider, string $model, string $api_key): array {
        try {
            $service = self::create($provider, $api_key, $model);
            return $service->get_model_config();
        } catch (\Exception $e) {
            waa_debug_log("Error getting model config: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get list of available AI providers
     *
     * @return array Array of provider information
     */
    public static function get_providers(): array {
        return [
            'gemini' => [
                'name' => __('Google Gemini AI', 'website-ai-assistant'),
                'description' => __('Google\'s latest AI model with strong performance across various tasks.', 'website-ai-assistant'),
                'requires_key' => true,
                'fetch_models' => false
            ],
            'openai' => [
                'name' => __('OpenAI', 'website-ai-assistant'),
                'description' => __('Industry-leading AI models including GPT-4 and GPT-3.5.', 'website-ai-assistant'),
                'requires_key' => true,
                'fetch_models' => true
            ],
            'deepseek' => [
                'name' => __('Deepseek', 'website-ai-assistant'),
                'description' => __('Specialized AI models for chat, coding, and mathematics.', 'website-ai-assistant'),
                'requires_key' => true,
                'fetch_models' => true
            ]
        ];
    }
}