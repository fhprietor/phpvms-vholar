<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileItem extends Model
{
    protected $table = 'vmsopen_files';

    protected $fillable = [
        'name', 'filename', 'extension', 'size', 'mime_type',
        'path', 'thumbnail_path', 'folder_id', 'user_id',
        'description', 'downloads', 'is_active', 'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'downloads' => 'integer',
        'metadata' => 'array',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function getIsImageAttribute(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    public function incrementDownloads(): void
    {
        $this->increment('downloads');
    }
}