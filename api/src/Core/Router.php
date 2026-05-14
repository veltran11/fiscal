<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $this->toPattern($path),
            'handler' => $handler,
        ];
    }

    public function get(string $path, callable|array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, callable|array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, callable|array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    private function toPattern(string $path): string
    {
        $escaped = preg_replace('/\//', '\\/', $path);
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $escaped);
        return '/^' . $pattern . '$/';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['pattern'], $path, $matches)) continue;

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->call($route['handler'], $request, $params);
            return;
        }

        Response::notFound("Ruta {$method} {$path} no encontrada");
    }

    private function call(callable|array $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            $handler($request, $params);
            return;
        }

        [$class, $method] = $handler;
        (new $class())->$method($request, $params);
    }
}
