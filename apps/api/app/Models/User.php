<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Deactivatable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $uuid
 * @property-read Collection<int, Grant> $grants
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Deactivatable, HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<Grant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }

    /** @return HasMany<Grant, $this> */
    public function activeGrants(): HasMany
    {
        return $this->grants()->whereNull('revoked_at');
    }

    /** @return HasMany<UserLocation, $this> */
    public function userLocations(): HasMany
    {
        return $this->hasMany(UserLocation::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
}
