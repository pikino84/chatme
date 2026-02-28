<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelForm;
use Illuminate\Http\Request;

class ChannelFormController extends Controller
{
    public function index()
    {
        $forms = ChannelForm::with(['channel.organization'])
            ->latest()
            ->paginate(20);

        return view('saas-admin.channel-forms.index', compact('forms'));
    }

    public function show(ChannelForm $channelForm)
    {
        $channelForm->load('channel.organization');
        $templates = config('form_templates');

        return view('saas-admin.channel-forms.show', compact('channelForm', 'templates'));
    }

    public function create(Request $request)
    {
        $channels = Channel::withoutGlobalScopes()
            ->where('type', 'webchat')
            ->whereDoesntHave('form')
            ->with('organization')
            ->get();

        $templates = config('form_templates');

        return view('saas-admin.channel-forms.create', compact('channels', 'templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id|unique:channel_forms,channel_id',
            'template_key' => 'required|string',
        ]);

        $template = config("form_templates.{$validated['template_key']}");

        if (!$template) {
            return back()->with('error', 'Invalid template key.')->withInput();
        }

        ChannelForm::create([
            'channel_id' => $validated['channel_id'],
            'template_key' => $validated['template_key'],
            'schema' => $template,
            'is_active' => true,
        ]);

        return redirect()->route('saas-admin.channel-forms.index')
            ->with('success', 'Form assigned to channel.');
    }

    public function toggleActive(ChannelForm $channelForm)
    {
        $channelForm->update(['is_active' => !$channelForm->is_active]);

        $status = $channelForm->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Form {$status}.");
    }

    public function destroy(ChannelForm $channelForm)
    {
        $channelForm->delete();

        return redirect()->route('saas-admin.channel-forms.index')
            ->with('success', 'Form removed from channel.');
    }
}
