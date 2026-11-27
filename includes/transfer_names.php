<?php
/**
 * Transfer display-name helpers (code-only fix for duplicated bank names).
 * When a bank account's bank_name and account_name are the same text, show it once.
 * Read-only: does not change any stored data.
 */

/** Display name for a bank account: "bank - account", or just "bank" when they are the same. */
function bankDisplayName(?string $bank_name, ?string $account_name): string
{
    $bank = trim((string)$bank_name);
    $account = trim((string)$account_name);
    if ($account === '' || $account === $bank) {
        return $bank;
    }
    return $bank . ' - ' . $account;
}

/** Collapses a name that was saved twice ("X - X" or "X - Y - X - Y") into one copy. */
function dedupeDisplayName(string $name): string
{
    $parts = explode(' - ', $name);
    $n = count($parts);
    if ($n >= 2 && $n % 2 === 0) {
        $half = $n / 2;
        if (array_slice($parts, 0, $half) === array_slice($parts, $half)) {
            return implode(' - ', array_slice($parts, 0, $half));
        }
    }
    return $name;
}
