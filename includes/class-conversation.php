<?php
namespace Website_Ai_Assistant;

class Conversation {
    private $session_id;
    private $max_history;
    private $messages = [];

    public function __construct(string $session_id, int $max_history = 10) {
        $this->session_id = $session_id;
        $this->max_history = $max_history;
        $this->load_history();
    }

    public function add_message(string $role, string $content): void {
        $this->messages[] = [
            'role' => $role,
            'parts' => [['text' => $content]]
        ];

        // Keep only the last N messages based on max_history
        if (count($this->messages) > $this->max_history) {
            $this->messages = array_slice($this->messages, -$this->max_history);
        }

        $this->save_history();
    }

    public function get_messages(): array {
        return $this->messages;
    }

    public function clear_history(): void {
        $this->messages = [];
        delete_transient($this->get_transient_key());
    }

    private function load_history(): void {
        $history = get_transient($this->get_transient_key());
        if ($history !== false) {
            $this->messages = $history;
        }
    }

    private function save_history(): void {
        set_transient($this->get_transient_key(), $this->messages, DAY_IN_SECONDS);
    }

    private function get_transient_key(): string {
        return 'waa_chat_history_' . $this->session_id;
    }
} 