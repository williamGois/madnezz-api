<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', 'min:2'],
            'email' => [
                'sometimes', 
                'string', 
                'email', 
                'max:255',
                Rule::unique('users')->ignore($this->user()->id)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name must be at least 2 characters',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already in use',
        ];
    }
}