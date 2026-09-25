<?php

require_once dirname(__DIR__) . '/includes/navbar_helpers.php';
require_once dirname(__DIR__) . '/includes/tt_sso_topbar.php';

function navbarExpect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$project = dirname(__DIR__);
$navbar = file_get_contents($project . '/includes/navbar.php');
$navigationCss = file_get_contents($project . '/assets/css/tt-navigation.css');
$communityCss = file_get_contents($project . '/assets/css/tt-community.css');

$aiPosition = strpos($navbar, "'key' => 'ai'");
$wikiPosition = strpos($navbar, "'key' => 'wiki'");
$forumPosition = strpos($navbar, "'key' => 'forum'");
$primaryEndPosition = strpos($navbar, '</ul>', $forumPosition);
$logoutPosition = strpos($navbar, "tt_nav_ops_href('/logout.php'");

navbarExpect(
    $aiPosition !== false
        && $wikiPosition > $aiPosition
        && $forumPosition > $wikiPosition
        && $primaryEndPosition > $forumPosition,
    'Wiki and Forum must follow AI Scanner in the primary navigation group.'
);
navbarExpect(
    $logoutPosition > $primaryEndPosition,
    'Logout must remain in the separate account navigation group.'
);
navbarExpect(
    strpos($navbar, "tt_nav_community_href('https://wiki.traintote.com/'") !== false,
    'The authenticated navbar must build the Wiki SSO link through the domain-aware helper.'
);
navbarExpect(
    strpos($navbar, "tt_nav_community_href('https://forum.traintote.com/'") !== false,
    'The authenticated navbar must build the Forum SSO link through the domain-aware helper.'
);
navbarExpect(
    strpos($navbar, "\$currentNavHost === 'demo.traintote.com'") !== false
        && strpos($navbar, "\$navItem['key'] !== 'forum'") !== false,
    'The demo navbar must hide the Forum link.'
);
navbarExpect(
    strpos($navbar, 'data-bs-target="#navbarNav"') !== false
        && strpos($navbar, 'id="navbarNav"') !== false,
    'The shared navbar must preserve its responsive collapse target.'
);
navbarExpect(
    strpos($navbar, 'aria-current="page"') !== false,
    'The active navigation link must identify the current page accessibly.'
);
navbarExpect(
    strpos($navigationCss, 'var(--tt-engine-red, #8B1E24)') !== false,
    'The active navigation item must use the TrainTote red treatment.'
);
navbarExpect(
    strpos($navigationCss, '.tt-demo-badge') !== false,
    'The demo badge must have a dedicated navbar treatment.'
);

$routes = array(
    array('dashboard', '/dashboard.php', 'ops.traintote.com'),
    array('equipment', '/equipment/list.php', 'ops.traintote.com'),
    array('car_status', '/equipment/status.php', 'ops.traintote.com'),
    array('industries', '/industries/list.php', 'ops.traintote.com'),
    array('waybills', '/waybills/list.php', 'ops.traintote.com'),
    array('operations', '/operations/dashboard.php', 'ops.traintote.com'),
    array('ai', '/ai/scan_equipment.php', 'ops.traintote.com'),
    array('wiki', '/index.php/Main_Page', 'wiki.traintote.com'),
    array('forum', '/index.php', 'forum.traintote.com'),
);

foreach ($routes as $route) {
    list($expectedItem, $path, $host) = $route;
    $activeItems = array();

    foreach (array_column($routes, 0) as $item) {
        if (tt_nav_is_active($item, $path, $host)) {
            $activeItems[] = $item;
        }
    }

    navbarExpect(
        $activeItems === array($expectedItem),
        sprintf('Route %s on %s must activate only %s.', $path, $host, $expectedItem)
    );
}

navbarExpect(
    !tt_nav_is_active('equipment', '/equipment/status.php', 'ops.traintote.com'),
    'Car Status must not also activate the Equipment item.'
);
navbarExpect(
    tt_nav_ops_href('/dashboard.php', 'ops.traintote.com') === '/dashboard.php',
    'Ops pages must keep local navigation links relative.'
);
navbarExpect(
    tt_nav_ops_href('/dashboard.php', 'wiki.traintote.com') === 'https://ops.traintote.com/dashboard.php'
        && tt_nav_ops_href('/logout.php', 'forum.traintote.com') === 'https://ops.traintote.com/logout.php',
    'Wiki and Forum must receive working absolute links back to Ops.'
);
navbarExpect(
    tt_nav_community_href('https://wiki.traintote.com/', 'demo.traintote.com') === 'https://wiki.traintote.com/?tt_ops_host=demo'
        && tt_nav_community_href('https://forum.traintote.com/', 'demo.traintote.com') === 'https://forum.traintote.com/?tt_ops_host=demo',
    'Demo must mark Wiki and Forum visits so the community topbar can return to Demo.'
);

$_SERVER['HTTP_HOST'] = 'demo.traintote.com';
$_SERVER['REQUEST_URI'] = '/dashboard.php';
ob_start();
require dirname(__DIR__) . '/includes/navbar.php';
$demoNavbar = ob_get_clean();
navbarExpect(
    strpos($demoNavbar, '>Wiki<') !== false
        && strpos($demoNavbar, '>Forum<') === false
        && strpos($demoNavbar, '>DEMO<') !== false,
    'Demo must keep the Wiki link, hide the Forum link, and show the DEMO badge.'
);
unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

$_SERVER['HTTP_HOST'] = 'ops.traintote.com';
$_SERVER['REQUEST_URI'] = '/dashboard.php';
ob_start();
require dirname(__DIR__) . '/includes/navbar.php';
$opsNavbar = ob_get_clean();
navbarExpect(
    strpos($opsNavbar, '>Forum<') !== false && strpos($opsNavbar, '>DEMO<') === false,
    'Ops must keep the Forum link and omit the DEMO badge.'
);
unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

$_COOKIE['TT_OPS_HOST'] = 'demo';
navbarExpect(
    tt_nav_ops_href('/dashboard.php', 'wiki.traintote.com') === 'https://demo.traintote.com/dashboard.php'
        && tt_nav_ops_href('/logout.php', 'forum.traintote.com') === 'https://demo.traintote.com/logout.php',
    'Wiki and Forum must return to Demo when the demo origin cookie is present.'
);
unset($_COOKIE['TT_OPS_HOST']);

$wikiTopbar = tt_sso_topbar_html('wiki');
$externalAiPosition = strpos($wikiTopbar, '>AI Scanner<');
$externalWikiPosition = strpos($wikiTopbar, '>Wiki<');
$externalForumPosition = strpos($wikiTopbar, '>Forum<');

navbarExpect(
    $externalAiPosition !== false
        && $externalWikiPosition > $externalAiPosition
        && $externalForumPosition > $externalWikiPosition,
    'The Wiki and Forum topbar must place Wiki and Forum directly after AI Scanner.'
);
navbarExpect(
    strpos($wikiTopbar, '>Jobs<') === false,
    'The community topbar must use the same item list as the Ops navbar.'
);
navbarExpect(
    strpos($wikiTopbar, 'operations/dashboard.php') !== false
        && strpos($wikiTopbar, 'operations/generate.php') === false,
    'The community Operations item must use the current dashboard route.'
);
navbarExpect(
    strpos($wikiTopbar, 'class="tt-sso-active active" aria-current="page" href="https://wiki.traintote.com/"') !== false,
    'The Wiki topbar must expose the same active-page state accessibly.'
);

$_COOKIE['TT_OPS_HOST'] = 'demo';
$demoWikiTopbar = tt_sso_topbar_html('wiki');
navbarExpect(
    strpos($demoWikiTopbar, 'https://demo.traintote.com/dashboard.php') !== false
        && strpos($demoWikiTopbar, 'https://demo.traintote.com/operations/dashboard.php') !== false
        && strpos(tt_sso_topbar_css(), 'https://demo.traintote.com/assets/css/tt-community.css?v=4') !== false,
    'Community topbar links and CSS must point back to Demo when the demo origin cookie is present.'
);
unset($_COOKIE['TT_OPS_HOST']);

navbarExpect(
    strpos($communityCss, '--tt-sso-red: #8B1E24') !== false
        && strpos($communityCss, '--tt-sso-nav-height: 60px') !== false,
    'The community topbar must share the Ops navbar color and compact height.'
);

$forumDocument = '<!doctype html><html><head></head><body id="phpbb"><main>Forum</main></body></html>';
$injectedForum = tt_sso_topbar_inject($forumDocument, 'forum');
navbarExpect(
    strpos($injectedForum, 'tt-sso-app-forum') !== false
        && strpos($injectedForum, 'tt-community.css?v=4') !== false
        && strpos($injectedForum, 'aria-current="page" href="https://forum.traintote.com/tt_ops_login.php"') !== false,
    'Forum injection must add its body class, shared stylesheet, and active Forum item.'
);
navbarExpect(
    substr_count(tt_sso_topbar_inject($injectedForum, 'forum'), 'class="tt-sso-topbar"') === 1,
    'Community topbar injection must remain idempotent.'
);

echo "navbar_test: OK\n";
