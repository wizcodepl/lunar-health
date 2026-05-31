<?php

declare(strict_types=1);

namespace WizcodePl\LunarHealth\Checks;

use Lunar\Models\Cart;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class CartHealthCheck extends Check
{
    protected ?int $deletedWarningThreshold = null;

    protected ?int $deletedFailureThreshold = null;

    public function warnWhenDeletedExceeds(int $count): self
    {
        $this->deletedWarningThreshold = $count;

        return $this;
    }

    public function failWhenDeletedExceeds(int $count): self
    {
        $this->deletedFailureThreshold = $count;

        return $this;
    }

    public function run(): Result
    {
        $active = Cart::query()->whereNull('completed_at')->count();
        $completed = Cart::query()->whereNotNull('completed_at')->count();
        $deleted = Cart::query()->onlyTrashed()->count();

        $result = Result::make()
            ->shortSummary(sprintf('%d active · %d completed · %d deleted', $active, $completed, $deleted))
            ->meta([
                'active' => $active,
                'completed' => $completed,
                'deleted' => $deleted,
            ]);

        if ($this->deletedFailureThreshold !== null && $deleted > $this->deletedFailureThreshold) {
            return $result->failed(sprintf(
                '%d deleted carts above failure threshold %d',
                $deleted,
                $this->deletedFailureThreshold,
            ));
        }

        if ($this->deletedWarningThreshold !== null && $deleted > $this->deletedWarningThreshold) {
            return $result->warning(sprintf(
                '%d deleted carts above warning threshold %d',
                $deleted,
                $this->deletedWarningThreshold,
            ));
        }

        return $result->ok();
    }
}
