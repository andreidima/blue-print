<?php

namespace App\Http\Controllers;

use App\Models\NomenclatorMaterial;
use Illuminate\Http\Request;

class NomenclatorMaterialController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:produse.write')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $search = $request->search;
        $activ = $request->activ;
        $sort = (string) $request->get('sort', 'denumire');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $materiale = NomenclatorMaterial::query()
            ->when($search, fn ($query, $value) => $query->where('denumire', 'like', '%' . $value . '%'))
            ->when($activ !== null && $activ !== '', fn ($query) => $query->where('activ', (bool) $activ))
            ->when($sort === 'denumire', fn ($query) => $query->orderBy('denumire', $dir))
            ->when($sort === 'unitate_masura', fn ($query) => $query->orderBy('unitate_masura', $dir))
            ->when($sort === 'activ', fn ($query) => $query->orderBy('activ', $dir))
            ->when($sort === 'created_at', fn ($query) => $query->orderBy('created_at', $dir))
            ->when(!in_array($sort, ['denumire', 'unitate_masura', 'activ', 'created_at'], true), fn ($query) => $query->orderBy('denumire'))
            ->orderBy('id')
            ->simplePaginate(25);

        return view('materiale.index', compact('materiale', 'search', 'activ', 'sort', 'dir'));
    }

    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

        return view('materiale.save');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'unitate_masura' => ['required', 'string', 'max:30'],
            'descriere' => ['nullable', 'string', 'max:1000'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $material = NomenclatorMaterial::create([
            'denumire' => trim((string) $data['denumire']),
            'unitate_masura' => trim((string) $data['unitate_masura']),
            'descriere' => trim((string) ($data['descriere'] ?? '')) ?: null,
            'activ' => $request->boolean('activ'),
            'created_by' => $request->user()?->id,
        ]);

        return redirect($request->session()->get('returnUrl', route('materiale.index')))
            ->with('success', 'Materialul <strong>' . e($material->denumire) . '</strong> a fost adaugat cu succes!');
    }

    public function show(Request $request, NomenclatorMaterial $materiale)
    {
        $this->rememberReturnUrl($request);

        return view('materiale.show', ['material' => $materiale]);
    }

    public function edit(Request $request, NomenclatorMaterial $materiale)
    {
        $this->rememberReturnUrl($request);

        return view('materiale.save', ['material' => $materiale]);
    }

    public function update(Request $request, NomenclatorMaterial $materiale)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'unitate_masura' => ['required', 'string', 'max:30'],
            'descriere' => ['nullable', 'string', 'max:1000'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $materiale->update([
            'denumire' => trim((string) $data['denumire']),
            'unitate_masura' => trim((string) $data['unitate_masura']),
            'descriere' => trim((string) ($data['descriere'] ?? '')) ?: null,
            'activ' => $request->boolean('activ'),
        ]);

        return redirect($request->session()->get('returnUrl', route('materiale.index')))
            ->with('status', 'Materialul <strong>' . e($materiale->denumire) . '</strong> a fost modificat cu succes!');
    }

    public function destroy(NomenclatorMaterial $materiale)
    {
        $materiale->delete();

        return back()->with('status', 'Materialul a fost sters cu succes!');
    }

    public function selectOptions(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'id' => ['nullable', 'integer', 'exists:nomenclator_materiale,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $data['limit'] ?? 12;
        $page = $data['page'] ?? 1;

        if (!empty($data['id'])) {
            $material = NomenclatorMaterial::query()->findOrFail((int) $data['id']);

            return response()->json([
                'results' => [[
                    'id' => $material->id,
                    'label' => $material->denumire,
                    'unitate_masura' => $material->unitate_masura,
                    'descriere' => $material->descriere,
                ]],
            ]);
        }

        $search = trim((string) ($data['search'] ?? ''));

        $paginator = NomenclatorMaterial::query()
            ->where('activ', true)
            ->when($search !== '', fn ($query) => $query->where('denumire', 'like', '%' . $search . '%'))
            ->orderBy('denumire')
            ->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn (NomenclatorMaterial $material) => [
                'id' => $material->id,
                'label' => $material->denumire,
                'unitate_masura' => $material->unitate_masura,
                'descriere' => $material->descriere,
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
