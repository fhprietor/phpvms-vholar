<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenManufacturersTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Insertar datos por defecto
        DB::table('vmsopen_manufacturers')->insert([
            ['name' => 'PMDG', 'slug' => 'pmdg', 'logo' => null, 'order' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fenix Simulations', 'slug' => 'fenix', 'logo' => null, 'order' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Toliss', 'slug' => 'toliss', 'logo' => null, 'order' => 3, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'FlightSim Labs', 'slug' => 'fslabs', 'logo' => null, 'order' => 4, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Flight Factor', 'slug' => 'flightfactor', 'logo' => null, 'order' => 5, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Just Flight', 'slug' => 'justflight', 'logo' => null, 'order' => 6, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_manufacturers');
    }
}