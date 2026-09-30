<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVmsopenPermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('vmsopen_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('permission_type', ['read', 'write', 'delete', 'manage']);
            $table->timestamps();

            $table->unique(['folder_id', 'role_id', 'user_id', 'permission_type'], 'unique_permission');
            $table->index(['role_id', 'user_id']);
            $table->index('folder_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vmsopen_permissions');
    }
}