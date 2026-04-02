<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccessRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
        ]);

        // Send notification email
        Mail::raw(
            "Nueva solicitud de acceso a ChatMe:\n\n" .
            "Nombre: {$validated['name']}\n" .
            "Email: {$validated['email']}\n" .
            "Teléfono: " . ($validated['phone'] ?? 'No proporcionado') . "\n" .
            "Empresa: " . ($validated['company'] ?? 'No proporcionada') . "\n\n" .
            "Fecha: " . now()->format('d/m/Y H:i') . "\n",
            function ($message) use ($validated) {
                $message->to('jfcruz@outlook.com')
                    ->subject('Nueva solicitud de acceso - ' . $validated['name']);
            }
        );

        Log::info('Access request received', $validated);

        return back()->with('success', '¡Gracias! Hemos recibido tu solicitud. Te contactaremos pronto.');
    }
}
