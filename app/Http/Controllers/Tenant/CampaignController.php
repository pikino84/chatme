<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Contact;
use App\Services\CampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private CampaignService $campaignService)
    {
    }

    public function index(Request $request)
    {
        if (! $request->user()->can('campaigns.view')) {
            abort(403);
        }

        $campaigns = Campaign::where('organization_id', app('tenant')->id)
            ->with('channel', 'createdBy')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $channels = Channel::where('organization_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('campaigns.form', ['campaign' => null, 'channels' => $channels]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'channel_id' => 'required|exists:channels,id',
            'message_template' => 'required|string|max:5000',
        ]);

        try {
            $campaign = $this->campaignService->create(
                app('tenant')->id,
                $request->user()->id,
                $data
            );
        } catch (\App\Exceptions\BillingLimitException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña creada.');
    }

    public function show(Request $request, Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $campaign->load('channel', 'createdBy');
        $recipients = $campaign->recipients()->with('contact')->paginate(50);
        $stats = $this->campaignService->getStats($campaign);

        return view('campaigns.show', compact('campaign', 'recipients', 'stats'));
    }

    public function edit(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $channels = Channel::where('organization_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('campaigns.form', compact('campaign', 'channels'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'channel_id' => 'required|exists:channels,id',
            'message_template' => 'required|string|max:5000',
        ]);

        $this->campaignService->update($campaign, $data);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña actualizada.');
    }

    public function addRecipients(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'exists:contacts,id',
        ]);

        $added = $this->campaignService->addRecipients($campaign, $request->input('contact_ids'));

        return back()->with('success', "{$added} destinatarios agregados.");
    }

    public function removeRecipient(Request $request, Campaign $campaign, int $contactId)
    {
        $this->authorize('update', $campaign);

        $this->campaignService->removeRecipient($campaign, $contactId);

        return back()->with('success', 'Destinatario eliminado.');
    }

    public function send(Request $request, Campaign $campaign)
    {
        $this->authorize('send', $campaign);

        try {
            $this->campaignService->send($campaign);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña enviando...');
    }

    public function cancel(Request $request, Campaign $campaign)
    {
        $this->authorize('send', $campaign);

        $this->campaignService->cancel($campaign);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaña cancelada.');
    }

    public function destroy(Request $request, Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaña eliminada.');
    }

    public function selectRecipients(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $existingIds = $campaign->recipients()->pluck('contact_id')->toArray();

        $contacts = Contact::where('organization_id', app('tenant')->id)
            ->whereNotIn('id', $existingIds)
            ->whereNotNull('phone')
            ->orderBy('name')
            ->paginate(50);

        return view('campaigns.select-recipients', compact('campaign', 'contacts'));
    }
}
