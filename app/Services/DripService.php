<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\DripEnrollment;
use App\Models\DripSequence;
use App\Models\DripSequenceStep;

class DripService
{
    public function __construct(private BillingService $billingService)
    {
    }

    public function create(int $organizationId, array $data): DripSequence
    {
        $org = \App\Models\Organization::findOrFail($organizationId);

        if (! $this->billingService->checkFeature($org, 'drip_sequences_enabled')) {
            throw new \App\Exceptions\BillingLimitException('Drip sequences are not available on your current plan.');
        }

        return DripSequence::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'status' => 'paused',
            'trigger_event' => $data['trigger_event'] ?? null,
        ]);
    }

    public function update(DripSequence $sequence, array $data): DripSequence
    {
        $sequence->update(array_intersect_key($data, array_flip([
            'name', 'trigger_event',
        ])));

        return $sequence->fresh();
    }

    public function activate(DripSequence $sequence): DripSequence
    {
        if ($sequence->steps()->count() === 0) {
            throw new \LogicException('Cannot activate a sequence without steps.');
        }

        $sequence->update(['status' => 'active']);

        return $sequence->fresh();
    }

    public function pause(DripSequence $sequence): DripSequence
    {
        $sequence->update(['status' => 'paused']);

        return $sequence->fresh();
    }

    public function addStep(DripSequence $sequence, array $data): DripSequenceStep
    {
        $maxPosition = $sequence->steps()->max('position') ?? 0;

        return DripSequenceStep::create([
            'drip_sequence_id' => $sequence->id,
            'position' => $maxPosition + 1,
            'delay_minutes' => $data['delay_minutes'],
            'message_template' => $data['message_template'],
            'channel_type' => $data['channel_type'] ?? 'whatsapp',
        ]);
    }

    public function updateStep(DripSequenceStep $step, array $data): DripSequenceStep
    {
        $step->update(array_intersect_key($data, array_flip([
            'delay_minutes', 'message_template', 'channel_type',
        ])));

        return $step->fresh();
    }

    public function removeStep(DripSequenceStep $step): void
    {
        $sequenceId = $step->drip_sequence_id;
        $position = $step->position;
        $step->delete();

        // Reorder remaining steps
        DripSequenceStep::where('drip_sequence_id', $sequenceId)
            ->where('position', '>', $position)
            ->decrement('position');
    }

    public function enroll(DripSequence $sequence, Contact $contact, int $organizationId): DripEnrollment
    {
        if (! $sequence->isActive()) {
            throw new \LogicException('Cannot enroll in an inactive sequence.');
        }

        $existing = DripEnrollment::withoutGlobalScopes()
            ->where('drip_sequence_id', $sequence->id)
            ->where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw new \LogicException('Contact is already enrolled in this sequence.');
        }

        $firstStep = $sequence->steps()->orderBy('position')->first();
        if (! $firstStep) {
            throw new \LogicException('Sequence has no steps.');
        }

        return DripEnrollment::create([
            'drip_sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'organization_id' => $organizationId,
            'current_step' => 1,
            'status' => 'active',
            'next_step_at' => now()->addMinutes($firstStep->delay_minutes),
        ]);
    }

    public function cancelEnrollment(DripEnrollment $enrollment): void
    {
        $enrollment->update([
            'status' => 'canceled',
            'next_step_at' => null,
        ]);
    }

    public function processNextStep(DripEnrollment $enrollment): void
    {
        if (! $enrollment->isActive()) {
            return;
        }

        $sequence = $enrollment->sequence;
        if (! $sequence->isActive()) {
            return;
        }

        $currentStep = $sequence->steps()
            ->where('position', $enrollment->current_step)
            ->first();

        if (! $currentStep) {
            $enrollment->update(['status' => 'completed', 'next_step_at' => null]);
            return;
        }

        // Send the message
        $this->sendStepMessage($enrollment, $currentStep);

        // Advance to next step
        $nextStep = $sequence->steps()
            ->where('position', '>', $currentStep->position)
            ->orderBy('position')
            ->first();

        if ($nextStep) {
            $enrollment->update([
                'current_step' => $nextStep->position,
                'next_step_at' => now()->addMinutes($nextStep->delay_minutes),
            ]);
        } else {
            $enrollment->update([
                'status' => 'completed',
                'next_step_at' => null,
            ]);
        }
    }

    private function sendStepMessage(DripEnrollment $enrollment, DripSequenceStep $step): void
    {
        $contact = $enrollment->contact;

        if (! $contact || ! $contact->phone) {
            return;
        }

        try {
            if ($step->channel_type === 'whatsapp') {
                $channel = \App\Models\Channel::withoutGlobalScopes()
                    ->where('organization_id', $enrollment->organization_id)
                    ->where('type', 'whatsapp')
                    ->where('is_active', true)
                    ->first();

                if ($channel) {
                    $whatsapp = app(WhatsAppService::class);
                    $whatsapp->sendTextMessage($channel, $contact->phone, $step->message_template);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Drip step message failed', [
                'enrollment_id' => $enrollment->id,
                'step_position' => $step->position,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getPendingEnrollments(): \Illuminate\Database\Eloquent\Collection
    {
        return DripEnrollment::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('next_step_at', '<=', now())
            ->get();
    }
}
