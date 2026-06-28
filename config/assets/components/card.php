<?php
/**
 * TrainTote Component: Card
 *
 * Lightweight reusable card helpers.
 * These functions are intentionally simple so existing pages can adopt them
 * without changing their database queries, forms, tables, or page logic.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_card_start')) {
    function tt_card_start(string $title = '', array $options = []): void
    {
        $class = trim('tt-card ' . ($options['class'] ?? ''));

        echo '<section class="' . tt_component_escape($class) . '">';

        if ($title !== '') {
            echo '<header class="tt-card-header">';
            echo '<h2>' . tt_component_escape($title) . '</h2>';
            echo '</header>';
        }

        echo '<div class="tt-card-body">';
    }
}

if (!function_exists('tt_card_end')) {
    function tt_card_end(): void
    {
        echo '</div>';
        echo '</section>';
    }
}

if (!function_exists('tt_card')) {
    function tt_card(string $title, string $bodyHtml, array $options = []): void
    {
        tt_card_start($title, $options);
        echo $bodyHtml;
        tt_card_end();
    }
}
