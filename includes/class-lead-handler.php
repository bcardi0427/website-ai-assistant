<?php
namespace Website_Ai_Assistant;

class Lead_Handler {
    private $options;

    public function __construct() {
        $this->options = get_option('waa_options', []);
        
        // Add AJAX handlers
        add_action('wp_ajax_waa_save_lead', [$this, 'handle_lead_submission']);
        add_action('wp_ajax_nopriv_waa_save_lead', [$this, 'handle_lead_submission']);
    }

    public function handle_lead_submission(): void {
        check_ajax_referer('waa_chat_nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please provide a valid email address.', 'website-ai-assistant')]);
        }

        try {
            // Save to FluentCRM
            $this->save_to_fluentcrm($name, $email);
            
            wp_send_json_success([
                'message' => __('Thank you! Let\'s continue our conversation.', 'website-ai-assistant')
            ]);

        } catch (\Exception $e) {
            error_log('Website AI Assistant Error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function save_to_fluentcrm(string $name, string $email): void {
        if (!function_exists('FluentCrmApi')) {
            throw new \Exception(__('FluentCRM is not installed or activated.', 'website-ai-assistant'));
        }

        $contactApi = FluentCrmApi('contacts');
        $listId = absint($this->options['fluentcrm_list_id']);
        $tagId = absint($this->options['fluentcrm_tag_id']);
        $status = sanitize_text_field($this->options['fluentcrm_status'] ?? 'subscribed');

        $contactData = [
            'first_name' => $name,
            'email' => $email,
            'status' => $status,
            'lists' => [$listId],
            'tags' => $tagId ? [$tagId] : [],
            'source' => 'AI Chat Widget'
        ];

        $contact = $contactApi->createOrUpdate($contactData);

        if (!$contact) {
            throw new \Exception(__('Failed to save contact to FluentCRM.', 'website-ai-assistant'));
        }
    }
} 