<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenLiveriesTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_liveries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            
            $table->unsignedBigInteger('subfleet_id');
            $table->unsignedBigInteger('simulator_id');
            $table->unsignedBigInteger('manufacturer_id');
            $table->unsignedBigInteger('aircraft_id')->nullable();
            
            $table->string('thumbnail_path')->nullable();
            
            // Tipo de archivo: 'local' o 'external'
            $table->enum('file_type', ['local', 'external'])->default('local');
            
            // Ruta del archivo local o URL externa
            $table->string('file_path');
            
            $table->string('file_size')->nullable();
            $table->text('description')->nullable();
            $table->integer('downloads')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->index(['subfleet_id', 'simulator_id', 'manufacturer_id']);
            $table->index('aircraft_id');
            $table->index('slug');
            $table->index('file_type');
            
            // Foreign keys
            $table->foreign('subfleet_id')->references('id')->on('subfleets')->onDelete('cascade');
            $table->foreign('simulator_id')->references('id')->on('vmsopen_simulators')->onDelete('cascade');
            $table->foreign('manufacturer_id')->references('id')->on('vmsopen_manufacturers')->onDelete('cascade');
            $table->foreign('aircraft_id')->references('id')->on('aircraft')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_liveries');
    }
}