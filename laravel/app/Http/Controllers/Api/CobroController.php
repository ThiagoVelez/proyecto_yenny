<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cobro;
use App\Services\SoapAlquilerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CobroController extends Controller
{
    protected SoapAlquilerService $soapService;

    public function __construct(SoapAlquilerService $soapService)
    {
        $this->soapService = $soapService;
    }

    /**
     * POST /api/cobros
     * Generar un nuevo cobro para un alquiler consumiendo el servicio SOAP.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idAlquiler'     => 'required|integer|min:1',
            'montoPenalidad' => 'nullable|numeric|min:0',
            'montoBase'      => 'nullable|numeric|min:0',
        ], [
            'idAlquiler.required' => 'El campo idAlquiler es obligatorio.',
            'idAlquiler.integer'  => 'El campo idAlquiler debe ser un número entero.',
            'idAlquiler.min'      => 'El campo idAlquiler debe ser mayor a 0.',
            'montoPenalidad.min'  => 'El monto de penalidad no puede ser negativo.',
            'montoBase.min'       => 'El monto base no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación en los parámetros enviados.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $idAlquiler = (int) $request->input('idAlquiler');
        $montoPenalidad = (float) $request->input('montoPenalidad', 0.0);
        $montoBaseOverride = $request->has('montoBase') ? (float) $request->input('montoBase') : null;

        // 1. Integración REST -> SOAP: Validar alquiler en el servicio SOAP
        try {
            $alquilerSoap = $this->soapService->consultarAlquiler($idAlquiler);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => 'Validación SOAP fallida: ' . $e->getMessage(),
            ], $code);
        }

        // 2. Regla de negocio: No se puede generar cobro sobre un alquiler cancelado
        if (strcasecmp($alquilerSoap['estado'] ?? '', 'Cancelado') === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible emitir cobros para un alquiler en estado Cancelado.',
                'alquiler' => $alquilerSoap,
            ], 422);
        }

        // 3. Verificar si ya existe un cobro pendiente o pagado para este alquiler
        $cobroExistente = Cobro::where('idAlquiler', $idAlquiler)
            ->whereIn('estado', ['PENDIENTE', 'PAGADO'])
            ->latest('id')
            ->first();

        if ($cobroExistente && !$request->boolean('forzarNuevoCobro')) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un cobro registrado para el alquiler con ID ' . $idAlquiler . '.',
                'cobroExistente' => [
                    'id'             => $cobroExistente->id,
                    'idAlquiler'     => $cobroExistente->idAlquiler,
                    'montoTotal'     => (float) $cobroExistente->montoTotal,
                    'estado'         => $cobroExistente->estado,
                    'saldoPendiente' => $cobroExistente->saldoPendiente(),
                    'fechaEmision'   => $cobroExistente->fechaEmision->toDateTimeString(),
                ],
            ], 409);
        }

        // 4. Calcular tarifas basadas en la respuesta SOAP (horas, tarifa de bicicleta, penalidad)
        $tarifas = $this->soapService->calcularTarifas($alquilerSoap, $montoPenalidad, $montoBaseOverride);

        // 5. Registrar el nuevo cobro en la base de datos
        $cobro = Cobro::create([
            'idAlquiler'     => $idAlquiler,
            'montoBase'      => $tarifas['montoBase'],
            'montoPenalidad' => $tarifas['montoPenalidad'],
            'montoTotal'     => $tarifas['montoTotal'],
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cobro generado exitosamente a partir de la información del servicio SOAP.',
            'data'    => [
                'id'             => $cobro->id,
                'idAlquiler'     => $cobro->idAlquiler,
                'montoBase'      => (float) $cobro->montoBase,
                'montoPenalidad' => (float) $cobro->montoPenalidad,
                'montoTotal'     => (float) $cobro->montoTotal,
                'estado'         => $cobro->estado,
                'fechaEmision'   => $cobro->fechaEmision->toDateTimeString(),
                'detalleAlquilerSoap' => [
                    'bicicleta_codigo'  => $alquilerSoap['bicicleta_codigo'] ?? null,
                    'bicicleta_tipo'    => $alquilerSoap['bicicleta_tipo'] ?? null,
                    'bicicleta_tarifa'  => $alquilerSoap['bicicleta_tarifa'] ?? null,
                    'cliente_documento' => $alquilerSoap['cliente_documento'] ?? null,
                    'cliente_nombre'    => $alquilerSoap['cliente_nombre'] ?? null,
                    'fecha_inicio'      => $alquilerSoap['fecha_inicio'] ?? null,
                    'fecha_fin'         => $alquilerSoap['fecha_fin'] ?? null,
                    'estado_alquiler'   => $alquilerSoap['estado'] ?? null,
                    'horas_calculadas'  => $tarifas['horas'],
                ],
            ],
        ], 201);
    }

    /**
     * GET /api/cobros/{id}
     * Consultar el detalle de un cobro específico por ID.
     */
    public function show(int $id): JsonResponse
    {
        $cobro = Cobro::with('pagos')->find($id);

        if (!$cobro) {
            return response()->json([
                'success' => false,
                'message' => 'El cobro con ID ' . $id . ' no existe en el sistema.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $cobro->id,
                'idAlquiler'     => $cobro->idAlquiler,
                'montoBase'      => (float) $cobro->montoBase,
                'montoPenalidad' => (float) $cobro->montoPenalidad,
                'montoTotal'     => (float) $cobro->montoTotal,
                'totalPagado'    => $cobro->totalPagado(),
                'saldoPendiente' => $cobro->saldoPendiente(),
                'estado'         => $cobro->estado,
                'fechaEmision'   => $cobro->fechaEmision->toDateTimeString(),
                'pagos'          => $cobro->pagos->map(function ($pago) {
                    return [
                        'id'          => $pago->id,
                        'fechaPago'   => $pago->fechaPago->toDateTimeString(),
                        'metodoPago'  => $pago->metodoPago,
                        'montoPagado' => (float) $pago->montoPagado,
                    ];
                }),
            ],
        ]);
    }

    /**
     * GET /api/cobros/alquiler/{idAlquiler}
     * Obtener el cobro o historial de cobros asociados a un alquiler.
     */
    public function byAlquiler(int $idAlquiler): JsonResponse
    {
        if ($idAlquiler <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El identificador del alquiler debe ser mayor a 0.',
            ], 400);
        }

        $cobros = Cobro::with('pagos')
            ->where('idAlquiler', $idAlquiler)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success'    => true,
            'idAlquiler' => $idAlquiler,
            'totalRegistros' => $cobros->count(),
            'data'       => $cobros->map(function ($c) {
                return [
                    'id'             => $c->id,
                    'idAlquiler'     => $c->idAlquiler,
                    'montoBase'      => (float) $c->montoBase,
                    'montoPenalidad' => (float) $c->montoPenalidad,
                    'montoTotal'     => (float) $c->montoTotal,
                    'totalPagado'    => $c->totalPagado(),
                    'saldoPendiente' => $c->saldoPendiente(),
                    'estado'         => $c->estado,
                    'fechaEmision'   => $c->fechaEmision->toDateTimeString(),
                    'pagos'          => $c->pagos->map(function ($pago) {
                        return [
                            'id'          => $pago->id,
                            'fechaPago'   => $pago->fechaPago->toDateTimeString(),
                            'metodoPago'  => $pago->metodoPago,
                            'montoPagado' => (float) $pago->montoPagado,
                        ];
                    }),
                ];
            }),
        ]);
    }
}
