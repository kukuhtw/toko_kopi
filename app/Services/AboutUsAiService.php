<?php

declare(strict_types=1);

namespace App\Services;

final class AboutUsAiService
{
    public function buildPrompt(array $payload): string
    {
        return sprintf(
            "Create a professional About Us page in Indonesian language for this business. Business Name: %s. Business Type: %s. Business Description: %s. Vision: %s. Mission: %s. Strengths: %s. Tone: professional, friendly, trustworthy, SEO-friendly.",
            $payload['business_name'] ?? '',
            $payload['business_type'] ?? '',
            $payload['business_description'] ?? '',
            $payload['vision'] ?? '',
            $payload['mission'] ?? '',
            $payload['strengths'] ?? ''
        );
    }

    public function mockGenerate(array $payload): array
    {
        $title = 'Tentang ' . ($payload['business_name'] ?? 'Perusahaan Kami');

        $content = "<h2>Tentang Kami</h2>\n\n"
            . '<p>'
            . ($payload['business_name'] ?? 'Perusahaan Kami')
            . ' adalah '
            . ($payload['business_description'] ?? 'usaha profesional')
            . ' yang berkomitmen memberikan pelayanan terbaik kepada pelanggan.</p>'
            . "\n\n"
            . '<p>Dengan fokus pada kualitas layanan, inovasi, dan kepuasan pelanggan, kami terus berkembang untuk menjadi pilihan terpercaya di bidang '
            . ($payload['business_type'] ?? 'usaha kami')
            . '.</p>';

        return [
            'title' => $title,
            'content' => $content,
        ];
    }
}
