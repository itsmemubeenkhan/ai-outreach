<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'brand_id' => ['required', 'exists:brands,id'], 'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,active,paused,completed'], 'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'start_date' => ['nullable', 'date'], 'sender_strategy' => ['required', 'in:round_robin'],
            'audience.category' => ['nullable', 'string', 'max:120'], 'audience.country' => ['nullable', 'string', 'max:100'],
            'audience.state' => ['nullable', 'string', 'max:100'], 'audience.city' => ['nullable', 'string', 'max:100'],
            'audience.email_status' => ['nullable', 'in:unknown,valid,invalid,catch_all,bounced'],
            'audience.phone_type' => ['nullable', 'string', 'max:32'], 'audience.lead_status' => ['nullable', 'in:new,queued,contacted,opened,clicked,replied,interested,not_interested,unsubscribed,closed'],
            'audience.source' => ['nullable', 'string', 'max:120'], 'audience.email_availability' => ['nullable', 'in:yes,no'],
            'audience.website_availability' => ['nullable', 'in:yes,no'],
            'sending_account_ids' => ['nullable', 'array'],
            'sending_account_ids.*' => ['integer', 'exists:sending_accounts,id'],
        ];
    }
}
