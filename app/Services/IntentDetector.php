<?php

declare(strict_types=1);

namespace App\Services;

use App\Plugin\HookManager;

/**
 * Rule-based intent detector.
 * Can be swapped with an LLM-based implementation via the IntentDetectorInterface.
 */
class IntentDetector implements IntentDetectorInterface
{
    // Keyword patterns per intent (Indonesian + common English)
    private static array $patterns = [
        'tanya_menu' => [
            'menu', 'daftar menu', 'ada apa', 'jual apa', 'minuman apa', 'ada minuman',
            'makanan apa', 'cemilan', 'snack', 'what do you have', 'what\'s on the menu',
            'itu apa', 'apa itu', 'jelaskan', 'seperti apa', 'itu minuman',
            'detail', 'detail info', 'info detail', 'minta detail', 'minta info',
            'deskripsi', 'description', 'describe', 'tell me more', 'more info',
            'show me the menu', 'show menu', 'see the menu', 'view menu',
            'what can i order', 'what can i get', 'what do you offer', 'what do you sell',
            'what\'s available', 'what is available', 'list of menu', 'your menu',
            'tell me about', 'what kind of', 'any food', 'any drink', 'any coffee',
            'recommend', 'recommend me', 'suggest', 'something hot', 'something cold',
            'something cool', 'minuman panas', 'minuman dingin', 'kopi panas',
            'kopi dingin', 'rekomendasi', 'sarankan',
        ],
        'tanya_harga' => [
            'harga', 'berapa', 'price', 'cost', 'mahal', 'murah', 'tarif', 'biaya',
            'how much', 'berapa harganya', 'how much is', 'how much does', 'how much for',
            'what is the price', 'what\'s the price', 'pricing',
        ],
        'tanya_promo' => [
            'promo', 'diskon', 'discount', 'voucher', 'kode promo', 'promo code',
            'penawaran', 'offer', 'hemat', 'gratis', 'free', 'special',
            'any deals', 'any promo', 'any discount', 'current promo', 'do you have promo',
        ],
        'tambah_item' => [
            'pesan', 'order', 'beli', 'mau', 'minta', 'tambah', 'add',
            'satu', 'dua', 'tiga', 'empat', 'lima', 'enam',
            '1 ', '2 ', '3 ', '4 ', '5 ', 'sepuluh',
            'saya mau', 'boleh minta', 'tolong pesan',
            'i want', 'i\'d like', 'i would like', 'can i have', 'can i get',
            'give me', 'i\'ll have', 'i\'ll take', 'let me get', 'let me have',
            'please give', 'i need', 'one ', 'two ', 'three ', 'four ', 'five ',
        ],
        'ubah_item' => [
            'ganti', 'ubah', 'change', 'update', 'edit', 'kurangi', 'tambahi',
            'ganti jadi', 'ubah jadi', 'bikin jadi', 'jadiin', 'jadi ',
            'make it', 'switch to', 'replace with',
        ],
        'hapus_item' => [
            'hapus', 'cancel item', 'remove', 'delete', 'batalkan item',
            'take off', 'get rid of', 'drop the',
        ],
        'clear_cart' => [
            'kosongkan', 'clear cart', 'hapus semua', 'mulai ulang', 'reset', 'bersihkan',
            'cancel semua', 'start over', 'clear everything', 'remove everything',
            'cancel order', 'cancel all',
        ],
        'lihat_cart' => [
            'keranjang', 'cart', 'pesanan saya', 'lihat pesanan', 'my order',
            'sudah pesan apa', 'daftar pesanan', 'saya pesan apa', 'pesan apa saya',
            'what\'s in my cart', 'show my cart', 'show my order', 'what did i order',
            'what have i ordered', 'current order',
        ],
        'checkout' => [
            'checkout', 'bayar', 'pay', 'selesai pesan', 'konfirmasi', 'confirm',
            'pesan sekarang', 'lanjut', 'proceed', 'finalize',
            'place order', 'submit order', 'complete order', 'i\'m done',
        ],
        'isi_nama' => [
            'nama saya', 'my name is', 'nama:', 'panggil saya',
            'i am ', 'this is ', 'call me',
        ],
        'isi_email' => [
            'email saya', 'my email', 'email:', '@',
            'my email is', 'email address',
        ],
        'isi_alamat' => [
            'alamat', 'address', 'dikirim ke', 'antar ke', 'jl.', 'jalan', 'gang',
            'perumahan', 'kompleks', 'deliver to', 'send to', 'street', 'road', 'avenue',
        ],
        'isi_fulfillment' => [
            'ambil di toko', 'pickup', 'pick up', 'pick-up', 'take away',
            'delivery', 'antar ke alamat', 'kirim ke alamat',
            'nomor meja', 'meja', 'antar ke meja', 'table',
        ],
        'isi_nomor_meja' => [
            'meja', 'table', 'nomor meja', 'table number',
        ],
        'isi_kode_pos' => [
            'kode pos', 'postal code', 'zip code', 'kodepos', 'zip',
        ],
        'konfirmasi_order' => [
            'ya benar', 'sudah benar', 'iya', 'yes', 'betul', 'confirm', 'ok lanjut',
            'oke', 'deal', 'setuju', 'fix', 'jadi', 'ya', 'ok', 'siap', 'yap', 'yep', 'baik',
            'correct', 'that\'s right', 'that is right', 'looks good', 'all good', 'sure',
            'go ahead', 'sounds good',
        ],
        'tanya_status_order' => [
            'status order', 'status pesanan', 'sudah diproses', 'kapan sampai',
            'cek pesanan', 'track order', 'lacak order',
            'riwayat order', 'riwayat pesanan', 'history order', 'history pesanan',
            'tadi pesan', 'pesan apa tadi', 'saya beli apa tadi', 'order apa tadi',
            'pernah order', 'pernah pesan', 'pernah beli',
            'cek order saya', 'order sebelumnya',
            'my previous order', 'order history', 'order status', 'track my order',
            'where is my order', 'when will my order',
        ],
        'pakai_promo' => [
            'pakai kode', 'gunakan kode', 'kode diskon', 'apply promo', 'use code',
            'use promo', 'use voucher', 'punya kode', 'ada kode', 'kode saya',
            'promo saya', 'voucher saya', 'redeem', 'tukar kode',
        ],
        'batal_order' => [
            'batal', 'cancel', 'tidak jadi', 'ga jadi',
            'never mind', 'nevermind', 'forget it', 'no thanks', 'stop order',
        ],
    ];

    public function detect(string $message, array $context = []): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        $state = $context['state'] ?? 'idle';
        $customIntent = HookManager::applyFilters('intent.detect', '', $message, $context);
        if (is_string($customIntent) && trim($customIntent) !== '') {
            return trim($customIntent);
        }

        if (in_array($state, ['awaiting_name','awaiting_email','awaiting_wa','awaiting_fulfillment','awaiting_table','awaiting_address','awaiting_postal'])) {
            return $this->detectCheckoutField($lower, $state);
        }

        if ($state === 'awaiting_confirmation') {
            return $this->detectConfirmation($lower);
        }

        if ($this->looksLikeOrderHistoryQuestion($lower)) {
            return 'tanya_status_order';
        }

        return $this->scoreMessage($lower);
    }

    private function detectConfirmation(string $lower): string
    {
        if ($this->isCancel($lower)) { return 'batal_order'; }

        if (preg_match('/\b(tambah|nambah|tambahkan|add|minta|pesan|order|beli)\b/u', $lower)) {
            return 'tambah_item';
        }

        if (preg_match('/\b(hapus|remove|delete|batalkan item|take out|take off|get rid of|drop the)\b/u', $lower)) {
            return 'hapus_item';
        }

        if (preg_match('/\b(kosongkan|clear cart|hapus semua|reset|mulai ulang)\b/u', $lower)) {
            return 'clear_cart';
        }

        if ($this->scoreMessage($lower) === 'komplain_customer') {
            return 'komplain_customer';
        }

        if (preg_match('/\b(keranjang|cart|lihat pesanan|pesanan saya|saya pesan apa)\b/u', $lower)) {
            return 'lihat_cart';
        }

        if (preg_match('/\b(menu|lihat menu|daftar menu|pilihan menu)\b/u', $lower)) {
            return 'tanya_menu';
        }

        $hasChange = str_contains($lower, 'ganti') || str_contains($lower, 'ubah') || str_contains($lower, 'change');
        if ($hasChange) {
            if (!preg_match('/\b(kode\s*pos|kodepos|postal|email|wa|whatsapp|nomor|hp|telepon|nomer|alamat|address|nama|name)\b/u', $lower)) {
                return 'ubah_item';
            }
            if (preg_match('/\b(kode\s*pos|kodepos|postal)\b/u', $lower))          { return 'isi_kode_pos'; }
            if (preg_match('/\bemail\b/u', $lower))                                 { return 'isi_email'; }
            if (preg_match('/\b(wa|whatsapp|nomor|hp|telepon|nomer)\b/u', $lower))  { return 'isi_wa'; }
            if (preg_match('/\b(meja|table|pickup|pick\s*up|delivery|antar|ambil)\b/u', $lower)) { return 'isi_fulfillment'; }
            if (preg_match('/\b(alamat|address)\b/u', $lower))                      { return 'isi_alamat'; }
            if (preg_match('/\b(nama|name)\b/u', $lower))                           { return 'isi_nama'; }
        }

        return 'konfirmasi_order';
    }

    private function isCancel(string $lower): bool
    {
        $words = array_merge(self::$patterns['batal_order'], ['jangan', 'tidak']);
        foreach ($words as $w) {
            if (str_contains($lower, $w)) { return true; }
        }
        return false;
    }

    private function looksLikeOrderHistoryQuestion(string $lower): bool
    {
        if (preg_match('/\bord-\d{8}-[a-z0-9]+\b/i', $lower) === 1) {
            return true;
        }

        return preg_match(
            '/\b(status\s+order|riwayat\s+order|riwayat\s+pesanan|history\s+order|history\s+pesanan|cek\s+order\s+saya|order\s+sebelumnya|pesan\s+apa\s+tadi|tadi\s+saya\s+pesan\s+apa|barusan\s+saya\s+pesan\s+apa|saya\s+pesan\s+apa\s+tadi|detail\s+ord-|detail\s+order)\b/u',
            $lower
        ) === 1;
    }

    private function scoreMessage(string $lower): string
    {
        $patterns = HookManager::applyFilters('intent.patterns', self::$patterns);
        $scores = [];
        foreach ((array) $patterns as $intent => $keywords) {
            $score = 0;
            foreach ((array) $keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $score += strlen($keyword);
                }
            }
            if ($score > 0) {
                $scores[$intent] = $score;
            }
        }
        if (empty($scores)) { return 'out_of_scope'; }
        arsort($scores);
        return array_key_first($scores);
    }

    private function detectCheckoutField(string $lower, string $state): string
    {
        static $escapeIntents = [
            'tanya_promo', 'tanya_menu', 'lihat_cart', 'clear_cart',
            'hapus_item', 'pakai_promo', 'tambah_item', 'ubah_item', 'komplain_customer', 'faq_customer',
        ];

        $scored = $this->scoreMessage($lower);
        if (in_array($scored, $escapeIntents, true)) {
            return $scored;
        }

        return match ($state) {
            'awaiting_name'   => 'isi_nama',
            'awaiting_email'  => 'isi_email',
            'awaiting_wa'     => 'isi_wa',
            'awaiting_fulfillment' => 'isi_fulfillment',
            'awaiting_table'  => 'isi_nomor_meja',
            'awaiting_address'=> 'isi_alamat',
            'awaiting_postal' => 'isi_kode_pos',
            default           => 'konfirmasi_order',
        };
    }

    /**
     * Extract item name and quantity from a single-item order phrase.
     * e.g. "mau 2 latte" -> ['item_query' => 'latte', 'qty' => 2]
     */
    public function extractOrderIntent(string $message): array
    {
        $items = $this->extractMultipleItems($message);
        return $items[0] ?? ['item_query' => '', 'qty' => 1];
    }

    /**
     * Extract multiple items from one order message.
     * e.g. "pesan 2 croissant dan 1 lemon tea" ->
     *   [['item_query'=>'croissant','qty'=>2], ['item_query'=>'lemon tea','qty'=>1]]
     */
    public function extractMultipleItems(string $message): array
    {
        $numbers = [
            'satu'=>1,'dua'=>2,'tiga'=>3,'empat'=>4,'lima'=>5,
            'enam'=>6,'tujuh'=>7,'delapan'=>8,'sembilan'=>9,'sepuluh'=>10,
        ];
        $sizes = ['small', 'medium', 'large', 'sm', 'md', 'lg'];

        $lower = mb_strtolower(trim($message), 'UTF-8');

        // Strip multi-word ordering phrases first, then single verbs/particles
        $multiPhrases = [
            'i would like', "i'd like", 'can i have', 'can i get',
            'let me get', 'let me have', "i'll have", "i'll take",
            'please give me', 'give me', 'i want to order', 'i want', 'i need',
            'tolong pesan', 'boleh minta', 'mau pesan', 'mau nambah', 'mau tambah',
        ];
        $stripped = str_ireplace($multiPhrases, '', $lower);

        $singleWords = [
            'pesan','order','beli','minta','mau','tambah','nambah','tambahin','tambahkan','add',
            'ganti','ubah','bikin','jadi','jadiin',
            'saja','aja','dong','deh','lah','yah','ya','nih','tuh','lagi','kembali',
            'please','tolong','lg',
        ];
        foreach ($singleWords as $w) {
            $stripped = preg_replace('/\b' . preg_quote($w, '/') . '\b/u', '', $stripped);
        }
        // Strip bare English articles/pronouns that survive after phrase removal
        $stripped = preg_replace('/\b(a|an|the|some|me|us)\b/u', '', $stripped);
        $stripped = preg_replace('/\s+/', ' ', trim($stripped));

        // Split on "dan", comma, "+", "&"
        $parts = preg_split('/\s+dan\s+|,\s*|\s*\+\s*|\s*&\s*/u', $stripped, -1, PREG_SPLIT_NO_EMPTY);

        $results = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') { continue; }

            $qty = 1;
            $variantLabel = null;

            // Word quantity (check before digit so "dua" isn't eaten by \d+)
            $wordQtyFound = false;
            foreach ($numbers as $word => $val) {
                if (str_contains($part, $word)) {
                    $qty          = $val;
                    $part         = preg_replace('/\b' . $word . '\b/u', '', $part);
                    $wordQtyFound = true;
                    break;
                }
            }

            // Digit quantity — skip if word qty already found to avoid overriding it
            // and only remove the FIRST digit so numbers embedded in item names are preserved
            if (!$wordQtyFound && preg_match('/\b(\d+)\b/', $part, $m)) {
                $qty  = (int)$m[1];
                $part = preg_replace('/\b\d+\b/', '', $part, 1);
            }

            foreach ($sizes as $size) {
                if (preg_match('/\b' . preg_quote($size, '/') . '\b/u', $part)) {
                    $variantLabel = match ($size) {
                        'sm' => 'small',
                        'md' => 'medium',
                        'lg' => 'large',
                        default => $size,
                    };
                    $part = preg_replace('/\b' . preg_quote($size, '/') . '\b/u', '', $part);
                    break;
                }
            }

            $itemName = preg_replace('/\s+/', ' ', trim($part));
            if ($itemName !== '') {
                $results[] = ['item_query' => $itemName, 'qty' => $qty, 'variant_label' => $variantLabel];
            }
        }

        return $results ?: [['item_query' => '', 'qty' => 1, 'variant_label' => null]];
    }
}
