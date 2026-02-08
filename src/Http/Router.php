<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, handler: callable}>
     */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * @return array{handler: callable, params: array<string, string>}|null
     */
    public function match(Request $request): ?array
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = rtrim($route['pattern'], '/') ?: '/';
            $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
            if ($regex === null) {
                continue;
            }

            $regex = '#^' . $regex . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }
}
