<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordPair extends Model
{
    use HasFactory;

    protected $fillable = [
        'civilian_word',
        'undercover_word',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope untuk kata aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope berdasarkan kategori
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Ambil pasangan kata random
     */
    public static function getRandomPair(?string $category = null): ?self
    {
        $query = self::active();
        
        if ($category) {
            $query->category($category);
        }

        return $query->inRandomOrder()->first();
    }
}
