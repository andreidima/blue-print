<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables are ordered child-first to avoid FK issues when a driver ignores FK toggles.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'comanda_produs_consum_rebuturi',
        'comanda_produs_consumuri',
        'comanda_produs_histories',
        'comanda_etapa_histories',
        'comanda_produse',
        'comanda_solicitari',
        'comanda_note',
        'comanda_gdpr_consents',
        'comanda_atasamente',
        'comanda_factura_emails',
        'comanda_facturi',
        'comanda_oferta_emails',
        'comanda_email_logs',
        'sms_messages',
        'plati',
        'mockupuri',
        'comanda_etapa_user',
        'comenzi',
        'produse',
        'nomenclator_produse_custom',
        'nomenclator_materiale',
        'nomenclator_echipamente',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Irreversible by design: this migration removes business data.
    }
};
