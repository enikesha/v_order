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
        $parts = explode(':', $balance);
        set('balance', sprintf("%.02f", round($parts[0]/100,2)));
        set('locked', sprintf("%.02f", round($parts[2]/100,2)));
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
