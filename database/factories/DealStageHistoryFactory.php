<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealStageHistory>
 */
class DealStageHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'deal_id' => Deal::factory(),
            'from_stage_id' => null,
            'to_stage_id' => PipelineStage::factory(),
            'changed_by' => null,
            'changed_at' => now(),
        ];
    }

    public function changedBy(User $user): static
    {
        return $this->state(['changed_by' => $user->id]);
    }
}
