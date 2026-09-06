<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('user_id', $this->user()->id),
            ],
            'type' => ['required', Rule::in(['payable', 'receivable'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
        ];
    }
}