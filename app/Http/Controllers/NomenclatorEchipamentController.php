<?php

namespace App\Http\Controllers;

use App\Models\NomenclatorEchipament;
use Illuminate\Http\Request;

class NomenclatorEchipamentController extends Controller
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

        $echipamente = NomenclatorEchipament::query()
            ->when($search, fn ($query, $value) => $query->where('denumire', 'like', '%' . $value . '%'))
            ->when($activ !== null && $activ !== '', fn ($query) => $query->where('activ', (bool) $activ))
            ->when($sort === 'denumire', fn ($query) => $query->orderBy('denumire', $dir))
            ->when($sort === 'activ', fn ($query) => $query->orderBy('activ', $dir))
            ->when($sort === 'created_at', fn ($query) => $query->orderBy('created_at', $dir))
            ->when(!in_array($sort, ['denumire', 'activ', 'created_at'], true), fn ($query) => $query->orderBy('denumire'))
            ->orderBy('id')
            ->simplePaginate(25);

        return view('echipamente.index', compact('echipamente', 'search', 'activ', 'sort', 'dir'));
    }

    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

        return view('echipamente.save');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $echipament = NomenclatorEchipament::create([
            'denumire' => trim((string) $data['denumire']),
            'activ' => $request->boolean('activ'),
            'created_by' => $request->user()?->id,
        ]);

        return redirect($request->session()->get('returnUrl', route('echipamente.index')))
            ->with('success', 'Echipamentul <strong>' . e($echipament->denumire) . '</strong> a fost adaugat cu succes!');
    }

    public function show(Request $request, NomenclatorEchipament $echipamente)
    {
        $this->rememberReturnUrl($request);

        return view('echipamente.show', ['echipament' => $echipamente]);
    }

    public function edit(Request $request, NomenclatorEchipament $echipamente)
    {
        $this->rememberReturnUrl($request);

        return view('echipamente.save', ['echipament' => $echipamente]);
    }

    public function update(Request $request, NomenclatorEchipament $echipamente)
    {
        $data = $request->validate([
            'denumire' => ['required', 'string', 'max:150'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $echipamente->update([
            'denumire' => trim((string) $data['denumire']),
            'activ' => $request->boolean('activ'),
        ]);

        return redirect($request->session()->get('returnUrl', route('echipamente.index')))
            ->with('status', 'Echipamentul <strong>' . e($echipamente->denumire) . '</strong> a fost modificat cu succes!');
    }

    public function destroy(NomenclatorEchipament $echipamente)
    {
        $echipamente->delete();

        return back()->with('status', 'Echipamentul a fost sters cu succes!');
    }

    public function selectOptions(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'id' => ['nullable', 'integer', 'exists:nomenclator_echipamente,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $data['limit'] ?? 12;
        $page = $data['page'] ?? 1;

        if (!empty($data['id'])) {
            $echipament = NomenclatorEchipament::query()->findOrFail((int) $data['id']);

            return response()->json([
                'results' => [[
                    'id' => $echipament->id,
                    'label' => $echipament->denumire,
                ]],
            ]);
        }

        $search = trim((string) ($data['search'] ?? ''));

        $paginator = NomenclatorEchipament::query()
            ->where('activ', true)
            ->when($search !== '', fn ($query) => $query->where('denumire', 'like', '%' . $search . '%'))
            ->orderBy('denumire')
            ->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn (NomenclatorEchipament $echipament) => [
                'id' => $echipament->id,
                'label' => $echipament->denumire,
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
