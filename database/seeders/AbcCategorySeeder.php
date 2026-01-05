<?php

namespace Database\Seeders;

use App\Models\AbcCategory;
use Illuminate\Database\Seeder;

class AbcCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Nama Orang',
            'Nama Negara',
            'Nama Kota',
            'Nama Buah',
            'Nama Hewan',
            'Nama Sayuran',
            'Nama Makanan',
            'Nama Minuman',
            'Nama Tumbuhan',
            'Nama Pekerjaan',
            'Nama Merek',
            'Nama Film',
            'Nama Artis',
            'Nama Tempat Wisata',
            'Nama Tokoh Kartun',
            'Nama Kendaraan',
            'Nama Alat Musik',
            'Nama Olahraga',
            'Nama Warna',
            'Nama Benda di Rumah',
        ];

        foreach ($categories as $name) {
            AbcCategory::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
