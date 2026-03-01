<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealAttachment;
use App\Models\DealNote;
use App\Models\DealStageHistory;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DealService
{
    public function convertToDeal(Conversation $conversation, ?User $actor = null, array $overrides = []): Deal
    {
        $pipeline = Pipeline::withoutGlobalScopes()
            ->where('organization_id', $conversation->organization_id)
            ->where('is_default', true)
            ->firstOrFail();

        $firstStage = $pipeline->firstStage;

        $data = array_merge([
            'organization_id' => $conversation->organization_id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $firstStage->id,
            'conversation_id' => $conversation->id,
            'assigned_user_id' => $conversation->assigned_user_id,
            'contact_name' => $conversation->contact_name,
            'contact_email' => $conversation->metadata['email'] ?? null,
            'contact_phone' => $conversation->contact_identifier,
            'value' => 0,
            'currency' => 'MXN',
            'stage_entered_at' => now(),
            'status' => 'open',
        ], $overrides);

        $deal = Deal::create($data);

        $this->recordStageHistory($deal, null, $firstStage, $actor);

        return $deal;
    }

    public function createDeal(array $data, ?User $actor = null): Deal
    {
        $pipeline = Pipeline::withoutGlobalScopes()->findOrFail($data['pipeline_id']);

        if ($pipeline->organization_id !== ($data['organization_id'] ?? null)) {
            throw new \InvalidArgumentException('Pipeline does not belong to the specified organization.');
        }

        if (!isset($data['pipeline_stage_id'])) {
            $data['pipeline_stage_id'] = $pipeline->firstStage->id;
        }

        $data['stage_entered_at'] = $data['stage_entered_at'] ?? now();
        $data['status'] = $data['status'] ?? 'open';
        $data['currency'] = $data['currency'] ?? 'MXN';

        $deal = Deal::create($data);

        $stage = PipelineStage::withoutGlobalScopes()->find($deal->pipeline_stage_id);
        $this->recordStageHistory($deal, null, $stage, $actor);

        return $deal;
    }

    public function moveToStage(Deal $deal, PipelineStage $newStage, ?User $actor = null): Deal
    {
        $oldStage = $deal->stage;

        $deal->update([
            'pipeline_id' => $newStage->pipeline_id,
            'pipeline_stage_id' => $newStage->id,
            'stage_entered_at' => now(),
            'status' => $newStage->is_won ? 'won' : ($newStage->is_lost ? 'lost' : 'open'),
            'closed_at' => $newStage->isTerminal() ? now() : null,
        ]);

        $this->recordStageHistory($deal, $oldStage, $newStage, $actor);

        return $deal->fresh();
    }

    public function setDefaultPipeline(Pipeline $pipeline): void
    {
        Pipeline::withoutGlobalScopes()
            ->where('organization_id', $pipeline->organization_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $pipeline->update(['is_default' => true]);
    }

    public function addNote(Deal $deal, User $user, string $body): DealNote
    {
        return DealNote::create([
            'organization_id' => $deal->organization_id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }

    public function addAttachment(Deal $deal, User $user, UploadedFile $file): DealAttachment
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $path = $file->storeAs(
            "deal-attachments/{$deal->organization_id}/{$deal->id}",
            Str::uuid() . '.' . $extension,
            'local'
        );

        return DealAttachment::create([
            'organization_id' => $deal->organization_id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }

    private function recordStageHistory(Deal $deal, ?PipelineStage $from, ?PipelineStage $to, ?User $actor): void
    {
        DealStageHistory::create([
            'organization_id' => $deal->organization_id,
            'deal_id' => $deal->id,
            'from_stage_id' => $from?->id,
            'to_stage_id' => $to->id,
            'changed_by' => $actor?->id,
            'changed_at' => now(),
        ]);
    }
}
