<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProdusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $produsId = $this->route('produs')?->id;

        $aliasUniqueRule = Rule::unique('product_sku_aliases', 'sku');

        if ($produsId) {
            $aliasUniqueRule = $aliasUniqueRule->where(fn ($query) => $query->where('produs_id', '!=', $produsId));
        }

        return [
            'categorie_id'    => 'required|exists:categorii,id',
            'nume'            => 'required|string|max:255',
            'sku'             => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('produse', 'sku')
                    ->ignore($this->route('produs')?->id)
                    ->where(fn ($query) => $query->whereNotNull('sku')),
            ],
            'sku_aliases'     => ['array'],
            'sku_aliases.*'   => [
                'string',
                'max:100',
                'distinct:strict',
                $aliasUniqueRule,
            ],
            'cantitate'       => 'nullable|integer',
            'prag_minim'      => 'required|integer',
            'data_procesare'  => 'nullable|date',
            'lungime'         => 'nullable|numeric',
            'latime'          => 'nullable|numeric',
            'grosime'         => 'nullable|numeric',
            'pret'            => 'nullable|numeric',
            'observatii'      => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $primarySku = trim((string) $this->input('sku'));

        $aliases = $this->input('sku_aliases');

        if (is_string($aliases)) {
            $aliases = preg_split('/\r\n|\r|\n/', $aliases) ?: [];
        }

        if (! is_array($aliases)) {
            $aliases = [];
        }

        $aliases = collect($aliases)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->reject(fn ($value) => $primarySku !== '' && strcasecmp($value, $primarySku) === 0)
            ->values();

        $this->merge([
            'sku' => $primarySku !== '' ? $primarySku : null,
            'sku_aliases' => $aliases->all(),
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $aliases = $this->input('sku_aliases', []);
            $produsId = $this->route('produs')?->id;

            if (! empty($aliases)) {
                $conflictingPrimary = DB::table('produse')
                    ->whereIn('sku', $aliases)
                    ->when($produsId, fn ($query) => $query->where('id', '!=', $produsId))
                    ->pluck('sku')
                    ->filter()
                    ->all();

                if (! empty($conflictingPrimary)) {
                    $validator->errors()->add(
                        'sku_aliases',
                        'SKU-urile suplimentare se suprapun cu SKU-uri principale existente: ' . implode(', ', $conflictingPrimary)
                    );
                }
            }

            $primarySku = $this->input('sku');

            if ($primarySku) {
                $conflictingAlias = DB::table('product_sku_aliases')
                    ->where('sku', $primarySku)
                    ->when($produsId, fn ($query) => $query->where('produs_id', '!=', $produsId))
                    ->exists();

                if ($conflictingAlias) {
                    $validator->errors()->add('sku', 'SKU-ul principal este deja folosit ca alias pentru alt produs.');
                }
            }
        });
    }
}
