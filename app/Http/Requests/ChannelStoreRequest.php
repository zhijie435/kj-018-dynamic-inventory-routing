<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChannelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'unique:channels,code'],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'max:8'],
            'locale' => ['required', 'string', 'max:16'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'inventory_source_ids' => ['nullable', 'array'],
            'inventory_source_ids.*.id' => [
                'required',
                Rule::exists('inventory_sources', 'id')->where('is_active', true),
            ],
            'inventory_source_ids.*.is_primary' => ['nullable', 'boolean'],
            'inventory_source_ids.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
