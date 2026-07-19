<?php

/**
 * Return the normalized path used to select the active global navigation item.
 */
function tt_nav_request_path()
{
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $phpSelf = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '/';
    $path = parse_url($requestUri !== '' ? $requestUri : $phpSelf, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
}

/**
 * Return the hostname without a port so the shared navbar also works when it is
 * included by the Wiki and Forum applications.
 */
function tt_nav_request_host()
{
    $host = strtolower(trim(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : ''));
    $separator = strpos($host, ':');

    return $separator === false ? $host : substr($host, 0, $separator);
}

/**
 * Keep Ops links valid when this shared partial is rendered on another
 * TrainTote subdomain. Relative links remain convenient for local development.
 */
function tt_nav_ops_href($path, $host)
{
    $path = '/' . ltrim((string) $path, '/');
    $host = strtolower((string) $host);

    if ($host === 'wiki.traintote.com' || $host === 'forum.traintote.com') {
        return 'https://ops.traintote.com' . $path;
    }

    return $path;
}

/**
 * Determine which one (and only one) global navigation item is active.
 */
function tt_nav_is_active($item, $path, $host)
{
    $path = '/' . ltrim((string) $path, '/');
    $host = strtolower((string) $host);

    if ($host === 'wiki.traintote.com') {
        return $item === 'wiki';
    }

    if ($host === 'forum.traintote.com') {
        return $item === 'forum';
    }

    switch ($item) {
        case 'dashboard':
            return $path === '/' || $path === '/dashboard.php';
        case 'equipment':
            return strpos($path, '/equipment/') === 0 && $path !== '/equipment/status.php';
        case 'car_status':
            return $path === '/equipment/status.php';
        case 'industries':
            return strpos($path, '/industries/') === 0;
        case 'waybills':
            return strpos($path, '/waybills/') === 0;
        case 'operations':
            return strpos($path, '/operations/') === 0;
        case 'ai':
            return strpos($path, '/ai/') === 0;
        default:
            return false;
    }
}
