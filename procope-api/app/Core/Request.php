<?php

namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    /** Paramètres extraits du pattern de route ({id}, ...) */
    public array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Support sous-dossier : retire le chemin du script si présent
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $this->path = '/' . trim($uri, '/');
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Valeur tableau (checkboxes multiples). */
    public function inputArray(string $key): array
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /**
     * Fichiers multiples (input name="key[]") : normalise la structure
     * $_FILES de PHP en liste de tableaux fichier simples, en ignorant
     * les entrées vides (UPLOAD_ERR_NO_FILE).
     */
    public function files(string $key): array
    {
        $batch = $_FILES[$key] ?? null;
        if (!$batch || !is_array($batch['name'] ?? null)) {
            $single = $this->file($key);
            return $single ? [$single] : [];
        }
        $files = [];
        foreach (array_keys($batch['name']) as $i) {
            if (($batch['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $batch['name'][$i],
                'type'     => $batch['type'][$i] ?? '',
                'tmp_name' => $batch['tmp_name'][$i] ?? '',
                'error'    => $batch['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $batch['size'][$i] ?? 0,
            ];
        }
        return $files;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    }

    public function origin(): ?string
    {
        return $_SERVER['HTTP_ORIGIN'] ?? null;
    }
}
