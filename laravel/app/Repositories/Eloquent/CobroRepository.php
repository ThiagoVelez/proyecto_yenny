<?php

namespace App\Repositories\Eloquent;

use App\Models\Cobro;
use App\Repositories\Contracts\CobroRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repositorio Eloquent para la gestion de cobros.
 */
class CobroRepository extends BaseRepository implements CobroRepositoryInterface
{
    public function __construct(Cobro $model)
    {
        parent::__construct($model);
    }

    /**
     * @inheritDoc
     */
    public function findActiveByAlquiler(int $idAlquiler): ?Cobro
    {
        return $this->model->newQuery()
            ->where('idAlquiler', $idAlquiler)
            ->whereIn('estado', ['PENDIENTE', 'PAGADO'])
            ->latest('id')
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function findWithPagos(int $id): ?Cobro
    {
        return $this->model->newQuery()
            ->with('pagos')
            ->find($id);
    }

    /**
     * @inheritDoc
     */
    public function getByAlquilerWithPagos(int $idAlquiler): Collection
    {
        return $this->model->newQuery()
            ->with('pagos')
            ->where('idAlquiler', $idAlquiler)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function updateEstado(int $id, string $estado): bool
    {
        $cobro = $this->findById($id);

        if (!$cobro) {
            return false;
        }

        $cobro->estado = $estado;

        return $cobro->save();
    }
}
