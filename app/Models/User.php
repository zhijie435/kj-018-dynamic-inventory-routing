<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'title'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->isRole('admin');
    }

    public function isManager(): bool
    {
        return $this->isRole('manager');
    }

    public function isAnalyst(): bool
    {
        return $this->isRole('analyst');
    }

    public function cases()
    {
        return $this->hasMany(BusinessCase::class, 'created_by');
    }

    public function assignedCases()
    {
        return $this->hasMany(BusinessCase::class, 'assigned_to');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
