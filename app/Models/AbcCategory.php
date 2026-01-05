<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbcCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope untuk kategori aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Ambil kategori random
     */
    public static function getRandomCategories(int $count = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->inRandomOrder()->limit($count)->get();
    }
}
