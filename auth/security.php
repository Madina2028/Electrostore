<?php

function secureSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrf(): bool
{
    $provided = $_POST['csrf_token'] ?? '';
    return is_string($provided)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $provided);
}

function rejectInvalidCsrf(): void
{
    if (!validateCsrf()) {
        http_response_code(403);
        exit('Invalid or missing security token.');
    }
}

function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function ensureLoginAttemptsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier_hash CHAR(64) NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier_time (identifier_hash, attempted_at),
            INDEX idx_ip_time (ip_hash, attempted_at)
        ) ENGINE=InnoDB"
    );
}

function loginIsLocked(PDO $pdo, string $identifier): bool
{
    ensureLoginAttemptsTable($pdo);
    $identifierHash = hash('sha256', strtolower(trim($identifier)));
    $ipHash = hash('sha256', clientIp());

    $stmt = $pdo->prepare(
        "SELECT 1 FROM login_attempts
         WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND (identifier_hash = ? OR ip_hash = ?)
         LIMIT 5"
    );
    $stmt->execute([$identifierHash, $ipHash]);
    return count($stmt->fetchAll(PDO::FETCH_COLUMN)) >= 5;
}

function recordLoginFailure(PDO $pdo, string $identifier): void
{
    ensureLoginAttemptsTable($pdo);
    $identifierHash = hash('sha256', strtolower(trim($identifier)));
    $ipHash = hash('sha256', clientIp());

    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (identifier_hash, ip_hash) VALUES (?, ?)"
    );
    $stmt->execute([$identifierHash, $ipHash]);
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

function clearLoginFailures(PDO $pdo, string $identifier): void
{
    ensureLoginAttemptsTable($pdo);
    $identifierHash = hash('sha256', strtolower(trim($identifier)));
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier_hash = ?");
    $stmt->execute([$identifierHash]);
}
