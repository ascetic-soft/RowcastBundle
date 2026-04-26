<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\DataCollector;

use AsceticSoft\RowcastProfiler\QueryProfileStoreInterface;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @psalm-type ProfileArray = array<string, mixed>
 * @psalm-type DuplicateArray = array{fingerprint: string, count: int, total_duration_ms: float}
 */
final class RowcastDataCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly QueryProfileStoreInterface $store,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $profiles = $this->store->getProfiles();
        $totalMs = 0.0;

        foreach ($profiles as $p) {
            $totalMs += $p->durationMs;
        }

        /** @var list<ProfileArray> $serialized */
        $serialized = array_map(static fn ($p) => $p->toArray(), $profiles);

        $this->data = [
            'query_count' => \count($profiles),
            'total_duration_ms' => $totalMs,
            'profiles' => $serialized,
            'duplicates' => $this->store->getDuplicatedFingerprints(),
            'error_count' => \count(array_filter($profiles, static fn ($p) => $p->errorClass !== null)),
            'slow_count' => \count(array_filter($profiles, static fn ($p) => $p->slow)),
        ];
    }

    public static function getTemplate(): ?string
    {
        return '@Rowcast/Collector/rowcast.html.twig';
    }

    public function getName(): string
    {
        return 'rowcast';
    }

    public function getQueryCount(): int
    {
        return (int) ($this->data['query_count'] ?? 0);
    }

    public function getTotalDurationMs(): float
    {
        return (float) ($this->data['total_duration_ms'] ?? 0.0);
    }

    /**
     * @return list<ProfileArray>
     */
    public function getProfiles(): array
    {
        return $this->data['profiles'] ?? [];
    }

    /**
     * @return list<DuplicateArray>
     */
    public function getDuplicates(): array
    {
        return $this->data['duplicates'] ?? [];
    }

    public function getErrorCount(): int
    {
        return (int) ($this->data['error_count'] ?? 0);
    }

    public function getSlowCount(): int
    {
        return (int) ($this->data['slow_count'] ?? 0);
    }

    /**
     * Wraps profiler data for {@see \Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension::profilerDump}.
     * Symfony 7+ requires {@see Data}, not a plain array.
     */
    public function cloneVarForProfiler(mixed $var): Data
    {
        return $this->cloneVar($var);
    }
}
