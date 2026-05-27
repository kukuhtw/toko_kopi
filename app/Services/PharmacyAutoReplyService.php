<?php

declare(strict_types=1);

namespace App\Services;

class PharmacyAutoReplyService
{
    public function generateReply(string $message): array
    {
        $message = strtolower(trim($message));

        $reply = 'Terima kasih telah menghubungi layanan apotek. ';
        $risk = 'low';
        $escalate = false;

        if (str_contains($message, 'demam')) {
            $reply .= 'Demam ringan dapat dibantu dengan Paracetamol dan istirahat cukup. ';
        }

        if (str_contains($message, 'batuk')) {
            $reply .= 'Batuk ringan dapat dibantu dengan obat batuk OTC dan minum hangat. ';
        }

        if (str_contains($message, 'sesak') || str_contains($message, 'darah')) {
            $risk = 'high';
            $escalate = true;
            $reply .= 'Gejala berisiko tinggi terdeteksi. Segera konsultasi dokter atau apoteker. ';
        }

        if (str_contains($message, 'antibiotik')) {
            $reply .= 'Antibiotik membutuhkan konsultasi apoteker atau dokter. ';
            $escalate = true;
        }

        $reply .= 'AI assistant ini tidak menggantikan diagnosis dokter.';

        return [
            'risk_level' => $risk,
            'escalate_to_pharmacist' => $escalate,
            'reply' => $reply,
        ];
    }
}
