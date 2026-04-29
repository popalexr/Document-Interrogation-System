<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

class SaveEditedFileAsNewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_id' => ['required', 'string', 'exists:edits,_id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
