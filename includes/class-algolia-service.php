<?php
namespace Website_Ai_Assistant;

/**
 * Algolia search service integration
 */
class Algolia_Service {
    private $client = null;
    private $index_name = null;
    private $logger;

    public function __construct() {
        $this->logger = Debug_Logger::get_instance()->set_context('Algolia');
        
        $options = \get_option('waa_options', []);
        
        // Log all Algolia-related options
        $this->logger->info('Algolia settings from database:', [
            'all_options' => $options,
            'algolia_app_id' => $options['algolia_app_id'] ?? 'not set',
            'algolia_search_key' => !empty($options['algolia_search_key']) ? 'set' : 'not set',
            'algolia_admin_key' => !empty($options['algolia_admin_key']) ? 'set' : 'not set',
            'algolia_index' => $options['algolia_index'] ?? 'not set'
        ]);

        $app_id = $options['algolia_app_id'] ?? null;
        $search_key = $options['algolia_search_key'] ?? null;
        $admin_key = $options['algolia_admin_key'] ?? null;
        $this->index_name = $options['algolia_index'] ?? null;

        $this->logger->section('INITIALIZING ALGOLIA');
        
        // Check if required classes exist
        if (!class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
            require_once(dirname(dirname(__FILE__)) . '/vendor/autoload.php');
        }

        if (!class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
            throw new \Exception('Algolia SDK not found - please run composer require algolia/algoliasearch-client-php');
        }

        // Verify credentials
        if (!$app_id || !$search_key) {
            throw new \Exception('Missing Algolia credentials');
        }

        try {
            // Initialize client
            $this->client = \Algolia\AlgoliaSearch\SearchClient::create($app_id, $search_key);
            
            if (!$this->client) {
                throw new \Exception('Failed to create Algolia client');
            }

            $this->logger->info('Client initialized', [
                'app_id' => $app_id,
                'client_class' => get_class($this->client)
            ]);

            // Verify client is working
            $indices = $this->client->listIndices();
            $this->logger->info('Available indices', [
                'count' => count($indices['items'] ?? []),
                'indices' => array_map(function($index) {
                    return [
                        'name' => $index['name'],
                        'entries' => $index['entries'],
                        'updatedAt' => $index['updatedAt']
                    ];
                }, $indices['items'] ?? [])
            ]);

            // Auto-detect index if not configured
            if (empty($this->index_name) && !empty($indices['items'])) {
                foreach ($indices['items'] as $index) {
                    if ($index['name'] === 'website_contentsearchable_posts') {
                        $this->index_name = $index['name'];
                        $this->logger->info('Auto-detected index: ' . $this->index_name);
                        break;
                    }
                }
            }

            if (!$this->index_name) {
                throw new \Exception('No index configured or auto-detected');
            }

            // Verify index exists and is accessible
            $index = $this->client->initIndex($this->index_name);
            $index->getSettings();
            $this->logger->info('Successfully verified Algolia index access');

        } catch (\Exception $e) {
            $this->logger->exception($e, 'Initialization failed');
            $this->client = null;
            throw $e;
        }
    }

    public function get_search_results(string $query): array {
        $this->logger->section('ALGOLIA SEARCH');
        
        if (!$this->client || !$this->index_name) {
            $this->logger->error('Client not properly initialized', [
                'has_client' => (bool)$this->client,
                'has_index' => (bool)$this->index_name
            ]);
            return [];
        }

        try {
            $index = $this->client->initIndex($this->index_name);
            
            // Log the search request
            $this->logger->info('Executing search', [
                'query' => $query,
                'index' => $this->index_name,
                'timestamp' => \current_time('mysql')
            ]);

            // Perform search
            $searchParams = [
                'attributesToRetrieve' => ['post_title', 'post_excerpt', 'permalink', 'content'],
                'attributesToSnippet' => ['content:50'],
                'snippetEllipsisText' => '...',
                'hitsPerPage' => 5
            ];

            $this->logger->debug('Search parameters', $searchParams);

            // Execute search and capture raw response
            $startTime = microtime(true);
            $rawResults = $index->search($query, $searchParams);
            $endTime = microtime(true);

            // Log the complete raw response
            $this->logger->section('RAW ALGOLIA RESPONSE');
            $this->logger->debug('Search response', [
                'execution_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
                'query' => $rawResults['query'],
                'total_hits' => $rawResults['nbHits'],
                'page' => $rawResults['page'],
                'hits_per_page' => $rawResults['hitsPerPage'],
                'raw_response' => $rawResults
            ]);

            // If no results, log and return early
            if (empty($rawResults['hits'])) {
                $this->logger->info('No results found');
                return [];
            }

            // Process and format results
            $formatted_results = [];
            foreach ($rawResults['hits'] as $hit) {
                $this->logger->debug('Processing hit', [
                    'object_id' => $hit['objectID'],
                    'raw_hit' => $hit
                ]);

                $result = [
                    'title' => $hit['post_title'] ?? '',
                    'snippet' => $hit['post_excerpt'] ?? ($hit['_snippetResult']['content']['value'] ?? ''),
                    'link' => $hit['permalink'] ?? '',
                    'relevance_score' => $hit['_rankingInfo']['score'] ?? 0
                ];

                $formatted_results[] = $result;
            }

            $this->logger->section('FORMATTED RESULTS');
            $this->logger->info('Search complete', [
                'total_results' => count($formatted_results),
                'formatted_results' => $formatted_results
            ]);

            return $formatted_results;

        } catch (\Exception $e) {
            $this->logger->exception($e, 'Search failed');
            return [];
        }
    }

    private function get_index_stats() {
        if (!$this->client || !$this->index_name) {
            return null;
        }

        try {
            $index = $this->client->initIndex($this->index_name);
            return $index->getSettings();
        } catch (\Exception $e) {
            $this->logger->exception($e, 'Failed to get index stats');
            return null;
        }
    }
}