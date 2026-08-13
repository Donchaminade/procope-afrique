<?php

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array, handler:array, middleware:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $keys = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => '#^' . $regex . '$#',
            'keys'       => $keys,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $p, array $h, array $mw = []): void    { $this->add('GET', $p, $h, $mw); }
    public function post(string $p, array $h, array $mw = []): void   { $this->add('POST', $p, $h, $mw); }

    public function dispatch(Request $request): void
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            array_shift($matches);
            $request->params = array_combine($route['keys'], $matches) ?: [];

            foreach ($route['middleware'] as $middlewareClass) {
                (new $middlewareClass())->handle($request);
            }

            [$class, $action] = $route['handler'];
            (new $class())->$action($request);
            return;
        }

        if ($pathMatched) {
            Response::abort(405, 'Méthode non autorisée');
        }
        if (str_starts_with($request->path, '/api/')) {
            Response::json(['error' => 'Route introuvable'], 404);
        }
        Response::abort(404, 'Page introuvable');
    }
}
