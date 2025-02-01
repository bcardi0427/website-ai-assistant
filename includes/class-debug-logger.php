<?php
namespace Website_Ai_Assistant;

/**
 * Centralized debug logging system
 */
class Debug_Logger {
    private static $instance = null;
    private $enabled = false;
    private $context = '';
    private $log_file = '';

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $options = \get_option('waa_options', []);
        $this->enabled = !empty($options['enable_debug']);
        $this->log_file = WP_CONTENT_DIR . '/debug.log';
    }

    /**
     * Get singleton instance
     *
     * @return Debug_Logger
     */
    public static function get_instance(): Debug_Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set current logging context (e.g., 'Algolia', 'Chat', etc.)
     *
     * @param string $context
     * @return Debug_Logger
     */
    public function set_context(string $context): Debug_Logger {
        $this->context = $context;
        return $this;
    }

    /**
     * Log a debug message with optional data
     *
     * @param string $message
     * @param array $data
     */
    public function debug(string $message, array $data = []): void {
        if (!$this->enabled) {
            return;
        }

        $this->log('DEBUG', $message, $data);
    }

    /**
     * Log an info message with optional data
     *
     * @param string $message
     * @param array $data
     */
    public function info(string $message, array $data = []): void {
        if (!$this->enabled) {
            return;
        }

        $this->log('INFO', $message, $data);
    }

    /**
     * Log an error message with optional data
     *
     * @param string $message
     * @param array $data
     */
    public function error(string $message, array $data = []): void {
        if (!$this->enabled) {
            return;
        }

        $this->log('ERROR', $message, $data);
    }

    /**
     * Log an exception with stack trace
     *
     * @param \Exception $e
     * @param string $context Additional context for the error
     */
    public function exception(\Exception $e, string $context = ''): void {
        if (!$this->enabled) {
            return;
        }

        $data = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];

        $this->error($context ?: 'Exception caught', $data);
    }

    /**
     * Start a new section in the log
     *
     * @param string $title Section title
     */
    public function section(string $title): void {
        if (!$this->enabled) {
            return;
        }

        $this->info(str_repeat('=', 20), []);
        $this->info($title, []);
        $this->info(str_repeat('=', 20), []);
    }

    /**
     * Enable or disable logging
     *
     * @param bool $enabled
     */
    public function set_enabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Internal logging method
     *
     * @param string $level
     * @param string $message
     * @param array $data
     */
    private function log(string $level, string $message, array $data): void {
        $timestamp = current_time('mysql');
        $context = $this->context ? "[$this->context]" : '';
        
        $log_message = sprintf(
            "[%s] [%s]%s %s",
            $timestamp,
            $level,
            $context,
            $message
        );

        if (!empty($data)) {
            $log_message .= "\n" . print_r($data, true);
        }

        $log_message .= "\n";
        error_log($log_message, 3, $this->log_file);
    }
}