<?php

declare(strict_types=1);

namespace Scholarly\Core;

use function array_filter;
use function array_map;
use function array_keys;
use function array_unique;
use function array_values;
use function implode;
use function is_array;
use function is_string;
use function ksort;
use function strip_tags;
use function stripos;
use function str_pad;
use function substr;
use function trim;

class Normalizer
{
    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public static function work(array $raw, string $provider): array
    {
        return match (strtolower($provider)) {
            'openalex' => self::normalizeOpenAlexWork($raw),
            's2', 'semantic_scholar' => self::normalizeS2Work($raw),
            'crossref' => self::normalizeCrossrefWork($raw),
            default    => self::normalizeGenericWork($raw, $provider),
        };
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public static function author(array $raw, string $provider): array
    {
        return match (strtolower($provider)) {
            'openalex' => self::normalizeOpenAlexAuthor($raw),
            's2', 'semantic_scholar' => self::normalizeS2Author($raw),
            'crossref' => self::normalizeCrossrefAuthor($raw),
            default    => self::normalizeGenericAuthor($raw, $provider),
        };
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeOpenAlexWork(array $raw): array
    {
        $idRaw      = isset($raw['id']) ? (string) $raw['id'] : '';
        $openAlexId = self::stripPrefix($idRaw, ['https://openalex.org/', 'openalex:']);
        $ids        = is_array($raw['ids'] ?? null) ? $raw['ids'] : [];

        $hostVenueRaw = $raw['host_venue'] ?? ($raw['primary_location']['source'] ?? null);
        $hostVenue    = is_array($hostVenueRaw) ? $hostVenueRaw : [];
        $venueIdRaw   = isset($hostVenue['id']) ? (string) $hostVenue['id'] : null;
        $venueId      = $venueIdRaw ? self::stripPrefix($venueIdRaw, ['https://openalex.org/', 'openalex:']) : null;

        $openAccess      = is_array($raw['open_access'] ?? null) ? $raw['open_access'] : [];
        $bestLocation    = is_array($raw['best_oa_location'] ?? null) ? $raw['best_oa_location'] : [];
        $primaryLocation = is_array($raw['primary_location'] ?? null) ? $raw['primary_location'] : [];

        return [
            'id'               => $openAlexId !== '' ? Identity::ns('openalex', $openAlexId) : null,
            'title'            => $raw['display_name'] ?? null,
            'abstract'         => self::resolveOpenAlexAbstract($raw),
            'year'             => $raw['publication_year'] ?? null,
            'publication_date' => $raw['publication_date'] ?? ($hostVenue['published_date'] ?? null),
            'venue'            => [
                'id'   => $venueId ? Identity::ns('openalex', $venueId) : null,
                'name' => $hostVenue['display_name'] ?? null,
                'type' => $hostVenue['type']         ?? null,
            ],
            'external_ids' => array_filter([
                'doi'      => isset($ids['doi']) ? Identity::normalizeDoi((string) $ids['doi']) : null,
                'pmid'     => isset($ids['pmid']) ? Identity::normalizePmid((string) $ids['pmid']) : null,
                'arxiv'    => isset($ids['arxiv']) ? Identity::normalizeArxiv((string) $ids['arxiv']) : null,
                'openalex' => $openAlexId !== '' ? Identity::ns('openalex', $openAlexId) : null,
            ]),
            'is_oa'  => $openAccess['is_oa']                 ?? null,
            'oa_url' => $openAccess['oa_url']                ?? ($bestLocation['url'] ?? null),
            'url'    => $primaryLocation['landing_page_url'] ?? $idRaw,
            'counts' => [
                'citations'  => $raw['cited_by_count'] ?? null,
                'references' => isset($raw['referenced_works']) && is_array($raw['referenced_works']) ? count($raw['referenced_works']) : null,
            ],
            'authors'          => self::mapOpenAlexAuthors($raw['authorships'] ?? []),
            'references_count' => $raw['referenced_works_count'] ?? (isset($raw['referenced_works']) && is_array($raw['referenced_works']) ? count($raw['referenced_works']) : null),
            'tldr'             => $raw['summary']                ?? null,
            'type'             => $raw['type']                   ?? null,
            'language'         => $raw['language']               ?? null,
        ];
    }

    /**
     * @param array<int, mixed> $authorships
     *
     * @return list<array<string, mixed>>
     */
    private static function mapOpenAlexAuthors(array $authorships): array
    {
        $authors = [];

        foreach ($authorships as $authorship) {
            if (! is_array($authorship)) {
                continue;
            }

            $authorData   = is_array($authorship['author'] ?? null) ? $authorship['author'] : [];
            $authorIdRaw  = isset($authorData['id']) ? (string) $authorData['id'] : '';
            $authorId     = $authorIdRaw !== '' ? self::stripPrefix($authorIdRaw, ['https://openalex.org/', 'openalex:']) : null;
            $orcid        = isset($authorData['orcid']) ? Identity::normalizeOrcid((string) $authorData['orcid']) : null;
            $institutions = is_array($authorship['institutions'] ?? null) ? $authorship['institutions'] : [];

            $authors[] = [
                'id'           => $authorId ? Identity::ns('openalex', $authorId) : null,
                'name'         => $authorData['display_name'] ?? null,
                'orcid'        => $orcid,
                'affiliations' => self::normalizeAffiliationList($institutions),
            ];
        }

        return $authors;
    }

    /**
     * @param array<int, mixed> $institutions
     *
     * @return list<string>
     */
    private static function normalizeAffiliationList(array $institutions): array
    {
        $names = [];

        foreach ($institutions as $institution) {
            if (is_array($institution) && ! empty($institution['display_name'])) {
                $names[] = (string) $institution['display_name'];
            } elseif (is_string($institution) && trim($institution) !== '') {
                $names[] = trim($institution);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function resolveOpenAlexAbstract(array $raw): ?string
    {
        if (! empty($raw['abstract'])) {
            return (string) $raw['abstract'];
        }

        $inverted = $raw['abstract_inverted_index'] ?? null;

        if (! is_array($inverted)) {
            return null;
        }

        $positions = [];

        foreach ($inverted as $word => $indices) {
            if (! is_array($indices)) {
                continue;
            }

            foreach ($indices as $index) {
                $positions[(int) $index] = $word;
            }
        }

        if ($positions === []) {
            return null;
        }

        ksort($positions);

        return trim(implode(' ', $positions));
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeS2Work(array $raw): array
    {
        $external   = is_array($raw['externalIds'] ?? null) ? $raw['externalIds'] : [];
        $venue      = is_array($raw['publicationVenue'] ?? null) ? $raw['publicationVenue'] : [];
        $openAccess = is_array($raw['openAccessPdf'] ?? null) ? $raw['openAccessPdf'] : [];

        return [
            'id'               => isset($raw['paperId']) ? Identity::ns('s2', (string) $raw['paperId']) : null,
            'title'            => $raw['title']           ?? null,
            'abstract'         => $raw['abstract']        ?? ($raw['abstractText'] ?? null),
            'year'             => $raw['year']            ?? null,
            'publication_date' => $raw['publicationDate'] ?? null,
            'venue'            => [
                'id'   => isset($venue['id']) ? Identity::ns('s2', (string) $venue['id']) : null,
                'name' => $raw['venue']  ?? ($venue['name'] ?? null),
                'type' => $venue['type'] ?? null,
            ],
            'external_ids' => array_filter([
                'doi'   => isset($external['DOI']) ? Identity::normalizeDoi((string) $external['DOI']) : null,
                'arxiv' => isset($external['ArXiv']) ? Identity::normalizeArxiv((string) $external['ArXiv']) : null,
                'pmid'  => isset($external['PubMed']) ? Identity::normalizePmid((string) $external['PubMed']) : null,
                's2'    => isset($raw['paperId']) ? Identity::ns('s2', (string) $raw['paperId']) : null,
            ]),
            'is_oa'  => $raw['isOpenAccess'] ?? ($openAccess !== []),
            'oa_url' => $openAccess['url']   ?? null,
            'url'    => $raw['url']          ?? (isset($external['DOI']) ? Identity::doiToUrl((string) $external['DOI']) : null),
            'counts' => [
                'citations'  => $raw['citationCount']  ?? null,
                'references' => $raw['referenceCount'] ?? null,
            ],
            'authors'          => self::mapS2Authors($raw['authors'] ?? []),
            'references_count' => $raw['referenceCount'] ?? null,
            'tldr'             => is_array($raw['tldr'] ?? null) ? ($raw['tldr']['text'] ?? null) : null,
            'type'             => is_array($raw['publicationTypes'] ?? null) ? ($raw['publicationTypes'][0] ?? null) : ($raw['publicationType'] ?? null),
            'language'         => $raw['language'] ?? null,
        ];
    }

    /**
     * @param array<int, mixed> $authors
     *
     * @return list<array<string, mixed>>
     */
    private static function mapS2Authors(array $authors): array
    {
        $normalized = [];

        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }

            $affiliations = [];
            if (isset($author['affiliations']) && is_array($author['affiliations'])) {
                foreach ($author['affiliations'] as $affiliation) {
                    if (is_string($affiliation) && trim($affiliation) !== '') {
                        $affiliations[] = trim($affiliation);
                    } elseif (is_array($affiliation) && ! empty($affiliation['name'])) {
                        $affiliations[] = (string) $affiliation['name'];
                    }
                }
            }

            $normalized[] = [
                'id'           => isset($author['authorId']) ? Identity::ns('s2', (string) $author['authorId']) : null,
                'name'         => $author['name'] ?? null,
                'orcid'        => isset($author['orcid']) ? Identity::normalizeOrcid((string) $author['orcid']) : null,
                'affiliations' => array_values(array_unique($affiliations)),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeCrossrefWork(array $raw): array
    {
        if (isset($raw['message']) && is_array($raw['message'])) {
            $raw = $raw['message'];
        }

        $doi      = isset($raw['DOI']) ? Identity::normalizeDoi((string) $raw['DOI']) : null;
        $title    = self::firstString($raw['title'] ?? null);
        $abstract = isset($raw['abstract']) && is_string($raw['abstract']) ? trim(strip_tags($raw['abstract'])) : null;
        $issued   = self::firstDatePart(is_array($raw['issued'] ?? null) ? $raw['issued'] : null)
            ?? self::firstDatePart(is_array($raw['created'] ?? null) ? $raw['created'] : null);

        return [
            'id'               => $doi ? Identity::ns('crossref', $doi) : null,
            'title'            => $title,
            'abstract'         => $abstract,
            'year'             => $issued['year'] ?? null,
            'publication_date' => $issued['date'] ?? null,
            'venue'            => [
                'id'   => null,
                'name' => self::firstString($raw['container-title'] ?? null),
                'type' => $raw['type'] ?? null,
            ],
            'external_ids' => array_filter([
                'doi'  => $doi,
                'isbn' => self::firstString($raw['ISBN'] ?? null),
                'issn' => self::firstString($raw['ISSN'] ?? null),
            ]),
            'is_oa'  => ! empty($raw['license']),
            'oa_url' => isset($raw['link'][0]['URL']) ? (string) $raw['link'][0]['URL'] : null,
            'url'    => $raw['URL'] ?? ($doi ? Identity::doiToUrl($doi) : null),
            'counts' => [
                'citations'  => $raw['is-referenced-by-count'] ?? null,
                'references' => $raw['references-count']       ?? $raw['reference-count'] ?? null,
            ],
            'authors'          => self::mapCrossrefAuthors($raw['author'] ?? []),
            'references_count' => $raw['references-count'] ?? $raw['reference-count'] ?? null,
            'tldr'             => null,
            'type'             => $raw['type']     ?? null,
            'language'         => $raw['language'] ?? null,
        ];
    }

    /**
     * @param array<int, mixed> $authors
     *
     * @return list<array<string, mixed>>
     */
    private static function mapCrossrefAuthors(array $authors): array
    {
        $normalized = [];

        foreach ($authors as $author) {
            if (is_array($author)) {
                $nameParts = array_filter([
                    $author['given']  ?? null,
                    $author['family'] ?? null,
                ], static fn ($part) => is_string($part) && trim($part) !== '');

                $orcid = isset($author['ORCID']) ? Identity::normalizeOrcid((string)$author['ORCID']) : null;

                $normalized[] = [
                    'id'           => $orcid ? Identity::ns('orcid', $orcid) : null,
                    'name'         => $nameParts !== [] ? implode(' ', $nameParts) : ($author['name'] ?? null),
                    'orcid'        => $orcid,
                    'affiliations' => self::extractAffiliations($author['affiliation'] ?? []),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $affiliations
     *
     * @return list<string>
     */
    private static function extractAffiliations(array $affiliations): array
    {
        $names = [];

        foreach ($affiliations as $affiliation) {
            if (is_array($affiliation) && ! empty($affiliation['name'])) {
                $names[] = (string) $affiliation['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeGenericWork(array $raw, string $provider): array
    {
        return [
            'id'               => isset($raw['id']) ? Identity::ns($provider, (string) $raw['id']) : null,
            'title'            => $raw['title']            ?? null,
            'abstract'         => $raw['abstract']         ?? null,
            'year'             => $raw['year']             ?? null,
            'publication_date' => $raw['publication_date'] ?? null,
            'venue'            => $raw['venue']            ?? null,
            'external_ids'     => is_array($raw['external_ids'] ?? null) ? $raw['external_ids'] : [],
            'is_oa'            => $raw['is_oa']  ?? null,
            'oa_url'           => $raw['oa_url'] ?? null,
            'url'              => $raw['url']    ?? null,
            'counts'           => is_array($raw['counts'] ?? null) ? $raw['counts'] : [],
            'authors'          => is_array($raw['authors'] ?? null) ? $raw['authors'] : [],
            'references_count' => $raw['references_count'] ?? null,
            'tldr'             => $raw['tldr']             ?? null,
            'type'             => $raw['type']             ?? null,
            'language'         => $raw['language']         ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeOpenAlexAuthor(array $raw): array
    {
        $idRaw           = isset($raw['id']) ? (string) $raw['id'] : '';
        $authorId        = self::stripPrefix($idRaw, ['https://openalex.org/', 'openalex:']);
        $orcid           = isset($raw['orcid']) ? Identity::normalizeOrcid((string) $raw['orcid']) : null;
        $lastInstitution = is_array($raw['last_known_institution'] ?? null) ? [$raw['last_known_institution']] : [];

        return [
            'id'     => $authorId !== '' ? Identity::ns('openalex', $authorId) : null,
            'name'   => $raw['display_name'] ?? null,
            'orcid'  => $orcid,
            'url'    => $raw['homepage_url'] ?? $idRaw,
            'counts' => [
                'works'     => $raw['works_count']              ?? null,
                'citations' => $raw['cited_by_count']           ?? null,
                'h_index'   => $raw['summary_stats']['h_index'] ?? null,
            ],
            'affiliations' => self::normalizeAffiliationList($lastInstitution),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeS2Author(array $raw): array
    {
        $orcid        = isset($raw['orcid']) ? Identity::normalizeOrcid((string) $raw['orcid']) : null;
        $affiliations = [];

        if (isset($raw['affiliations']) && is_array($raw['affiliations'])) {
            foreach ($raw['affiliations'] as $affiliation) {
                if (is_string($affiliation) && trim($affiliation) !== '') {
                    $affiliations[] = trim($affiliation);
                } elseif (is_array($affiliation) && ! empty($affiliation['name'])) {
                    $affiliations[] = (string) $affiliation['name'];
                }
            }
        }

        return [
            'id'     => isset($raw['authorId']) ? Identity::ns('s2', (string) $raw['authorId']) : null,
            'name'   => $raw['name'] ?? null,
            'orcid'  => $orcid,
            'url'    => $raw['url'] ?? null,
            'counts' => [
                'works'     => $raw['paperCount']    ?? null,
                'citations' => $raw['citationCount'] ?? null,
                'h_index'   => $raw['hIndex']        ?? null,
            ],
            'affiliations' => array_values(array_unique($affiliations)),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeCrossrefAuthor(array $raw): array
    {
        $nameParts = array_filter([
            $raw['given']  ?? null,
            $raw['family'] ?? null,
        ], static fn ($part) => is_string($part) && trim($part) !== '');

        $orcid = isset($raw['ORCID']) ? Identity::normalizeOrcid((string) $raw['ORCID']) : null;

        return [
            'id'           => $orcid ? Identity::ns('orcid', $orcid) : null,
            'name'         => $nameParts !== [] ? implode(' ', $nameParts) : ($raw['name'] ?? null),
            'orcid'        => $orcid,
            'url'          => $orcid ? Identity::orcidToUrl($orcid) : null,
            'counts'       => [],
            'affiliations' => self::extractAffiliations($raw['affiliation'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeGenericAuthor(array $raw, string $provider): array
    {
        return [
            'id'           => isset($raw['id']) ? Identity::ns($provider, (string) $raw['id']) : null,
            'name'         => $raw['name'] ?? null,
            'orcid'        => isset($raw['orcid']) ? Identity::normalizeOrcid((string) $raw['orcid']) : null,
            'url'          => $raw['url'] ?? null,
            'counts'       => is_array($raw['counts'] ?? null) ? $raw['counts'] : [],
            'affiliations' => is_array($raw['affiliations'] ?? null) ? $raw['affiliations'] : [],
        ];
    }

    /**
     * @param string|array<int, mixed>|null $value
     */
    private static function firstString(array|string|null $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return trim($item);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $date
     *
     * @return array{year: int|null, date: string|null}|null
     */
    private static function firstDatePart(?array $date): ?array
    {
        if ($date === null) {
            return null;
        }

        $parts = $date['date-parts'] ?? null;

        if (! is_array($parts) || ! isset($parts[0]) || ! is_array($parts[0])) {
            return null;
        }

        $values = $parts[0];
        $year   = isset($values[0]) ? (int) $values[0] : null;

        $dateString = null;
        if ($values !== []) {
            $dateString = implode('-', array_map(static fn ($segment, $index) => $index === 0 ? (string) $segment : str_pad((string) $segment, 2, '0', STR_PAD_LEFT), $values, array_keys($values)));
        }

        return [
            'year' => $year,
            'date' => $dateString,
        ];
    }

    /**
     * @param string $value
     * @param array $prefixes
     * @return string
     */
    /**
     * @param list<string> $prefixes
     */
    private static function stripPrefix(string $value, array $prefixes): string
    {
        $trimmed = trim($value);

        foreach ($prefixes as $prefix) {
            if ($prefix !== '') {
                if (stripos($trimmed, $prefix) === 0) {
                    return substr($trimmed, strlen($prefix));
                }
            }
        }

        return $trimmed;
    }
}
