<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCobro extends Model
{
    use HasFactory;

    protected $table = 'pago_cobros';

    protected $fillable = [
        'idCobro',
        'fechaPago',
        'metodoPago',
        'montoPagado',
    ];

    protected $casts = [
        'idCobro'     => 'integer',
        'fechaPago'   => 'datetime',
        'montoPagado' => 'decimal:2',
    ];

    /**
     * Relación con el cobro asociado.
     */
    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class, 'idCobro', 'id');
    }
}
