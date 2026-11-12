<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Valida si el usuario posee rol de administrador.
     */
    public function isAdmin(): bool
    {
        return in_array(strtolower((string) ($this->role ?? '')), ['admin', 'administrador'], true);
    }

    /**
     * Valida si el usuario posee rol de operador de recepcion.
     */
    public function isOperador(): bool
    {
        return in_array(strtolower((string) ($this->role ?? '')), ['operador', 'operador_recepcion', 'operador de recepcion'], true);
    }

    /**
     * Valida si el usuario posee rol de cajero.
     */
    public function isCajero(): bool
    {
        return strtolower((string) ($this->role ?? '')) === 'cajero';
    }

    /**
     * Valida si el usuario posee rol de cliente.
     */
    public function isCliente(): bool
    {
        return strtolower((string) ($this->role ?? '')) === 'cliente';
    }

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
