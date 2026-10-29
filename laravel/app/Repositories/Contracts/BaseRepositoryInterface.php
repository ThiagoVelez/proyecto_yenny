<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrato base para operaciones genericas de persistencia y acceso a datos.
 */
interface BaseRepositoryInterface
{
    /**
     * Obtiene todos los registros del modelo.
     *
     * @return Collection<int, Model>
     */
    public function all(): Collection;

    /**
     * Busca un registro por su clave primaria.
     *
     * @param int $id Identificador del registro
     * @return Model|null Modelo encontrado o null
     */
    public function findById(int $id): ?Model;

    /**
     * Crea un nuevo registro en la base de datos.
     *
     * @param array<string, mixed> $attributes Atributos para la creacion
     * @return Model Modelo creado
     */
    public function create(array $attributes): Model;

    /**
     * Actualiza un registro existente por su clave primaria.
     *
     * @param int $id Identificador del registro
     * @param array<string, mixed> $attributes Atributos a modificar
     * @return bool True si se actualizo correctamente
     */
    public function update(int $id, array $attributes): bool;

    /**
     * Elimina un registro por su clave primaria.
     *
     * @param int $id Identificador del registro
     * @return bool True si se elimino correctamente
     */
    public function delete(int $id): bool;
}
