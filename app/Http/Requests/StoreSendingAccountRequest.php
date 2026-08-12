<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSendingAccountRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->route('sending_account') ? 'nullable' : 'required';

        return ['name' => ['required', 'string', 'max:255'], 'sender_name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'], 'smtp_port' => ['required', 'integer', 'between:1,65535'], 'smtp_username' => ['required', 'string', 'max:255'], 'smtp_password' => [$required, 'string', 'max:1000'], 'smtp_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'imap_host' => ['nullable', 'string', 'max:255'], 'imap_port' => ['nullable', 'integer', 'between:1,65535'], 'imap_username' => ['nullable', 'string', 'max:255'], 'imap_password' => ['nullable', 'string', 'max:1000'], 'imap_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'], 'status' => ['required', Rule::in(['active', 'paused', 'error'])]];
    }
}
