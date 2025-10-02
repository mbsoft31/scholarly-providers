<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\AbstractLogger;
use Scholarly\Adapters\Traits\ArxivTrait;
use Scholarly\Adapters\Traits\DoiTrait;
use Scholarly\Adapters\Traits\OrcidTrait;
use Scholarly\Adapters\Traits\PubmedTrait;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;

use function PHPUnit\Framework\assertIsArray;

final class ArrayLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->entries[] = [
            'level'   => (string)$level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}

function makeTestClient(?ArrayLogger &$logger = null): Client
{
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();
    $logger ??= new ArrayLogger();

    return new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        $logger,
        new Backoff(0.0, 0.0, 1.0),
    );
}

abstract class FakeTraitAdapter
{
    public ?string $lastNormalized = null;

    public function __construct(protected Client $client, protected bool $shouldThrow = false)
    {
    }

    /**
     * @return array{token: string}
     */
    protected function respond(string $normalized): array
    {
        $this->lastNormalized = $normalized;

        if ($this->shouldThrow) {
            throw new NotFoundException();
        }

        return ['token' => $normalized];
    }

    protected function client(): Client
    {
        return $this->client;
    }
}

final class FakeDoiAdapter extends FakeTraitAdapter
{
    use DoiTrait;

    /**
     * @return array{token: string}|null
     */
    protected function fetchWorkByDoi(string $normalizedDoi): ?array
    {
        return $this->respond($normalizedDoi);
    }
}

final class FakeArxivAdapter extends FakeTraitAdapter
{
    use ArxivTrait;

    /**
     * @return array{token: string}|null
     */
    protected function fetchWorkByArxiv(string $normalizedId): ?array
    {
        return $this->respond($normalizedId);
    }
}

final class FakePubmedAdapter extends FakeTraitAdapter
{
    use PubmedTrait;

    /**
     * @return array{token: string}|null
     */
    protected function fetchWorkByPubmed(string $pmid): ?array
    {
        return $this->respond($pmid);
    }
}

final class FakeOrcidAdapter extends FakeTraitAdapter
{
    use OrcidTrait;

    /**
     * @return array{token: string}|null
     */
    protected function fetchAuthorByOrcid(string $normalizedOrcid): ?array
    {
        return $this->respond($normalizedOrcid);
    }
}

it('normalizes DOI values before fetching', function (): void {
    $client  = makeTestClient();
    $adapter = new FakeDoiAdapter($client);

    $result = $adapter->getWorkByDoi('https://doi.org/10.1000/XYZ');
    assertIsArray($result);

    expect($adapter->lastNormalized)->toBe('10.1000/xyz')
        ->and($result['token'])->toBe('10.1000/xyz');
});

it('returns null when DOI is invalid', function (): void {
    $client  = makeTestClient();
    $adapter = new FakeDoiAdapter($client);

    expect($adapter->getWorkByDoi('   '))
        ->toBeNull()
        ->and($adapter->lastNormalized)->toBeNull();
});

it('logs when DOI lookup is not found', function (): void {
    $logger = new ArrayLogger();
    $client = makeTestClient($logger);

    $adapter = new FakeDoiAdapter($client, true);

    expect($adapter->getWorkByDoi('10.1000/xyz'))->toBeNull();

    expect($logger->entries)
        ->toHaveCount(1)
        ->and($logger->entries[0]['message'])->toBe('Work not found by DOI')
        ->and($logger->entries[0]['context'])->toBe(['doi' => '10.1000/xyz']);
});

it('normalizes arXiv identifiers', function (): void {
    $client  = makeTestClient();
    $adapter = new FakeArxivAdapter($client);

    $result = $adapter->getWorkByArxiv('arXiv:2101.12345v2');
    assertIsArray($result);

    expect($adapter->lastNormalized)->toBe('2101.12345')
        ->and($result['token'])->toBe('2101.12345');
});

it('logs when arXiv lookup misses', function (): void {
    $logger = new ArrayLogger();
    $client = makeTestClient($logger);

    $adapter = new FakeArxivAdapter($client, true);

    expect($adapter->getWorkByArxiv('2101.12345'))->toBeNull();

    expect($logger->entries[0] ?? null)
        ->toMatchArray([
            'message' => 'Work not found by arXiv identifier',
            'context' => ['arxiv' => '2101.12345'],
        ]);
});

it('normalizes PubMed identifiers', function (): void {
    $client  = makeTestClient();
    $adapter = new FakePubmedAdapter($client);

    $result = $adapter->getWorkByPubmed('PMID: 123456');
    assertIsArray($result);

    expect($adapter->lastNormalized)->toBe('123456')
        ->and($result['token'])->toBe('123456');
});

it('logs when PubMed lookup is not found', function (): void {
    $logger = new ArrayLogger();
    $client = makeTestClient($logger);

    $adapter = new FakePubmedAdapter($client, true);

    expect($adapter->getWorkByPubmed('123456'))->toBeNull();

    expect($logger->entries[0] ?? null)
        ->toMatchArray([
            'message' => 'Work not found by PubMed identifier',
            'context' => ['pmid' => '123456'],
        ]);
});

it('normalizes ORCID identifiers', function (): void {
    $client  = makeTestClient();
    $adapter = new FakeOrcidAdapter($client);

    $result = $adapter->getAuthorByOrcid('https://orcid.org/0000-0001-2345-6789');
    assertIsArray($result);

    expect($adapter->lastNormalized)->toBe('0000-0001-2345-6789')
        ->and($result['token'])->toBe('0000-0001-2345-6789');
});

it('logs when ORCID lookup misses', function (): void {
    $logger = new ArrayLogger();
    $client = makeTestClient($logger);

    $adapter = new FakeOrcidAdapter($client, true);

    expect($adapter->getAuthorByOrcid('0000-0001-2345-6789'))->toBeNull();

    expect($logger->entries[0] ?? null)
        ->toMatchArray([
            'message' => 'Author not found by ORCID',
            'context' => ['orcid' => '0000-0001-2345-6789'],
        ]);
});
