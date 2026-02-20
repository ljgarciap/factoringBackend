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
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->string('tipo_garantia')->nullable()->after('plan_amortizacion');
            $table->string('estado_garantia')->nullable()->after('tipo_garantia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_carteras', function (Blueprint $table) {
            //
        });
    }
};
