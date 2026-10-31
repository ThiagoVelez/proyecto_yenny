<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../token/token.php';

class SoapTokenExpirationTest extends TestCase
{
    public function test_token_es_vigente_antes_de_15_minutos(): void
    {
        $this->assertFalse(\is_token_expired(0));
        $this->assertFalse(\is_token_expired(899));
    }

    public function test_token_vence_al_cumplir_15_minutos(): void
    {
        $this->assertTrue(\is_token_expired(900));
        $this->assertTrue(\is_token_expired('3600'));
    }

    public function test_token_sin_fecha_se_considera_vencido(): void
    {
        $this->assertTrue(\is_token_expired(null));
    }
}
