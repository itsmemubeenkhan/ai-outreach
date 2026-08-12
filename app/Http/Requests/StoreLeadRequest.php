<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
        return [
            'business_name' => ['nullable', 'string', 'max:255'], 'number_of_employees' => ['nullable', 'integer', 'min:0'],
            'contact_person' => ['nullable', 'string', 'max:255'], 'first_name' => ['nullable', 'string', 'max:255'], 'last_name' => ['nullable', 'string', 'max:255'],
            'corporate_email' => ['nullable', 'email', 'max:255'], 'email' => ['nullable', 'email', 'max:255', Rule::unique('leads', 'email')->ignore($this->route('lead'))],
            'website' => ['nullable', 'url', 'max:255'], 'phone' => ['nullable', 'string', 'max:32'], 'phone_type' => ['nullable', 'string', 'max:32'],
            'street_address' => ['nullable', 'string', 'max:255'], 'zip_code' => ['nullable', 'string', 'max:20'], 'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:120'], 'source' => ['nullable', 'string', 'max:120'],
            'email_status' => ['required', 'in:unknown,valid,invalid,catch_all,bounced'],
            'lead_status' => ['required', 'in:new,queued,contacted,opened,clicked,replied,interested,not_interested,unsubscribed,closed'],
            'lead_score' => ['nullable', 'integer'], 'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}
