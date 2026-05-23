<?php

declare(strict_types=1);

namespace WizcodePl\LunarHealthChecks\Checks;

use Lunar\Models\Order;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class OrderHealthCheck extends Check
{
    protected string $stuckStatus = 'awaiting-payment';

    protected int $stuckAfterMinutes = 60;

    protected int $stuckWarningCount = 5;

    protected int $stuckFailureCount = 20;

    public function stuckStatus(string $status): self
    {
        $this->stuckStatus = $status;

        return $this;
    }

    public function stuckAfterMinutes(int $minutes): self
    {
        $this->stuckAfterMinutes = $minutes;

        return $this;
    }

    public function warnWhenStuckExceeds(int $count): self
    {
        $this->stuckWarningCount = $count;

        return $this;
    }

    public function failWhenStuckExceeds(int $count): self
    {
        $this->stuckFailureCount = $count;

        return $this;
    }

    public function run(): Result
    {
        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $total = array_sum($byStatus);

        $stuck = Order::query()
            ->where('status', $this->stuckStatus)
            ->where('placed_at', '<', now()->subMinutes($this->stuckAfterMinutes))
            ->count();

        $result = Result::make()
            ->shortSummary(sprintf('%d orders · %d stuck in "%s"', $total, $stuck, $this->stuckStatus))
            ->meta([
                'total' => $total,
                'by_status' => $byStatus,
                'stuck_count' => $stuck,
                'stuck_status' => $this->stuckStatus,
                'stuck_after_minutes' => $this->stuckAfterMinutes,
            ]);

        if ($stuck > $this->stuckFailureCount) {
            return $result->failed(sprintf(
                '%d orders stuck in "%s" >%dmin (above failure threshold %d)',
                $stuck,
                $this->stuckStatus,
                $this->stuckAfterMinutes,
                $this->stuckFailureCount,
            ));
        }

        if ($stuck > $this->stuckWarningCount) {
            return $result->warning(sprintf(
                '%d orders stuck in "%s" >%dmin',
                $stuck,
                $this->stuckStatus,
                $this->stuckAfterMinutes,
            ));
        }

        return $result->ok();
    }
}
