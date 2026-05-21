<?php

declare(strict_types=1);

namespace App\Skills;

class SmallTalkSkill implements SkillInterface
{
    private static array $responses = [
        'id' => [
            'halo'         => ["Halo! 👋 Selamat datang di Toko Kopi! Mau minum apa hari ini?", "Hai! 😊 Ada yang bisa kami bantu?"],
            'terima_kasih' => ["Sama-sama! 😊 Ada yang bisa kami bantu lagi?", "Dengan senang hati! Selamat menikmati kopinya ☕"],
            'oke'          => ["Oke! 😊 Ada yang bisa kami bantu?"],
            'bye'          => ["Sampai jumpa! ☕ Selamat menikmati harimu!", "Terima kasih sudah berkunjung! Sampai jumpa lagi 😊"],
            'english'      => ["Yes, I can help in English. Type *menu* to browse our menu, or tell me what you'd like to order."],
        ],
        'en' => [
            'halo'         => ["Hello! 👋 Welcome to our Coffee Shop! What would you like today?", "Hi! 😊 How can I help you?"],
            'terima_kasih' => ["You're welcome! 😊 Anything else I can help with?", "My pleasure! Enjoy your coffee ☕"],
            'oke'          => ["Ok! 😊 How can I help you?"],
            'bye'          => ["Goodbye! ☕ Have a wonderful day!", "Thank you for visiting! See you again 😊"],
            'english'      => ["Yes, I can help in English. Type *menu* to browse our menu, or tell me what you'd like to order."],
        ],
    ];

    private static array $greetingKeywords    = ['halo', 'hai', 'hello', 'hi', 'selamat', 'pagi', 'siang', 'sore', 'malam'];
    private static array $thankKeywords       = ['terima kasih', 'makasih', 'thanks', 'thank you', 'thx'];
    private static array $byeKeywords         = ['bye', 'dadah', 'sampai jumpa', 'selamat tinggal', 'goodbye'];
    private static array $languageKeywords    = [
        'speak english', 'english please', 'in english', 'use english',
        'can you speak english', 'do you speak english', 'bahasa inggris', 'pakai bahasa inggris',
    ];
    private static array $branchInfoKeywords  = [
        'alamat', 'lokasi', 'di mana', 'dimana', 'where', 'telepon', 'no hp',
        'jam buka', 'jam operasional', 'buka jam', 'tutup jam', 'kontak',
        'cabang mana', 'branch mana', 'nomor cabang', 'cabang ini',
        'saya di cabang mana', 'ini cabang mana', 'which branch', 'what branch',
    ];

    public function canHandle(string $intent): bool
    {
        return false; // SmallTalk is fallback — detected manually in engine
    }

    public function handle(array $ctx): array
    {
        $msg  = mb_strtolower(trim($ctx['message']), 'UTF-8');
        $lang = $ctx['language'] ?? 'id';
        $name = $ctx['customer']['name'] ?? null;

        // Branch info questions take priority
        foreach (self::$branchInfoKeywords as $kw) {
            if (self::containsKeyword($msg, $kw)) {
                return $this->replyBranchInfo($ctx);
            }
        }

        $category = 'halo';
        if (self::isLanguagePreference($msg)) {
            $category = 'english';
            $lang = 'en';
        }

        foreach (self::$thankKeywords as $kw) {
            if (self::containsKeyword($msg, $kw)) { $category = 'terima_kasih'; break; }
        }
        foreach (self::$byeKeywords as $kw) {
            if (self::containsKeyword($msg, $kw)) { $category = 'bye'; break; }
        }

        $pool  = self::$responses[$lang][$category] ?? self::$responses['id'][$category];
        $reply = $pool[array_rand($pool)];

        if ($name && $category === 'halo') {
            $reply = str_replace(['Halo!', 'Hai!', 'Hello!', 'Hi!'], "Halo, {$name}!", $reply);
        }

        return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
    }

    private function replyBranchInfo(array $ctx): array
    {
        $branch = $ctx['branch'] ?? [];
        $lang   = $ctx['language'] ?? 'id';
        $name   = $branch['name'] ?? 'Toko Kopi';

        $lines = [$lang === 'id' ? "📍 Info *{$name}*:" : "📍 *{$name}* Info:"];

        if (!empty($branch['address'])) {
            $lines[] = ($lang === 'id' ? '🏠 Alamat: ' : '🏠 Address: ') . $branch['address'];
        }
        if (!empty($branch['city'])) {
            $lines[] = '🌆 ' . $branch['city'];
        }
        if (!empty($branch['phone'])) {
            $lines[] = '📞 ' . $branch['phone'];
        }

        $lines[] = '';
        $lines[] = $lang === 'id'
            ? 'Ada yang bisa kami bantu? Ketik *menu* untuk melihat pilihan kami.'
            : 'Anything else? Type *menu* to browse our menu.';

        return ['reply' => implode("\n", $lines), 'state' => 'idle', 'action_result' => $branch];
    }

    public static function isSmallTalk(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $all   = array_merge(
            self::$greetingKeywords,
            self::$thankKeywords,
            self::$byeKeywords,
            self::$branchInfoKeywords,
            self::$languageKeywords
        );
        foreach ($all as $kw) {
            if (self::containsKeyword($lower, $kw)) { return true; }
        }
        return false;
    }

    public static function isLanguagePreference(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        foreach (self::$languageKeywords as $kw) {
            if (self::containsKeyword($lower, $kw)) { return true; }
        }
        return false;
    }

    private static function containsKeyword(string $message, string $keyword): bool
    {
        $message = mb_strtolower($message, 'UTF-8');
        $keyword = mb_strtolower($keyword, 'UTF-8');

        if (str_contains($keyword, ' ')) {
            return str_contains($message, $keyword);
        }

        return preg_match('/(^|[^\p{L}\p{N}])' . preg_quote($keyword, '/') . '([^\p{L}\p{N}]|$)/u', $message) === 1;
    }
}
