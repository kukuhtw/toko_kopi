<?php

declare(strict_types=1);

final class ComplaintAnalyzer
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function analyze(string $message, array $context = []): array
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        $recentComplaintCount = (int)($context['recent_complaint_count'] ?? 0);

        $humanKeywords = [
            'refund', 'uang kembali', 'balikin uang', 'pengembalian dana', 'kompensasi',
            'salah bayar', 'double charge', 'ditagih dua kali', 'penipuan', 'scam',
            'lapor', 'laporkan', 'viral', 'kecewa berat', 'marah', 'kapok',
            'manager', 'admin', 'cs manusia', 'orang asli', 'human agent',
            'pesanan tidak datang', 'belum datang', 'kurir', 'driver', 'cancel',
            'batalkan order', 'salah kirim', 'salah antar', 'gantirugi', 'ganti rugi',
            'food poisoning', 'alergi', 'bahaya',
        ];

        $qualityKeywords = [
            'dingin', 'tumpah', 'pahit', 'manis banget', 'keasinan', 'kurang enak',
            'tidak enak', 'ga enak', 'gak enak', 'kotor', 'lama', 'telat', 'menunggu',
            'salah menu', 'salah minuman', 'kurang lengkap', 'tidak sesuai',
        ];

        $category = 'general';
        if ($this->containsAny($lower, ['refund', 'pengembalian dana', 'uang kembali', 'double charge', 'salah bayar'])) {
            $category = 'payment';
        } elseif ($this->containsAny($lower, ['kurir', 'driver', 'belum datang', 'telat', 'lama', 'salah antar'])) {
            $category = 'delivery';
        } elseif ($this->containsAny($lower, ['rasa', 'dingin', 'pahit', 'manis', 'tumpah', 'kotor', 'salah menu'])) {
            $category = 'product';
        }

        $needsHuman = $this->containsAny($lower, $humanKeywords)
            || $recentComplaintCount >= 2
            || (preg_match('/\bord-\d{8}-[a-z0-9]+\b/i', $message) === 1 && $this->containsAny($lower, $qualityKeywords))
            || $this->containsAny($lower, ['tidak terima', 'mau bicara', 'hubungi saya']);

        $priority = 'medium';
        if ($needsHuman && $this->containsAny($lower, ['penipuan', 'food poisoning', 'alergi', 'bahaya', 'viral', 'lapor'])) {
            $priority = 'high';
        } elseif ($this->containsAny($lower, ['lama', 'telat', 'dingin', 'salah menu', 'tidak sesuai'])) {
            $priority = 'medium';
        } else {
            $priority = 'low';
        }

        $reason = $needsHuman
            ? $this->buildHumanReason($lower, $recentComplaintCount)
            : 'Komplain masih bisa dijawab AI dengan empati dan tindak lanjut ringan.';

        return [
            'is_complaint' => true,
            'handling_mode' => $needsHuman ? 'human' : 'ai',
            'priority' => $priority,
            'category' => $category,
            'follow_up_reason' => $reason,
            'subject' => $this->buildSubject($category, $message),
        ];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function buildHumanReason(string $lower, int $recentComplaintCount): string
    {
        if ($recentComplaintCount >= 2) {
            return 'Customer sudah beberapa kali komplain dalam periode dekat, perlu follow-up manusia.';
        }

        if ($this->containsAny($lower, ['refund', 'uang kembali', 'pengembalian dana', 'double charge', 'salah bayar'])) {
            return 'Komplain terkait pembayaran/refund perlu verifikasi dan keputusan manusia.';
        }

        if ($this->containsAny($lower, ['admin', 'manager', 'cs manusia', 'orang asli', 'human agent'])) {
            return 'Customer secara eksplisit meminta bantuan manusia.';
        }

        if ($this->containsAny($lower, ['penipuan', 'viral', 'lapor', 'bahaya', 'alergi'])) {
            return 'Komplain berisiko tinggi dan perlu penanganan manusia segera.';
        }

        return 'Komplain memerlukan tindakan operasional atau verifikasi lebih lanjut dari tim cabang.';
    }

    private function buildSubject(string $category, string $message): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        $snippet = mb_substr($plain, 0, 80, 'UTF-8');
        return '[' . strtoupper($category) . '] ' . $snippet;
    }
}
