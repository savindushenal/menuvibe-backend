<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'version_number',
        'description',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'version_number' => 'integer',
    ];

    /**
     * Get the menu that owns the version
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get the user who created this version
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
