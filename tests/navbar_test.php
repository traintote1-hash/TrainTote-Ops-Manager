<?php

function navbarExpect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$navbar = file_get_contents(dirname(__DIR__) . '/includes/navbar.php');

navbarExpect(
    strpos($navbar, 'href="https://wiki.traintote.com/"') !== false,
    'The authenticated navbar must link to the Wiki SSO host.'
);
navbarExpect(
    strpos($navbar, 'href="https://forum.traintote.com/"') !== false,
    'The authenticated navbar must link to the Forum SSO host.'
);
navbarExpect(
    strpos($navbar, 'data-bs-target="#navbarNav"') !== false
        && strpos($navbar, 'id="navbarNav"') !== false,
    'The shared navbar must preserve its responsive collapse target.'
);

echo "navbar_test: OK\n";
