jQuery(document).ready(function($) {
    // Function to toggle visibility of search provider fields
    function toggleSearchFields() {
        var provider = $('#waa_options_search_provider').val();
        var $googleFields = $('.google-search-field').closest('tr');
        var $algoliaFields = $('.algolia-field').closest('tr');
        
        if (provider === 'google') {
            $googleFields.show();
            $algoliaFields.hide();
        } else {
            $googleFields.hide();
            $algoliaFields.show();
        }
    }

    // Initial state
    toggleSearchFields();

    // Handle changes to search provider
    $('#waa_options_search_provider').on('change', toggleSearchFields);

    // Handle settings form submission
    $('#waa-settings-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const originalText = submitButton.text();
        const noticeContainer = $('#waa-settings-notices');

        // Serialize form data including unchecked checkboxes
        const formData = form.serializeArray();
        $('input[type=checkbox]:not(:checked)', form).each(function() {
            formData.push({name: this.name, value: 0});
        });

        // Add nonce and action
        formData.push(
            {name: 'action', value: 'waa_save_settings'},
            {name: 'security', value: waaAdmin.nonce}
        );

        // Disable submit button during save
        submitButton.prop('disabled', true).text(waaAdmin.saving);
        noticeContainer.html('').removeClass('error success');

        $.ajax({
            url: waaAdmin.ajaxUrl,
            type: 'POST',
            data: $.param(formData),
            success: function(response) {
                if (response.success) {
                    noticeContainer.html(
                        `<div class="notice notice-success"><p>${response.data.message}</p></div>`
                    );
                } else {
                    noticeContainer.html(
                        `<div class="notice notice-error"><p>${response.data.message}</p></div>`
                    );
                }
            },
            error: function() {
                noticeContainer.html(
                    `<div class="notice notice-error"><p>${waaAdmin.ajaxError}</p></div>`
                );
            },
            complete: function() {
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });

    $('.waa-test-api').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const apiType = button.data('api');
        const resultSpan = button.siblings('.waa-api-test-result');
        const originalText = button.text();
        
        // Get the API keys
        const geminiKey = $('input[name="waa_options[gemini_api_key]"]').val();
        const searchKey = $('input[name="waa_options[google_search_api_key]"]').val();
        const searchEngineId = $('input[name="waa_options[search_engine_id]"]').val();
        
        // Prepare the data based on API type
        let data = {
            action: 'waa_test_api',
            nonce: waaAdmin.nonce,
            api_type: apiType,
        };

        if (apiType === 'gemini') {
            data.api_key = geminiKey;
            button.text(waaAdmin.testGemini);
        } else if (apiType === 'search') {
            const query = button.closest('.waa-search-test').find('.waa-search-query').val();
            if (!query) {
                alert(waaAdmin.enterQuery);
                return;
            }
            if (!searchKey || !searchEngineId) {
                alert(waaAdmin.missingSearchCreds);
                return;
            }
            data.api_key = searchKey;
            data.search_engine_id = searchEngineId;
            data.query = query;
            button.text(waaAdmin.testSearch);
        }

        // Disable button and show loading state
        button.prop('disabled', true);
        resultSpan.html('<span class="spinner is-active"></span>');

        // Make the AJAX call
        $.post(waaAdmin.ajaxUrl, data, function(response) {
            if (response.success) {
                resultSpan.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + response.data.message);
            } else {
                resultSpan.html('<span class="dashicons dashicons-warning" style="color: red;"></span> ' + response.data.message);
            }
        })
        .fail(function() {
            resultSpan.html('<span class="dashicons dashicons-warning" style="color: red;"></span> ' + waaAdmin.ajaxError);
        })
        .always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
}); 