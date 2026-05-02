<?php

function envValue(string $key, $default = null) {
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    $normalized = strtolower($value);
    if ($normalized === 'true') {
        return true;
    }

    if ($normalized === 'false') {
        return false;
    }

    if ($normalized === 'null') {
        return null;
    }

    return $value;
}

function detectAppUrl(): string {
    $https = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $scheme = ($forwardedProto === 'https' || $https === 'on') ? 'https' : 'http';

    $host = $_SERVER['HTTP_X_FORWARDED_HOST']
        ?? $_SERVER['HTTP_HOST']
        ?? $_SERVER['SERVER_NAME']
        ?? 'localhost';

    return $scheme . '://' . $host;
}
