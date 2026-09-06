<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('user_id', $this->user()->id),
            ],
            'type' => [
                'sometimes',
                Rule::in(['payable', 'receivable']),
            ],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'due_date' => ['sometimes', 'required', 'date'],
        ];
    }
}