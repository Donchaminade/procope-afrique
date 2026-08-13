<?php

namespace App\Services;

use App\Models\Inscription;
use App\Models\JobApplication;

/**
 * Export des inscriptions et des candidatures d'emploi :
 * XLSX via PhpSpreadsheet si dispo, sinon CSV UTF-8 BOM.
 */
final class Exporter
{
    private const HEADERS = [
        'ID', 'Formation', 'Nom et prénom', 'Sexe', 'Téléphone', 'Email', 'Ville / Quartier',
        'Situation professionnelle', 'Déjà entrepreneur', 'Entreprise', 'Secteur', 'Motivation',
        'Modules', 'Modalité de paiement', 'Type de paiement', 'Montant déclaré', 'Montant reçu',
        'Reste à payer', 'Preuve fournie', 'Source', 'Autorisation images',
        'Statut', 'Date d\'inscription',
    ];

    private const APPLICATION_HEADERS = [
        'Offre', 'Nom et prénom', 'Email', 'Téléphone', 'Statut',
        'Date de candidature', 'Message (extrait)',
    ];

    public static function download(array $filters): never
    {
        $rows = array_map([self::class, 'mapRow'], Inscription::allFiltered($filters));
        $filename = 'inscriptions-procope-' . date('Ymd-His');
        self::downloadTable(self::HEADERS, $rows, $filename, 'Inscriptions');
    }

    /**
     * Export Excel/CSV des candidatures d'emploi.
     * $filters : offer_id, statut — les filtres actifs de la vue sont respectés.
     */
    public static function downloadApplications(array $filters): never
    {
        $rows = array_map([self::class, 'mapApplicationRow'], JobApplication::allFiltered($filters));
        $filename = 'candidatures-procope-' . date('Ymd-His');
        self::downloadTable(self::APPLICATION_HEADERS, $rows, $filename, 'Candidatures');
    }

    private static function downloadTable(array $headers, array $rows, string $filename, string $sheetTitle): never
    {
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            self::xlsx($headers, $rows, $filename, $sheetTitle);
        }
        self::csv($headers, $rows, $filename);
    }

    private static function mapApplicationRow(array $a): array
    {
        return [
            $a['offer_title'],
            $a['full_name'],
            $a['email'],
            $a['phone'],
            JobApplication::STATUT_LABELS[$a['statut']] ?? $a['statut'],
            $a['created_at'],
            self::truncate((string) ($a['message'] ?? ''), 180),
        ];
    }

    /** Tronque proprement un texte (extrait du message dans les exports). */
    private static function truncate(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max)) . '…' : $text;
    }

    private static function mapRow(array $i): array
    {
        $modules = $i['modules'] ? implode(' | ', (array) json_decode($i['modules'], true)) : '';
        $prix = (float) ($i['formation_prix'] ?? 0);
        $resteAPayer = $i['amount_received'] !== null
            ? number_format(max(0, $prix - (float) $i['amount_received']), 0, ',', ' ')
            : '';
        return [
            $i['id'],
            $i['formation_titre'],
            $i['full_name'],
            $i['gender'] === 'M' ? 'Masculin' : ($i['gender'] === 'F' ? 'Féminin' : ''),
            $i['phone'],
            $i['email'],
            $i['city'],
            $i['professional_status'],
            $i['is_entrepreneur'] === null ? '' : ((int) $i['is_entrepreneur'] ? 'OUI' : 'NON'),
            $i['company_name'],
            $i['sector'],
            $i['motivation'],
            $modules,
            $i['payment_method'] === 'mobile_money' ? 'Mobile Money (TMoney / Flooz)'
                : ($i['payment_method'] === 'ecobank' ? 'Ecobank' : ''),
            ($i['payment_type'] ?? 'total') === 'partiel' ? 'Partiel' : 'Total',
            $i['amount_declared'] !== null ? number_format((float) $i['amount_declared'], 0, ',', ' ') : '',
            $i['amount_received'] !== null ? number_format((float) $i['amount_received'], 0, ',', ' ') : '',
            $resteAPayer,
            $i['payment_proof_path'] ? 'Oui' : 'Non',
            trim(($i['acquisition_source'] ?? '') . ($i['acquisition_other'] ? ' — ' . $i['acquisition_other'] : '')),
            (int) $i['consent_image'] ? 'Oui' : 'Non',
            Inscription::STATUT_LABELS[$i['statut']] ?? $i['statut'],
            $i['created_at'],
        ];
    }

    private static function xlsx(array $headers, array $rows, string $filename, string $sheetTitle): never
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray($headers, null, 'A1');
        if ($rows) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index))
                ->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: no-store');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private static function csv(array $headers, array $rows, string $filename): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }
}
