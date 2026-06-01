<?php

declare(strict_types=1);

final class ShopeeLiveCopilot
{
    public function __construct(
        private ShopeeLiveAnalyticsService $analytics,
        private ShopeeCommentAnalyzer $commentAnalyzer
    ) {}

    public function generate(array $metric, array $comments = []): array
    {
        $summary = $this->analytics->summarize($metric);
        $commentInsight = $this->commentAnalyzer->analyze($comments);
        $actions = [$summary['insight']];

        $topTopic = (string)($commentInsight['top_topic'] ?? '');
        if ($topTopic !== '') {
            $actions[] = 'Topik komentar paling sering: ' . $topTopic . '. Host perlu menjawab topik ini berulang saat live.';
        }

        if (($summary['conversion_rate'] ?? 0) < 1 && ($summary['viewers'] ?? 0) > 500) {
            $actions[] = 'Conversion rendah. Tekankan voucher, gratis ongkir, stok terbatas, dan ajak klik keranjang.';
        }

        if (($summary['orders'] ?? 0) > 30) {
            $actions[] = 'Momentum order bagus. Jalankan countdown flash sale dan tawarkan bundle produk.';
        }

        return [
            'summary' => $summary,
            'comment_insight' => $commentInsight,
            'host_actions' => $actions,
        ];
    }
}
