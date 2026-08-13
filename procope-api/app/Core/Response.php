<?php

namespace App\Core;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
            . '<body style="font-family:sans-serif;padding:3rem;text-align:center">'
            . '<h1>' . $status . '</h1><p>' . htmlspecialchars($message ?: 'Accès refusé') . '</p></body>';
        exit;
    }
}
