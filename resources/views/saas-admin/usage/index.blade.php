@extends('saas-admin.layouts.admin')
@section('title', 'Usage Monitoring')

@section('content')
    <div class="page-header">
        <h1>Usage Monitoring</h1>
        <p>Feature usage per organization</p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('saas-admin.usage.index') }}" class="flex gap-2 items-center">
            <select name="period" class="form-select" style="max-width:180px;">
                @if($periods->isEmpty())
                    <option value="{{ $period }}">{{ $period }}</option>
                @endif
                @foreach($periods as $p)
                    <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">View</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Feature</th>
                    <th>Usage</th>
                    <th>Period</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usage as $record)
                <tr>
                    <td>{{ $record->organization->name ?? 'N/A' }}</td>
                    <td><span class="badge badge-blue">{{ $record->feature_code }}</span></td>
                    <td style="font-weight:600;">{{ number_format($record->usage) }}</td>
                    <td>{{ $record->period }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:#6b7280;">No usage records for this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $usage->links() }}</div>
    </div>
@endsection
