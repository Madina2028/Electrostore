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
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://stackpath.bootstrapcdn.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function authRedirectTarget(): ?string
{
    $candidate = $_POST['redirect'] ?? $_GET['redirect'] ?? null;
    if (!is_string($candidate) || $candidate === '' || preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
        return null;
    }

    $parts = parse_url($candidate);
    if (
        $parts === false
        || isset($parts['scheme'])
        || isset($parts['host'])
        || !isset($parts['path'])
        || $parts['path'] === ''
        || $parts['path'][0] !== '/'
        || substr($parts['path'], 0, 2) === '//'
        || strpos($parts['path'], '\\') !== false
        || preg_match('/%(?:2f|5c)/i', $parts['path'])
    ) {
        return null;
    }

    $scriptPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);
    $basePath = dirname(is_string($scriptPath) ? $scriptPath : '');
    if (basename($basePath) === 'auth') {
        $basePath = dirname($basePath);
    }
    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    $path = rawurldecode($parts['path']);
    $segments = explode('/', $path);

    if (
        preg_match('/[\x00-\x1F\x7F]/', $path)
        || strpos($path, '\\') !== false
        || substr($path, 0, 2) === '//'
        || in_array('.', $segments, true)
        || in_array('..', $segments, true)
        || ($basePath !== '' && $path !== $basePath && strpos($path, $basePath . '/') !== 0)
        || strpos($path, $basePath . '/auth/login.php') === 0
        || strpos($path, $basePath . '/auth/signup.php') === 0
    ) {
        return null;
    }

    return $candidate;
}

function authLoginUrlForCurrentRequest(): string
{
    $scriptPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);
    $basePath = dirname(is_string($scriptPath) ? $scriptPath : '');
    if (basename($basePath) === 'auth') {
        $basePath = dirname($basePath);
    }

    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    return $basePath . '/auth/login.php?redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/');
}

function authRedirectQuery(): string
{
    return '?redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/');
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

function loginIsLocked(PDO $pdo, string $identifier): bool
{
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
    $identifierHash = hash('sha256', strtolower(trim($identifier)));
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier_hash = ?");
    $stmt->execute([$identifierHash]);
}
