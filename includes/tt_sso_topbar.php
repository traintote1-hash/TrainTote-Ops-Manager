<?php

/*
TrainTote shared top navigation + theme injector for Forum and Wiki.

This helper is intentionally limited to:
- deciding whether the current request is an HTML page request;
- adding the shared TrainTote navbar and CSS to the final HTML buffer;
- applying Forum/Wiki body classes used by the shared CSS;
- removing the exact old plain mini-header fragment while the real phpBB and
  MediaWiki source files are being cleaned up.
*/

if (!function_exists('tt_sso_request_wants_html')) {
    function tt_sso_request_wants_html()
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $method =
            strtoupper(
                (string)(
                    $_SERVER['REQUEST_METHOD']
                    ?? 'GET'
                )
            );

        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        $accept =
            (string)(
                $_SERVER['HTTP_ACCEPT']
                ?? ''
            );

        return
            $accept === ''
            || stripos($accept, 'text/html') !== false
            || stripos($accept, 'application/xhtml+xml') !== false
            || stripos($accept, '*/*') !== false;
    }
}
if (!function_exists('tt_sso_send_logged_in_html_no_cache_headers')) {
    function tt_sso_send_logged_in_html_no_cache_headers()
    {
        if (headers_sent()) {
            return;
        }

        if (!tt_sso_request_wants_html()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

if (!function_exists('tt_sso_is_forum_admin_request')) {
    function tt_sso_is_forum_admin_request()
    {
        $scriptName =
            str_replace(
                '\\',
                '/',
                (string)(
                    $_SERVER['SCRIPT_NAME']
                    ?? ''
                )
            );

        $requestPath =
            parse_url(
                (string)(
                    $_SERVER['REQUEST_URI']
                    ?? ''
                ),
                PHP_URL_PATH
            );

        return
            strpos($scriptName, '/adm/') !== false
            || strpos((string)$requestPath, '/adm/') !== false;
    }
}

if (!function_exists('tt_sso_topbar_css')) {
    function tt_sso_topbar_css()
    {
        return '<link id="tt-sso-topbar-css" rel="stylesheet" href="https://ops.traintote.com/assets/css/tt-community.css?v=4">';
    }
}

if (!function_exists('tt_sso_topbar_html')) {
    function tt_sso_topbar_html($active = '')
    {
        $active =
            strtolower(
                trim(
                    (string)$active
                )
            );

        $items = [
            'dashboard' => ['Dashboard', 'https://ops.traintote.com/dashboard.php'],
            'equipment' => ['Equipment', 'https://ops.traintote.com/equipment/list.php'],
            'status' => ['Car Status', 'https://ops.traintote.com/equipment/status.php'],
            'industries' => ['Industries', 'https://ops.traintote.com/industries/list.php'],
            'waybills' => ['Waybills', 'https://ops.traintote.com/waybills/list.php'],
            'operations' => ['Operations', 'https://ops.traintote.com/operations/dashboard.php'],
            'ai' => ['AI Scanner', 'https://ops.traintote.com/ai/scan_equipment.php'],
            'wiki' => ['Wiki', 'https://wiki.traintote.com/'],
            'forum' => ['Forum', 'https://forum.traintote.com/tt_ops_login.php'],
        ];

        $html =
            '<nav class="tt-sso-topbar" aria-label="TrainTote global navigation">'
            . '<div class="tt-sso-topbar-inner">'
            . '<a class="tt-sso-brand" href="https://ops.traintote.com/dashboard.php">TrainTote Ops Manager</a>'
            . '<div class="tt-sso-links">';

        foreach ($items as $key => $item) {
            $class =
                $key === $active
                ? ' class="tt-sso-active active" aria-current="page"'
                : '';

            $html .=
                '<a'
                . $class
                . ' href="'
                . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8')
                . '">'
                . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }

        $html .=
            '</div>'
            . '<div class="tt-sso-logout"><a href="https://ops.traintote.com/logout.php">Logout</a></div>'
            . '</div>'
            . '</nav>';

        return $html;
    }
}

if (!function_exists('tt_sso_add_body_classes')) {
    function tt_sso_add_body_classes($html, $classes)
    {
        $classes =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        (array)$classes
                    )
                )
            );

        if (!$classes || stripos($html, '<body') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/<body\b([^>]*)>/i',
            function ($matches) use ($classes) {
                $attrs =
                    $matches[1];

                if (preg_match('/\bclass\s*=\s*([\'"])(.*?)\1/i', $attrs, $classMatch)) {
                    $existing =
                        preg_split(
                            '/\s+/',
                            trim($classMatch[2])
                        );

                    $merged =
                        array_unique(
                            array_merge(
                                $existing,
                                $classes
                            )
                        );

                    $newClass =
                        'class='
                        . $classMatch[1]
                        . htmlspecialchars(
                            implode(
                                ' ',
                                $merged
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . $classMatch[1];

                    $attrs =
                        preg_replace(
                            '/\bclass\s*=\s*([\'"])(.*?)\1/i',
                            $newClass,
                            $attrs,
                            1
                        );

                    return '<body' . $attrs . '>';
                }

                return
                    '<body'
                    . $attrs
                    . ' class="'
                    . htmlspecialchars(
                        implode(
                            ' ',
                            $classes
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '">';
            },
            $html,
            1
        );
    }
}

if (!function_exists('tt_sso_remove_legacy_plain_header')) {
    function tt_sso_remove_legacy_plain_header($html)
    {
        $betweenLabels =
            '(?:\s|&nbsp;|<br\s*/?>|<img\b[^>]*>|</?a\b[^>]*>|</?div\b[^>]*>|</?p\b[^>]*>|</?span\b[^>]*>|</?small\b[^>]*>)*';

        $patterns = [
            '~'
            . $betweenLabels
            . '(?:&#128642;|&#x1F682;|[^A-Za-z0-9<]{0,12})?'
            . $betweenLabels
            . 'TrainTote\s+Ops\s+Manager'
            . $betweenLabels
            . 'Dashboard'
            . $betweenLabels
            . 'Wiki'
            . $betweenLabels
            . 'Forum'
            . $betweenLabels
            . 'Logged\s+in\s+through\s+TrainTote'
            . $betweenLabels
            . 'Logout'
            . $betweenLabels
            . '~isu',
        ];

        foreach ($patterns as $pattern) {
            $html =
                preg_replace(
                    $pattern,
                    '',
                    $html,
                    1
                );
        }

        return $html;
    }
}

if (!function_exists('tt_sso_topbar_inject')) {
    function tt_sso_topbar_inject($html, $active = '')
    {
        if (!is_string($html) || trim($html) === '') {
            return $html;
        }

        if (strpos($html, 'class="tt-sso-topbar"') !== false) {
            return $html;
        }

        if (
            stripos($html, '<html') === false
            && stripos($html, '<body') === false
            && stripos($html, '</head>') === false
        ) {
            return $html;
        }

        $active =
            strtolower(
                trim(
                    (string)$active
                )
            );

        $classes = [
            'tt-sso-has-topbar',
        ];

        $html =
            tt_sso_remove_legacy_plain_header($html);

        if ($active === 'forum') {
            $classes[] = 'tt-sso-app-forum';
        }

        if ($active === 'wiki') {
            $classes[] = 'tt-sso-app-wiki';
        }

        $html =
            tt_sso_add_body_classes(
                $html,
                $classes
            );

        $css =
            stripos($html, 'tt-community.css') === false
            ? tt_sso_topbar_css()
            : '';

        if (stripos($html, '</head>') !== false) {
            $html =
                preg_replace(
                    '/<\/head>/i',
                    $css . "\n</head>",
                    $html,
                    1
                );
        } else {
            $html =
                $css
                . "\n"
                . $html;
        }

        $nav =
            tt_sso_topbar_html($active);

        if (preg_match('/<body\b[^>]*>/i', $html)) {
            return preg_replace(
                '/(<body\b[^>]*>)/i',
                '$1' . "\n" . $nav . "\n",
                $html,
                1
            );
        }

        return
            $nav
            . "\n"
            . $html;
    }
}

if (!function_exists('tt_sso_topbar_buffer_start')) {
    function tt_sso_topbar_buffer_start($active = '')
    {
        static $started = false;

        $active =
            strtolower(
                trim(
                    (string)$active
                )
            );

        if ($started) {
            return;
        }

        if ($active === 'forum' && tt_sso_is_forum_admin_request()) {
            return;
        }

        if (!tt_sso_request_wants_html()) {
            return;
        }

        $started = true;

        ob_start(
            function ($html) use ($active) {
                return tt_sso_topbar_inject(
                    $html,
                    $active
                );
            }
        );
    }
}
