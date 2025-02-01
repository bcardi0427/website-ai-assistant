<?php
namespace Website_Ai_Assistant;

class Deactivator {
    public static function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
} 