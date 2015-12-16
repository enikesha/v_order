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
    set('script', 'pages.deposit()');
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

    if (isset($_POST['amount'])) {
        $existing = $PMC->get($key);
        if (!$existing) {
            $val = trim($_POST['amount']);
            // Accepts '123', '123.23', '123,23'
            if (preg_match('/^\d+(?:[\.\,]\d{2})?$/', $val)) {
                // Convert to kopecs
                $amount = round(str_replace(',','.', $val) * 100);
                if ($amount > 0 && $amount < MAX_ACC_INCR) {
                    // Create and lock deposit for 10 mins
                    $parts = create_deposit_transaction($member['id'], $amount);
                    if ($parts) {
                        $unlock = mt_rand(1000, 9999);
                        $PMC->set($key, array('unlock' => $unlock,
                                              'tr_id' => $parts[1],
                                              'auth_code' => $parts[2]), 600);
                        // 2-step auth emulation
                        $response['code'] = $unlock;
                    } else {
                        $response['error'] = 'DEPOSIT_ERROR';
                    }
                } else {
                    $response['error'] = 'BAD_AMOUNT';
                }
            } else {
                $response['error'] = 'BAD_AMOUNT';
            }
        } else {
            $response['error'] = 'EXISTING_DEPOSIT';
        }
    } elseif (isset($_POST['verify'])) {
        $existing = $PMC->get($key);
        if ($existing) {
            if ($_POST['verify'] == $existing['unlock']) {
                $parts = explode(':', long_commit_transaction($existing['tr_id'], $existing['auth_code']));
                if ($parts[0] == 1) {
                    $balance = get_balance("USR", $member['id'], null);
                    if ($balance) {
                        list($bal, $cur, $lock) = explode(':', $balance);
                        $response['balance'] = sprintf("%.02f", round($bal/100, 2));
                        $response['locked'] = sprintf("%.02f", round($lock/100, 2));
                        $response['raw'] = $balance;
                    } else {
                        $response['error'] = 'DEPOSIT_ERROR';
                    }
                } else {
                    $response['error'] = 'DEPOSIT_ERROR';
                }
                $PMC->delete($key);
            } else {
                $response['error'] = 'BAD_VERIFY';
            }
        } else {
            $response['error'] = 'NO_DEPOSIT';
        }
    } else {
        status(HTTP_BAD_REQUEST);
        exit;
    }

    send_header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}

function hello($world) {
    set('title', $world);
    global $page;
    include 'templates/_layout.php';
}

dispatch('/', 'index');
dispatch_post('/auth', 'auth');
dispatch('/deposit', 'deposit');
dispatch_post('/deposit', 'post_deposit');
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
case 'post_deposit':
    post_deposit();
    break;
case 'hello':
    hello($route['params']['world']);
    break;
}
