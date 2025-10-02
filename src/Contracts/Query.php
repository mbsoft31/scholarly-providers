<?php

declare(strict_types=1);

namespace Scholarly\Contracts;

use InvalidArgumentException;

/**
 * Represents a provider-agnostic scholarly search query.
 */
class Query
{
    /**
     * Full text query string interpreted by the provider (supports boolean syntax).
     */
    public ?string $q = null;

    /**
     * Publication year filter. Accepts YYYY, YYYY-YYYY, or open ranges (e.g. 2018-).
     */
    public ?string $year = null;

    /**
     * Whether to restrict results to open-access works when supported.
     */
    public ?bool $openAccess = null;

    /**
     * Minimum citation count filter.
     */
    public ?int $minCitations = null;

    /**
     * Maximum citation count filter.
     */
    public ?int $maxCitations = null;

    /**
     * Optional venue identifiers (provider specific) to filter search scope.
     *
     * @var list<string>|null
     */
    public ?array $venueIds = null;

    /**
     * Normalized list of field names requested from the provider.
     *
     * @var list<string>
     */
    public array $fields = [];

    /**
     * Maximum number of items to return per page (provider upper bounds may apply).
     */
    public int $limit = 25;

    /**
     * Provider supplied cursor token for pagination.
     */
    public ?string $cursor = null;

    /**
     * Numeric offset fallback for providers that do not support cursors.
     */
    public ?int $offset = null;

    /**
     * Raw provider-specific parameters that do not have first-class properties.
     *
     * @var array<string, mixed>
     */
    public array $raw = [];

    public function q(?string $value): self
    {
        $this->q = $value !== null ? trim($value) : null;

        return $this;
    }

    public function year(?string $value): self
    {
        if ($value !== null) {
            $value = trim($value);

            if ($value === '') {
                throw new InvalidArgumentException('Year filter cannot be empty when provided.');
            }
        }

        $this->year = $value ?: null;

        return $this;
    }

    public function openAccess(?bool $value): self
    {
        $this->openAccess = $value;

        return $this;
    }

    public function minCitations(?int $value): self
    {
        if ($value !== null && $value < 0) {
            throw new InvalidArgumentException('Minimum citations must be zero or greater.');
        }

        $this->minCitations = $value;

        return $this;
    }

    public function maxCitations(?int $value): self
    {
        if ($value !== null && $value < 0) {
            throw new InvalidArgumentException('Maximum citations must be zero or greater.');
        }

        $this->maxCitations = $value;

        return $this;
    }

    /**
     * @param list<string>|null $values
     */
    public function venueIds(?array $values): self
    {
        $this->venueIds = $values === null ? null : array_values(array_filter($values, static fn ($value) => $value !== ''));

        return $this;
    }

    /**
     * @param list<string> $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values(array_unique(array_map(static fn ($field) => strtolower(trim((string) $field)), $fields)));

        return $this;
    }

    public function addField(string $field): self
    {
        $field = strtolower(trim($field));

        if ($field !== '' && ! in_array($field, $this->fields, true)) {
            $this->fields[] = $field;
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be at least 1.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function cursor(?string $cursor): self
    {
        $this->cursor = $cursor !== null ? trim($cursor) : null;

        return $this;
    }

    public function offset(?int $offset): self
    {
        if ($offset !== null && $offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function raw(array $raw): self
    {
        $this->raw = $raw;

        return $this;
    }

    /**
     * Hydrate the query from an associative array payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function from(array $payload): self
    {
        $query = new self();

        if (array_key_exists('q', $payload)) {
            $query->q((string) $payload['q']);
        }

        if (array_key_exists('year', $payload)) {
            $query->year($payload['year'] !== null ? (string) $payload['year'] : null);
        }

        if (array_key_exists('openAccess', $payload)) {
            $query->openAccess($payload['openAccess'] !== null ? (bool) $payload['openAccess'] : null);
        }

        if (array_key_exists('minCitations', $payload)) {
            $query->minCitations($payload['minCitations'] !== null ? (int) $payload['minCitations'] : null);
        }

        if (array_key_exists('maxCitations', $payload)) {
            $query->maxCitations($payload['maxCitations'] !== null ? (int) $payload['maxCitations'] : null);
        }

        if (array_key_exists('venueIds', $payload)) {
            $venueIds = $payload['venueIds'];
            $query->venueIds($venueIds === null ? null : (array) $venueIds);
        }

        if (array_key_exists('fields', $payload) && is_array($payload['fields'])) {
            $query->fields($payload['fields']);
        }

        if (array_key_exists('limit', $payload)) {
            $query->limit((int) $payload['limit']);
        }

        if (array_key_exists('cursor', $payload)) {
            $query->cursor($payload['cursor'] !== null ? (string) $payload['cursor'] : null);
        }

        if (array_key_exists('offset', $payload)) {
            $query->offset($payload['offset'] !== null ? (int) $payload['offset'] : null);
        }

        if (array_key_exists('raw', $payload) && is_array($payload['raw'])) {
            $query->raw($payload['raw']);
        }

        return $query;
    }

    /**
     * Export the query into an array representation suitable for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q'            => $this->q,
            'year'         => $this->year,
            'openAccess'   => $this->openAccess,
            'minCitations' => $this->minCitations,
            'maxCitations' => $this->maxCitations,
            'venueIds'     => $this->venueIds,
            'fields'       => $this->fields,
            'limit'        => $this->limit,
            'cursor'       => $this->cursor,
            'offset'       => $this->offset,
            'raw'          => $this->raw,
        ];
    }
}
