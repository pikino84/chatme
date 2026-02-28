<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationSubscription>
 */
class OrganizationSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }

    public function trialing(): static
    {
        return $this->state([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state([
            'status' => 'canceled',
            'canceled_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state([
            'status' => 'past_due',
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'canceled',
            'canceled_at' => now()->subDays(60),
            'grace_period_ends_at' => now()->subDays(30),
        ]);
    }

    public function yearly(): static
    {
        return $this->state([
            'billing_cycle' => 'yearly',
            'ends_at' => now()->addYear(),
        ]);
    }

    public function withStripe(): static
    {
        return $this->state([
            'stripe_customer_id' => 'cus_' . fake()->regexify('[A-Za-z0-9]{14}'),
            'stripe_subscription_id' => 'sub_' . fake()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }
}
