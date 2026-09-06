<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_has_players', function (Blueprint $table) {
            // paid  = confirmado (carteira ou webhook)
            // pending = aguardando pagamento (Pix/boleto emitido, ainda não pago)
            // free  = posição gratuita (valor 0)
            $table->string('payment_status')->default('paid')->after('price_payed');
            // Referência da cobrança no gateway (txid Pix / nossoNumero boleto).
            $table->string('payment_reference')->nullable()->after('payment_status');
            // Método usado: wallet, pix, boleto.
            $table->string('payment_method')->nullable()->after('payment_reference');

            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('match_has_players', function (Blueprint $table) {
            $table->dropIndex(['payment_reference']);
            $table->dropColumn(['payment_status', 'payment_reference', 'payment_method']);
        });
    }
};
