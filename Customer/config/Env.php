<?php
declare(strict_types=1);

/**
 * Env — Lightweight, dependency-free .env loader.
 *
 * Loads key/value pairs from /home/sadakalo/App/.env (outside the web root)
 * so that no credential ever lives inside public_html.
 *
 * Supported syntax:
 *   KEY=value
 *   KEY="quoted value"
 *   # comment lines
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    private static bool $loaded = false;

    /**
     * Load the .env file once per request.
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        // public_html/Customer/config -> up 3 levels = /home/sadakalo
        $path = $path ?? dirname(__DIR__, 3) . '/App/.env';

        if (!is_readable($path)) {
            return; // Missing .env is handled by callers via defaults.
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes.
            if (strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"'))
                 || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                self::$vars[$key] = $value;
            }
        }
    }

    /**
     * Get a value by key. Checks aliases so existing .env naming keeps working.
     *
     * @param string|string[] $keys  A key, or list of accepted key aliases.
     */
    public static function get(string|array $keys, string $default = ''): string
    {
        self::load();
        foreach ((array)$keys as $key) {
            if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
                return self::$vars[$key];
            }
            $fromServer = getenv($key);
            if ($fromServer !== false && $fromServer !== '') {
                return $fromServer;
            }
        }
        return $default;
    }
}
