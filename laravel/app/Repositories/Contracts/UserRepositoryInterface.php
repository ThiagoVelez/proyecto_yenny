<?php

namespace App\Repositories\Contracts;

use App\Models\User;

/**
 * Contrato de repositorio para la entidad User.
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Busca un usuario por su direccion de correo electronico.
     *
     * @param string $email Correo del usuario
     * @return User|null
     */
    public function findByEmail(string $email): ?User;
}
