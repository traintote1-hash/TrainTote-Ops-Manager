<?php
/**
 * TrainTote Component: Stat Tile
 *
 * Small dashboard/list summary block for counts and KPIs.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_stat_tile')) {
    function tt_stat_tile(string $label, $value, array $options = []): void
    {
        $class = trim('tt-stat-tile ' . ($options['class'] ?? ''));
        $href = $options['href'] ?? '';

        $tag = $href !== '' ? 'a' : 'div';

        echo '<' . $tag;

        if ($href !== '') {
            echo ' href="' . tt_component_escape($href) . '"';
        }

        echo ' class="' . tt_component_escape($class) . '">';

        if (!empty($options['icon'])) {
            echo '<div class="tt-stat-icon">' . tt_component_escape($options['icon']) . '</div>';
        }

        echo '<div class="tt-stat-value">' . tt_component_escape($value) . '</div>';
        echo '<div class="tt-stat-label">' . tt_component_escape($label) . '</div>';

        if (!empty($options['note'])) {
            echo '<div class="tt-stat-note">' . tt_component_escape($options['note']) . '</div>';
        }

        echo '</' . $tag . '>';
    }
}
