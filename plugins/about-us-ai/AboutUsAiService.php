<?php

declare(strict_types=1);

final class AboutUsAiService
{
    public function __construct(
        private AboutUsAiRepository $repo
    ) {}

    public function generateDraft(int $branchId, array $payload): array
    {
        $brand = trim((string) ($payload['brand_name'] ?? 'Brand Anda'));
        $businessType = trim((string) ($payload['business_type'] ?? 'usaha lokal'));
        $tone = trim((string) ($payload['tone'] ?? 'hangat dan terpercaya'));
        $prompt = trim((string) ($payload['prompt'] ?? ''));

        $generated = "Tentang {$brand}\n\n"
            . "{$brand} adalah {$businessType} yang berfokus menghadirkan pengalaman terbaik untuk pelanggan. "
            . "Kami membangun layanan dengan pendekatan {$tone}, mengutamakan kualitas, konsistensi, dan hubungan jangka panjang.\n\n"
            . "Melalui tim yang terus berkembang, kami ingin membantu pelanggan menemukan solusi yang relevan, cepat, dan menyenangkan di setiap interaksi.";

        $short = "{$brand} menghadirkan layanan {$businessType} dengan pendekatan {$tone}.";

        $contentId = $this->repo->saveContent(
            $branchId,
            'Tentang ' . $brand,
            $short,
            $generated,
            'draft',
            $prompt !== '' ? $prompt : null,
            $generated,
            'scaffold-template',
            'system'
        );

        $this->repo->addGenerationLog(
            $branchId,
            $contentId,
            'about_us.generate',
            'success',
            $prompt !== '' ? $prompt : null,
            $generated,
            'scaffold-template'
        );

        return [
            'success' => true,
            'content_id' => $contentId,
            'title' => 'Tentang ' . $brand,
            'short_description' => $short,
            'content' => $generated,
            'message' => 'Draft About Us berhasil dibuat dalam mode scaffold.',
        ];
    }

    public function publish(int $branchId, array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? 'Tentang Kami'));
        $short = trim((string) ($payload['short_description'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));

        $contentId = $this->repo->saveContent(
            $branchId,
            $title,
            $short,
            $content,
            'published',
            null,
            null,
            null,
            'system'
        );

        $this->repo->addGenerationLog($branchId, $contentId, 'about_us.publish', 'success');

        return [
            'success' => true,
            'content_id' => $contentId,
            'message' => 'Konten About Us berhasil dipublish.',
        ];
    }
}
