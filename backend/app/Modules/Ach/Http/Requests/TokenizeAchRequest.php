<?php

namespace App\Modules\Ach\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TokenizeAchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'routing_number' => ['required', 'regex:/^\d{9}$/'],
            'account_number' => ['required', 'regex:/^\d{4,17}$/'],
            'account_number_confirmation' => ['required', 'same:account_number'],
            'account_type' => ['required', Rule::in(['checking', 'savings'])],
            'account_holder_type' => ['nullable', Rule::in(['personal', 'business'])],
            'ach_provider' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_first_name' => ['nullable', 'string', 'max:255'],
            'customer_last_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'routing_number.regex' => 'Routing number must be exactly 9 digits.',
            'account_number.regex' => 'Account number must be between 4 and 17 digits.',
            'account_number_confirmation.same' => 'Account numbers do not match.',
            'account_type.required' => 'Please select an account type.',
            'account_holder_name.required' => 'Account holder name is required.',
        ];
    }
}
