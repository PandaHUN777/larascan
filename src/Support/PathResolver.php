<?php

declare(strict_types=1);

namespace Larascan\Support;

/**
 * Centralized path and Laravel environment resolver to ensure DRY compliance.
 */
final class PathResolver
{
    /**
     * Get active Laravel application instance if available.
     */
    public static function container(): ?object
    {
        if (! function_exists('app')) {
            return null;
        }

        try {
            $app = app();
            return is_object($app) ? $app : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Determine if Laravel's base_path environment is available.
     */
    public static function hasBasePath(): bool
    {
        return function_exists('base_path')
            && ($app = self::container()) !== null
            && method_exists($app, 'basePath');
    }

    /**
     * Resolve path relative to Laravel base path or fallback directory.
     */
    public static function basePath(string $path = ''): string
    {
        if (self::hasBasePath()) {
            return base_path($path);
        }

        $base = getcwd() ?: '.';

        return $path === '' ? $base : rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Resolve target directory or file to scan based on argument and configuration.
     *
     * @param  array<string>|null  $configuredPaths
     */
    public static function resolveScanPath(?string $argumentPath = null, ?array $configuredPaths = null): string
    {
        if ($argumentPath !== null && trim($argumentPath) !== '') {
            return $argumentPath;
        }

        foreach ($configuredPaths ?? [] as $configured) {
            $candidate = file_exists($configured) ? $configured : self::basePath($configured);
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $defaultApp = self::basePath('app');

        return is_dir($defaultApp) ? $defaultApp : self::basePath();
    }

    /**
     * Safely read package-namespaced configuration value.
     */
    public static function packageConfig(string $key, mixed $default = null): mixed
    {
        return self::config(\Larascan\LarascanServiceProvider::CONFIG_KEY . '.' . $key, $default);
    }

    /**
     * Safely read configuration value if config repository is bound.
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $app = self::container();
        if ($app !== null && function_exists('config') && method_exists($app, 'bound') && $app->bound('config')) {
            return config($key, $default);
        }

        return $default;
    }
}
