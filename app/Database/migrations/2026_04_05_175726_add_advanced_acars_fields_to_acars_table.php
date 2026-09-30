<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acars', function (Blueprint $table) {
            $table->decimal('gforce', 5, 2)->nullable()->after('fuel_flow');
            $table->decimal('pitch', 6, 2)->nullable()->after('gforce');
            $table->decimal('bank', 6, 2)->nullable()->after('pitch');
        });
    }

    public function down(): void
    {
        Schema::table('acars', function (Blueprint $table) {
            $table->dropColumn(['gforce', 'pitch', 'bank']);
        });
    }
};