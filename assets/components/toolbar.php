<?php
/**
 * TrainTote Shared Toolbar Component
 *
 * Safe helper functions for module toolbars.
 * Existing pages do not change until they explicitly include/use this file.
 */

if (!function_exists('tt_toolbar_escape')) {
    function tt_toolbar_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_toolbar_start')) {
    function tt_toolbar_start(array $options = []): void
    {
        $class = trim('tt-toolbar ' . ($options['class'] ?? ''));
        echo '<div class="' . tt_toolbar_escape($class) . '">';
    }
}

if (!function_exists('tt_toolbar_end')) {
    function tt_toolbar_end(): void
    {
        echo '</div>';
    }
}

if (!function_exists('tt_toolbar_link')) {
    function tt_toolbar_link(string $label, string $href, array $options = []): void
    {
        $class = $options['class'] ?? 'tt-btn tt-btn-primary';

        echo '<a class="' . tt_toolbar_escape($class) . '" href="' . tt_toolbar_escape($href) . '">';
        echo tt_toolbar_escape($label);
        echo '</a>';
    }
}

if (!function_exists('tt_toolbar')) {
    function tt_toolbar(array $links = [], array $options = []): void
    {
        tt_toolbar_start($options);

        foreach ($links as $link) {
            $label = $link['label'] ?? '';
            $href = $link['href'] ?? '#';

            if ($label === '') {
                continue;
            }

            tt_toolbar_link($label, $href, $link);
        }

        tt_toolbar_end();
    }
}
