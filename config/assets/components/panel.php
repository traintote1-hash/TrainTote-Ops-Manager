<?php
/**
 * TrainTote Component: Panel
 *
 * Panels are simple content containers for settings, forms, and grouped content.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_panel_start')) {
    function tt_panel_start(string $title = '', array $options = []): void
    {
        $class = trim('tt-panel ' . ($options['class'] ?? ''));

        echo '<section class="' . tt_component_escape($class) . '">';

        if ($title !== '') {
            echo '<header class="tt-panel-header">';
            echo '<h2>' . tt_component_escape($title) . '</h2>';
            echo '</header>';
        }

        echo '<div class="tt-panel-body">';
    }
}

if (!function_exists('tt_panel_end')) {
    function tt_panel_end(): void
    {
        echo '</div>';
        echo '</section>';
    }
}

if (!function_exists('tt_panel')) {
    function tt_panel(string $title, string $bodyHtml, array $options = []): void
    {
        tt_panel_start($title, $options);
        echo $bodyHtml;
        tt_panel_end();
    }
}
