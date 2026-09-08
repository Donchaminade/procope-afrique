<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\ProjectApplication;

/**
 * Exports PDF des candidatures d'emploi via dompdf (composer require
 * dompdf/dompdf — vendor/ gitignoré). Le HTML est généré ici avec un CSS
 * inline simple : logo PROCOPE, titre, tableau, date d'édition.
 */
final class Pdf
{
    /**
     * Génère et streame le PDF d'une liste de candidatures.
     * $rows : lignes JobApplication::allFiltered ; $title : ex. « Candidats retenus — Offre X ».
     */
    public static function downloadApplications(array $rows, string $title): never
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            \App\Core\Response::abort(500, 'Export PDF indisponible : dompdf n\'est pas installé (composer install).');
        }

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(self::applicationsHtml($rows, $title), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'candidatures-procope-' . date('Ymd-His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $dompdf->output();
        exit;
    }

    private static function applicationsHtml(array $rows, string $title): string
    {
        $logo = self::logoDataUri();
        $count = count($rows);

        $body = '';
        foreach ($rows as $a) {
            $statut = JobApplication::STATUT_LABELS[$a['statut']] ?? $a['statut'];
            $message = trim((string) preg_replace('/\s+/', ' ', (string) ($a['message'] ?? '')));
            if (mb_strlen($message) > 120) {
                $message = rtrim(mb_substr($message, 0, 120)) . '…';
            }
            $body .= '<tr>'
                . '<td>' . e($a['offer_title']) . '</td>'
                . '<td><strong>' . e($a['full_name']) . '</strong></td>'
                . '<td>' . e($a['email']) . '</td>'
                . '<td>' . e($a['phone'] ?: '—') . '</td>'
                . '<td>' . e($statut) . '</td>'
                . '<td>' . e(format_datetime($a['created_at'])) . '</td>'
                . '<td>' . e($message ?: '—') . '</td>'
                . '</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:18px;">'
                . 'Aucune candidature ne correspond aux filtres.</td></tr>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 24px; }
            .head { border-bottom: 3px solid #06A3DA; padding-bottom: 10px; margin-bottom: 14px; }
            .head img { height: 42px; }
            h1 { font-size: 16px; color: #062a4d; margin: 8px 0 2px; }
            .meta { color: #64748b; font-size: 9px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #062a4d; color: #fff; text-align: left; padding: 6px 7px; font-size: 9px; }
            td { border-bottom: 1px solid #e2e8f0; padding: 5px 7px; vertical-align: top; }
            tr:nth-child(even) td { background: #f8fafc; }
            .foot { margin-top: 14px; color: #94a3b8; font-size: 8px; text-align: right; }
        </style></head><body>
            <div class="head">'
                . ($logo ? '<img src="' . $logo . '" alt="PROCOPE Afrique">' : '')
                . '<h1>' . e($title) . '</h1>
                <div class="meta">' . $count . ' candidature' . ($count > 1 ? 's' : '')
                    . ' — Édité le ' . e(date('d/m/Y à H\hi')) . '</div>
            </div>
            <table>
                <thead><tr>
                    <th>Offre</th><th>Nom et prénom</th><th>Email</th><th>Téléphone</th>
                    <th>Statut</th><th>Date</th><th>Message (extrait)</th>
                </tr></thead>
                <tbody>' . $body . '</tbody>
            </table>
            <div class="foot">PROCOPE Afrique — procopeafrique@gmail.com — +228 96 45 76 95 — Lomé, Togo</div>
        </body></html>';
    }

    public static function downloadProjectApplications(array $rows, string $title): never
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            \App\Core\Response::abort(500, 'Export PDF indisponible : dompdf n\'est pas installé (composer install).');
        }

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(self::projectApplicationsHtml($rows, $title), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'depots-projets-procope-' . date('Ymd-His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $dompdf->output();
        exit;
    }

    private static function projectApplicationsHtml(array $rows, string $title): string
    {
        $logo = self::logoDataUri();
        $count = count($rows);

        $body = '';
        foreach ($rows as $a) {
            $statut = ProjectApplication::STATUT_LABELS[$a['statut']] ?? $a['statut'];
            $pitch = trim((string) preg_replace('/\s+/', ' ', (string) ($a['pitch'] ?? $a['message'] ?? '')));
            if (mb_strlen($pitch) > 120) {
                $pitch = rtrim(mb_substr($pitch, 0, 120)) . '…';
            }
            $body .= '<tr>'
                . '<td>' . e($a['call_title'] ?: 'Spontané') . '</td>'
                . '<td>' . e($a['project_name'] ?: '—') . '</td>'
                . '<td><strong>' . e($a['full_name']) . '</strong></td>'
                . '<td>' . e($a['email']) . '</td>'
                . '<td>' . e($a['phone'] ?: '—') . '</td>'
                . '<td>' . e($statut) . '</td>'
                . '<td>' . e(format_datetime($a['created_at'])) . '</td>'
                . '<td>' . e($pitch ?: '—') . '</td>'
                . '</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:18px;">'
                . 'Aucun dépôt ne correspond aux filtres.</td></tr>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 24px; }
            .head { border-bottom: 3px solid #06A3DA; padding-bottom: 10px; margin-bottom: 14px; }
            .head img { height: 42px; }
            h1 { font-size: 16px; color: #062a4d; margin: 8px 0 2px; }
            .meta { color: #64748b; font-size: 9px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #062a4d; color: #fff; text-align: left; padding: 6px 7px; font-size: 9px; }
            td { border-bottom: 1px solid #e2e8f0; padding: 5px 7px; vertical-align: top; }
            tr:nth-child(even) td { background: #f8fafc; }
            .foot { margin-top: 14px; color: #94a3b8; font-size: 8px; text-align: right; }
        </style></head><body>
            <div class="head">'
                . ($logo ? '<img src="' . $logo . '" alt="PROCOPE Afrique">' : '')
                . '<h1>' . e($title) . '</h1>
                <div class="meta">' . $count . ' dépôt' . ($count > 1 ? 's' : '')
                    . ' — Édité le ' . e(date('d/m/Y à H\hi')) . '</div>
            </div>
            <table>
                <thead><tr>
                    <th>Origine</th><th>Projet porté</th><th>Nom et prénom</th><th>Email</th>
                    <th>Téléphone</th><th>Statut</th><th>Date</th><th>Pitch (extrait)</th>
                </tr></thead>
                <tbody>' . $body . '</tbody>
            </table>
            <div class="foot">PROCOPE Afrique — procopeafrique@gmail.com — +228 96 45 76 95 — Lomé, Togo</div>
        </body></html>';
    }

    /** Logo PROCOPE embarqué en data URI (dompdf sans accès distant). */
    private static function logoDataUri(): ?string
    {
        $path = dirname(__DIR__, 2) . '/public/assets/logo.png';
        if (!is_file($path)) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }
}
