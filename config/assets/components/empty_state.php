<?php
/**
 * TrainTote Component: Empty State
 *
 * Shared empty-result message for lists, dashboards, and setup screens.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_empty_state')) {
    function tt_empty_state(string $title, string $message = '', array $actions = []): void
    {
        echo '<div class="tt-empty-state">';

        echo '<h3>' . tt_component_escape($title) . '</h3>';

        if ($message !== '') {
            echo '<p>' . tt_component_escape($message) . '</p>';
        }

        if (!empty($actions)) {
            echo '<div class="tt-empty-state-actions">';

            foreach ($actions as $action) {
                $label = $action['label'] ?? '';
                $href = $action['href'] ?? '#';
                $class = $action['class'] ?? 'tt-btn tt-btn-primary';

                if ($label === '') {
                    continue;
                }

                echo '<a class="' . tt_component_escape($class) . '" href="' . tt_component_escape($href) . '">';
                echo tt_component_escape($label);
                echo '</a>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}
