<?php

declare(strict_types=1);

use App\Plugin\HookManager;
use App\Plugin\PluginInterface;
use App\Services\IntentPatternRegistry;
use App\Skills\SkillRegistry;

final class FaqRagPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'FAQ RAG';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'KopiBot Team';
    }

    public function register(): void
    {
        $repo = new FaqRepository();
        $repo->ensureSchema();
        $this->seedStarterFaqs($repo);

        HookManager::addFilter('skills.registered', [$this, 'registerSkill'], 25);
        HookManager::addFilter('intent.patterns', [$this, 'registerIntentPatterns'], 25);
        HookManager::addFilter('intent.detect', [$this, 'detectIntent'], 25);
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 25);
    }

    public function registerSkill(array $skills): array
    {
        return SkillRegistry::register($skills, new FaqSkill(), 25);
    }

    public function registerIntentPatterns(array $patterns): array
    {
        return IntentPatternRegistry::extend($patterns, 'faq_customer', [
            'wifi', 'wi-fi', 'jam buka', 'jam tutup', 'buka jam', 'alamat toko',
            'parkir', 'halal', 'reservasi', 'booking', 'dine in', 'take away',
            'takeaway', 'cashless', 'qris', 'metode pembayaran', 'faq',
        ]);
    }

    public function detectIntent(string $intent, string $message, array $context = []): string
    {
        if (trim($intent) !== '') {
            return $intent;
        }

        $branchId = (int)($context['branch_id'] ?? 0);
        if ($branchId <= 0) {
            return '';
        }

        $detector = new FaqRagResponder();
        return $detector->detectAsFaq($message, $branchId) ? 'faq_customer' : '';
    }

    public function addNavItems(array $navItems, string $role): array
    {
        if ($role === 'super_admin') {
            if (!isset($navItems['Management']) || !is_array($navItems['Management'])) {
                $navItems['Management'] = [];
            }
            $navItems['Management'][] = [
                'url' => '/dashboard/super/faqs.php',
                'icon' => 'FQ',
                'label' => 'FAQ Global',
            ];
            return $navItems;
        }

        if ($role === 'branch_admin') {
            if (!isset($navItems['Settings']) || !is_array($navItems['Settings'])) {
                $navItems['Settings'] = [];
            }
            $navItems['Settings'][] = [
                'url' => '/dashboard/branch/faqs.php',
                'icon' => 'FQ',
                'label' => 'FAQ Cabang',
            ];
        }

        return $navItems;
    }

    private function seedStarterFaqs(FaqRepository $repo): void
    {
        if ($repo->countGlobalFaqs() > 0) {
            return;
        }

        $starters = [
            [
                'question' => 'Apakah tersedia Wi-Fi di toko?',
                'answer' => 'Ya, Wi-Fi tersedia untuk customer yang dine-in. Silakan minta password Wi-Fi ke kasir atau barista saat berkunjung.',
                'tags' => 'wifi, wi-fi, internet, dine in',
            ],
            [
                'question' => 'Jam operasional toko setiap hari jam berapa?',
                'answer' => 'Jam operasional dapat berbeda per cabang. Anda bisa menanyakan cabang yang ingin dikunjungi, lalu kami bantu informasikan jam bukanya.',
                'tags' => 'jam buka, jam tutup, operasional, opening hours',
            ],
            [
                'question' => 'Apakah bisa bayar pakai QRIS atau cashless?',
                'answer' => 'Bisa. Kami menerima pembayaran cashless seperti QRIS, transfer, dan metode non-tunai lain sesuai yang tersedia di cabang.',
                'tags' => 'qris, cashless, metode pembayaran, payment',
            ],
            [
                'question' => 'Apakah bisa takeaway dan delivery?',
                'answer' => 'Bisa. Anda dapat pesan untuk takeaway maupun delivery, tergantung layanan yang aktif di cabang tersebut.',
                'tags' => 'takeaway, take away, delivery, pesan antar',
            ],
            [
                'question' => 'Apakah tersedia area parkir untuk customer?',
                'answer' => 'Ketersediaan parkir tergantung cabang. Jika Anda sebutkan cabang tujuan, kami bisa bantu cek apakah tersedia parkir motor atau mobil.',
                'tags' => 'parkir, mobil, motor, lokasi',
            ],
        ];

        foreach ($starters as $faq) {
            $repo->create([
                'scope' => 'global',
                'branch_id' => null,
                'parent_global_id' => null,
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'tags' => $faq['tags'],
                'is_active' => 1,
            ]);
        }
    }
}
