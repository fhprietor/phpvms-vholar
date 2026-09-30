<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenFoldersTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_livery_folder')->default(false);
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'order']);
            $table->index('slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_folders');
    }
}