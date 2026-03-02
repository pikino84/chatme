@extends('saas-admin.layouts.admin')
@section('title', 'New Subscription')

@section('content')
    <div class="page-header">
        <h1>Create Subscription</h1>
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ route('saas-admin.subscriptions.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Organization</label>
                <select name="organization_id" class="form-select" required>
                    <option value="">Select organization</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
                @error('organization_id') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Plan</label>
                <select name="plan_id" class="form-select" required>
                    <option value="">Select plan</option>
                    @foreach($plans as $p)
                        <option value="{{ $p->id }}" {{ old('plan_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} (${{ number_format($p->price_monthly / 100, 2) }}/mo)</option>
                    @endforeach
                </select>
                @error('plan_id') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="trialing" {{ old('status') === 'trialing' ? 'selected' : '' }}>Trialing</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Billing Cycle</label>
                    <select name="billing_cycle" class="form-select" required>
                        <option value="monthly" {{ old('billing_cycle') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="yearly" {{ old('billing_cycle') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Create Subscription</button>
                <a href="{{ route('saas-admin.subscriptions.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
