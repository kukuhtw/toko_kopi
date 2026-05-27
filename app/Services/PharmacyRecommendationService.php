<?php

declare(strict_types=1);

namespace App\Services;

class PharmacyRecommendationService
{
    public function recommend(string $symptom): array
    {
        $symptom = strtolower(trim($symptom));

        $recommendations = [];
        $warnings = [];

        if (str_contains($symptom, 'demam')) {
            $recommendations[] = 'Paracetamol 500mg';
            $recommendations[] = 'Vitamin C 1000mg';
        }

        if (str_contains($symptom, 'batuk')) {
            $recommendations[] = 'OBH Combi';
            $recommendations[] = 'Lozenges';
        }

        if (str_contains($symptom, 'lambung')) {
            $recommendations[] = 'Antasida';
            $recommendations[] = 'Omeprazole';
        }

        if (str_contains($symptom, 'antibiotik')) {
            $warnings[] = 'Antibiotik memerlukan resep dokter atau verifikasi apoteker.';
        }

        return [
            'recommendations' => array_values(array_unique($recommendations)),
            'warnings' => $warnings,
            'disclaimer' => 'AI recommendation is educational only and not a medical diagnosis.'
        ];
    }
}
