<?php
/**
 * Migration runner (Yaman).
 * Runs database/migrations/*.sql files that are NOT yet recorded in table schema_migrations, in name order.
 * Rollback files (*_rollback.sql) are never run by the normal run.
 *
 * Usage, from the project folder on the server (SSH):
 *   php database/migrations/run_migrations.php                          run all pending migrations
 *   php database/migrations/run_migrations.php --dry-run                show pending migrations, change nothing
 *   php database/migrations/run_migrations.php --mark-applied=FILE.sql  record a file as applied, without running it
 *   php database/migrations/run_migrations.php --rollback=NAME --yes    undo one migration (DROPs its tables)
 *
 * Browser (logged-in admin only): runs pending migrations and shows the result. Rollback is CLI only.
 *
 * DB change made by this runner: creates the small table schema_migrations (name, applied_at).
 */

$is_cli = (php_sapi_name() === 'cli');
$dir = __DIR__;
$args = $is_cli ? array_slice($argv, 1) : [];
$dry_run = $is_cli && in_array('--dry-run', $args, true);
$confirmed = $is_cli && in_array('--yes', $args, true);
$rollback = null;
$mark = null;
$only = null;
foreach ($args as $a) {
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, strlen('--rollback='));
    if (strpos($a, '--mark-applied=') === 0) $mark = substr($a, strlen('--mark-applied='));
    if (strpos($a, '--only=') === 0) $only = preg_replace('/\.sql$/', '', basename(substr($a, strlen('--only='))));
}

if (!$is_cli) {
    session_start();
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Admin login required.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function say(string $msg): void
{
    echo $msg . "\n";
    flush();
}

/** Splits a .sql file into single statements (comment lines removed). */
function split_sql_statements(string $sql): array
{
    $lines = preg_split('/\r?\n/', $sql);
    $kept = [];
    foreach ($lines as $line) {
        if (!preg_match('/^\s*--/', $line)) $kept[] = $line;
    }
    $parts = preg_split('/;\s*(?=\r?\n|$)/', implode("\n", $kept));
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}

function table_exists(PDO $db, string $name): bool
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $st->execute([$name]);
    return (int)$st->fetchColumn() > 0;
}

function applied_names(PDO $db): array
{
    if (!table_exists($db, 'schema_migrations')) return [];
    return $db->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function ensure_tracking(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ---------- which files exist ----------
$all = array_filter(glob($dir . '/*.sql') ?: [], fn($f) => !preg_match('/_rollback\.sql$/', $f));
// Never run these. create_rbac_system.sql is old and not safe to re-run (it alters users).
// It is recorded as applied by hand, once, with --mark-applied, if ever needed.
$never_run = ['create_rbac_system.sql'];
$all = array_filter($all, fn($f) => !in_array(basename($f), $never_run, true));
sort($all, SORT_STRING);

// ---------- rollback (CLI only) ----------
if ($rollback !== null) {
    if (!$is_cli) exit("Rollback is CLI only.\n");
    // Accept the name with or without ".sql": --rollback=p8_customer_refunds or --rollback=p8_customer_refunds.sql
    $rb_name = preg_replace('/\.sql$/', '', basename($rollback));
    $rb_file = $dir . '/' . $rb_name . '_rollback.sql';
    if (!is_file($rb_file)) { say("Rollback file not found: " . basename($rb_file)); exit(1); }
    say("ROLLBACK " . basename($rb_file));
    say("This DROPs the tables listed in that file. Their data will be lost.");
    if (!$confirmed) { say("Not run. Add --yes to confirm: --rollback=" . $rb_name . " --yes"); exit(0); }
    require_once __DIR__ . '/../../config/database.php';
    foreach (split_sql_statements(file_get_contents($rb_file)) as $s) {
        $db->exec($s);
        say("  ok: " . substr(preg_replace('/\s+/', ' ', $s), 0, 80));
    }
    if (table_exists($db, 'schema_migrations')) {
        $st = $db->prepare("DELETE FROM schema_migrations WHERE name = ?");
        $st->execute([$rb_name . '.sql']);
    }
    say("Rollback done.");
    exit(0);
}

require_once __DIR__ . '/../../config/database.php';

// ---------- mark an existing file as applied (no SQL is run) ----------
if ($mark !== null) {
    if (!in_array(basename($mark), array_map('basename', $all), true)) { say("No such migration file: $mark"); exit(1); }
    ensure_tracking($db);
    $st = $db->prepare("INSERT IGNORE INTO schema_migrations (name) VALUES (?)");
    $st->execute([basename($mark)]);
    say("Marked as applied (nothing was run): " . basename($mark));
    exit(0);
}

$done = applied_names($db);
$pending = array_values(array_filter($all, fn($f) => !in_array(basename($f), $done, true)));
// --only=NAME: consider only that one file (name with or without .sql). Other pending files are left alone.
if ($only !== null) {
    $known = array_map(fn($f) => preg_replace('/\.sql$/', '', basename($f)), $all);
    if (!in_array($only, $known, true)) { say("No such migration file for --only: $only"); exit(1); }
    $pending = array_values(array_filter($pending, fn($f) => preg_replace('/\.sql$/', '', basename($f)) === $only));
    say("Only: $only (other pending migrations are NOT run)");
}
$dbname = $db->query('SELECT DATABASE()')->fetchColumn();

if ($dry_run) {
    say("DRY RUN on database '$dbname' (nothing is executed or recorded)");
    say("Already applied: " . (count($done) ? implode(', ', $done) : '(none recorded yet)'));
    say("Pending: " . count($pending));
    foreach ($pending as $f) {
        $stmts = split_sql_statements(file_get_contents($f));
        say("- " . basename($f) . " : " . count($stmts) . " statement(s)");
        foreach ($stmts as $s) say("    " . substr(preg_replace('/\s+/', ' ', $s), 0, 110));
    }
    exit(0);
}

say("Running pending migrations on database '$dbname'");
if (empty($pending)) { say("Nothing pending. Database is up to date."); exit(0); }

ensure_tracking($db);
$failed = false;
foreach ($pending as $f) {
    $name = basename($f);
    say("== " . $name);
    try {
        $stmts = split_sql_statements(file_get_contents($f));
        foreach ($stmts as $s) $db->exec($s);
        $st = $db->prepare("INSERT INTO schema_migrations (name) VALUES (?)");
        $st->execute([$name]);
        say("   OK (" . count($stmts) . " statement(s))");
    } catch (PDOException $e) {
        say("   FAILED: " . $e->getMessage());
        say("   Stopped here. Nothing after this file ran. This file may be partly applied: check it before retrying.");
        $failed = true;
        break;
    }
}
say($failed ? "Stopped with an error." : "All pending migrations finished.");
exit($failed ? 1 : 0);
