<?php

declare(strict_types=1);

namespace App\Domain\Import\Exceptions;

use Exception;

/**
 * Exception for import validation errors
 * Provides user-friendly messages and actionable suggestions
 */
class ImportValidationException extends Exception
{
    /**
     * @param string $userMessage User-friendly message in Spanish
     * @param string $technicalDetails Technical details for logging
     * @param array<string> $suggestions Actionable suggestions for the user
     */
    public function __construct(
        private readonly string $userMessage,
        private readonly string $technicalDetails = '',
        private readonly array $suggestions = []
    ) {
        parent::__construct($userMessage);
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getTechnicalDetails(): string
    {
        return $this->technicalDetails;
    }

    /**
     * @return array<string>
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Get formatted error data for storage
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'validation_error',
            'message' => $this->userMessage,
            'technical_details' => $this->technicalDetails,
            'suggestions' => $this->suggestions,
        ];
    }
}
