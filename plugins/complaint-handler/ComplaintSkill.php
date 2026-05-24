<?php

declare(strict_types=1);

use App\Models\OrderModel;
use App\Skills\SkillInterface;

final class ComplaintSkill implements SkillInterface
{
    private ComplaintTicketRepository $tickets;
    private ComplaintAnalyzer $analyzer;
    private OrderModel $orders;

    public function __construct(
        ?ComplaintTicketRepository $tickets = null,
        ?ComplaintAnalyzer $analyzer = null,
        ?OrderModel $orders = null
    ) {
        $this->tickets = $tickets ?? new ComplaintTicketRepository();
        $this->analyzer = $analyzer ?? new ComplaintAnalyzer();
        $this->orders = $orders ?? new OrderModel();
    }

    public function canHandle(string $intent): bool
    {
        return $intent === 'komplain_customer';
    }

    public function handle(array $context): array
    {
        $branchId = (int)$context['branch_id'];
        $customerId = (int)($context['customer']['id'] ?? 0);
        $conversationId = (int)($context['conversation']['id'] ?? 0);
        $message = trim((string)($context['message'] ?? ''));
        $language = (string)($context['language'] ?? 'id');
        $channel = (string)($context['channel'] ?? 'web');
        $convCtx = (array)($context['conv_context'] ?? []);

        $recentComplaintCount = $customerId > 0
            ? $this->tickets->countRecentCustomerComplaints($branchId, $customerId)
            : 0;
        $analysis = $this->analyzer->analyze($message, [
            'recent_complaint_count' => $recentComplaintCount,
        ]);

        $order = $this->resolveOrderReference($message, $customerId);
        $handlingMode = (string)$analysis['handling_mode'];
        $ticketStatus = $handlingMode === 'human' ? 'open' : 'resolved';
        $reply = $handlingMode === 'human'
            ? $this->buildHumanReply($language)
            : $this->buildAiReply($language, (string)$analysis['category']);

        $ticketId = $this->tickets->create([
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'conversation_id' => $conversationId,
            'order_id' => (int)($order['id'] ?? 0),
            'source_channel' => $channel,
            'status' => $ticketStatus,
            'handling_mode' => $handlingMode,
            'priority' => (string)$analysis['priority'],
            'category' => (string)$analysis['category'],
            'subject' => (string)$analysis['subject'],
            'customer_message' => $message,
            'ai_reply' => $reply,
            'follow_up_reason' => (string)$analysis['follow_up_reason'],
        ]);

        $convCtx['last_topic'] = 'complaint';
        $convCtx['last_complaint_ticket_id'] = $ticketId;
        $convCtx['last_complaint_mode'] = $handlingMode;

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => [
                'complaint_ticket_id' => $ticketId,
                'handling_mode' => $handlingMode,
                'order_number' => $order['order_number'] ?? null,
            ],
            'conv_context' => $convCtx,
        ];
    }

    private function resolveOrderReference(string $message, int $customerId): array|false
    {
        if (preg_match('/\b(ORD-\d{8}-[A-Z0-9]+)\b/i', $message, $matches) === 1) {
            $order = $this->orders->findByOrderNumber(strtoupper($matches[1]));
            if ($order) {
                return $order;
            }
        }

        if ($customerId <= 0) {
            return false;
        }

        $recent = $this->orders->getCustomerOrders($customerId, 1);
        return $recent[0] ?? false;
    }

    private function buildHumanReply(string $language): string
    {
        if ($language === 'en') {
            return "Sorry about that. I've created a complaint ticket for our team, and a human staff member will follow up with you shortly.";
        }

        return "Maaf atas ketidaknyamanannya. Saya sudah buat tiket komplain untuk tim cabang, dan staf kami akan follow up ke Anda secepatnya.";
    }

    private function buildAiReply(string $language, string $category): string
    {
        if ($language === 'en') {
            return match ($category) {
                'product' => "Sorry about the product quality. I've noted your feedback and passed it into our service notes. If you'd like direct staff help, just say human admin.",
                'delivery' => "Sorry about the delivery experience. I've recorded your feedback. If the issue is still unresolved, say human admin and I'll escalate it.",
                default => "Sorry about your experience. I've noted your complaint and responded on behalf of the store. If you want a human follow-up, just say human admin.",
            };
        }

        return match ($category) {
            'product' => "Maaf ya soal kualitas pesanannya. Keluhan Anda sudah saya catat sebagai feedback layanan. Kalau ingin dibantu staf langsung, cukup balas *admin manusia*.",
            'delivery' => "Maaf ya soal pengalaman pengirimannya. Keluhan Anda sudah saya catat. Kalau masalahnya masih belum selesai, balas *admin manusia* dan saya eskalasi.",
            default => "Maaf atas pengalaman kurang nyamannya. Keluhan Anda sudah saya catat dan saya bantu jawab dulu. Kalau ingin follow-up staf langsung, balas *admin manusia*.",
        };
    }
}
