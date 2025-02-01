<?php
// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    require_once '../../../../wp-load.php';
}

// Load plugin files
require_once dirname(__DIR__) . '/includes/class-debug-logger.php';
require_once dirname(__DIR__) . '/includes/class-algolia-service.php';

// Initialize the Algolia service
$algolia = new Website_Ai_Assistant\Algolia_Service();

// Test search
$results = $algolia->get_search_results('test query');

// Results will be logged by the service
echo "Test completed. Check the WordPress debug log for results.";