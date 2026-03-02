@extends('saas-admin.layouts.admin')
@section('title', $plan->name)

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>{{ $plan->name }}</h1>
            <p>{{ $plan->description ?? 'No description' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('saas-admin.plans.edit', $plan) }}" class="btn btn-primary btn-sm">Edit</a>
            <a href="{{ route('saas-admin.plans.index') }}" class="btn btn-sm" style="background:#e5e7eb;">Back</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Monthly Price</div>
            <div class="stat-value">${{ number_format($plan->price_monthly / 100, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Yearly Price</div>
            <div class="stat-value">${{ number_format($plan->price_yearly / 100, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Trial Days</div>
            <div class="stat-value">{{ $plan->trial_days }}</div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-bottom:1rem;font-size:1rem;font-weight:600;">Feature Values</h3>
        <table>
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Type</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plan->featureValues as $fv)
                <tr>
                    <td>{{ $fv->feature->code }}</td>
                    <td><span class="badge badge-gray">{{ $fv->feature->type }}</span></td>
                    <td>
                        @if($fv->isUnlimited())
                            <span class="badge badge-blue">unlimited</span>
                        @elseif($fv->value === 'true')
                            <span class="badge badge-green">true</span>
                        @elseif($fv->value === 'false' || $fv->value === '0')
                            <span class="badge badge-red">{{ $fv->value }}</span>
                        @else
                            {{ $fv->value }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align:center;color:#6b7280;">No feature values configured.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
