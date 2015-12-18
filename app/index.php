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

$MC_Queue = new Memcache;
$MC_Queue->connect(MC_QUEUE_HOST, MC_QUEUE_PORT);

set('VK_APP_ID', APP_ID);
set('title', 'V-order');
set('content', '');

function route_index($mine)
{
    $member = checkAuth();
    set('member', $member);

    if (isset($_GET['o']) && preg_match('/^\d+$/', $_GET['o'])) {
        $from = get_reverse_pos($_GET['o'], $mine ? $member['id'] : null);
    } else {
        $from = 1;
    }
    $orders = get_orders(-$from, -$from - PAGE_SIZE + 1, $mine ? $member['id'] : null);

    /* AJAX check  */
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        global $i;
        global $page;
        send_header('Content-Type: text/html; charset=utf-8');
        foreach ($orders as $i) {
            include 'templates/_order.php';
        }
        return;
    }

    set('mine', $mine);
    set('orders', $orders);

    // Get queues keys
    global $MC_Queue;
    $ip = ip2long(getRealIpAddr());
    $uid = $member['id'];
    $timeout = 30;
    set('mine_queue', $MC_Queue->get("timestamp_key{$uid},{$ip},{$timeout}(orders{$uid})"));
    if ($mine == null) {
        set('common_queue', $MC_Queue->get("timestamp_key{$uid},{$ip},{$timeout}(orders)"));
    }
    global $page;
    ob_start();
    include 'templates/index.php';
    set('content', ob_get_clean());
    include 'templates/_layout.php';
}

function route_auth()
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

function route_deposit()
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

function route_post_deposit()
{
    global $PMC;

    $member = authOpenAPIMember();
    if ($member === FALSE) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $response = array();
    $uid = $member['id'];
    $key = "dep$uid";
    $existing = $PMC->get($key);

    if (isset($_POST['amount'])) {
        if ($existing)
            return json_error('EXISTING_DEPOSIT');
        $amount = parseMoney($_POST['amount']);
        if ($amount === FALSE)
            return json_error('BAD_AMOUNT');
        // Create and lock deposit for 10 mins
        $parts = create_deposit_transaction($uid, $amount);
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
        $response['balance'] = formatBalance("USR", $uid);

        // Send to user's queue
        global $MC_Queue;
        $MC_Queue->add("queue(orders$uid)", "\x02".json_encode(array('balance'=>$response['balance'])));
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

function route_post_order()
{
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

    // Render user's html
    global $i;
    global $page;
    $i = get_order($local_id);
    $page = array('member' => $member);
    ob_start();
    include 'templates/_order.php';
    $html = ob_get_clean();

    $response = array('order' => $i, 'html' => $html, 'balance' => formatBalance('USR', $uid));

    // Send to user's queue
    global $MC_Queue;
    $MC_Queue->add("queue(orders$uid)", "\x02".json_encode($response));
    
    // Render common html
    $page['member']['id'] = 0;
    ob_start();
    include 'templates/_order.php';
    $html = ob_get_clean();
    // Send to common queue
    $MC_Queue->add("queue(orders)", "\x02".json_encode(array('order'=>$i, 'html'=> $html)));


    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}

function route_post_order_action($local_id)
{
    global $MC_Text;
    global $MC_Queue;

    if (!preg_match('/^\d+$/', $local_id)) {
        status(HTTP_NOT_FOUND);
        exit;
    }

    $member = authOpenAPIMember();
    if ($member === FALSE) {
        status(HTTP_FORBIDDEN);
        exit;
    }

    $uid = $member['id'];
    $order = get_order($local_id);
    if ($order === FALSE) {
        status(HTTP_NOT_FOUND);
        exit;
    }

    $response = array();
    $act = $_POST['act'];

    switch ($act) {
    case 'cancel':
        if ($order['uid'] != $member['id']) {
            status(HTTP_FORBIDDEN);
            exit;
        }
        if (($order['flags'] & FLAG_DELETED) == FLAG_DELETED)
            return json_error('ORDER_CANCELLED');

        if (($order['flags'] & FLAG_REPLIED) == FLAG_REPLIED)
            return json_error('ORDER_COMMITTED');

        // Start and lock 'reverse-order' transaction
        $temp = start_order_transaction($uid, -$order['amount']);
        if ($temp === FALSE)
            return json_error('START_TRANS');

        // Set order 'DELETED' flag
        $res = $MC_Text->increment("flags-1_$local_id", FLAG_DELETED);
        if ($res === FALSE) {
            // Cancel transaction
            delete_temp_transaction($temp);
            return json_error('CANCEL_ORDER');
        }
        // Commit 'reverse-order' transaction
        commit_transaction($temp);

        $response['ok'] = TRUE;
	
        // Send to common queue
        $MC_Queue->add("queue(orders)", "\x02".json_encode(array('cancel'=>$local_id)));

        // Refresh balance
        $response['balance'] = formatBalance('USR', $uid);
        $response['order_balance'] = formatBalance('ORD', $uid);

        // Send to user's queue
        $MC_Queue->add("queue(orders$uid)", "\x02".json_encode(array('cancel'=>$local_id, 'balance'=>$response['balance'])));

        break;
    case 'commit':
        # Prevent from committing own orders
        #if ($order['uid'] == $member['id']) {
        #    status(HTTP_FORBIDDEN);
        #    exit;
        #}
        if (($order['flags'] & FLAG_DELETED) == FLAG_DELETED)
            return json_error('ORDER_CANCELLED');

        if (($order['flags'] & FLAG_REPLIED) == FLAG_REPLIED)
            return json_error('ORDER_COMMITTED');

        // Start and lock 'commit-order' transaction
        $temp = start_commit_order_transaction($uid, $order['uid'], $order['amount']);
        if ($temp === FALSE)
            return json_error('START_TRANS');

        // Set order 'COMMITTED' flag
        $resp = $MC_Text->increment("flags-1_$local_id", FLAG_REPLIED);
        if ($resp === FALSE) {
            // Cancel transaction
            delete_temp_transaction($temp);
            return json_error('COMMIT_ORDER');
        }
        // Commit 'commit-order' transaction
        $resp = commit_transaction($temp);
        # TODO: Store transaction id in the order

        $response['ok'] = TRUE;
        // Refresh balance
        $response['balance'] = formatBalance('USR', $uid);
        $response['order_balance'] = formatBalance('ORD', $uid);

        // Send to user's queue
        $MC_Queue->add("queue(orders$uid)", "\x02".json_encode(array('commit'=>$local_id, 'balance'=>$response['balance'])));

        // Send to common queue
        $MC_Queue->add("queue(orders)", "\x02".json_encode(array('commit'=>$local_id)));

        // Render author's html
        $author = $order['uid'];
        global $i;
        global $page;
        $i = get_order($local_id);
        $page = array('member' => array('id'=>$author));
        ob_start();
        include 'templates/_order.php';
        $html = ob_get_clean();

        // Send to author's queue
        $MC_Queue->add("queue(orders$author)", "\x02".json_encode(array('commit' => $local_id, 'order'=>$i, 'html'=> $html)));

        break;
    default:
        status(HTTP_BAD_REQUEST);
        exit;
    }
    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}

dispatch_post('/auth', 'auth');
dispatch('/', 'index');
dispatch('/mine', 'mine');
dispatch_post('/order', 'post_order');
dispatch_post('/order/:id', 'post_order_action');
dispatch('/deposit', 'deposit');
dispatch_post('/deposit', 'post_deposit');

$route = run();
set('route', $route);

switch ($route['callback']) {
case 'index':
    route_index(null);
    break;
case 'mine':
    route_index(true);
    break;
case 'post_order':
    route_post_order();
    break;
case 'post_order_action':
    route_post_order_action($route['params']['id']);
    break;
case 'auth':
    route_auth();
    break;
case 'deposit':
    route_deposit();
    break;
case 'post_deposit':
    route_post_deposit();
    break;
default:
    status(HTTP_NOT_FOUND);
}
