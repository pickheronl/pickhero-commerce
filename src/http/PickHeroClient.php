<?php

namespace pickhero\commerce\http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use pickhero\commerce\errors\PickHeroApiException;

/**
 * HTTP client for the PickHero REST API
 * 
 * @see https://demo.pickhero.nl/docs/api.json
 */
class PickHeroClient
{
    private Client $httpClient;
    private string $baseUrl;
    private string $bearerToken;

    public function __construct(string $baseUrl, string $bearerToken)
    {
        // Ensure base URL ends with / for proper Guzzle URL resolution
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->bearerToken = $bearerToken;
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Send a GET request
     *
     * @throws PickHeroApiException
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $queryParams]);
    }

    /**
     * Send a POST request
     *
     * @throws PickHeroApiException
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Send a PUT request
     *
     * @throws PickHeroApiException
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Send a PATCH request
     *
     * @throws PickHeroApiException
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /**
     * Send a DELETE request
     *
     * @throws PickHeroApiException
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Execute an HTTP request and parse the response
     *
     * @throws PickHeroApiException
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            // Log the request in dev mode
            if (\craft\helpers\App::devMode()) {
                \Craft::info(sprintf(
                    "PickHero API %s %s%s: %s",
                    $method,
                    $this->baseUrl,
                    $endpoint,
                    json_encode($options['json'] ?? $options['query'] ?? [])
                ), 'commerce-pickhero');
            }
            
            $response = $this->httpClient->request($method, $endpoint, $options);
            return $this->parseResponse($response);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();
                $data = json_decode($body, true);
                
                // Handle non-JSON responses (e.g., HTML error pages)
                if ($data === null) {
                    throw new PickHeroApiException(
                        "API request failed with status {$statusCode}: " . substr($body, 0, 200),
                        $statusCode,
                        [],
                        $e
                    );
                }
                
                throw new PickHeroApiException(
                    $data['message'] ?? 'API request failed',
                    $statusCode,
                    $data['errors'] ?? [],
                    $e
                );
            }
            throw new PickHeroApiException(
                $e->getMessage(),
                0,
                [],
                $e
            );
        } catch (GuzzleException $e) {
            throw new PickHeroApiException(
                'HTTP request failed: ' . $e->getMessage(),
                0,
                [],
                $e
            );
        }
    }

    /**
     * Parse the API response
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        
        if (empty($body)) {
            return [];
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PickHeroApiException(
                'Invalid JSON response: ' . json_last_error_msg(),
                $response->getStatusCode()
            );
        }
        
        return $data;
    }

    /**
     * Get the base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}

