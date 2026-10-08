<?php

namespace App\Services;

use Exception;
use DateTime;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (file_exists(base_path('../helpers/SoapAdapter.php'))) {
    require_once base_path('../helpers/SoapAdapter.php');
}

class SoapAlquilerService
{
    protected string $url;
    protected string $namespace;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->url = config('services.soap.url', env('SOAP_SERVER_URL', 'http://127.0.0.1:8000/server.php'));
        $this->namespace = config('services.soap.namespace', env('SOAP_NAMESPACE', 'InsertUserSOAP'));
        $this->username = config('services.soap.username', env('SOAP_USERNAME', 'admin'));
        $this->password = config('services.soap.password', env('SOAP_PASSWORD', 'admin123'));
        $this->timeout = (int) config('services.soap.timeout', env('SOAP_TIMEOUT', 10));
    }

    /**
     * Consulta y valida la existencia de un alquiler en el servicio SOAP.
     * Retorna los datos del alquiler mapeados en array asociativo.
     *
     * @param int $idAlquiler
     * @return array
     * @throws Exception
     */
    public function consultarAlquiler(int $idAlquiler): array
    {
        if ($idAlquiler <= 0) {
            throw new Exception("El identificador del alquiler debe ser mayor a 0.", 400);
        }

        // Intento 1: Llamar a la operación especializada consultarAlquilerPorId
        try {
            $alquiler = $this->llamarConsultarPorId($idAlquiler);
            if ($alquiler !== null) {
                return $alquiler;
            }
        } catch (Exception $e) {
            // Si el error indica explícitamente no encontrado, relanzar 404
            if (str_contains($e->getMessage(), 'no existe') || str_contains($e->getMessage(), 'ALQUILER_NO_ENCONTRADO')) {
                throw new Exception("El alquiler con ID $idAlquiler no fue encontrado en el sistema SOAP.", 404);
            }
            Log::warning("Fallo consultarAlquilerPorId en SOAP, intentando consultarAlquileres: " . $e->getMessage());
        }

        // Intento 2: Fallback a consultarAlquileres() y filtrar por ID
        return $this->buscarEnListaAlquileres($idAlquiler);
    }

    /**
     * Ejecuta la operación SOAP consultarAlquilerPorId
     */
    protected function llamarConsultarPorId(int $idAlquiler): ?array
    {
        $xmlBody = "<ins:consultarAlquilerPorId><id>{$idAlquiler}</id></ins:consultarAlquilerPorId>";
        $soapResponse = $this->enviarPeticionSoap($xmlBody);

        return $this->parsearAlquilerIndividual($soapResponse, $idAlquiler);
    }

    /**
     * Fallback: Consulta la lista completa de alquileres y filtra por ID
     */
    protected function buscarEnListaAlquileres(int $idAlquiler): array
    {
        $xmlBody = "<ins:consultarAlquileres/>";
        $soapResponse = $this->enviarPeticionSoap($xmlBody);

        $alquileres = $this->parsearArrayAlquileres($soapResponse);

        foreach ($alquileres as $item) {
            if ((int)$item['id'] === $idAlquiler) {
                return $item;
            }
        }

        throw new Exception("El alquiler con ID $idAlquiler no existe en el sistema SOAP de alquileres.", 404);
    }

    /**
     * Envía la trama XML SOAP con cabecera WS-Security y autenticación.
     */
    protected function enviarPeticionSoap(string $soapActionBody): string
    {
        $envelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                  xmlns:ins="{$this->namespace}">
   <soapenv:Header>
      <wsse:Security>
         <wsse:UsernameToken>
            <wsse:Username>{$this->username}</wsse:Username>
            <wsse:Password>{$this->password}</wsse:Password>
         </wsse:UsernameToken>
      </wsse:Security>
   </soapenv:Header>
   <soapenv:Body>
      {$soapActionBody}
   </soapenv:Body>
</soapenv:Envelope>
XML;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction'   => $this->namespace,
            ])->timeout($this->timeout)->send('POST', $this->url, [
                'body' => $envelope,
            ]);

            if (!$response->successful()) {
                throw new Exception("El servicio SOAP respondió con código HTTP " . $response->status(), 502);
            }

            return $response->body();

        } catch (Exception $e) {
            Log::error("Error de comunicación con el servicio SOAP [{$this->url}]: " . $e->getMessage());
            throw new Exception("No fue posible comunicarse con el servicio SOAP de alquileres: " . $e->getMessage(), 503);
        }
    }

    /**
     * Parsea un único alquiler desde la respuesta SOAP
     */
    protected function parsearAlquilerIndividual(string $xmlContent, int $idAlquiler): ?array
    {
        if (empty($xmlContent)) {
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xmlContent)) {
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Validar si la respuesta es un Fault o excepción
        $fault = $xpath->query("//*[local-name()='Fault']//*[local-name()='faultstring']");
        if ($fault->length > 0) {
            $errorMsg = trim($fault->item(0)->nodeValue);
            throw new Exception("SOAP Fault: " . $errorMsg, 400);
        }

        // Buscar nodo return o contenedor de datos
        $nodes = $xpath->query("//*[local-name()='consultarAlquilerPorIdResponse']/*[local-name()='return'] | //*[local-name()='return']");
        if ($nodes->length === 0) {
            return null;
        }

        $returnNode = $nodes->item(0);

        // Si el retorno es un string simple con mensaje de error
        if ($returnNode->childNodes->length <= 1 && !empty($returnNode->nodeValue)) {
            $val = trim($returnNode->nodeValue);
            if (str_starts_with($val, 'ERROR:')) {
                throw new Exception($val, 404);
            }
        }

        return $this->extractDataFromNode($xpath, $returnNode, $idAlquiler);
    }

    /**
     * Parsea un listado de alquileres desde la respuesta SOAP
     */
    protected function parsearArrayAlquileres(string $xmlContent): array
    {
        $list = [];
        if (empty($xmlContent)) {
            return $list;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xmlContent)) {
            libxml_clear_errors();
            return $list;
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Buscar cada item dentro de consultarAlquileresResponse
        $items = $xpath->query("//*[local-name()='consultarAlquileresResponse']//*[local-name()='item'] | //*[local-name()='AlquilerArray']//*[local-name()='item']");

        foreach ($items as $itemNode) {
            $data = $this->extractDataFromNode($xpath, $itemNode);
            if (!empty($data['id'])) {
                $list[] = $data;
            }
        }

        return $list;
    }

    /**
     * Extrae las propiedades de una entidad AlquilerData a partir de un DOMNode
     */
    protected function extractDataFromNode(\DOMXPath $xpath, \DOMNode $node, int $defaultId = 0): array
    {
        $getValue = function (string $tag) use ($xpath, $node): string {
            $results = $xpath->query(".//*[local-name()='{$tag}']", $node);
            return $results->length > 0 ? trim($results->item(0)->nodeValue ?? '') : '';
        };

        $id = (int) $getValue('id');
        if ($id === 0 && $defaultId > 0) {
            $id = $defaultId;
        }

        return [
            'id'                => $id,
            'bicicleta_codigo'  => $getValue('bicicleta_codigo'),
            'bicicleta_tipo'    => $getValue('bicicleta_tipo'),
            'bicicleta_tarifa'  => (float) ($getValue('bicicleta_tarifa') ?: 0.0),
            'cliente_documento' => $getValue('cliente_documento'),
            'cliente_nombre'    => $getValue('cliente_nombre'),
            'cliente_telefono'  => $getValue('cliente_telefono'),
            'fecha_inicio'      => $getValue('fecha_inicio'),
            'fecha_fin'         => $getValue('fecha_fin') ?: null,
            'horas'             => (int) ($getValue('horas') ?: 0),
            'total'             => (float) ($getValue('total') ?: 0.0),
            'estado'            => $getValue('estado'),
        ];
    }

    /**
     * Calcula montos de cobro a partir de los datos recibidos del servicio SOAP.
     * Si no se especifica montoBase, calcula automáticamente horas * tarifa.
     *
     * @param array $alquiler
     * @param float $montoPenalidad
     * @param float|null $montoBaseOverride
     * @return array ['montoBase' => float, 'montoPenalidad' => float, 'montoTotal' => float, 'horas' => int]
     */
    public function calcularTarifas(array $alquiler, float $montoPenalidad = 0.0, ?float $montoBaseOverride = null): array
    {
        $tarifa = (float) ($alquiler['bicicleta_tarifa'] ?? 0.0);

        // 1. Determinar horas transcurridas o liquidadas
        if (!empty($alquiler['horas']) && (int)$alquiler['horas'] > 0) {
            $horas = (int) $alquiler['horas'];
        } else {
            $inicio = !empty($alquiler['fecha_inicio']) ? new DateTime($alquiler['fecha_inicio']) : null;
            $fin = !empty($alquiler['fecha_fin']) ? new DateTime($alquiler['fecha_fin']) : null;

            if ($inicio && $fin) {
                $diff = $inicio->diff($fin);
                $horas = ($diff->days * 24) + $diff->h + ($diff->i > 0 ? 1 : 0);
            } elseif ($inicio) {
                $diff = $inicio->diff(new DateTime());
                $horas = ($diff->days * 24) + $diff->h + ($diff->i > 0 ? 1 : 0);
            } else {
                $horas = 0;
            }
        }

        // 2. Determinar monto base
        if ($montoBaseOverride !== null && $montoBaseOverride >= 0) {
            $montoBase = round($montoBaseOverride, 2);
            if ($horas <= 0 && $tarifa > 0) {
                $horas = (int) round($montoBase / $tarifa);
            }
        } else {
            // Si el alquiler ya fue liquidado en SOAP y tiene un total > 0, tomar ese total base
            if (!empty($alquiler['total']) && (float)$alquiler['total'] > 0) {
                $montoBase = (float) $alquiler['total'];
                if ($horas <= 0 && $tarifa > 0) {
                    $horas = (int) round($montoBase / $tarifa);
                }
            } else {
                if ($horas < 1) {
                    $horas = 1;
                }
                $montoBase = round($horas * $tarifa, 2);
            }
        }

        if ($horas < 1) {
            $horas = 1;
        }

        $penalidad = max(0.0, round($montoPenalidad, 2));
        $total = round($montoBase + $penalidad, 2);

        return [
            'montoBase'      => $montoBase,
            'montoPenalidad' => $penalidad,
            'montoTotal'     => $total,
            'horas'          => $horas,
        ];
    }
}
