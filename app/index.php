<?php

if (preg_match('/\.(?:png|jpg|jpeg|gif|js|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}

require_once 'config.php';
require_once 'lib/k_limonade.php';
require_once 'lib/k_money.php';
require_once 'lib/openapi.php';

$MC_Money = new Memcache;
#$MC_Money->connect(MC_MONEY_HOST, MC_MONEY_PORT);

$PMC = new Memcache;
$PMC->connect(PMC_HOST, PMC_PORT);

set('VK_APP_ID', APP_ID);
set('title', 'V-order');
set('content', '');

function checkAuth() {
    global $PMC;
    $member = authOpenAPIMember();
    if ($member === FALSE) {
        global $page;
        include 'templates/_non_member.php';
        exit;
    }
    $member['info'] = $PMC->get("id{$member['id']}");
    return $member;
}

function index()
{
    $member = checkAuth();

    set('cookie', $_COOKIE);
    set('member', $member);

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
    global $PMC;

    $info = array();
    foreach (array('mid', 'first_name', 'last_name','photo') as $key)
        $info[$key] = $env['POST'][$key];
    
    $PMC->set("id{$member['id']}", $info);
    $member['info'] = $info;

    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($member);
}

function hello($world) {
    set('title', $world);
    global $page;
    include 'templates/_layout.php';
}


dispatch('/', 'index');
dispatch_post('/auth/', 'auth');
dispatch('/hello/:world/', 'hello');

$route = run();
switch ($route['callback']) {
case 'index':
    index();
    break;
case 'auth':
    auth();
    break;
case 'hello':
    hello($route['params']['world']);
    break;
}
