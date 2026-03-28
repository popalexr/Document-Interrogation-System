<?php

namespace App\Http\Requests\Edits;

use Illuminate\Foundation\Http\FormRequest;

class EditDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_id'   => ['required', 'string'],
            'chat_id'       => ['nullable', 'string'],
            'query'         => ['required', 'string', 'max:5000'],
        ];
    }
}
