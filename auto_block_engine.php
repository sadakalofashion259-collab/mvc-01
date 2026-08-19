<?php
declare(strict_types=1);

/**
 * =====================================================================
 * Auto-Block Engine — SADA KALO FASHION
 * File : admin/auto_block_engine.php
 * =====================================================================
 *
 * Lazy-cron mechanism — called once per admin page load (no cron daemon needed).
 *
 * Behaviour
 * ─────────
 *  Step 1  Expire manual timed-blocks whose block_end timestamp has passed.
 *  Step 2  Load all active schedule windows from auto_block_schedules.
 *  Step 3  Decide: is the current server time inside any blocking window?
 *            • Supports normal windows   (e.g. 09:00 → 18:00, same day)
 *            • Supports overnight windows (e.g. 22:00 → 06:00, spans midnight)
 *  Step 4  Apply decision:
 *            INSIDE  window → SET status='blocked', auto_blocked=1
 *                              for every non-admin user currently active.
 *            OUTSIDE window → SET status='active',  auto_blocked=0
 *                              ONLY for users where auto_blocked=1.
 *                              Manually blocked users (auto_blocked=0) are
 *                              NEVER touched by this engine.
 *
 * Usage
 * ─────
 *  require_once __DIR__ . '/auto_block_engine.php';
 *  runAutoBlockEngine($conn);
 *
 * @param PDO $conn  Active PDO database connection
 */
function runAutoBlockEngine(PDO $conn): void
{
    // ── Step 1 : Expire manual timed-blocks ──────────────────────────────────
    try {
        $conn->exec("
            UPDATE `users`
            SET    `status`    = 'active',
                   `block_end` = NULL
            WHERE  `status`        = 'blocked'
              AND  `auto_blocked`  = 0
              AND  `block_end`    IS NOT NULL
              AND  `block_end`    <= NOW()
              AND  `role`         != 'admin'
        ");
    } catch (Throwable $e) {
        error_log('[AutoBlock] Timed-block expiry failed: ' . $e->getMessage());
    }

    // ── Step 2 : Load active schedule windows ────────────────────────────────
    try {
        $stmt      = $conn->query("
            SELECT `day_of_week`, `start_time`, `end_time`
            FROM   `auto_block_schedules`
            WHERE  `is_active` = 1
        ");
        $schedules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('[AutoBlock] Schedule fetch failed: ' . $e->getMessage());
        return; // Table may not exist yet — fail silently
    }

    // No schedules at all → restore any stale auto-blocked users and exit
    if (empty($schedules)) {
        try {
            $conn->exec("
                UPDATE `users`
                SET    `status`       = 'active',
                       `auto_blocked` = 0
                WHERE  `auto_blocked`  = 1
                  AND  `role`         != 'admin'
            ");
        } catch (Throwable) { /* non-fatal */ }
        return;
    }

    // ── Step 3 : Determine if we are inside any blocking window ──────────────
    $now          = new DateTimeImmutable('now');
    $todayDow     = (int) $now->format('w');        // 0 = Sunday … 6 = Saturday
    $yesterdayDow = ($todayDow + 6) % 7;            // wraps correctly (0→6)
    $currentSec   = (int) $now->format('H') * 3600
                  + (int) $now->format('i') * 60
                  + (int) $now->format('s');

    $inWindow = false;

    foreach ($schedules as $sch) {
        $dow = (int) $sch['day_of_week'];

        // Parse HH:MM:SS (TIME column returns string, may include seconds)
        $sp       = explode(':', (string) $sch['start_time']);
        $ep       = explode(':', (string) $sch['end_time']);
        $startSec = ((int)($sp[0] ?? 0)) * 3600 + ((int)($sp[1] ?? 0)) * 60;
        $endSec   = ((int)($ep[0] ?? 0)) * 3600 + ((int)($ep[1] ?? 0)) * 60;

        if ($startSec === $endSec) {
            continue; // zero-length window — meaningless, skip
        }

        if ($startSec < $endSec) {
            // ── Normal same-day window (e.g. 09:00 → 18:00) ─────────────────
            if ($dow === $todayDow
                && $currentSec >= $startSec
                && $currentSec <  $endSec) {
                $inWindow = true;
                break;
            }
        } else {
            // ── Overnight window (e.g. 22:00 → 06:00) ───────────────────────

            // Late-night portion: today after start_time
            if ($dow === $todayDow && $currentSec >= $startSec) {
                $inWindow = true;
                break;
            }
            // Early-morning portion: next calendar day, before end_time
            // The schedule's day_of_week is yesterday relative to now
            if ($dow === $yesterdayDow && $currentSec < $endSec) {
                $inWindow = true;
                break;
            }
        }
    }

    // ── Step 4 : Apply or lift the auto-block ────────────────────────────────
    try {
        if ($inWindow) {
            // Block all non-admin ACTIVE users that are not yet auto-blocked
            $conn->exec("
                UPDATE `users`
                SET    `status`       = 'blocked',
                       `auto_blocked` = 1
                WHERE  `role`         != 'admin'
                  AND  `status`       = 'active'
                  AND  `auto_blocked` = 0
            ");
        } else {
            // Restore ONLY auto-blocked users — never touch manual blocks
            $conn->exec("
                UPDATE `users`
                SET    `status`       = 'active',
                       `auto_blocked` = 0
                WHERE  `auto_blocked`  = 1
                  AND  `role`         != 'admin'
            ");
        }
    } catch (Throwable $e) {
        error_log('[AutoBlock] Block/restore update failed: ' . $e->getMessage());
    }
}
