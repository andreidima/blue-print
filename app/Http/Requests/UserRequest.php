<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $allowEmptyRoles = $user?->isSuperAdmin() ?? false;

        return [
            'name' => 'required|max:255',
            'telefon' => 'nullable|max:50',
            'email' => 'required|max:255|email:rfc,dns|unique:users,email,' . $this->route('user')?->id,
            'password' => ($this->isMethod('POST') ? 'required' : 'nullable') . '|min:8|max:255|confirmed',
            'activ' => 'required',
            'roles' => array_values(array_filter([
                $allowEmptyRoles ? null : 'required',
                'array',
                $allowEmptyRoles ? null : 'min:1',
            ])),
            'roles.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', 'superadmin')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'roles' => $this->input('roles', []),
        ]);
    }

    public function messages()
    {
        return [
            'password.required' => 'Câmpul parola este obligatoriu.',
            'password.min' => 'Parola trebuie să aibă minim 8 caractere.',
            'password.max' => 'Câmpul parola nu poate conține mai mult de 255 de caractere.',
        ];
    }
}
