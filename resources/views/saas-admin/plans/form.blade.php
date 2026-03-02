@extends('saas-admin.layouts.admin')
@section('title', $plan ? 'Edit ' . $plan->name : 'New Plan')

@section('content')
    <div class="page-header">
        <h1>{{ $plan ? 'Edit Plan' : 'Create Plan' }}</h1>
    </div>

    <div class="card" style="max-width:700px;">
        <form method="POST" action="{{ $plan ? route('saas-admin.plans.update', $plan) : route('saas-admin.plans.store') }}">
            @csrf
            @if($plan) @method('PUT') @endif

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $plan?->name) }}" required>
                    @error('name') <small style="color:#dc2626;">{{ $message }}</small> @enderror
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-input" value="{{ old('slug', $plan?->slug) }}" required>
                    @error('slug') <small style="color:#dc2626;">{{ $message }}</small> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" rows="2">{{ old('description', $plan?->description) }}</textarea>
                @error('description') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Monthly Price (cents)</label>
                    <input type="number" name="price_monthly" class="form-input" value="{{ old('price_monthly', $plan?->price_monthly ?? 0) }}" min="0" required>
                    @error('price_monthly') <small style="color:#dc2626;">{{ $message }}</small> @enderror
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Yearly Price (cents)</label>
                    <input type="number" name="price_yearly" class="form-input" value="{{ old('price_yearly', $plan?->price_yearly ?? 0) }}" min="0" required>
                    @error('price_yearly') <small style="color:#dc2626;">{{ $message }}</small> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-input" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}" min="0" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Trial Days</label>
                    <input type="number" name="trial_days" class="form-input" value="{{ old('trial_days', $plan?->trial_days ?? 0) }}" min="0" required>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}>
                    <span class="form-label" style="margin:0;">Active</span>
                </label>
            </div>

            @if($features->isNotEmpty())
                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e5e7eb;">
                    <h3 style="margin-bottom:1rem;font-size:0.875rem;font-weight:600;text-transform:uppercase;color:#6b7280;">Feature Values</h3>
                    @php
                        $currentValues = $plan ? $plan->featureValues->keyBy(fn($fv) => $fv->feature->code) : collect();
                    @endphp
                    @foreach($features as $feature)
                        <div class="form-group">
                            <label class="form-label">{{ $feature->code }} <span style="color:#9ca3af;font-weight:400;">({{ $feature->type }})</span></label>
                            @if($feature->isBoolean())
                                <select name="features[{{ $feature->code }}]" class="form-select">
                                    <option value="">— not set —</option>
                                    <option value="true" {{ old("features.{$feature->code}", $currentValues[$feature->code]?->value ?? '') === 'true' ? 'selected' : '' }}>true</option>
                                    <option value="false" {{ old("features.{$feature->code}", $currentValues[$feature->code]?->value ?? '') === 'false' ? 'selected' : '' }}>false</option>
                                </select>
                            @else
                                <input type="text" name="features[{{ $feature->code }}]" class="form-input"
                                       value="{{ old("features.{$feature->code}", $currentValues[$feature->code]?->value ?? '') }}"
                                       placeholder="e.g. 100, unlimited">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2" style="margin-top:1rem;">
                <button type="submit" class="btn btn-primary">{{ $plan ? 'Save Changes' : 'Create Plan' }}</button>
                <a href="{{ $plan ? route('saas-admin.plans.show', $plan) : route('saas-admin.plans.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>

        @if($plan && $plan->subscriptions()->doesntExist())
            <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid #e5e7eb;">
                <form method="POST" action="{{ route('saas-admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan permanently?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete Plan</button>
                </form>
            </div>
        @endif
    </div>
@endsection
