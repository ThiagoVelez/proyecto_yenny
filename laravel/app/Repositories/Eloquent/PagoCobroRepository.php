<?php

namespace App\Repositories\Eloquent;

use App\Models\PagoCobro;
use App\Repositories\Contracts\PagoCobroRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repositorio Eloquent para la gestion de pagos asociados a cobros.
 */
class PagoCobroRepository extends BaseRepository implements PagoCobroRepositoryInterface
{
    public function __construct(PagoCobro $model)
    {
        parent::__construct($model);
    }

    /**
     * @inheritDoc
     */
    public function getByCobro(int $idCobro): Collection
    {
        return $this->model->newQuery()
            ->where('idCobro', $idCobro)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function sumPagosByCobro(int $idCobro): float
    {
        return (float) $this->model->newQuery()
            ->where('idCobro', $idCobro)
            ->sum('montoPagado');
    }

    /**
     * @inheritDoc
     */
    public function registrarPago(int $idCobro, string $metodoPago, float $montoPagado): PagoCobro
    {
        /** @var PagoCobro $pago */
        $pago = $this->create([
            'idCobro'     => $idCobro,
            'fechaPago'   => now(),
            'metodoPago'  => $metodoPago,
            'montoPagado' => $montoPagado,
        ]);

        return $pago;
    }
}
