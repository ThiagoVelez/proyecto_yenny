<?php

namespace App\Repositories\Contracts;

use App\Models\PagoCobro;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de repositorio para la entidad PagoCobro.
 */
interface PagoCobroRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Obtiene todos los pagos asociados a un cobro.
     *
     * @param int $idCobro Identificador del cobro
     * @return Collection<int, PagoCobro>
     */
    public function getByCobro(int $idCobro): Collection;

    /**
     * Calcula la suma acumulada de montos pagados para un cobro.
     *
     * @param int $idCobro Identificador del cobro
     * @return float
     */
    public function sumPagosByCobro(int $idCobro): float;

    /**
     * Registra un nuevo pago en el sistema.
     *
     * @param int $idCobro Identificador del cobro
     * @param string $metodoPago Metodo utilizado (TARJETA, TRANSFERENCIA, EFECTIVO)
     * @param float $montoPagado Monto transferido
     * @return PagoCobro
     */
    public function registrarPago(int $idCobro, string $metodoPago, float $montoPagado): PagoCobro;
}
