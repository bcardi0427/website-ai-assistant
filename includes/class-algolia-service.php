<?php
namespace Website_Ai_Assistant;

class Algolia_Service {
    private $client;
    private $index_name;

    public function __construct() {
        $options = get_option('waa_options', []);
        $app_id = $options['algolia_app_id'] ?? null;
        $search_key = $options['algolia_search_key'] ?? null;
        $admin_key = $options['algolia_admin_key'] ?? null;

        // Try to initialize Algolia client
        if ($app_id && ($search_key || $admin_key)) {
            try {
                // Use the Algolia WordPress plugin's client if available
                if (class_exists('\Algolia_Plugin_Factory')) {
                    $plugin = \Algolia_Plugin_Factory::create();
                    $this->client = $plugin->get_api()->get_client();
                    $this->index_name = $this->get_primary_index_name();
                }
                // Fall back to direct Algolia initialization
                elseif (class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
                    $this->client = \Algolia\AlgoliaSearch\SearchClient::create(
                        $app_id,
                        $admin_key ?? $search_key
                    );
                    // Use posts index by default
                    $this->index_name = $app_id . '_posts';
                }
            } catch (\Exception $e) {
                waa_debug_log('Algolia initialization error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get search results from Algolia
     *
     * @param string $query The search query
     * @return array Array of search results with title, snippet, and link
     */
    public function get_search_results(string $query): array {
        if (!$this->client || !$this->index_name) {
            return [];
        }

        try {
            // Get the index
            $index = $this->client->initIndex($this->index_name);

            // Perform the search
            $results = $index->search($query, [
                'hitsPerPage' => 5,
                'attributesToRetrieve' => ['post_title', 'post_excerpt', 'permalink'],
                'getRankingInfo' => true
            ]);

            // Format results to match the expected structure from Google Search
            $formatted_results = [];
            foreach ($results['hits'] as $hit) {
                // Get content from excerpt if available, otherwise use highlighted content
                $content = $hit['post_excerpt'] ?? '';
                if (empty($content) && isset($hit['_snippetResult']['content']['value'])) {
                    $content = $hit['_snippetResult']['content']['value'];
                }
                
                $formatted_results[] = [
                    'title' => $hit['post_title'] ?? '',
                    'snippet' => wp_strip_all_tags($content),
                    'link' => $hit['permalink'] ?? ''
                ];
            }

            return $formatted_results;

        } catch (\Exception $e) {
            waa_debug_log('Algolia search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the primary index name from the Algolia plugin settings
     *
     * @return string|null The index name or null if not found
     */
    private function get_primary_index_name(): ?string {
        if (!class_exists('\Algolia_Plugin_Factory')) {
            return null;
        }

        try {
            $plugin = \Algolia_Plugin_Factory::create();
            $indices = $plugin->get_indices(['searchable_posts']);
            
            if (empty($indices)) {
                return null;
            }

            return reset($indices)->get_name();
        } catch (\Exception $e) {
            waa_debug_log('Algolia index error: ' . $e->getMessage());
            return null;
        }
    }
}