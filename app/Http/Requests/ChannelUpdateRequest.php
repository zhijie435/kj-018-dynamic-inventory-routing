<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChannelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channelId = $this->route('channel')?->id ?? $this->route('channel');

        return [
            'code' => ['required', 'string', 'max:32', 'unique:channels,code,' . $channelId],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'max:8'],
            'locale' => ['required', 'string', 'max:16'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'inventory_source_ids' => ['nullable', 'array'],
            'inventory_source_ids.*.id' => ['required', 'exists:inventory_sources,id'],
            'inventory_source_ids.*.is_primary' => ['nullable', 'boolean'],
            'inventory_source_ids.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
