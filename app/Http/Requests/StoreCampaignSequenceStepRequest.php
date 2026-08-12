<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignSequenceStepRequest extends FormRequest
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
        return ['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:100000'], 'delay_days' => ['required', 'integer', 'min:0', 'max:365'], 'position' => ['required', 'integer', 'min:1', 'max:100', Rule::unique('campaign_sequence_steps')->where('campaign_id', $this->route('campaign')->id)->ignore($this->route('step'))], 'enabled' => ['nullable', 'boolean']];
    }
}
