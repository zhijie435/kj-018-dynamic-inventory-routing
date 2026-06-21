<?php

namespace App\Http\Requests;

use App\Models\InventorySource;
use Illuminate\Foundation\Http\FormRequest;

class InventorySourceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventorySource::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'unique:inventory_sources,code'],
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
