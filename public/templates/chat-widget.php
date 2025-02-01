<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="waa-chat-widget" class="waa-chat-widget">
    <!-- Chat Toggle Button -->
    <button id="waa-chat-toggle" class="waa-chat-toggle" aria-label="<?php esc_attr_e('Toggle Chat', 'website-ai-assistant'); ?>">
        <span class="waa-chat-toggle-icon">💬</span>
    </button>

    <!-- Chat Interface -->
    <div id="waa-chat-interface" class="waa-chat-interface">
        <!-- Chat Header -->
        <div class="waa-chat-header">
            <h3><?php esc_html_e('Website Assistant', 'website-ai-assistant'); ?></h3>
            <button class="waa-chat-close" aria-label="<?php esc_attr_e('Close Chat', 'website-ai-assistant'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <!-- Add this after the chat header -->
        <div id="waa-contact-form" class="waa-contact-form" style="display: none;">
            <div class="waa-contact-form-inner">
                <h4><?php echo esc_html($options['lead_collection_heading'] ?? __('Please share your contact info to continue', 'website-ai-assistant')); ?></h4>
                
                <?php if (!empty($options['lead_collection_description'])): ?>
                    <p class="waa-contact-description">
                        <?php echo esc_html($options['lead_collection_description']); ?>
                    </p>
                <?php endif; ?>

                <input type="text" id="waa-contact-name" 
                       placeholder="<?php esc_attr_e('Your Name', 'website-ai-assistant'); ?>">
                <input type="email" id="waa-contact-email" 
                       placeholder="<?php esc_attr_e('Your Email', 'website-ai-assistant'); ?>">

                <?php if (!empty($options['privacy_page_url'])): ?>
                    <p class="waa-privacy-notice">
                        <?php 
                        $privacy_text = $options['privacy_text'] ?? __('By continuing, you agree to our {privacy_policy}.', 'website-ai-assistant');
                        $privacy_link = sprintf(
                            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                            esc_url($options['privacy_page_url']),
                            esc_html__('privacy policy', 'website-ai-assistant')
                        );

                        // If text doesn't contain placeholder, append the link
                        if (strpos($privacy_text, '{privacy_policy}') === false) {
                            $privacy_text .= ' ' . $privacy_link;
                            echo wp_kses($privacy_text, [
                                'a' => [
                                    'href' => [],
                                    'target' => [],
                                    'rel' => []
                                ]
                            ]);
                        } else {
                            echo wp_kses(
                                str_replace('{privacy_policy}', $privacy_link, $privacy_text),
                                [
                                    'a' => [
                                        'href' => [],
                                        'target' => [],
                                        'rel' => []
                                    ]
                                ]
                            );
                        }
                        ?>
                    </p>
                <?php endif; ?>

                <div class="waa-contact-buttons">
                    <button id="waa-contact-submit" class="waa-contact-submit">
                        <?php esc_html_e('Continue', 'website-ai-assistant'); ?>
                    </button>
                    <button id="waa-contact-skip" class="waa-contact-skip">
                        <?php esc_html_e('Skip', 'website-ai-assistant'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="waa-chat-messages" id="waa-chat-messages">
            <!-- Welcome Message -->
            <div class="waa-message waa-assistant-message">
                <div class="waa-message-content">
                    <?php esc_html_e('Hello! How can I help you today?', 'website-ai-assistant'); ?>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="waa-chat-input-container">
            <textarea id="waa-chat-input" 
                      class="waa-chat-input" 
                      placeholder="<?php esc_attr_e('Type your message...', 'website-ai-assistant'); ?>"
                      rows="1"
                      aria-label="<?php esc_attr_e('Chat message', 'website-ai-assistant'); ?>"></textarea>
            <button id="waa-chat-send" class="waa-chat-send" aria-label="<?php esc_attr_e('Send message', 'website-ai-assistant'); ?>">
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
        </div>
    </div>
</div> 