<?php

declare(strict_types=1);

namespace BacklinkChecker\Support;

final class ViewRenderer
{
    public function __construct(private readonly string $templatesPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = rtrim($this->templatesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template . '.php';
        if (!is_file($file)) {
            return 'Template not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}
