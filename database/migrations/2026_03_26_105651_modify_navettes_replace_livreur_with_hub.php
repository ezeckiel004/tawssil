<?php
// database/migrations/2026_03_26_105651_modify_navettes_replace_livreur_with_hub.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyNavettesReplaceLivreurWithHub extends Migration
{
    public function up()
    {
        Schema::table('navettes', function (Blueprint $table) {
            // Supprimer la colonne livreur_id si elle existe
            if (Schema::hasColumn('navettes', 'livreur_id')) {
                $table->dropColumn('livreur_id');
            }

            // Ajouter la colonne hub_id
            if (!Schema::hasColumn('navettes', 'hub_id')) {
                $table->unsignedBigInteger('hub_id')->nullable()->after('wilaya_arrivee_id');
                $table->foreign('hub_id')->references('id')->on('hubs')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('navettes', function (Blueprint $table) {
            // Supprimer hub_id
            if (Schema::hasColumn('navettes', 'hub_id')) {
                $table->dropForeign(['hub_id']);
                $table->dropColumn('hub_id');
            }

            // Recréer livreur_id
            if (!Schema::hasColumn('navettes', 'livreur_id')) {
                $table->uuid('livreur_id')->nullable()->after('wilaya_arrivee_id');
            }
        });
    }
}
