<?php

namespace App\Http\Requests\Interrogations;

use Illuminate\Foundation\Http\FormRequest;

class DocumentInterrogationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'string'],
            'query' => ['required', 'string', 'max:5000'],
        ];
    }
}
