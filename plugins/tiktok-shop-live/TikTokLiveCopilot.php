<?php

declare(strict_types=1);

final class TikTokLiveCopilot
{
    public function __construct(
        private TikTokLiveAnalyticsService $analytics,
        private TikTokCommentAnalyzer $commentAnalyzer
    ) {}

    public function generate(array $metric, array $comments = []): array
    {
        $summary = $this->analytics->summarize($metric);
        $commentInsight = $this->commentAnalyzer->analyze($comments);

        $actions = [];
        $actions[] = $summary['insight'];

        $topQuestion = (string)($commentInsight['top_question'] ?? '');
        if ($topQuestion !== '') {
            $actions[] = 'Pertanyaan terbanyak terkait: ' . $topQuestion . '. Host sebaiknya menjawab topik ini secara berulang.';
        }

        if (($summary['conversion_rate'] ?? 0) < 1 && ($summary['viewers'] ?? 0) > 500) {
            $actions[] = 'Conversion masih rendah. Tampilkan produk hero, berikan bonus terbatas, lalu arahkan audience klik keranjang.';
        }

        if (($summary['orders'] ?? 0) > 30) {
            $actions[] = 'Momentum order bagus. Jalankan countdown flash sale dan tawarkan bundle.';
        }

        return [
            'summary' => $summary,
            'comment_insight' => $commentInsight,
            'host_actions' => $actions,
        ];
    }
}
