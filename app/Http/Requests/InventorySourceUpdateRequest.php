<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventorySourceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inventorySource = $this->route('inventory_source');
        return $this->user()?->can('update', $inventorySource) ?? false;
    }

    public function rules(): array
    {
        $inventorySourceId = $this->route('inventory_source')?->id ?? $this->route('inventory_source');

        return [
            'code' => ['required', 'string', 'max:32', 'unique:inventory_sources,code,' . $inventorySourceId],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'is_active' => ['boolean'],
        ];
    }
}
