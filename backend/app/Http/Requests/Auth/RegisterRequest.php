<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:190',
                'unique:users,email',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:32',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'tenant_name' => [
                'required',
                'string',
                'max:190',
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:64',
            ],
            'locale' => [
                'nullable',
                'string',
                'max:16',
            ],
        ];
    }
}
