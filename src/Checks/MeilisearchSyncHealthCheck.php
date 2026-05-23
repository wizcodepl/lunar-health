<?php

declare(strict_types=1);

namespace WizcodePl\LunarHealthChecks\Checks;

use Lunar\Models\Product;
use Meilisearch\Client;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

class MeilisearchSyncHealthCheck extends Check
{
    protected string $indexName = 'products';

    protected float $warningDriftPercentage = 1.0;

    protected float $failureDriftPercentage = 5.0;

    public function indexName(string $name): self
    {
        $this->indexName = $name;

        return $this;
    }

    public function warnWhenDriftExceeds(float $percentage): self
    {
        $this->warningDriftPercentage = $percentage;

        return $this;
    }

    public function failWhenDriftExceeds(float $percentage): self
    {
        $this->failureDriftPercentage = $percentage;

        return $this;
    }

    public function run(): Result
    {
        $dbCount = Product::query()->where('status', 'published')->count();

        try {
            $client = new Client(
                (string) config('scout.meilisearch.host'),
                (string) config('scout.meilisearch.key'),
            );
            $stats = $client->index($this->indexName)->stats();
            $indexCount = (int) ($stats['numberOfDocuments'] ?? 0);
        } catch (Throwable $e) {
            return Result::make()
                ->failed('Meilisearch unreachable: '.$e->getMessage())
                ->shortSummary('unreachable');
        }

        // When DB is empty but index has docs (e.g. after migrate:fresh without
        // re-index), treat as 100% drift — anything in Meili is stale.
        $drift = $dbCount === 0
            ? ($indexCount > 0 ? 100.0 : 0.0)
            : (abs($dbCount - $indexCount) / $dbCount) * 100;

        $result = Result::make()
            ->shortSummary(sprintf('db=%d · index=%d · drift=%.1f%%', $dbCount, $indexCount, $drift))
            ->meta([
                'db_count' => $dbCount,
                'index_count' => $indexCount,
                'drift_percentage' => round($drift, 2),
                'index_name' => $this->indexName,
            ]);

        if ($drift > $this->failureDriftPercentage) {
            return $result->failed(sprintf(
                'Drift %.1f%% above failure threshold %.1f%%',
                $drift,
                $this->failureDriftPercentage,
            ));
        }

        if ($drift > $this->warningDriftPercentage) {
            return $result->warning(sprintf(
                'Drift %.1f%% above warning threshold %.1f%%',
                $drift,
                $this->warningDriftPercentage,
            ));
        }

        return $result->ok();
    }
}
