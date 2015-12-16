<?php

#ifndef KittenPHP
if (preg_match('/\.(?:png|jpg|jpeg|gif|js|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}
#endif

require_once 'config.php';
require_once 'lib/k_limonade.php';
require_once 'lib/k_money.php';
require_once 'lib/openapi.php';
require_once 'utils.php';

$MC_Money = new Memcache;
$MC_Money->connect(MC_MONEY_HOST, MC_MONEY_PORT);

$PMC = new Memcache;
$PMC->connect(PMC_HOST, PMC_PORT);

set('VK_APP_ID', APP_ID);
set('title', 'V-order');
set('content', '');

function index()
{
    set('member', checkAuth());
    set('server', $_SERVER);

    global $page;
    ob_start();
    include 'templates/index.php';
    set('content', ob_get_clean());
    include 'templates/_layout.php';
}

function auth()
{
    $env = env();
    $member = authOpenAPIMember();
    if ($member === FALSE || $member['id'] != $env['POST']['mid']) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $info = array();
    foreach (array('mid', 'first_name', 'last_name','photo') as $key)
        $info[$key] = $env['POST'][$key];
    
    global $PMC;
    $PMC->set("id{$member['id']}", $info);
    $member['info'] = $info;

    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($member);
}

function deposit()
{
    set('member', checkAuth());
    set('script', 'pages.deposit()');

    global $page;
    ob_start();
    include 'templates/deposit.php';
    set('content', ob_get_clean());
    include 'templates/_layout.php';
}

function hello($world) {
    set('title', $world);
    global $page;
    include 'templates/_layout.php';
}

dispatch('/', 'index');
dispatch_post('/auth', 'auth');
dispatch('/deposit', 'deposit');
dispatch('/hello/:world/', 'hello');

$route = run();
set('route', $route);

switch ($route['callback']) {
case 'index':
    index();
    break;
case 'auth':
    auth();
    break;
case 'deposit':
    deposit();
    break;
case 'hello':
    hello($route['params']['world']);
    break;
}
