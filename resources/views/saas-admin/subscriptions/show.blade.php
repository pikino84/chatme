@extends('saas-admin.layouts.admin')
@section('title', 'Subscription #' . $subscription->id)

@section('content')
    <div class="page-header">
        <h1>Subscription #{{ $subscription->id }}</h1>
        <p>{{ $subscription->organization->name ?? 'Unknown' }}</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Current Plan</div>
            <div class="stat-value" style="font-size:1.25rem;">{{ $subscription->plan->name ?? 'N/A' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Status</div>
            <div class="stat-value" style="font-size:1.25rem;">
                <span class="badge badge-{{ $subscription->status === 'active' ? 'green' : ($subscription->status === 'trialing' ? 'blue' : 'yellow') }}">
                    {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                </span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Billing Cycle</div>
            <div class="stat-value" style="font-size:1.25rem;">{{ ucfirst($subscription->billing_cycle) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Stripe</div>
            <div class="stat-value" style="font-size:1rem;">{{ $subscription->isManual() ? 'Manual' : $subscription->stripe_subscription_id }}</div>
        </div>
    </div>

    <div class="card" style="max-width:600px;">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Update Subscription</h2>
        <form method="POST" action="{{ route('saas-admin.subscriptions.update', $subscription->id) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Plan</label>
                <select name="plan_id" class="form-select">
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ $subscription->plan_id == $plan->id ? 'selected' : '' }}>
                            {{ $plan->name }} (${{ number_format($plan->price_monthly / 100, 2) }}/mo)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(['active', 'trialing', 'past_due', 'canceled'] as $status)
                        <option value="{{ $status }}" {{ $subscription->status === $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Billing Cycle</label>
                <select name="billing_cycle" class="form-select">
                    <option value="monthly" {{ $subscription->billing_cycle === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="yearly" {{ $subscription->billing_cycle === 'yearly' ? 'selected' : '' }}>Yearly</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Update Subscription</button>
                <a href="{{ route('saas-admin.subscriptions.index') }}" class="btn" style="background:#e5e7eb;">Back</a>
            </div>
        </form>
    </div>
@endsection
