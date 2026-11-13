<?php

namespace App\Services;

use App\Models\PostIt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BountyCalculator
{
    /**
     * Base points awarded per unit of cognitive load / weight.
     */
    public const int BASE_POINTS_PER_WEIGHT = 10;

    /**
     * Multipliers applied depending on the task's urgency priority.
     *
     * @var array<string, float>
     */
    public const array PRIORITY_MULTIPLIERS = [
        'low' => 0.8,
        'normal' => 1.0,
        'high' => 1.25,
        'urgent' => 1.5,
    ];

    /**
     * Maximum bonus allowed when historical tasks of a weight take longer than average.
     */
    public const float MAX_HISTORICAL_BONUS = 0.25;

    /**
     * Calculate bounty points automatically based on weight, priority, and historical completion duration.
     */
    public function calculate(int $weight, string $priority = 'normal', ?int $tenantId = null): int
    {
        $clampedWeight = max(1, min(5, $weight));
        $basePoints = $clampedWeight * self::BASE_POINTS_PER_WEIGHT;

        $normalizedPriority = strtolower(trim($priority));
        $multiplier = self::PRIORITY_MULTIPLIERS[$normalizedPriority] ?? self::PRIORITY_MULTIPLIERS['normal'];

        $historicalBonus = 0.0;
        if ($tenantId !== null) {
            $historicalBonus = $this->calculateHistoricalBonus($tenantId, $clampedWeight);
        }

        $bounty = (int) round($basePoints * $multiplier * (1.0 + $historicalBonus));

        return max(1, $bounty);
    }

    /**
     * Calculate bounty points for a given PostIt model.
     */
    public function calculateForPostIt(PostIt $postIt): int
    {
        return $this->calculate(
            weight: $postIt->weight,
            priority: $postIt->priority ?? 'normal',
            tenantId: $postIt->tenant_id,
        );
    }

    /**
     * Calculate the historical duration bonus (0.0 to 0.25) for a given weight within a tenant.
     * Grants up to +25% bonus if historical completed tasks of that weight take longer than the tenant's average.
     */
    public function calculateHistoricalBonus(int $tenantId, int $weight): float
    {
        $driver = DB::connection()->getDriverName();
        $durationExpression = match ($driver) {
            'sqlite' => '(strftime("%s", completed_at) - strftime("%s", started_at))',
            'pgsql' => 'EXTRACT(EPOCH FROM (completed_at - started_at))',
            default => 'TIMESTAMPDIFF(SECOND, started_at, completed_at)',
        };

        $baseQuery = PostIt::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'done')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>=', 'started_at');

        $overallAvg = (float) (clone $baseQuery)
            ->selectRaw("AVG({$durationExpression}) as avg_duration")
            ->value('avg_duration');

        if ($overallAvg <= 0.0) {
            return 0.0;
        }

        $weightAvg = (float) (clone $baseQuery)
            ->where('weight', $weight)
            ->selectRaw("AVG({$durationExpression}) as avg_duration")
            ->value('avg_duration');

        if ($weightAvg <= $overallAvg) {
            return 0.0;
        }

        $ratio = ($weightAvg - $overallAvg) / $overallAvg;

        return min(self::MAX_HISTORICAL_BONUS, max(0.0, $ratio));
    }

    /**
     * Calculate the final payout splits between assignee and co-op helper.
     *
     * @return array{assignee_points: int, helper_points: int}
     */
    public function calculateSplit(int $bountyPoints, int $helperSplit = 0, bool $hasHelper = false): array
    {
        $safeBounty = max(0, $bountyPoints);
        $clampedSplit = min(50, max(0, $helperSplit));

        if (! $hasHelper || $clampedSplit === 0 || $safeBounty === 0) {
            return [
                'assignee_points' => $safeBounty,
                'helper_points' => 0,
            ];
        }

        $helperPoints = (int) round($safeBounty * ($clampedSplit / 100));
        $assigneePoints = $safeBounty - $helperPoints;

        return [
            'assignee_points' => $assigneePoints,
            'helper_points' => $helperPoints,
        ];
    }

    /**
     * Calculate the final payout split for a PostIt upon transitioning to done.
     *
     * @return array{assignee_points: int, helper_points: int}
     */
    public function calculatePayoutSplit(PostIt $postIt): array
    {
        $hasHelper = $postIt->helper_id !== null && $postIt->helper_id !== $postIt->assignee_id;

        return $this->calculateSplit(
            bountyPoints: $postIt->bounty_points,
            helperSplit: $postIt->helper_split,
            hasHelper: $hasHelper,
        );
    }

    /**
     * Award the payout split to the assignee and helper user accounts.
     *
     * @return array{assignee_points: int, helper_points: int}
     */
    public function awardPayout(PostIt $postIt): array
    {
        $split = $this->calculatePayoutSplit($postIt);

        if ($postIt->assignee_id !== null && $split['assignee_points'] > 0) {
            User::where('id', $postIt->assignee_id)->increment('total_points', $split['assignee_points']);
        }

        if ($postIt->helper_id !== null && $split['helper_points'] > 0) {
            User::where('id', $postIt->helper_id)->increment('total_points', $split['helper_points']);
        }

        return $split;
    }
}
