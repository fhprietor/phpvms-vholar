<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenFilesTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('filename');
            $table->string('extension', 10);
            $table->string('size');
            $table->string('mime_type');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('downloads')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['folder_id', 'is_active']);
            $table->index('extension');
            $table->index('downloads');
            $table->index('folder_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_files');
    }
}