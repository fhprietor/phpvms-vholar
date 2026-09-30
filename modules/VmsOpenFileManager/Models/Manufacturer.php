<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;

class Manufacturer extends Model
{
    protected $table = 'vmsopen_manufacturers';
    
    protected $fillable = [
        'name', 
        'slug', 
        'logo', 
        'order', 
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
    
    /**
     * Get all liveries for this manufacturer
     */
    public function liveries()
    {
        return $this->hasMany(Livery::class, 'manufacturer_id');
    }
    
    /**
     * Get active liveries for this manufacturer
     */
    public function activeLiveries()
    {
        return $this->hasMany(Livery::class, 'manufacturer_id')->where('is_active', true);
    }
    
    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo);
        }
        
        return null;
    }
}