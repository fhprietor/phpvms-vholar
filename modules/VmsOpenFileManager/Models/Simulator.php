<?php

namespace Modules\VmsOpenFileManager\Models;

use App\Contracts\Model;

class Simulator extends Model
{
    protected $table = 'vmsopen_simulators';
    
    protected $fillable = [
        'name', 
        'slug', 
        'logo', 
        'color', 
        'order', 
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
    
    /**
     * Get all liveries for this simulator
     */
    public function liveries()
    {
        return $this->hasMany(Livery::class, 'simulator_id');
    }
    
    /**
     * Get active liveries for this simulator
     */
    public function activeLiveries()
    {
        return $this->hasMany(Livery::class, 'simulator_id')->where('is_active', true);
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