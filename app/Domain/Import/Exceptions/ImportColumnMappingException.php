<?php

declare(strict_types=1);

namespace App\Domain\Import\Exceptions;

/**
 * Exception for column mapping errors
 * Specialized for missing or mismatched column names
 */
class ImportColumnMappingException extends ImportValidationException
{
    /**
     * @param array<string> $missingColumns Technical column names that are missing
     * @param array<string> $availableColumns Technical column names found in file
     * @param array<string, string> $columnTranslations Map of technical to user-friendly names
     */
    public function __construct(
        private readonly array $missingColumns,
        private readonly array $availableColumns,
        private readonly array $columnTranslations = []
    ) {
        $userMessage = $this->buildUserMessage();
        $technicalDetails = $this->buildTechnicalDetails();
        $suggestions = $this->buildSuggestions();

        parent::__construct($userMessage, $technicalDetails, $suggestions);
    }

    private function buildUserMessage(): string
    {
        // Check if we have numeric indices instead of column names
        $hasNumericIndices = !empty(array_filter(
            $this->availableColumns, 
            fn($col) => is_int($col) || is_numeric($col)
        ));
        
        if ($hasNumericIndices) {
            return '❌ El archivo Excel no tiene encabezados válidos. ' .
                   'La primera fila debe contener los nombres de las columnas, no datos.';
        }
        
        $translatedMissing = array_map(
            fn($col) => $this->columnTranslations[$col] ?? $col,
            $this->missingColumns
        );

        $count = count($translatedMissing);
        $columnList = implode(', ', $translatedMissing);

        if ($count === 1) {
            return "Falta la columna requerida: {$columnList}";
        }

        return "Faltan las siguientes columnas requeridas: {$columnList}";
    }

    private function buildTechnicalDetails(): string
    {
        // Check if we have numeric indices instead of column names
        $hasNumericIndices = !empty(array_filter(
            $this->availableColumns, 
            fn($col) => is_int($col) || is_numeric($col)
        ));
        
        if ($hasNumericIndices) {
            return 'El archivo Excel no tiene encabezados válidos en la primera fila. ' .
                   'Asegúrate de que la primera fila contenga los nombres de las columnas. ' .
                   sprintf(
                       'Missing columns: %s. Found numeric indices instead of column names.',
                       implode(', ', $this->missingColumns)
                   );
        }
        
        return sprintf(
            'Missing columns: %s. Available columns: %s',
            implode(', ', $this->missingColumns),
            implode(', ', $this->availableColumns)
        );
    }

    /**
     * @return array<string>
     */
    private function buildSuggestions(): array
    {
        $suggestions = [
            'Asegúrate de que tu archivo Excel tenga las columnas requeridas.',
        ];

        // Add specific column name examples
        $translatedMissing = array_map(
            fn($col) => $this->columnTranslations[$col] ?? $col,
            $this->missingColumns
        );

        if (count($translatedMissing) > 0) {
            $examples = implode('", "', $translatedMissing);
            $suggestions[] = "Las columnas deben llamarse exactamente: \"{$examples}\"";
        }

        // Check if there are similar column names (fuzzy matching)
        $similarColumns = $this->findSimilarColumns();
        if (!empty($similarColumns)) {
            $suggestions[] = 'Columnas similares encontradas: ' . implode(', ', $similarColumns);
        }

        return $suggestions;
    }

    /**
     * Find columns that might be similar to missing ones
     * @return array<string>
     */
    private function findSimilarColumns(): array
    {
        $similar = [];

        foreach ($this->missingColumns as $missing) {
            foreach ($this->availableColumns as $available) {
                // Skip non-string values (numeric indices from Excel without headers)
                if (!is_string($available)) {
                    continue;
                }
                
                // Simple similarity check (contains substring)
                if (stripos($available, substr($missing, 0, 5)) !== false) {
                    $translated = $this->columnTranslations[$available] ?? $available;
                    $similar[] = $translated;
                }
            }
        }

        return array_unique($similar);
    }

    /**
     * @return array<string>
     */
    public function getMissingColumns(): array
    {
        return $this->missingColumns;
    }

    /**
     * @return array<string>
     */
    public function getAvailableColumns(): array
    {
        return $this->availableColumns;
    }

    /**
     * Get formatted error data for storage
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $baseArray = parent::toArray();
        
        return array_merge($baseArray, [
            'type' => 'column_mapping_error',
            'missing_columns' => $this->missingColumns,
            'available_columns' => $this->availableColumns,
            'missing_columns_translated' => array_map(
                fn($col) => $this->columnTranslations[$col] ?? $col,
                $this->missingColumns
            ),
        ]);
    }
}
