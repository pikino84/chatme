<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\DripEnrollment;
use App\Models\DripSequence;
use App\Models\DripSequenceStep;
use App\Services\DripService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private DripService $dripService)
    {
    }

    public function index(Request $request)
    {
        if (! $request->user()->can('drip.view')) {
            abort(403);
        }

        $sequences = DripSequence::where('organization_id', app('tenant')->id)
            ->withCount('steps', 'enrollments')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('drip.index', compact('sequences'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', DripSequence::class);

        return view('drip.form', ['sequence' => null]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', DripSequence::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => 'nullable|string|max:255',
        ]);

        try {
            $sequence = $this->dripService->create(app('tenant')->id, $data);
        } catch (\App\Exceptions\BillingLimitException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('drip.show', $sequence)->with('success', 'Secuencia creada.');
    }

    public function show(Request $request, DripSequence $sequence)
    {
        $this->authorize('view', $sequence);

        $sequence->load('steps');
        $enrollments = $sequence->enrollments()->with('contact')->orderByDesc('created_at')->paginate(20);

        return view('drip.show', compact('sequence', 'enrollments'));
    }

    public function edit(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        return view('drip.form', compact('sequence'));
    }

    public function update(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => 'nullable|string|max:255',
        ]);

        $this->dripService->update($sequence, $data);

        return redirect()->route('drip.show', $sequence)->with('success', 'Secuencia actualizada.');
    }

    public function activate(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        try {
            $this->dripService->activate($sequence);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Secuencia activada.');
    }

    public function pause(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        $this->dripService->pause($sequence);

        return back()->with('success', 'Secuencia pausada.');
    }

    public function destroy(Request $request, DripSequence $sequence)
    {
        $this->authorize('delete', $sequence);

        $sequence->enrollments()->delete();
        $sequence->steps()->delete();
        $sequence->delete();

        return redirect()->route('drip.index')->with('success', 'Secuencia eliminada.');
    }

    public function addStep(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        $data = $request->validate([
            'delay_minutes' => 'required|integer|min:1',
            'message_template' => 'required|string|max:5000',
            'channel_type' => 'required|string|in:whatsapp,webchat',
        ]);

        $this->dripService->addStep($sequence, $data);

        return back()->with('success', 'Paso agregado.');
    }

    public function updateStep(Request $request, DripSequence $sequence, DripSequenceStep $step)
    {
        $this->authorize('update', $sequence);

        $data = $request->validate([
            'delay_minutes' => 'required|integer|min:1',
            'message_template' => 'required|string|max:5000',
            'channel_type' => 'required|string|in:whatsapp,webchat',
        ]);

        $this->dripService->updateStep($step, $data);

        return back()->with('success', 'Paso actualizado.');
    }

    public function removeStep(Request $request, DripSequence $sequence, DripSequenceStep $step)
    {
        $this->authorize('update', $sequence);

        $this->dripService->removeStep($step);

        return back()->with('success', 'Paso eliminado.');
    }

    public function enroll(Request $request, DripSequence $sequence)
    {
        $this->authorize('update', $sequence);

        $request->validate([
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = Contact::findOrFail($request->input('contact_id'));

        try {
            $this->dripService->enroll($sequence, $contact, app('tenant')->id);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Contacto inscrito en la secuencia.');
    }

    public function cancelEnrollment(Request $request, DripSequence $sequence, DripEnrollment $enrollment)
    {
        $this->authorize('update', $sequence);

        $this->dripService->cancelEnrollment($enrollment);

        return back()->with('success', 'Inscripción cancelada.');
    }
}
