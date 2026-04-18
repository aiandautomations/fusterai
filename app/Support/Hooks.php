<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class Hooks
{
    protected static array $actions = [];

    protected static array $filters = [];

    /** Register a callback for an action hook */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        static::$actions[$hook][$priority][] = $callback;
    }

    /**
     * Fire all callbacks registered for an action.
     *
     * Each listener is isolated: an exception in one never stops the others.
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        if (empty(static::$actions[$hook])) {
            return;
        }

        ksort(static::$actions[$hook]);
        foreach (static::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $callback(...$args);
                } catch (\Throwable $e) {
                    Log::error("Hook action [{$hook}] threw an exception: ".$e->getMessage(), [
                        'hook' => $hook,
                        'exception' => $e,
                    ]);
                }
            }
        }
    }

    /** Register a callback for a filter hook */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        static::$filters[$hook][$priority][] = $callback;
    }

    /**
     * Apply all filter callbacks to a value.
     *
     * Each filter is isolated: an exception keeps the last good value and
     * continues with the remaining filters.
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty(static::$filters[$hook])) {
            return $value;
        }

        ksort(static::$filters[$hook]);
        foreach (static::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = $callback($value, ...$args);
                } catch (\Throwable $e) {
                    Log::error("Hook filter [{$hook}] threw an exception: ".$e->getMessage(), [
                        'hook' => $hook,
                        'exception' => $e,
                    ]);
                    // Keep the last good value and continue
                }
            }
        }

        return $value;
    }

    /** Clear all hooks (useful in tests) */
    public static function reset(): void
    {
        static::$actions = [];
        static::$filters = [];
    }
}
