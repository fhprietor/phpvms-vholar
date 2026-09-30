<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenSimulatorsTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_simulators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('color')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Insertar datos por defecto
        DB::table('vmsopen_simulators')->insert([
            ['name' => 'Microsoft Flight Simulator', 'slug' => 'msfs', 'logo' => null, 'order' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Microsoft Flight Simulator 2024', 'slug' => 'msfs2024', 'logo' => null, 'order' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'X-Plane 12', 'slug' => 'xplane12', 'logo' => null, 'order' => 3, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'X-Plane 11', 'slug' => 'xplane11', 'logo' => null, 'order' => 4, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Prepar3D v5', 'slug' => 'p3dv5', 'logo' => null, 'order' => 5, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Prepar3D v4', 'slug' => 'p3dv4', 'logo' => null, 'order' => 6, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_simulators');
    }
}