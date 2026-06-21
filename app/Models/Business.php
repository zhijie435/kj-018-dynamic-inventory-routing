<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'name', 'uscc', 'legal_rep', 'registered_capital', 'establish_date',
        'address', 'scope', 'industry', 'region',
    ];

    protected function casts(): array
    {
        return [
            'establish_date' => 'date',
        ];
    }

    public function cases()
    {
        return $this->hasMany(BusinessCase::class);
    }
}
