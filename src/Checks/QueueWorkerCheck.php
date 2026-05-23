<?php

declare(strict_types=1);

namespace WizcodePl\LunarHealthChecks\Checks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class QueueWorkerCheck extends Check
{
    protected int $oldestJobWarningMinutes = 5;

    protected int $oldestJobFailureMinutes = 30;

    protected int $failedJobsFailureCount = 0;

    public function warnWhenOldestPendingOlderThan(int $minutes): self
    {
        $this->oldestJobWarningMinutes = $minutes;

        return $this;
    }

    public function failWhenOldestPendingOlderThan(int $minutes): self
    {
        $this->oldestJobFailureMinutes = $minutes;

        return $this;
    }

    public function failWhenFailedJobsExceed(int $count): self
    {
        $this->failedJobsFailureCount = $count;

        return $this;
    }

    public function run(): Result
    {
        $pending = (int) DB::table('jobs')->count();
        $oldestPendingAt = $pending > 0
            ? (int) DB::table('jobs')->min('available_at')
            : null;
        $oldestPendingMinutes = $oldestPendingAt !== null
            ? (int) round((time() - $oldestPendingAt) / 60)
            : 0;

        $failed = (int) DB::table('failed_jobs')->count();

        $result = Result::make()
            ->shortSummary(sprintf(
                'pending=%d (oldest %dmin) · failed=%d',
                $pending,
                $oldestPendingMinutes,
                $failed,
            ))
            ->meta([
                'pending' => $pending,
                'oldest_pending_minutes' => $oldestPendingMinutes,
                'failed' => $failed,
            ]);

        if ($failed > $this->failedJobsFailureCount) {
            return $result->failed(sprintf(
                '%d failed jobs (above threshold %d)',
                $failed,
                $this->failedJobsFailureCount,
            ));
        }

        if ($oldestPendingMinutes > $this->oldestJobFailureMinutes) {
            return $result->failed(sprintf(
                'Oldest pending job %dmin old (above failure threshold %dmin) — worker likely down',
                $oldestPendingMinutes,
                $this->oldestJobFailureMinutes,
            ));
        }

        if ($oldestPendingMinutes > $this->oldestJobWarningMinutes) {
            return $result->warning(sprintf(
                'Oldest pending job %dmin old (above warning threshold %dmin)',
                $oldestPendingMinutes,
                $this->oldestJobWarningMinutes,
            ));
        }

        return $result->ok();
    }
}
