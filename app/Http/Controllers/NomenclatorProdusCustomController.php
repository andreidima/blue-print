<?php

namespace App\Http\Controllers;

use App\Models\NomenclatorProdusCustom;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NomenclatorProdusCustomController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:produse.write')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;

        $nomenclator = NomenclatorProdusCustom::query()
            ->canonical()
            ->when($search, function ($query, $search) {
                $query->where('denumire', 'like', '%' . $search . '%');
            })
            ->orderBy('denumire')
            ->simplePaginate(25);

        return view('nomenclator.index', compact('nomenclator', 'search'));
    }

    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        return view('nomenclator.save');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
        ]);

        [$denumire, $lookupKey, $canonicalKey] = $this->normalizeDenumire($data['denumire']);
        $this->validateUniqueness($lookupKey, $canonicalKey);

        $entry = NomenclatorProdusCustom::create([
            'denumire' => $denumire,
            'lookup_key' => $lookupKey,
            'canonical_key' => $canonicalKey,
            'canonical_id' => null,
            'is_canonical' => true,
            'created_by' => $request->user()?->id,
        ]);

        return redirect($request->session()->get('returnUrl', route('nomenclator.index')))
            ->with('success', 'Intrarea <strong>' . e($entry->denumire) . '</strong> a fost adaugata cu succes!');
    }

    public function show(Request $request, NomenclatorProdusCustom $nomenclator)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $nomenclator = $this->ensureCanonical($nomenclator);

        return view('nomenclator.show', compact('nomenclator'));
    }

    public function edit(Request $request, NomenclatorProdusCustom $nomenclator)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $nomenclator = $this->ensureCanonical($nomenclator);

        return view('nomenclator.save', compact('nomenclator'));
    }

    public function update(Request $request, NomenclatorProdusCustom $nomenclator)
    {
        $nomenclator = $this->ensureCanonical($nomenclator);

        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
        ]);

        [$denumire, $lookupKey, $canonicalKey] = $this->normalizeDenumire($data['denumire']);
        $this->validateUniqueness($lookupKey, $canonicalKey, $nomenclator->id);

        $nomenclator->update([
            'denumire' => $denumire,
            'lookup_key' => $lookupKey,
            'canonical_key' => $canonicalKey,
        ]);

        return redirect($request->session()->get('returnUrl', route('nomenclator.index')))
            ->with('status', 'Intrarea <strong>' . e($nomenclator->denumire) . '</strong> a fost modificata cu succes!');
    }

    public function destroy(NomenclatorProdusCustom $nomenclator)
    {
        $nomenclator = $this->ensureCanonical($nomenclator);

        $nomenclator->aliases()->delete();
        $nomenclator->delete();

        return back()->with('status', 'Intrarea a fost stearsa cu succes!');
    }

    private function ensureCanonical(NomenclatorProdusCustom $entry): NomenclatorProdusCustom
    {
        if (!$entry->is_canonical) {
            abort(404);
        }

        return $entry;
    }

    private function normalizeDenumire(string $denumire): array
    {
        $normalizedName = trim($denumire);
        $lookupKey = NomenclatorProdusCustom::makeLookupKey($normalizedName);
        $canonicalKey = NomenclatorProdusCustom::makeCanonicalKey($normalizedName);

        if ($lookupKey === '' || $canonicalKey === '') {
            throw ValidationException::withMessages([
                'denumire' => 'Denumirea nu este valida.',
            ]);
        }

        return [$normalizedName, $lookupKey, $canonicalKey];
    }

    private function validateUniqueness(string $lookupKey, string $canonicalKey, ?int $ignoreId = null): void
    {
        $lookupConflict = NomenclatorProdusCustom::query()
            ->where('lookup_key', $lookupKey)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first();

        if ($lookupConflict) {
            $canonical = $this->resolveCanonical($lookupConflict);
            throw ValidationException::withMessages([
                'denumire' => 'Exista deja in nomenclator: ' . ($canonical?->denumire ?? $lookupConflict->denumire) . '.',
            ]);
        }

        $canonicalConflict = NomenclatorProdusCustom::query()
            ->canonical()
            ->where('canonical_key', $canonicalKey)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first();

        if ($canonicalConflict) {
            throw ValidationException::withMessages([
                'denumire' => 'Exista deja o denumire similara in nomenclator: ' . $canonicalConflict->denumire . '.',
            ]);
        }
    }

    private function resolveCanonical(NomenclatorProdusCustom $entry): NomenclatorProdusCustom
    {
        if ($entry->is_canonical || !$entry->canonical_id) {
            return $entry;
        }

        return $entry->canonical()->first() ?: $entry;
    }
}
