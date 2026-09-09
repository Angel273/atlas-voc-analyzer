<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function allPermissions(): Collection
    {
        $rolePermissions = $this->roles()->with('permissions')->get()
            ->flatMap(fn (Role $role) => $role->permissions);

        return $this->directPermissions->merge($rolePermissions)->unique('id');
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->hasRole('admin') || $this->hasRole('administrator')) {
            return true;
        }

        return $this->allPermissions()->contains('slug', $permissionSlug);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        return $this->roles->contains(fn (Role $role) => in_array($role->slug, $roles, true));
    }

    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::firstOrCreate(['slug' => $role], ['name' => ucfirst($role)]);
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function givePermission(Permission|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]);
        }

        $this->directPermissions()->syncWithoutDetaching([$permission->id]);
    }
}
