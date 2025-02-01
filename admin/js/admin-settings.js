jQuery(document).ready(function($) {
    // Function to handle provider changes
    function toggleProviderFields() {
        var provider = $('#waa_options_ai_provider').val();
        console.log('Toggle model fields for provider:', provider);

        // Keep all model fields visible
        $('.model-field').show();

        // Get current provider's field and API key
        var $modelSelect = $('#waa_options_' + provider + '_model');
        var apiKey = $('.' + provider + '-field input[type="password"]').val() || '';
        var currentModel = $modelSelect.data('current-value');

        if (provider === 'gemini' && !currentModel) {
            currentModel = 'gemini-1.5-flash';
            var fieldName = 'waa_options[gemini_model]';
            
            // Set default Gemini model
            $modelSelect.data('current-value', currentModel);
            
            // Update hidden field
            $('input[name="' + fieldName + '"]').remove();
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', fieldName)
                .val(currentModel)
                .appendTo($modelSelect.closest('form'));
        }

        // Update available models
        updateAvailableModels(provider, apiKey);
    }

    // Function to update available models
    function updateAvailableModels(provider, apiKey, forceRefresh) {
        // Get model select field
        var $modelSelect = $('#waa_options_' + provider + '_model');
        if (!$modelSelect.length) {
            console.error('Model select not found for provider:', provider);
            return;
        }
        
        var currentModel = $modelSelect.data('current-value') || '';
        var $status = $('.model-fetch-status');

        console.log('Updating models for:', provider);
        console.log('Current model:', currentModel);
        console.log('API key:', apiKey ? 'provided' : 'missing');

        $modelSelect.prop('disabled', true);
        $status.removeClass('error').hide();
        
        if (provider === 'gemini') {
            // For Gemini, use predefined models
            var models = {
                'gemini-2.0-flash-exp': 'Gemini 2.0 Flash Experimental',
                'gemini-1.5-flash': 'Gemini 1.5 Flash',
                'gemini-1.5-flash-8b': 'Gemini 1.5 Flash 8B',
                'gemini-1.5-pro': 'Gemini 1.5 Pro',
                'gemini-1.0-pro': 'Gemini 1.0 Pro',
                'text-embedding-004': 'Text Embedding 004'
            };

            $modelSelect.empty();
            Object.entries(models).forEach(([value, label]) => {
                $modelSelect.append(
                    $('<option></option>')
                        .attr('value', value)
                        .text(label)
                        .prop('selected', value === currentModel)
                );
            });

            $modelSelect.prop('disabled', false);
            return;
        }

        // For OpenAI and Deepseek, fetch models via AJAX if API key exists
        if (!apiKey) {
            $modelSelect.empty()
                .append($('<option></option>').text(waaAdmin.i18n.enterApiKey))
                .prop('disabled', true);
            return;
        }

        console.log('Fetching models for provider:', provider);
        console.log('Model select element found with ID:', $modelSelect.attr('id'));
        
        $modelSelect.prop('disabled', true);
        $status.removeClass('error')
            .text(waaAdmin.i18n.fetchingModels)
            .show();

        // For other providers, make AJAX request
        var ajaxData = {
            action: 'waa_fetch_models',
            provider: provider,
            api_key: apiKey,
            nonce: waaAdmin.nonce,
            force_refresh: forceRefresh ? 'true' : 'false'
        };
        console.log('Sending AJAX request:', {
            url: waaAdmin.ajaxUrl,
            data: {...ajaxData, api_key: '[REDACTED]'}
        });

        // Make AJAX request to fetch models
        $.ajax({
            url: waaAdmin.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('AJAX Response received:', response);
                if (response.success && response.data && response.data.models) {
                    var models = response.data.models;
                    console.log('Models received:', models);
                    
                    // Clear any existing options
                    $modelSelect.empty();
                    
                    // Add initial "Select a model" option
                    $modelSelect.append(
                        $('<option></option>')
                            .attr('value', '')
                            .text(waaAdmin.i18n.selectModel)
                    );
                    
                    // Check if we got any models
                    if (Object.keys(models).length > 0) {
                        var currentValue = $modelSelect.data('current-value');
                        console.log('Current model value:', currentValue);

                        // Add model options
                        Object.entries(models).forEach(([modelId, modelName]) => {
                            console.log('Adding model:', modelId, modelName);
                            var $option = $('<option></option>')
                                .attr('value', modelId)
                                .text(modelName);
    
                            if (modelId === currentValue) {
                                $option.prop('selected', true);
                            }
    
                            $modelSelect.append($option);
                        });
    
                        // If no current value, select the first model
                        if (!currentValue && Object.keys(models).length > 0) {
                            var firstModel = Object.keys(models)[0];
                            $modelSelect.val(firstModel);
                            console.log('Selected first available model:', firstModel);
                        }
                        
                        // Enable the select field and save the selected value
                        $modelSelect.prop('disabled', false);
                        $status.hide();
                        
                        // Save the selected value
                        var selectedModel = $modelSelect.val();
                        console.log('Saving selected model:', selectedModel);
                        
                        // Update the hidden field with the model name
                        var $modelField = $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'waa_options[' + provider + '_model]')
                            .val(selectedModel);
                            
                        // Remove any existing hidden field and add the new one
                        $('input[name="waa_options[' + provider + '_model]"]').remove();
                        $modelSelect.after($modelField);
                        
                        // Trigger change to ensure the value is saved
                        $modelSelect.trigger('change');
                    } else {
                        // Show no models found message
                        $modelSelect.empty()
                            .append($('<option></option>').text(waaAdmin.i18n.noModelsFound))
                            .prop('disabled', true);
                        $status.addClass('error').text(waaAdmin.i18n.noModelsFound);
                    }
                } else {
                    console.error('Error in response:', response);
                    $status.addClass('error')
                        .text(response.data?.message || waaAdmin.i18n.fetchError);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    statusCode: xhr.status,
                    provider: provider
                });

                // Reset the select field
                $modelSelect.empty()
                    .append($('<option></option>').text(waaAdmin.i18n.enterApiKey));

                // Try to get a meaningful error message
                var errorMessage = waaAdmin.i18n.fetchError;
                try {
                    if (xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        console.error('Parsed error response:', response);
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                } catch (e) {
                    console.error('Could not parse error response:', e);
                }

                // Show error status
                $status.addClass('error').text(errorMessage);
                $modelSelect.prop('disabled', true);
            },
            complete: function() {
                $modelSelect.prop('disabled', false);
            }
        });
    }

    // Initialize state on page load
    function initializeFields() {
        var provider = $('#waa_options_ai_provider').val();
        console.log('Initializing fields for provider:', provider);
        
        // Keep all model fields visible
        $('.model-field').show();
        
        // Get model select and current value
        var $modelSelect = $('#waa_options_' + provider + '_model');
        var currentModel = $modelSelect.data('current-value');
        var apiKey = $('.' + provider + '-field input[type="password"]').val() || '';

        console.log('Current model value:', currentModel);
        
        if (provider === 'gemini' && !currentModel) {
            // Set initial Gemini model if none selected
            currentModel = 'gemini-1.5-flash';
            $modelSelect.data('current-value', currentModel);
        }
        
        console.log('Initializing models for provider:', provider);
        updateAvailableModels(provider, apiKey);
    }

    // Handle model selection changes
    $('.model-field select').on('change', function() {
        var $select = $(this);
        var selectedModel = $select.val();
        var provider = $('#waa_options_ai_provider').val();
        
        console.log('Model selection changed:', {
            provider: provider,
            selectedModel: selectedModel,
            selectElement: $select.attr('id'),
            currentValue: $select.data('current-value')
        });
        
        // Save the selected model as a hidden input
        var $form = $select.closest('form');
        var fieldName = 'waa_options[' + provider + '_model]';
        
        // Remove any existing hidden input for this model
        $form.find('input[name="' + fieldName + '"]').remove();
        
        // Add new hidden input with the selected value
        $('<input>')
            .attr('type', 'hidden')
            .attr('name', fieldName)
            .val(selectedModel)
            .appendTo($form);
        
        console.log('Added hidden field:', {
            name: fieldName,
            value: selectedModel
        });
    });

    // Initial state setup
    console.log('Setting up initial state...');
    toggleProviderFields();
    initializeFields();

    // Event Handlers
    $('#waa_options_ai_provider').on('change', function() {
        var provider = $(this).val();
        console.log('Provider changed to:', provider);
        
        toggleProviderFields();
        
        // Get API key and model select elements
        var $modelSelect = $('#waa_options_' + provider + '_model');
        var currentModel = $modelSelect.data('current-value');
        var apiKey = $('.' + provider + '-field input[type="password"]').val() || '';
        
        console.log('Current model:', currentModel);
        console.log('API key present:', apiKey ? 'yes' : 'no');
        console.log('API key selector:', '.' + provider + '-field input[type="password"]');
        console.log('API key field found:', $('.' + provider + '-field input[type="password"]').length > 0);
        console.log('Fetching models for new provider:', provider);
        updateAvailableModels(provider, apiKey);
    });

    // Function to toggle search provider fields
    function toggleSearchFields() {
        var provider = $('#waa_options_search_provider').val();
        console.log('Search provider changed to:', provider);
        
        // Hide all provider-specific fields
        $('.google-search-field, .algolia-field').closest('tr').hide();
        
        // Show fields for selected provider
        if (provider === 'google') {
            $('.google-search-field').closest('tr').fadeIn();
        } else if (provider === 'algolia') {
            $('.algolia-field').closest('tr').fadeIn();
        }
    }

    // Initial search fields state
    toggleSearchFields();

    // Search provider selection
    $('#waa_options_search_provider').on('change', function() {
        toggleSearchFields();
        
        // Update hidden field to ensure the value is saved
        var provider = $(this).val();
        var $form = $(this).closest('form');
        
        // Remove any existing hidden input
        $form.find('input[name="waa_options[search_provider]"]').remove();
        
        // Add new hidden input with the selected value
        $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'waa_options[search_provider]')
            .val(provider)
            .appendTo($form);
            
        console.log('Search provider value saved:', provider);
    });

    // Handle API key changes
    $('input[type="password"], input[type="text"]').on('change', function() {
        var $field = $(this).closest('.waa-api-key-field');
        
        // Check if this is an AI provider API key
        var providerMatch = $field.attr('class').match(/(gemini|openai|deepseek)-field/);
        if (providerMatch && providerMatch[1] === $('#waa_options_ai_provider').val()) {
            updateAvailableModels(providerMatch[1], $(this).val());
        }
    });

    // Form submission handling
    $('#waa-settings-form').on('submit', function(e) {
        // Get current provider and API key
        var provider = $('#waa_options_ai_provider').val();
        var apiKey = $('.' + provider + '-field input[type="password"]').val();
        
        if (apiKey) {
            // Force refresh models after form submission
            setTimeout(function() {
                updateAvailableModels(provider, apiKey, true);
            }, 1000); // Wait for settings to save
        }
        return true;
    });
});