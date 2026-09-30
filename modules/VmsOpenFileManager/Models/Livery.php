<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;
use App\Models\Subfleet;
use App\Models\Aircraft;
use Illuminate\Support\Facades\Storage;

class Livery extends Model
{
    protected $table = 'vmsopen_liveries';
    
    protected $fillable = [
        'name',
        'slug',
        'subfleet_id',
        'simulator_id',
        'manufacturer_id',
        'aircraft_id',
        'thumbnail_path',
        'file_type',
        'file_path',
        'file_size',
        'description',
        'downloads',
        'is_active',
        'order'
    ];
    
    protected $casts = [
        'downloads' => 'integer',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
    
    /**
     * Get the subfleet that this livery belongs to
     */
    public function subfleet()
    {
        return $this->belongsTo(Subfleet::class, 'subfleet_id');
    }
    
    /**
     * Get the simulator that this livery is for
     */
    public function simulator()
    {
        return $this->belongsTo(Simulator::class, 'simulator_id');
    }
    
    /**
     * Get the manufacturer/publisher of this livery
     */
    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }
    
    /**
     * Get the aircraft (registration) that this livery is for
     */
    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class, 'aircraft_id');
    }
    
    /**
     * Increment download counter
     */
    public function incrementDownloads(): void
    {
        $this->increment('downloads');
    }
    
    /**
     * Get the download URL (local or external)
     */
    public function getDownloadUrlAttribute(): string
    {
        if ($this->file_type === 'external') {
            return $this->file_path;
        }
        
        return Storage::disk('public')->url($this->file_path);
    }
    
    /**
     * Check if this is an external livery
     */
    public function getIsExternalAttribute(): bool
    {
        return $this->file_type === 'external';
    }
    
    /**
     * Get the thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }
        
        return null;
    }
    
    /**
     * Get the file size formatted
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->file_size ?? 'N/A';
    }
    
    /**
     * Scope for active liveries only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for local files only
     */
    public function scopeLocal($query)
    {
        return $query->where('file_type', 'local');
    }
    
    /**
     * Scope for external URLs only
     */
    public function scopeExternal($query)
    {
        return $query->where('file_type', 'external');
    }
    
    /**
     * Scope by simulator
     */
    public function scopeBySimulator($query, $simulatorId)
    {
        return $query->where('simulator_id', $simulatorId);
    }
    
    /**
     * Scope by subfleet
     */
    public function scopeBySubfleet($query, $subfleetId)
    {
        return $query->where('subfleet_id', $subfleetId);
    }
    
    /**
     * Scope by manufacturer
     */
    public function scopeByManufacturer($query, $manufacturerId)
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }
}