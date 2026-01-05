<?php

namespace Database\Seeders;

use App\Models\WordPair;
use Illuminate\Database\Seeder;

class WordPairSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pairs = [
            // Makanan
            ['civilian_word' => 'Nasi Goreng', 'undercover_word' => 'Mie Goreng', 'category' => 'makanan'],
            ['civilian_word' => 'Pizza', 'undercover_word' => 'Roti', 'category' => 'makanan'],
            ['civilian_word' => 'Es Teh', 'undercover_word' => 'Es Jeruk', 'category' => 'makanan'],
            ['civilian_word' => 'Bakso', 'undercover_word' => 'Soto', 'category' => 'makanan'],
            ['civilian_word' => 'Ayam Goreng', 'undercover_word' => 'Ayam Bakar', 'category' => 'makanan'],
            ['civilian_word' => 'Sate', 'undercover_word' => 'Kebab', 'category' => 'makanan'],
            ['civilian_word' => 'Rendang', 'undercover_word' => 'Semur', 'category' => 'makanan'],
            ['civilian_word' => 'Martabak', 'undercover_word' => 'Terang Bulan', 'category' => 'makanan'],
            ['civilian_word' => 'Kopi', 'undercover_word' => 'Teh', 'category' => 'makanan'],
            ['civilian_word' => 'Es Krim', 'undercover_word' => 'Gelato', 'category' => 'makanan'],
            
            // Tempat
            ['civilian_word' => 'Mall', 'undercover_word' => 'Pasar', 'category' => 'tempat'],
            ['civilian_word' => 'Pantai', 'undercover_word' => 'Kolam Renang', 'category' => 'tempat'],
            ['civilian_word' => 'Bioskop', 'undercover_word' => 'Teater', 'category' => 'tempat'],
            ['civilian_word' => 'Sekolah', 'undercover_word' => 'Kampus', 'category' => 'tempat'],
            ['civilian_word' => 'Rumah Sakit', 'undercover_word' => 'Klinik', 'category' => 'tempat'],
            ['civilian_word' => 'Bandara', 'undercover_word' => 'Stasiun', 'category' => 'tempat'],
            ['civilian_word' => 'Hotel', 'undercover_word' => 'Penginapan', 'category' => 'tempat'],
            ['civilian_word' => 'Gym', 'undercover_word' => 'Taman', 'category' => 'tempat'],
            ['civilian_word' => 'Coffee Shop', 'undercover_word' => 'Warung Kopi', 'category' => 'tempat'],
            ['civilian_word' => 'Supermarket', 'undercover_word' => 'Minimarket', 'category' => 'tempat'],
            
            // Benda
            ['civilian_word' => 'HP', 'undercover_word' => 'Tablet', 'category' => 'benda'],
            ['civilian_word' => 'Laptop', 'undercover_word' => 'Komputer', 'category' => 'benda'],
            ['civilian_word' => 'Buku', 'undercover_word' => 'Majalah', 'category' => 'benda'],
            ['civilian_word' => 'Sepatu', 'undercover_word' => 'Sandal', 'category' => 'benda'],
            ['civilian_word' => 'Kacamata', 'undercover_word' => 'Lensa Kontak', 'category' => 'benda'],
            ['civilian_word' => 'Jam Tangan', 'undercover_word' => 'Gelang', 'category' => 'benda'],
            ['civilian_word' => 'Payung', 'undercover_word' => 'Jas Hujan', 'category' => 'benda'],
            ['civilian_word' => 'Tas', 'undercover_word' => 'Ransel', 'category' => 'benda'],
            ['civilian_word' => 'Earphone', 'undercover_word' => 'Headphone', 'category' => 'benda'],
            ['civilian_word' => 'Power Bank', 'undercover_word' => 'Charger', 'category' => 'benda'],
            
            // Hewan
            ['civilian_word' => 'Kucing', 'undercover_word' => 'Anjing', 'category' => 'hewan'],
            ['civilian_word' => 'Burung', 'undercover_word' => 'Ayam', 'category' => 'hewan'],
            ['civilian_word' => 'Ikan', 'undercover_word' => 'Udang', 'category' => 'hewan'],
            ['civilian_word' => 'Singa', 'undercover_word' => 'Harimau', 'category' => 'hewan'],
            ['civilian_word' => 'Gajah', 'undercover_word' => 'Badak', 'category' => 'hewan'],
            
            // Aktivitas
            ['civilian_word' => 'Tidur', 'undercover_word' => 'Rebahan', 'category' => 'aktivitas'],
            ['civilian_word' => 'Berenang', 'undercover_word' => 'Menyelam', 'category' => 'aktivitas'],
            ['civilian_word' => 'Berlari', 'undercover_word' => 'Berjalan', 'category' => 'aktivitas'],
            ['civilian_word' => 'Nonton Film', 'undercover_word' => 'Nonton TV', 'category' => 'aktivitas'],
            ['civilian_word' => 'Main Game', 'undercover_word' => 'Main HP', 'category' => 'aktivitas'],
            
            // Profesi
            ['civilian_word' => 'Dokter', 'undercover_word' => 'Perawat', 'category' => 'profesi'],
            ['civilian_word' => 'Guru', 'undercover_word' => 'Dosen', 'category' => 'profesi'],
            ['civilian_word' => 'Polisi', 'undercover_word' => 'Tentara', 'category' => 'profesi'],
            ['civilian_word' => 'Chef', 'undercover_word' => 'Koki', 'category' => 'profesi'],
            ['civilian_word' => 'Pilot', 'undercover_word' => 'Masinis', 'category' => 'profesi'],
        ];

        foreach ($pairs as $pair) {
            WordPair::create(array_merge($pair, ['is_active' => true]));
        }
    }
}
