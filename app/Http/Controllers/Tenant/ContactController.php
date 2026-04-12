<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ContactService $contactService)
    {
    }

    public function index(Request $request)
    {
        if (! $request->user()->can('contacts.view')) {
            abort(403);
        }

        $tenant = app('tenant');
        $query = Contact::where('organization_id', $tenant->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('company', 'ilike', "%{$search}%");
            });
        }

        $contacts = $query->orderBy('name')->paginate(25);

        return view('contacts.index', compact('contacts'));
    }

    public function show(Request $request, Contact $contact)
    {
        $this->authorize('view', $contact);

        $contact->load([
            'conversations' => fn ($q) => $q->latest()->limit(10),
            'conversations.channel',
            'deals' => fn ($q) => $q->latest()->limit(10),
            'deals.pipeline',
            'deals.stage',
        ]);

        return view('contacts.show', compact('contact'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Contact::class);

        return view('contacts.form', ['contact' => null]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Contact::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $contact = $this->contactService->create(app('tenant')->id, $data);
        } catch (\App\Exceptions\BillingLimitException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('contacts.show', $contact)->with('success', 'Contacto creado.');
    }

    public function edit(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        return view('contacts.form', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        $this->contactService->update($contact, $data);

        return redirect()->route('contacts.show', $contact)->with('success', 'Contacto actualizado.');
    }

    public function destroy(Request $request, Contact $contact)
    {
        $this->authorize('delete', $contact);

        $this->contactService->delete($contact);

        return redirect()->route('contacts.index')->with('success', 'Contacto eliminado.');
    }

    public function importForm(Request $request)
    {
        $this->authorize('import', Contact::class);

        return view('contacts.import');
    }

    public function searchJson(Request $request)
    {
        if (! $request->user()->can('contacts.view')) {
            return response()->json([], 403);
        }

        $tenant = app('tenant');
        $query = Contact::where('organization_id', $tenant->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'ilike', "%{$q}%")
                    ->orWhere('phone', 'ilike', "%{$q}%")
                    ->orWhere('company', 'ilike', "%{$q}%");
            });
        }

        $contacts = $query->orderBy('name')->limit(20)->get(['id', 'name', 'phone', 'company']);

        return response()->json($contacts);
    }

    public function import(Request $request)
    {
        $this->authorize('import', Contact::class);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $content = file_get_contents($request->file('csv_file')->getRealPath());
        $result = $this->contactService->importCsv(app('tenant')->id, $content);

        $message = "Importados: {$result['imported']}, Omitidos: {$result['skipped']}";
        if (! empty($result['errors'])) {
            $message .= '. Errores: ' . implode('; ', array_slice($result['errors'], 0, 5));
        }

        return redirect()->route('contacts.index')->with('success', $message);
    }
}
