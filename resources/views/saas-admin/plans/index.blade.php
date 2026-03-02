@extends('saas-admin.layouts.admin')
@section('title', 'Plans')

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>Plans</h1>
            <p>Manage subscription plans and features</p>
        </div>
        <a href="{{ route('saas-admin.plans.create') }}" class="btn btn-primary">+ New Plan</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Monthly</th>
                    <th>Yearly</th>
                    <th>Trial</th>
                    <th>Subs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                <tr>
                    <td>{{ $plan->sort_order }}</td>
                    <td><a href="{{ route('saas-admin.plans.show', $plan) }}" style="color:#2563eb;text-decoration:none;">{{ $plan->name }}</a></td>
                    <td>{{ $plan->slug }}</td>
                    <td>${{ number_format($plan->price_monthly / 100, 2) }}</td>
                    <td>${{ number_format($plan->price_yearly / 100, 2) }}</td>
                    <td>{{ $plan->trial_days }}d</td>
                    <td>{{ $plan->subscriptions_count }}</td>
                    <td>
                        @if($plan->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-gray">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('saas-admin.plans.edit', $plan) }}" class="btn btn-primary btn-sm">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:#6b7280;">No plans found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
