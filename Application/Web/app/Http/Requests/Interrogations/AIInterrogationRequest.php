<?php

namespace App\Http\Requests\Interrogations;

use Illuminate\Foundation\Http\FormRequest;

class AIInterrogationRequest extends FormRequest
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
        return [
            'documents_ids' => ['required', 'array', 'min:1'],
            'documents_ids.*' => ['required', 'string'],
            'query' => ['required', 'string', 'max:5000'],
            'chat_id' => ['nullable', 'string'],
        ];
    }
}
