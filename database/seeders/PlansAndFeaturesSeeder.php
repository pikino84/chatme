<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanFeatureValue;
use Illuminate\Database\Seeder;

class PlansAndFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        // Features catalog
        $features = [
            ['code' => 'max_agents', 'description' => 'Maximum number of agents', 'type' => 'limit'],
            ['code' => 'max_channels', 'description' => 'Maximum number of channels', 'type' => 'limit'],
            ['code' => 'max_conversations_monthly', 'description' => 'Monthly conversation limit', 'type' => 'limit'],
            ['code' => 'max_messages_monthly', 'description' => 'Monthly message limit', 'type' => 'limit'],
            ['code' => 'webchat_enabled', 'description' => 'Webchat widget access', 'type' => 'boolean'],
            ['code' => 'whatsapp_enabled', 'description' => 'WhatsApp integration', 'type' => 'boolean'],
            ['code' => 'sla_tracking', 'description' => 'SLA monitoring and alerts', 'type' => 'boolean'],
            ['code' => 'api_access', 'description' => 'REST API access', 'type' => 'boolean'],
            ['code' => 'custom_branding', 'description' => 'Custom branding on widget', 'type' => 'boolean'],
        ];

        foreach ($features as $feature) {
            PlanFeature::updateOrCreate(['code' => $feature['code']], $feature);
        }

        // Plans
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Para equipos pequeños que comienzan con atención al cliente',
                'price_monthly' => 49900,
                'price_yearly' => 499900,
                'sort_order' => 1,
                'trial_days' => 14,
                'features' => [
                    'max_agents' => '3',
                    'max_channels' => '1',
                    'max_conversations_monthly' => '100',
                    'max_messages_monthly' => '500',
                    'webchat_enabled' => 'false',
                    'whatsapp_enabled' => 'true',
                    'sla_tracking' => 'false',
                    'api_access' => 'false',
                    'custom_branding' => 'false',
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Para equipos en crecimiento con múltiples canales',
                'price_monthly' => 99900,
                'price_yearly' => 999900,
                'sort_order' => 2,
                'trial_days' => 14,
                'features' => [
                    'max_agents' => '10',
                    'max_channels' => '5',
                    'max_conversations_monthly' => '1000',
                    'max_messages_monthly' => '10000',
                    'webchat_enabled' => 'true',
                    'whatsapp_enabled' => 'true',
                    'sla_tracking' => 'true',
                    'api_access' => 'false',
                    'custom_branding' => 'false',
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Para empresas con necesidades avanzadas',
                'price_monthly' => 249900,
                'price_yearly' => 2499900,
                'sort_order' => 3,
                'trial_days' => 0,
                'features' => [
                    'max_agents' => 'unlimited',
                    'max_channels' => 'unlimited',
                    'max_conversations_monthly' => 'unlimited',
                    'max_messages_monthly' => 'unlimited',
                    'webchat_enabled' => 'true',
                    'whatsapp_enabled' => 'true',
                    'sla_tracking' => 'true',
                    'api_access' => 'true',
                    'custom_branding' => 'true',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $featureValues = $planData['features'];
            unset($planData['features']);

            $plan = Plan::updateOrCreate(['slug' => $planData['slug']], $planData);

            foreach ($featureValues as $featureCode => $value) {
                $feature = PlanFeature::where('code', $featureCode)->first();
                if ($feature) {
                    PlanFeatureValue::updateOrCreate(
                        ['plan_id' => $plan->id, 'plan_feature_id' => $feature->id],
                        ['value' => $value]
                    );
                }
            }
        }
    }
}
