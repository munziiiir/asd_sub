<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('admin_user')?->id ?? $this->route('user')?->id;

        return [
            'username' => ['required', 'string', 'max:100', Rule::unique('admin_users', 'username')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'password' => [
                'nullable',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'last_password_changed_at' => ['nullable', 'date'],
        ];
    }
}
