<?php
/**
 * Utility functions for Website AI Assistant
 */

if (!function_exists('waa_debug_log')) {
    /**
     * Log debug messages using the Debug_Logger class
     * 
     * This function maintains backward compatibility while using the new Debug_Logger system.
     * If Debug_Logger is not available, falls back to basic error_log functionality.
     *
     * @param string $message The message to log
     * @param mixed $data Optional data to include in the log
     */
    function waa_debug_log($message, $data = null): void {
        if (class_exists('Website_Ai_Assistant\Debug_Logger')) {
            $logger = Website_Ai_Assistant\Debug_Logger::get_instance();
            
            // Convert legacy format to new format
            if ($data !== null) {
                $logger->debug($message, is_array($data) ? $data : ['data' => $data]);
            } else {
                $logger->debug($message);
            }
        } else {
            // Legacy fallback - check both WP_DEBUG and our plugin setting
            $options = \get_option('waa_options', []);
            if ((defined('WP_DEBUG') && WP_DEBUG === true) && !empty($options['enable_debug'])) {
                $log_message = '[' . date('Y-m-d H:i:s') . '] Website AI Assistant: ' . $message;
                if ($data !== null) {
                    if (is_array($data) || is_object($data)) {
                        $log_message .= "\n" . print_r($data, true);
                    } else {
                        $log_message .= ' ' . $data;
                    }
                }
                error_log($log_message);
            }
        }
    }
}