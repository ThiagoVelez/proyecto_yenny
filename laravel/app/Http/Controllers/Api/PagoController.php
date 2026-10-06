<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cobro;
use App\Models\PagoCobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PagoController extends Controller
{
    /**
     * POST /api/pagos
     * Registrar el pago de un cobro existente.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idCobro'     => 'required|integer|min:1',
            'metodoPago'  => 'required|string|in:TARJETA,TRANSFERENCIA,EFECTIVO',
            'montoPagado' => 'required|numeric|min:0.01',
        ], [
            'idCobro.required'     => 'El campo idCobro es obligatorio.',
            'idCobro.integer'      => 'El campo idCobro debe ser un número entero.',
            'idCobro.min'          => 'El campo idCobro debe ser mayor a 0.',
            'metodoPago.required'  => 'El método de pago es obligatorio.',
            'metodoPago.in'        => 'El método de pago debe ser TARJETA, TRANSFERENCIA o EFECTIVO.',
            'montoPagado.required' => 'El monto pagado es obligatorio.',
            'montoPagado.min'      => 'El monto pagado debe ser mayor a 0.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación en los parámetros enviados.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $idCobro = (int) $request->input('idCobro');
        $metodoPago = strtoupper(trim($request->input('metodoPago')));
        $montoPagado = (float) $request->input('montoPagado');

        // 1. Validar existencia del cobro
        $cobro = Cobro::find($idCobro);
        if (!$cobro) {
            return response()->json([
                'success' => false,
                'message' => 'El cobro con ID ' . $idCobro . ' no existe en el sistema.',
            ], 404);
        }

        // 2. Validar que el cobro no esté cancelado
        if ($cobro->estado === 'CANCELADO') {
            return response()->json([
                'success' => false,
                'message' => 'El cobro con ID ' . $idCobro . ' se encuentra CANCELADO y no admite pagos.',
            ], 422);
        }

        // 3. Validar que el cobro no esté ya pagado
        if ($cobro->estado === 'PAGADO') {
            return response()->json([
                'success' => false,
                'message' => 'El cobro con ID ' . $idCobro . ' ya se encuentra totalmente PAGADO.',
                'montoTotal'  => (float) $cobro->montoTotal,
                'totalPagado' => $cobro->totalPagado(),
            ], 422);
        }

        $saldoPendiente = $cobro->saldoPendiente();

        // 4. Validar que el monto no exceda el saldo pendiente (con tolerancia de 0.01 por redondeo)
        if ($montoPagado > ($saldoPendiente + 0.01)) {
            return response()->json([
                'success' => false,
                'message' => 'El monto a pagar ($' . number_format($montoPagado, 2) . ') supera el saldo pendiente actual ($' . number_format($saldoPendiente, 2) . ').',
                'saldoPendiente' => $saldoPendiente,
                'montoTotal'     => (float) $cobro->montoTotal,
            ], 422);
        }

        // 5. Registrar el pago dentro de una transacción de base de datos
        $pago = DB::transaction(function () use ($cobro, $metodoPago, $montoPagado) {
            $nuevoPago = PagoCobro::create([
                'idCobro'     => $cobro->id,
                'fechaPago'   => now(),
                'metodoPago'  => $metodoPago,
                'montoPagado' => $montoPagado,
            ]);

            // Si el total acumulado cubre el monto total, actualizar estado a PAGADO
            $totalAcumulado = (float) $cobro->pagos()->sum('montoPagado');
            if ($totalAcumulado >= ((float) $cobro->montoTotal - 0.01)) {
                $cobro->estado = 'PAGADO';
                $cobro->save();
            }

            return $nuevoPago;
        });

        // Recargar datos actualizados
        $cobro->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado exitosamente.',
            'data'    => [
                'id'          => $pago->id,
                'idCobro'     => $pago->idCobro,
                'fechaPago'   => $pago->fechaPago->toDateTimeString(),
                'metodoPago'  => $pago->metodoPago,
                'montoPagado' => (float) $pago->montoPagado,
                'resumenCobro' => [
                    'montoTotal'     => (float) $cobro->montoTotal,
                    'totalPagado'    => $cobro->totalPagado(),
                    'saldoPendiente' => $cobro->saldoPendiente(),
                    'estadoCobro'    => $cobro->estado,
                ],
            ],
        ], 201);
    }

    /**
     * GET /api/pagos/cobro/{idCobro}
     * Consultar los pagos realizados sobre un cobro determinado.
     */
    public function byCobro(int $idCobro): JsonResponse
    {
        if ($idCobro <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El identificador del cobro debe ser mayor a 0.',
            ], 400);
        }

        $cobro = Cobro::find($idCobro);
        if (!$cobro) {
            return response()->json([
                'success' => false,
                'message' => 'El cobro con ID ' . $idCobro . ' no existe en el sistema.',
            ], 404);
        }

        $pagos = PagoCobro::where('idCobro', $idCobro)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success'     => true,
            'idCobro'     => $cobro->id,
            'montoTotal'  => (float) $cobro->montoTotal,
            'totalPagado' => $cobro->totalPagado(),
            'saldoPendiente' => $cobro->saldoPendiente(),
            'estadoCobro' => $cobro->estado,
            'totalPagos'  => $pagos->count(),
            'pagos'       => $pagos->map(function ($p) {
                return [
                    'id'          => $p->id,
                    'fechaPago'   => $p->fechaPago->toDateTimeString(),
                    'metodoPago'  => $p->metodoPago,
                    'montoPagado' => (float) $p->montoPagado,
                ];
            }),
        ]);
    }
}
