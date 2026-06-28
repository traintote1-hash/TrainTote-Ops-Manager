<?php
/**
 * TrainTote Component: Page Header
 *
 * Shared page heading block used by list pages, dashboards, and module screens.
 */

if (!function_exists('tt_component_escape')) {
    function tt_component_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tt_page_header')) {
    function tt_page_header(string $title, string $subtitle = '', array $actions = []): void
    {
        echo '<div class="tt-page-header">';

        echo '<div class="tt-page-header-main">';
        echo '<h1>' . tt_component_escape($title) . '</h1>';

        if ($subtitle !== '') {
            echo '<p>' . tt_component_escape($subtitle) . '</p>';
        }

        echo '</div>';

        if (!empty($actions)) {
            echo '<div class="tt-page-header-actions">';

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
