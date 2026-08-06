<?php
/**
 * Append everything below to your existing includes/functions.php
 * (just the function bodies — don't duplicate the opening <?php tag).
 */

function grade_for(float $total): string
{
    if ($total >= 70) return 'A';
    if ($total >= 60) return 'B';
    if ($total >= 50) return 'C';
    if ($total >= 45) return 'D';
    if ($total >= 40) return 'E';
    return 'F';
}

function grade_point(string $grade): float
{
    $points = ['A' => 5.0, 'B' => 4.0, 'C' => 3.0, 'D' => 2.0, 'E' => 1.0, 'F' => 0.0];
    return $points[$grade] ?? 0.0;
}

function has_any_role(array $roles): bool
{
    $user = current_user();
    return isset($user['role']) && in_array($user['role'], $roles, true);
}

function require_any_role(array $roles): void
{
    require_login();
    if (!has_any_role($roles)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

/**
 * Tiny cache helper. Uses APCu when the extension is available
 * (common on shared hosts and most PHP-FPM setups); silently
 * falls back to "no cache" everywhere else, so nothing breaks
 * if APCu isn't installed.
 */
function result_cache_get(string $key)
{
    if (!function_exists('apcu_fetch')) return false;
    return apcu_fetch($key);
}

function result_cache_set(string $key, $value, int $ttlSeconds): void
{
    if (!function_exists('apcu_store')) return;
    apcu_store($key, $value, $ttlSeconds);
}

function result_cache_clear(string $key): void
{
    if (!function_exists('apcu_delete')) return;
    apcu_delete($key);
}
