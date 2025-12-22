<?php

namespace pickhero\commerce\errors;

use Throwable;

/**
 * Exception thrown when a PickHero API request fails
 */
class PickHeroApiException extends \Exception
{
    /**
     * HTTP status codes that indicate specific error conditions
     */
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_VALIDATION_ERROR = 422;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_FORBIDDEN = 403;

    /**
     * Validation errors returned by the API
     */
    protected array $validationErrors = [];

    /**
     * HTTP status code of the response
     */
    protected int $statusCode;

    public function __construct(
        string $message,
        int $statusCode = 0,
        array $validationErrors = [],
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->validationErrors = $validationErrors;

        // Build a more descriptive message if we have validation errors
        if (!empty($validationErrors)) {
            $errorDetails = [];
            foreach ($validationErrors as $field => $messages) {
                $errorDetails[] = sprintf('%s: %s', $field, implode(', ', (array) $messages));
            }
            $message .= ' - ' . implode('; ', $errorDetails);
        }

        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get validation errors returned by the API
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Check if this is a "not found" error
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === self::STATUS_NOT_FOUND;
    }

    /**
     * Check if this is a validation error
     */
    public function isValidationError(): bool
    {
        return $this->statusCode === self::STATUS_VALIDATION_ERROR;
    }

    /**
     * Check if this is an authentication error
     */
    public function isAuthError(): bool
    {
        return $this->statusCode === self::STATUS_UNAUTHORIZED || $this->statusCode === self::STATUS_FORBIDDEN;
    }

    /**
     * Get a user-friendly error message
     */
    public function getUserMessage(): string
    {
        if ($this->isNotFound()) {
            return 'The requested resource was not found in PickHero.';
        }

        if ($this->isAuthError()) {
            return 'Authentication failed. Please check your PickHero API credentials.';
        }

        if ($this->isValidationError()) {
            return 'PickHero rejected the request due to invalid data: ' . $this->getMessage();
        }

        return 'An error occurred while communicating with PickHero: ' . $this->getMessage();
    }
}
