<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileFolder extends Model
{
    protected $table = 'vmsopen_folders';

    protected $fillable = [
        'name', 'slug', 'parent_id', 'icon', 'is_public',
        'is_livery_folder', 'description', 'order', 'settings'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_livery_folder' => 'boolean',
        'settings' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FileFolder::class, 'parent_id')->orderBy('order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileItem::class, 'folder_id');
    }
}