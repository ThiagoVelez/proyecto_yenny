<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cobro extends Model
{
    use HasFactory;

    protected $table = 'cobros';

    protected $fillable = [
        'idAlquiler',
        'montoBase',
        'montoPenalidad',
        'montoTotal',
        'estado',
        'fechaEmision',
    ];

    protected $casts = [
        'idAlquiler'     => 'integer',
        'montoBase'      => 'decimal:2',
        'montoPenalidad' => 'decimal:2',
        'montoTotal'     => 'decimal:2',
        'fechaEmision'   => 'datetime',
    ];

    /**
     * Relación con los pagos realizados para este cobro.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCobro::class, 'idCobro', 'id');
    }

    /**
     * Calcula el monto acumulado pagado hasta el momento.
     */
    public function totalPagado(): float
    {
        return (float) $this->pagos()->sum('montoPagado');
    }

    /**
     * Calcula el saldo pendiente por pagar.
     */
    public function saldoPendiente(): float
    {
        $pendiente = (float) $this->montoTotal - $this->totalPagado();
        return $pendiente > 0 ? round($pendiente, 2) : 0.00;
    }
}
