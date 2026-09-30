<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilePermission extends Model
{
    protected $table = 'vmsopen_permissions';

    protected $fillable = ['folder_id', 'role_id', 'user_id', 'permission_type'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'folder_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}