<?php

function getRealIpAddr()
{
    if (isset($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
        return $_SERVER['HTTP_CLIENT_IP'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function checkAuth()
{
    // Check vk user id from cookies and render login page if not present
    $member = authOpenAPIMember();
    if ($member === FALSE) {
        global $page;
        include 'templates/_non_member.php';
        exit;
    }

    // Load user info from persistent memcache
    global $PMC;
    $member['info'] = $PMC->get("id{$member['id']}");

    if ($member['info']) {
        // Set page user variables
        set('info', $member['info']);
        set('photo', $member['info']['photo']);
        set('name', "{$member['info']['first_name']} {$member['info']['last_name']}");
    }

    // Load user balance from money engine
    $balance = get_balance("USR", $member['id'], null);
    if ($balance == 'NO_ACCOUNT') {
        // Create account
        if (create_account("USR", $member['id'], null, $member['id'], ip2long(getRealIpAddr()), null, null, '')) {
            $balance = get_balance("USR", $member['id'], null);
        } else {
            $balance = FALSE;
        }
    }
    
    $member['balance'] = $balance;
    if ($balance) {
        // Set page balance variables
        list($bal, $cur, $lock) = explode(':', $balance);
        set('balance', sprintf("%.02f", round($bal/100,2)));
        set('locked', sprintf("%.02f", round($lock/100,2)));
    }
    return $member;
}

function url_active($route)
{
    global $page;
    if ($page['route']['callback'] == $route)
        return ' class="active"';
    return '';
}


function create_deposit_transaction($mid, $amount)
{
    $date = time();
    $temp = mt_rand() + 1;

    $res = create_temp_transaction($temp, array(k_transaction_party("USR", $mid, 1, $amount, $temp, $date, null),
                                                k_transaction_party("DEP", 1, 1, -$amount, $temp, $date, DEP_WITHDRAW_SECRET)),
                                   ip2long(getRealIpAddr()), $date, '');
    if ($res < 1)
        return FALSE;

    $parts = explode(':', long_lock_transaction($temp, 600));
    return (count($parts) == 4) ? $parts : FALSE;
}