<?php

namespace App\Core;

final class View
{
    /** Rend une vue admin dans le layout. */
    public static function render(string $template, array $data = []): never
    {
        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__, 2) . '/views/admin/' . $template . '.php';
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require dirname(__DIR__, 2) . '/views/admin/layout.php';
        exit;
    }

    /** Rend une vue sans layout (login, pages autonomes). */
    public static function renderBare(string $template, array $data = []): never
    {
        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/admin/' . $template . '.php';
        exit;
    }
}
