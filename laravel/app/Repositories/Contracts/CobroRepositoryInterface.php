<?php

namespace App\Repositories\Contracts;

use App\Models\Cobro;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de repositorio para la entidad Cobro.
 */
interface CobroRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Busca el ultimo cobro activo (PENDIENTE o PAGADO) asociado a un alquiler.
     *
     * @param int $idAlquiler Identificador del alquiler
     * @return Cobro|null
     */
    public function findActiveByAlquiler(int $idAlquiler): ?Cobro;

    /**
     * Busca un cobro por ID cargando sus pagos relacionados.
     *
     * @param int $id Identificador del cobro
     * @return Cobro|null
     */
    public function findWithPagos(int $id): ?Cobro;

    /**
     * Obtiene todos los cobros asociados a un alquiler ordenados descendentemente con sus pagos.
     *
     * @param int $idAlquiler Identificador del alquiler
     * @return Collection<int, Cobro>
     */
    public function getByAlquilerWithPagos(int $idAlquiler): Collection;

    /**
     * Actualiza el estado de un cobro especifico.
     *
     * @param int $id Identificador del cobro
     * @param string $estado Nuevo estado (PENDIENTE, PAGADO, CANCELADO)
     * @return bool
     */
    public function updateEstado(int $id, string $estado): bool;
}
