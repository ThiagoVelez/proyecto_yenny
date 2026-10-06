<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAlquiler')->index()->comment('ID del alquiler obtenido mediante servicio SOAP');
            $table->decimal('montoBase', 10, 2)->comment('Monto calculado por el tiempo de alquiler');
            $table->decimal('montoPenalidad', 10, 2)->default(0.00)->comment('Monto adicional aplicable por retraso o daños');
            $table->decimal('montoTotal', 10, 2)->comment('Suma del monto base y penalidades');
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'CANCELADO'])->default('PENDIENTE')->index()->comment('Estado actual del cobro');
            $table->dateTime('fechaEmision')->useCurrent()->comment('Fecha y hora de generacion del cobro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};
