<?php

#ifndef KittenPHP
if (preg_match('/\.(?:png|jpg|jpeg|gif|js|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}
#endif

require_once 'config.php';
require_once 'lib/k_limonade.php';
require_once 'lib/k_money.php';
require_once 'utils.php';

$MC_Money = new Memcache;
$MC_Money->connect(MC_MONEY_HOST, MC_MONEY_PORT);

$PMC = new Memcache;
$PMC->connect(PMC_HOST, PMC_PORT);

$MC_Text = new Memcache;
$MC_Text->connect(MC_TEXT_HOST, MC_TEXT_PORT);

set('VK_APP_ID', APP_ID);
set('title', 'V-order');
set('content', '');

function index($mine)
{
    $member = checkAuth();
    set('member', $member);
    set('mine', $mine);
    set('orders', get_orders(-1, -10, $mine ? $member['id'] : null));

    global $page;
    ob_start();
    include 'templates/index.php';
    set('content', ob_get_clean());
    include 'templates/_layout.php';
}

function auth()
{
    $member = authOpenAPIMember();
    if ($member === FALSE || $member['id'] != $_POST['mid']) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $info = array();
    foreach (array('mid', 'first_name', 'last_name','photo') as $key)
        $info[$key] = $_POST[$key];
    
    global $PMC;
    $PMC->set("id{$member['id']}", $info);
    $member['info'] = $info;

    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($member);
}

function deposit()
{
    global $PMC;
    $member = checkAuth();
    set('member', $member);
    set('deposit', $PMC->get("dep{$member['id']}"));

    global $page;
    ob_start();
    include 'templates/deposit.php';
    set('content', ob_get_clean());
    include 'templates/_layout.php';
}

function post_deposit()
{
    global $PMC;

    $member = authOpenAPIMember();
    if ($member === FALSE) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $response = array();
    $key = "dep{$member['id']}";
    $existing = $PMC->get($key);

    if (isset($_POST['amount'])) {
        if ($existing)
            return json_error('EXISTING_DEPOSIT');
        $amount = parseMoney($_POST['amount']);
        if ($amount === FALSE)
            return json_error('BAD_AMOUNT');
        // Create and lock deposit for 10 mins
        $parts = create_deposit_transaction($member['id'], $amount);
        if (!$parts)
            return json_error('DEPOSIT_ERROR');
        $unlock = mt_rand(1000, 9999);
        $PMC->set($key, array('unlock' => $unlock,
                              'tr_id' => $parts[1],
                              'auth_code' => $parts[2]), 600);
        // 2-step auth emulation
        $response['code'] = $unlock;
    } elseif (isset($_POST['verify'])) {
        if (!$existing)
            return json_error('NO_DEPOSIT');

        if ($_POST['verify'] != $existing['unlock'])
            return json_error('BAD_VERIFY');
        // Commit deposit transaction
        $parts = explode(':', long_commit_transaction($existing['tr_id'], $existing['auth_code']));
        $PMC->delete($key);
        if ($parts[0] != 1)
            return json_error('DEPOSIT_ERROR');
        $balance = get_balance("USR", $member['id'], null);
        if (!$balance)
            return json_error('DEPOSIT_ERROR');
        list($bal, $cur, $lock) = explode(':', $balance);
        $response['balance'] = sprintf("%.02f", round($bal/100, 2));
        $response['locked'] = sprintf("%.02f", round($lock/100, 2));
        $response['raw'] = $balance;
    } else {
        status(HTTP_BAD_REQUEST);
        exit;
    }

    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}

function json_error($msg)
{
    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $msg));
}

function order()
{
    global $PMC;

    $member = authOpenAPIMember();
    if ($member === FALSE) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $uid = $member['id'];
    $title = str_replace("\t", '    ', trim($_POST['title']));
    $description = str_replace("\t", '    ', trim($_POST['description']));
    $price = parseMoney($_POST['price']);

    if (!$title || strlen($title) > 140 ||
        !$description || strlen($description) > 2000 ||
        $price === FALSE)
        return json_error('BAD_ORDER');

    // Pre-validate balance
    $balance = get_balance("USR", $uid, null);
    if (!$balance)
        return json_error('NO_BALANCE');
    list($bal, $cur, $lock) = explode(':', $balance);
    if ($bal < $price)
        return json_error('INSUFFICIENT_FUNDS');

    // Start and lock 'order' transaction
    $temp = start_order_transaction($uid, $price);
    if ($temp === FALSE)
        return json_error('START_TRANS');
    // Create 'order' text
    $local_id = post_order($uid, $title, $description, $price);
    if ($local_id === FALSE) {
        // Cancel transaction
        delete_temp_transaction($temp);
        return json_error('POST_ORDER');
    }
    // Commit order transaction
    $res = commit_transaction($temp);
    if (explode(':', $res)[0] != 1) {
        // Cancel transaction
        delete_temp_transaction($temp);
        return json_error('COMMIT_TRANS');
    }

    // Render order item html
    $i = get_order($local_id);
    $page = array('member' => $member);
    ob_start();
    include 'templates/_order.php';
    $html = ob_get_clean();

    $response = array('order' => $i, 'html' => $html);
    $balance = get_balance("USR", $uid, null);
    if ($balance) {
        list($bal, $cur, $lock) = explode(':', $balance);
        $response['balance'] = sprintf("%.02f", round($bal/100, 2));
    }
    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}

function hello($world) {
    set('title', $world);
    global $page;
    include 'templates/_layout.php';
}

dispatch_post('/auth', 'auth');
dispatch('/', 'index');
dispatch('/mine', 'mine');
dispatch_post('/order', 'order');
dispatch('/deposit', 'deposit');
dispatch_post('/deposit', 'post_deposit');
dispatch('/hello/:world/', 'hello');

$route = run();
set('route', $route);

switch ($route['callback']) {
case 'index':
    index(null);
    break;
case 'mine':
    index(true);
    break;
case 'order':
    order();
    break;
case 'auth':
    auth();
    break;
case 'deposit':
    deposit();
    break;
case 'post_deposit':
    post_deposit();
    break;
case 'hello':
    hello($route['params']['world']);
    break;
}
