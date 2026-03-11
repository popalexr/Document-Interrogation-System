<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTitleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chat_id' => 'required|string',
            'query' => 'required|string',
        ];
    }
}
