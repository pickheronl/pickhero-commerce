<?php

namespace pickhero\commerce\http;

/**
 * Base class for API resource handlers
 */
abstract class ApiResource
{
    protected PickHeroClient $client;

    public function __construct(PickHeroClient $client)
    {
        $this->client = $client;
    }

    /**
     * Build query parameters for list endpoints with filtering and sorting
     */
    protected function buildListParams(array $filters = [], ?string $sort = null, ?string $include = null): array
    {
        $params = [];
        
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $params["filter[$key]"] = $value;
            }
        }
        
        if ($sort !== null) {
            $params['sort'] = $sort;
        }
        
        if ($include !== null) {
            $params['include'] = $include;
        }
        
        return $params;
    }

    /**
     * Format an ID with optional prefix for lookups
     * 
     * The PickHero API supports prefixes to control matching:
     * - `123` - Auto: tries internal ID first, then external_id
     * - `id:123` - Match by internal ID only
     * - `external_id:ABC-123` - Match by external_id only
     */
    protected function formatId(string|int $id, ?string $idType = null): string
    {
        if ($idType === 'external') {
            return 'external_id:' . $id;
        }
        
        if ($idType === 'internal') {
            return 'id:' . $id;
        }
        
        return (string) $id;
    }
}

