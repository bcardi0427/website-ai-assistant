jQuery.migrateMute = true;

jQuery(document).ready(function($) {
    const chatWidget = $('#waa-chat-widget');
    const chatToggle = $('#waa-chat-toggle');
    const chatInterface = $('#waa-chat-interface');
    const chatClose = $('.waa-chat-close');
    const chatMessages = $('#waa-chat-messages');
    const chatInput = $('#waa-chat-input');
    const chatSend = $('#waa-chat-send');
    const contactForm = $('#waa-contact-form');
    const contactName = $('#waa-contact-name');
    const contactEmail = $('#waa-contact-email');
    const contactSubmit = $('#waa-contact-submit');
    let isProcessing = false;
    let messageCount = 0;
    let contactInfoCollected = false;
    
    // Generate a unique session ID
    const sessionId = 'waa_' + Math.random().toString(36).substr(2, 9);

    // Check if we should show the contact form immediately
    if (waaData.enableLeadCollection && waaData.leadCollectionTiming === 'immediate') {
        contactForm.show();
    }

    // Toggle chat interface
    chatToggle.on('click', function() {
        chatInterface.toggleClass('active');
        if (chatInterface.hasClass('active')) {
            chatInput.focus();
        }
    });

    // Close chat interface
    chatClose.on('click', function() {
        chatInterface.removeClass('active');
    });

    // Auto-resize input
    chatInput.on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Handle send message
    function sendMessage() {
        if (isProcessing) return;

        const message = chatInput.val().trim();
        if (!message) return;

        // Disable input and show processing state
        isProcessing = true;
        chatInput.prop('disabled', true);
        chatSend.prop('disabled', true);

        // Add user message to chat
        appendMessage('user', message);
        chatInput.val('').trigger('input');

        // Show typing indicator
        appendTypingIndicator();

        // Send to server
        $.ajax({
            url: waaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'waa_chat_message',
                nonce: waaData.nonce,
                message: message,
                session_id: sessionId
            },
            success: function(response) {
                removeTypingIndicator();
                if (response.success) {
                    appendMessage('assistant', response.data.message);
                } else {
                    appendMessage('assistant', 'Sorry, I encountered an error. Please try again.');
                }
            },
            error: function() {
                removeTypingIndicator();
                appendMessage('assistant', 'Sorry, there was a network error. Please try again.');
            },
            complete: function() {
                isProcessing = false;
                chatInput.prop('disabled', false).focus();
                chatSend.prop('disabled', false);
            }
        });

        messageCount++;
        checkShowContactForm();
    }

    // Handle send button click
    chatSend.on('click', sendMessage);

    // Handle enter key (with shift+enter for new line)
    chatInput.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Append message to chat
    function appendMessage(type, content) {
        // Only convert line breaks to <br> and preserve HTML
        const htmlContent = content.replace(/\n/g, '<br>');

        const messageHtml = `
            <div class="waa-message waa-${type}-message">
                <div class="waa-message-content">${htmlContent}</div>
            </div>
        `;
        chatMessages.append(messageHtml);
        scrollToBottom();
    }

    // Show typing indicator
    function appendTypingIndicator() {
        const indicator = `
            <div class="waa-message waa-assistant-message waa-typing-indicator">
                <div class="waa-typing-dot"></div>
                <div class="waa-typing-dot"></div>
                <div class="waa-typing-dot"></div>
            </div>
        `;
        chatMessages.append(indicator);
        scrollToBottom();
    }

    // Remove typing indicator
    function removeTypingIndicator() {
        chatMessages.find('.waa-typing-indicator').remove();
    }

    // Scroll chat to bottom
    function scrollToBottom() {
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }

    // Add this function to check if we should show the contact form
    function checkShowContactForm() {
        console.log('Checking contact form display:', {
            collected: contactInfoCollected,
            enabled: waaData.enableLeadCollection,
            timing: waaData.leadCollectionTiming,
            count: messageCount
        });

        if (contactInfoCollected || !waaData.enableLeadCollection) return;

        const timing = waaData.leadCollectionTiming;
        if (
            (timing === 'immediate' && messageCount === 0) ||
            (timing === 'after_first' && messageCount === 1) ||
            (timing === 'after_two' && messageCount === 2) ||
            (timing === 'end' && messageCount >= 3)
        ) {
            contactForm.show();
        }
    }

    // Add contact form submission handler
    contactSubmit.on('click', function() {
        const name = contactName.val().trim();
        const email = contactEmail.val().trim();

        if (!email) {
            alert(waaData.emailRequired);
            return;
        }

        $.ajax({
            url: waaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'waa_save_lead',
                nonce: waaData.nonce,
                name: name,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    contactInfoCollected = true;
                    contactForm.hide();
                    appendMessage('assistant', response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(waaData.ajaxError);
            }
        });
    });

    // Add skip button handler
    $('#waa-contact-skip').on('click', function() {
        contactInfoCollected = true;
        contactForm.hide();
    });
}); 