<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;

        $clienti = Client::when($search, function ($query, $search) {
            $query->where('nume', 'like', '%' . $search . '%')
                ->orWhere('prenume', 'like', '%' . $search . '%')
                ->orWhere('telefon', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%');
        })
            ->orderBy('nume')
            ->orderBy('prenume')
            ->simplePaginate(25);

        return view('clienti.index', compact('clienti', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.save');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:100'],
            'prenume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $client = Client::create($data);

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('success', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost adaugat cu succes!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Client $client)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Client $client)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('clienti.save', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:100'],
            'prenume' => ['required', 'string', 'max:100'],
            'adresa' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $client->update($data);

        return redirect($request->session()->get('returnUrl', route('clienti.index')))
            ->with('status', 'Clientul <strong>' . e($client->nume_complet) . '</strong> a fost modificat cu succes!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return back()->with('status', 'Clientul a fost sters cu succes!');
    }
}
