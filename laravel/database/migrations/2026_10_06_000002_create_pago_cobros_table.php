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
        Schema::create('pago_cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idCobro')->comment('Identificador del cobro asociado');
            $table->dateTime('fechaPago')->useCurrent()->comment('Fecha y hora en que se realiza el pago');
            $table->enum('metodoPago', ['TARJETA', 'TRANSFERENCIA', 'EFECTIVO'])->comment('Metodo utilizado para el pago');
            $table->decimal('montoPagado', 10, 2)->comment('Valor abonado al cobro');
            $table->timestamps();

            $table->foreign('idCobro')
                ->references('id')
                ->on('cobros')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index('idCobro');
            $table->index('metodoPago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_cobros');
    }
};
