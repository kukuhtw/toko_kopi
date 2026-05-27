<?php

declare(strict_types=1);

namespace App\Services;

class PharmacyEscposPrinterService
{
    public function generateReceipt(array $sale, array $items): string
    {
        $text = "================================\n";
        $text .= "        APOTEK KOPIBOT\n";
        $text .= "================================\n";
        $text .= "Invoice : " . ($sale['invoice_no'] ?? '-') . "\n";
        $text .= "Date    : " . date('Y-m-d H:i:s') . "\n";
        $text .= "--------------------------------\n";

        foreach ($items as $item) {
            $line = sprintf(
                "%s x%s\nRp %s\n",
                $item['item_name'] ?? '-',
                $item['qty'] ?? 1,
                number_format((float)($item['total_price'] ?? 0), 0, ',', '.')
            );

            $text .= $line;
        }

        $text .= "--------------------------------\n";
        $text .= "Grand Total : Rp " . number_format((float)($sale['grand_total'] ?? 0), 0, ',', '.') . "\n";
        $text .= "================================\n";
        $text .= "Terima kasih\n";
        $text .= "================================\n";

        return $text;
    }
}
