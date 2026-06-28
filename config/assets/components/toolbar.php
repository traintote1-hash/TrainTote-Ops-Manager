<?php
/**
 * TrainTote Component: Toolbar
 *
 * Shared toolbar helpers.
 * This file previously existed as an empty component. This revision adds
 * helper functions without forcing any existing page to use them yet.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_toolbar_start')) {
    function tt_toolbar_start(array $options = []): void
    {
        $class = trim('tt-toolbar ' . ($options['class'] ?? ''));
        echo '<div class="' . tt_component_escape($class) . '">';
    }
}

if (!function_exists('tt_toolbar_end')) {
    function tt_toolbar_end(): void
    {
        echo '</div>';
    }
}

if (!function_exists('tt_toolbar_button')) {
    function tt_toolbar_button(string $label, string $href, array $options = []): void
    {
        $class = $options['class'] ?? 'tt-btn tt-btn-primary';

        echo '<a class="' . tt_component_escape($class) . '" href="' . tt_component_escape($href) . '">';
        echo tt_component_escape($label);
        echo '</a>';
    }
}

if (!function_exists('tt_toolbar')) {
    function tt_toolbar(array $buttons = [], array $options = []): void
    {
        tt_toolbar_start($options);

        foreach ($buttons as $button) {
            $label = $button['label'] ?? '';
            $href = $button['href'] ?? '#';

            if ($label === '') {
                continue;
            }

            tt_toolbar_button($label, $href, $button);
        }

        tt_toolbar_end();
    }
}
