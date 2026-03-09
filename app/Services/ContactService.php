<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Collection;

class ContactService
{
    public function __construct(private BillingService $billingService)
    {
    }

    public function create(int $organizationId, array $data): Contact
    {
        $org = \App\Models\Organization::findOrFail($organizationId);

        if (! $this->canCreateContact($org)) {
            throw new \App\Exceptions\BillingLimitException('Contact limit reached for your plan.');
        }

        $data['organization_id'] = $organizationId;

        return Contact::create($data);
    }

    private function canCreateContact(\App\Models\Organization $org): bool
    {
        $subscription = $this->billingService->getActiveSubscription($org);
        if (! $subscription || ! $subscription->hasAccess()) {
            return false;
        }

        $value = $subscription->plan->getFeatureValue('max_contacts');
        if (! $value) {
            return false;
        }
        if ($value === 'unlimited') {
            return true;
        }

        $currentCount = Contact::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->count();

        return $currentCount < (int) $value;
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact->fresh();
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    public function findOrCreateByPhone(int $organizationId, string $phone, array $defaults = []): Contact
    {
        return Contact::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('phone', $phone)
            ->first()
            ?? $this->create($organizationId, array_merge($defaults, ['phone' => $phone]));
    }

    public function findOrCreateByEmail(int $organizationId, string $email, array $defaults = []): Contact
    {
        return Contact::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('email', $email)
            ->first()
            ?? $this->create($organizationId, array_merge($defaults, ['email' => $email]));
    }

    public function merge(Contact $primary, Contact $secondary): Contact
    {
        if ($primary->organization_id !== $secondary->organization_id) {
            throw new \InvalidArgumentException('Cannot merge contacts from different organizations.');
        }

        // Transfer conversations
        $secondary->conversations()->update(['contact_id' => $primary->id]);

        // Transfer deals
        $secondary->deals()->update(['contact_id' => $primary->id]);

        // Merge missing fields into primary
        $fillable = ['email', 'phone', 'company', 'notes', 'external_id', 'channel_type'];
        foreach ($fillable as $field) {
            if (empty($primary->{$field}) && ! empty($secondary->{$field})) {
                $primary->{$field} = $secondary->{$field};
            }
        }

        // Merge metadata
        if (! empty($secondary->metadata)) {
            $primary->metadata = array_merge($secondary->metadata ?? [], $primary->metadata ?? []);
        }

        $primary->save();
        $secondary->delete();

        return $primary->fresh();
    }

    public function importCsv(int $organizationId, string $csvContent): array
    {
        $org = \App\Models\Organization::findOrFail($organizationId);
        $lines = array_filter(explode("\n", $csvContent));

        if (count($lines) < 2) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV must have a header and at least one data row.']];
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $allowedFields = ['name', 'email', 'phone', 'company', 'notes'];
        $nameIdx = array_search('name', $headers);

        if ($nameIdx === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV must have a "name" column.']];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $fields = str_getcsv($line);
            if (count($fields) !== count($headers)) {
                $errors[] = "Line " . ($lineNum + 2) . ": column count mismatch.";
                $skipped++;
                continue;
            }

            if (! $this->canCreateContact($org)) {
                $errors[] = "Line " . ($lineNum + 2) . ": contact limit reached.";
                $skipped += count($lines) - $lineNum;
                break;
            }

            $row = array_combine($headers, $fields);
            $data = array_intersect_key($row, array_flip($allowedFields));

            if (empty($data['name'])) {
                $errors[] = "Line " . ($lineNum + 2) . ": name is required.";
                $skipped++;
                continue;
            }

            // Skip duplicate by phone or email
            if (! empty($data['phone'])) {
                $exists = Contact::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('phone', $data['phone'])
                    ->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }
            }

            $data['organization_id'] = $organizationId;
            Contact::create($data);
            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }
}
