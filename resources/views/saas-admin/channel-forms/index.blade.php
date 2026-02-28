@extends('saas-admin.layouts.admin')

@section('title', 'Channel Forms')

@section('content')
<div class="page-header flex justify-between items-center">
    <div>
        <h1>Channel Forms</h1>
        <p>Manage pre-chat forms for webchat channels</p>
    </div>
    <a href="{{ route('saas-admin.channel-forms.create') }}" class="btn btn-primary">Assign Form</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Channel</th>
                <th>Organization</th>
                <th>Template</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($forms as $form)
            <tr>
                <td>{{ $form->channel->name ?? 'N/A' }}</td>
                <td>{{ $form->channel->organization->name ?? 'N/A' }}</td>
                <td><span class="badge badge-blue">{{ $form->template_key ?? 'custom' }}</span></td>
                <td>
                    @if($form->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-gray">Inactive</span>
                    @endif
                </td>
                <td>{{ $form->created_at->format('Y-m-d') }}</td>
                <td class="flex gap-2">
                    <a href="{{ route('saas-admin.channel-forms.show', $form) }}" class="btn btn-primary btn-sm">View</a>
                    <form method="POST" action="{{ route('saas-admin.channel-forms.toggle', $form) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn {{ $form->is_active ? 'btn-warning' : 'btn-success' }} btn-sm">
                            {{ $form->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('saas-admin.channel-forms.destroy', $form) }}" style="display:inline" onsubmit="return confirm('Remove this form?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:#6b7280;">No forms assigned yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $forms->links() }}</div>
</div>
@endsection
