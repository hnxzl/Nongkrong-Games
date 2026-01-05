<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KbbiService
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $cacheMinutes = 60 * 24; // Cache 24 jam

    public function __construct()
    {
        $this->baseUrl = config('games.kbbi.base_url', 'https://new-kbbi-api.herokuapp.com');
        $this->timeout = config('games.kbbi.timeout', 5);
    }

    /**
     * Validasi kata menggunakan API KBBI
     * 
     * @param string $word Kata yang akan divalidasi
     * @return array ['valid' => bool, 'definition' => string|null, 'error' => string|null]
     */
    public function validateWord(string $word): array
    {
        // Normalize kata
        $word = $this->normalizeWord($word);

        if (empty($word)) {
            return [
                'valid' => false,
                'definition' => null,
                'error' => 'Kata tidak boleh kosong'
            ];
        }

        // Cek cache dulu
        $cacheKey = 'kbbi_' . md5($word);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/cari/{$word}");

            if ($response->successful()) {
                $data = $response->json();

                // Cek format respons API
                // API mengembalikan { "status": true/false, "data": [...] }
                if (isset($data['status']) && $data['status'] === true) {
                    $result = [
                        'valid' => true,
                        'definition' => $this->extractDefinition($data),
                        'error' => null
                    ];
                } else {
                    $result = [
                        'valid' => false,
                        'definition' => null,
                        'error' => 'Kata tidak ditemukan di KBBI'
                    ];
                }

                // Cache result
                Cache::put($cacheKey, $result, now()->addMinutes($this->cacheMinutes));

                return $result;
            }

            return [
                'valid' => false,
                'definition' => null,
                'error' => 'Gagal mengakses API KBBI'
            ];

        } catch (\Exception $e) {
            Log::error('KBBI API Error: ' . $e->getMessage());

            return [
                'valid' => false,
                'definition' => null,
                'error' => 'Terjadi kesalahan saat validasi kata'
            ];
        }
    }

    /**
     * Cek apakah kata valid untuk game Last Letter
     * 
     * @param string $word Kata baru
     * @param string $lastWord Kata sebelumnya
     * @param array $usedWords Daftar kata yang sudah digunakan
     * @return array
     */
    public function validateLastLetterWord(string $word, string $lastWord, array $usedWords = []): array
    {
        $word = $this->normalizeWord($word);
        $lastWord = $this->normalizeWord($lastWord);

        // Cek panjang minimum
        if (strlen($word) < config('games.last_letter.min_word_length', 2)) {
            return [
                'valid' => false,
                'error' => 'Kata terlalu pendek'
            ];
        }

        // Cek huruf awal
        $expectedFirstLetter = $this->getLastLetter($lastWord);
        $actualFirstLetter = mb_strtolower(mb_substr($word, 0, 1));

        if ($expectedFirstLetter !== $actualFirstLetter) {
            return [
                'valid' => false,
                'error' => "Kata harus diawali huruf '{$expectedFirstLetter}'"
            ];
        }

        // Cek kata sudah digunakan
        if (in_array($word, array_map([$this, 'normalizeWord'], $usedWords))) {
            return [
                'valid' => false,
                'error' => 'Kata sudah pernah digunakan'
            ];
        }

        // Validasi ke KBBI
        $kbbiResult = $this->validateWord($word);

        if (!$kbbiResult['valid']) {
            return [
                'valid' => false,
                'error' => $kbbiResult['error'] ?? 'Kata tidak valid'
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'definition' => $kbbiResult['definition']
        ];
    }

    /**
     * Normalize kata (lowercase, trim, remove special chars)
     */
    protected function normalizeWord(string $word): string
    {
        $word = mb_strtolower(trim($word));
        // Hapus karakter non-alfabet Indonesia
        $word = preg_replace('/[^a-z\-]/', '', $word);
        return $word;
    }

    /**
     * Ambil huruf terakhir dari kata
     */
    public function getLastLetter(string $word): string
    {
        $word = $this->normalizeWord($word);
        return mb_substr($word, -1);
    }

    /**
     * Extract definisi dari respons API
     */
    protected function extractDefinition(array $data): ?string
    {
        if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
            $firstEntry = $data['data'][0];
            
            if (isset($firstEntry['arti']) && is_array($firstEntry['arti'])) {
                return implode('; ', array_slice($firstEntry['arti'], 0, 2));
            }
        }

        return null;
    }

    /**
     * Generate kata random untuk memulai game
     */
    public function getRandomStartWord(): string
    {
        $startWords = [
            'apel', 'buku', 'cinta', 'daun', 'elang',
            'flora', 'gajah', 'hari', 'ikan', 'jalan',
            'kuda', 'laut', 'malam', 'nama', 'orang',
            'pagi', 'rasa', 'seni', 'tari', 'udara',
            'waktu', 'yakin', 'zaman'
        ];

        return $startWords[array_rand($startWords)];
    }
}
