<?php

namespace App\Services;

use App\Core\Env;

/** Upload sécurisé des preuves de paiement (JPEG/PNG/PDF, taille limitée). */
final class Uploader
{
    private const ALLOWED = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * @return array{path:string, mime:string, original:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeProof(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi du fichier (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Fichier trop volumineux (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Format non accepté. Envoyez une image JPEG/PNG ou un PDF.');
        }

        $dir = dirname(__DIR__, 2) . '/storage/proofs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer le fichier.");
        }

        return [
            'path'     => $name,
            'mime'     => $mime,
            'original' => mb_substr((string) ($file['name'] ?? ''), 0, 180),
        ];
    }

    public static function proofFullPath(string $storedName): string
    {
        // basename() bloque toute traversée de répertoire
        return dirname(__DIR__, 2) . '/storage/proofs/' . basename($storedName);
    }

    private const AFFICHE_ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Affiche de formation : stockage PUBLIC dans public/uploads/affiches/.
     *
     * @return array{path:string, mime:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeAffiche(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi de l'affiche (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Affiche trop volumineuse (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::AFFICHE_ALLOWED[$mime])) {
            throw new \RuntimeException('Format d\'affiche non accepté. Envoyez une image JPEG, PNG ou WebP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/affiches';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::AFFICHE_ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer l'affiche.");
        }

        return ['path' => $name, 'mime' => $mime];
    }

    /**
     * CV de candidature (PDF uniquement) : stockage protégé dans storage/cv/,
     * servi via /admin/emplois/candidatures/{id}/cv (session requise).
     *
     * @return array{path:string, mime:string, original:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeCv(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi du CV (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('CV trop volumineux (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if ($mime !== 'application/pdf') {
            throw new \RuntimeException('Format de CV non accepté. Envoyez un fichier PDF.');
        }

        $dir = dirname(__DIR__, 2) . '/storage/cv';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer le CV.");
        }

        return [
            'path'     => $name,
            'mime'     => $mime,
            'original' => mb_substr((string) ($file['name'] ?? ''), 0, 180),
        ];
    }

    public static function cvFullPath(string $storedName): string
    {
        // basename() bloque toute traversée de répertoire
        return dirname(__DIR__, 2) . '/storage/cv/' . basename($storedName);
    }

    /** Supprime un CV (silencieux si le fichier n'existe plus). */
    public static function deleteCv(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = self::cvFullPath($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Affiche d'offre d'emploi : stockage PUBLIC dans public/uploads/offres/
     * (même pattern que les affiches de formation, JPEG/PNG/WebP).
     *
     * @return array{path:string, mime:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeOffreImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi de l'affiche (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Affiche trop volumineuse (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::AFFICHE_ALLOWED[$mime])) {
            throw new \RuntimeException('Format d\'affiche non accepté. Envoyez une image JPEG, PNG ou WebP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/offres';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::AFFICHE_ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer l'affiche.");
        }

        return ['path' => $name, 'mime' => $mime];
    }

    /** Supprime une affiche d'offre (silencieux si le fichier n'existe plus). */
    public static function deleteOffreImage(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/offres/' . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Supprime une affiche remplacée (silencieux si le fichier n'existe plus). */
    public static function deleteAffiche(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/affiches/' . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Affiche d'un projet incubé : stockage PUBLIC dans public/uploads/projets/
     * (même pattern que les affiches d'offres, JPEG/PNG/WebP).
     *
     * @return array{path:string, mime:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeProjetImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi de l'affiche (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Affiche trop volumineuse (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::AFFICHE_ALLOWED[$mime])) {
            throw new \RuntimeException('Format d\'affiche non accepté. Envoyez une image JPEG, PNG ou WebP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/projets';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::AFFICHE_ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer l'affiche.");
        }

        return ['path' => $name, 'mime' => $mime];
    }

    public static function deleteProjetImage(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/projets/' . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Pitch deck PDF d'un dépôt de projet : stockage protégé dans storage/projets/,
     * servi via /admin/projets/depots/{id}/fichier (session requise).
     *
     * @return array{path:string, mime:string, original:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storePitch(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi du fichier (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Fichier trop volumineux (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if ($mime !== 'application/pdf') {
            throw new \RuntimeException('Format non accepté. Envoyez un pitch deck au format PDF.');
        }

        $dir = dirname(__DIR__, 2) . '/storage/projets';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer le fichier.");
        }

        return [
            'path'     => $name,
            'mime'     => $mime,
            'original' => mb_substr((string) ($file['name'] ?? ''), 0, 180),
        ];
    }

    public static function pitchFullPath(string $storedName): string
    {
        return dirname(__DIR__, 2) . '/storage/projets/' . basename($storedName);
    }

    public static function deletePitch(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = self::pitchFullPath($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Photo de témoignage : stockage PUBLIC dans public/uploads/temoignages/
     * (JPEG/PNG/WebP, 2 Mo max — portrait, pas une affiche).
     *
     * @return array{path:string, mime:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeTemoignage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi de la photo (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = 2 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Photo trop volumineuse (max 2 Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::AFFICHE_ALLOWED[$mime])) {
            throw new \RuntimeException('Format non accepté. Envoyez une image JPEG, PNG ou WebP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/temoignages';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::AFFICHE_ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer la photo.");
        }

        return ['path' => $name, 'mime' => $mime];
    }

    public static function deleteTemoignage(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/temoignages/' . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Photo de galerie de formation : stockage PUBLIC dans public/uploads/galeries/
     * (JPEG/PNG/WebP).
     *
     * @return array{path:string, mime:string}
     * @throws \RuntimeException si le fichier est invalide
     */
    public static function storeGalerieImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Échec de l'envoi de la photo (code " . ($file['error'] ?? '?') . ').');
        }

        $maxBytes = Env::int('MAX_UPLOAD_MB', 5) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('Photo trop volumineuse (max ' . Env::int('MAX_UPLOAD_MB', 5) . ' Mo).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::AFFICHE_ALLOWED[$mime])) {
            throw new \RuntimeException('Format non accepté. Envoyez une image JPEG, PNG ou WebP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/galeries';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . self::AFFICHE_ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException("Impossible d'enregistrer la photo.");
        }

        return ['path' => $name, 'mime' => $mime];
    }

    public static function deleteGalerieImage(?string $storedName): void
    {
        if (!$storedName) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/galeries/' . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
