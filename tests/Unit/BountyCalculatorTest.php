<?php

namespace Tests\Unit;

use App\Models\PostIt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BountyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BountyCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private BountyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BountyCalculator;
    }

    public function test_it_calculates_base_bounty_points_for_priorities(): void
    {
        // Base is weight * 10
        // weight 2 base = 20
        $this->assertEquals(16, $this->calculator->calculate(weight: 2, priority: 'low'));    // 20 * 0.8 = 16
        $this->assertEquals(20, $this->calculator->calculate(weight: 2, priority: 'normal')); // 20 * 1.0 = 20
        $this->assertEquals(25, $this->calculator->calculate(weight: 2, priority: 'high'));   // 20 * 1.25 = 25
        $this->assertEquals(30, $this->calculator->calculate(weight: 2, priority: 'urgent')); // 20 * 1.5 = 30
    }

    public function test_it_clamps_weight_between_one_and_five(): void
    {
        // Weight 0 clamps to 1 -> base 10 * 1.0 = 10
        $this->assertEquals(10, $this->calculator->calculate(weight: 0, priority: 'normal'));

        // Weight 10 clamps to 5 -> base 50 * 1.0 = 50
        $this->assertEquals(50, $this->calculator->calculate(weight: 10, priority: 'normal'));
    }

    public function test_it_calculates_historical_duration_bonus_when_weight_tasks_take_longer(): void
    {
        $tenant = Tenant::factory()->create();

        // Seed baseline completed tasks for tenant
        // Task of weight 1 takes 1 hour (3600s)
        PostIt::factory()->done()->create([
            'tenant_id' => $tenant->id,
            'weight' => 1,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(1),
        ]);

        // Task of weight 4 takes 5 hours (18000s)
        PostIt::factory()->done()->create([
            'tenant_id' => $tenant->id,
            'weight' => 4,
            'started_at' => now()->subHours(6),
            'completed_at' => now()->subHours(1),
        ]);

        // Overall average duration: (3600 + 18000) / 2 = 10800s
        // Weight 4 duration: 18000s > 10800s.
        // Ratio = (18000 - 10800) / 10800 = 7200 / 10800 = 0.6667
        // Capped at MAX_HISTORICAL_BONUS (0.25)
        $bonus = $this->calculator->calculateHistoricalBonus($tenant->id, 4);
        $this->assertEquals(0.25, $bonus);

        // For weight 1 (3600s <= 10800s), bonus should be 0.0
        $bonusWeight1 = $this->calculator->calculateHistoricalBonus($tenant->id, 1);
        $this->assertEquals(0.0, $bonusWeight1);

        // Calculated points for weight 4 urgent with +25% bonus:
        // Base 40 * 1.5 * 1.25 = 75
        $points = $this->calculator->calculate(weight: 4, priority: 'urgent', tenantId: $tenant->id);
        $this->assertEquals(75, $points);
    }

    public function test_it_calculates_split_without_helper(): void
    {
        $split = $this->calculator->calculateSplit(bountyPoints: 50, helperSplit: 0, hasHelper: false);

        $this->assertEquals(50, $split['assignee_points']);
        $this->assertEquals(0, $split['helper_points']);
    }

    public function test_it_calculates_split_with_helper_and_clamps_max_fifty_percent(): void
    {
        // 30% split on 50 points = 15 points helper, 35 assignee
        $split = $this->calculator->calculateSplit(bountyPoints: 50, helperSplit: 30, hasHelper: true);
        $this->assertEquals(35, $split['assignee_points']);
        $this->assertEquals(15, $split['helper_points']);

        // 70% split clamped to 50% = 25 points helper, 25 points assignee
        $splitClamped = $this->calculator->calculateSplit(bountyPoints: 50, helperSplit: 70, hasHelper: true);
        $this->assertEquals(25, $splitClamped['assignee_points']);
        $this->assertEquals(25, $splitClamped['helper_points']);
    }

    public function test_it_awards_payout_and_increments_user_points(): void
    {
        $tenant = Tenant::factory()->create();
        $assignee = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 10,
        ]);
        $helper = User::factory()->worker()->create([
            'tenant_id' => $tenant->id,
            'total_points' => 5,
        ]);

        $postIt = PostIt::factory()->done($assignee)->assisted($helper, 20)->create([
            'tenant_id' => $tenant->id,
            'bounty_points' => 40,
        ]);

        $split = $this->calculator->awardPayout($postIt);

        $this->assertEquals(32, $split['assignee_points']); // 80% of 40 = 32
        $this->assertEquals(8, $split['helper_points']);     // 20% of 40 = 8

        $this->assertEquals(42, $assignee->fresh()->total_points); // 10 + 32
        $this->assertEquals(13, $helper->fresh()->total_points);   // 5 + 8
    }
}
