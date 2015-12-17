<?php

define('FLAG_REPLIED', 2);
define('FLAG_DELETED', 128);

function authOpenAPIMember() { 
  $session = array(); 
  $member = FALSE; 
  $valid_keys = array('expire', 'mid', 'secret', 'sid', 'sig'); 
  $app_cookie = 'vk_app_'.APP_ID;
  if (isset($_COOKIE[$app_cookie])) {
    $app_cookie = $_COOKIE[$app_cookie];  
    $session_data = explode ('&', $app_cookie, 10); 
    foreach ($session_data as $pair) { 
      list($key, $value) = explode('=', $pair, 2); 
      if (empty($key) || empty($value) || !in_array($key, $valid_keys)) { 
        continue; 
      } 
      $session[$key] = $value; 
    } 
    foreach ($valid_keys as $key) { 
      if (!isset($session[$key])) return $member; 
    } 
    ksort($session); 

    $sign = ''; 
    foreach ($session as $key => $value) { 
      if ($key != 'sig') { 
        $sign .= ($key.'='.$value); 
      } 
    } 
    $sign .= APP_SHARED_SECRET; 
    $sign = md5($sign); 
    if ($session['sig'] == $sign && $session['expire'] > time()) { 
      $member = array( 
        'id' => intval($session['mid']), 
        'secret' => $session['secret'], 
        'sid' => $session['sid'] 
      ); 
    } 
  } 
  return $member; 
} 

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
        set('balance', sprintf("%.02f", round($bal/100, 2)));
        set('locked', sprintf("%.02f", round($lock/100, 2)));
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

function formatBalance($acc_type, $acc_id)
{
    $balance = get_balance($acc_type, $acc_id, null);
    if (!$balance)
        return FALSE;

    list($bal, $cur, $lock) = explode(':', $balance);
    return sprintf("%.02f", round($bal/100, 2));
}

function parseMoney($value)
{
    $val = trim($value);
    // Accepts '123', '123.23', '123,23'
    if (!preg_match('/^\d+(?:[\.\,]\d{2})?$/', $val))
        return FALSE;

    // Convert to kopecs
    $amount = round(str_replace(',','.', $val) * 100);
    if ($amount > 0 && $amount < MAX_ACC_INCR)
        return $amount;
    return FALSE;
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

function start_order_transaction($mid, $amount)
{
    // Ensure 'order' account
    if (get_balance("ORD", $mid, null) == 'NO_ACCOUNT') {
        if (!create_account("ORD", $mid, null, $mid, ip2long(getRealIpAddr()), null, null, ''))
            return FALSE;
    }

    $date = time();
    $temp = mt_rand() + 1;

    $res = create_temp_transaction($temp, array(k_transaction_party("ORD", $mid, 1, $amount, $temp, $date, null),
                                                k_transaction_party("USR", $mid, 1, -$amount, $temp, $date, null)),
                                   ip2long(getRealIpAddr()), $date, '');
    if ($res < 1)
        return FALSE;

    $res = lock_transaction($temp);
    return $res == "2" ? $temp : FALSE;
}

function start_commit_order_transaction($mid, $author_id, $amount)
{
    $date = time();
    $temp = mt_rand() + 1;
    $fee = round($amount * ORDER_FEE);

    $res = create_temp_transaction($temp, array(k_transaction_party("ORD", $author_id, 1, -$amount, $temp, $date, null),
                                                k_transaction_party("MAS", 1, 1, $fee, $temp, $date, null),
                                                k_transaction_party("USR", $mid, 1, ($amount-$fee), $temp, $date, null)),
                                   ip2long(getRealIpAddr()), $date, '');
    if ($res < 1)
        return FALSE;

    $res = lock_transaction($temp);
    return $res == "2" ? $temp : FALSE;
}

function post_order($uid, $title, $description, $price)
{
    global $MC_Text;

    $random_tag = mt_rand() + 1;
    $flags = 0;

    $MC_Text->set("newmsg-1#$random_tag", "$flags,$uid\n\x1price\x20$price\t$title\t$description");
    return $MC_Text->get("newmsgid-1#$random_tag");
}

function get_order($local_id)
{
    global $MC_Text;
    global $PMC;

    $response = $MC_Text->get("message-1_$local_id#8");
    if ($response === FALSE)
        return FALSE;

    list($params, $cludges, $title, $description) = explode("\t", $response);
    list($flags, $time, $uid) = explode(',', $params);
    list(,$price) = explode("\x20", $cludges);
    return array('id' => $local_id,
                 'uid' => $uid,
                 'flags' => $flags,
                 'time' => $time,
                 'title' => $title,
                 'description' => $description,
                 'amount' => $price,
                 'price' => sprintf("%.02f", round($price/100, 2)),
                 'info' => $PMC->get("id$uid"));
}

function get_orders($from, $to, $peer)
{
    global $MC_Text;

    $orders = array();
    $req = $peer == null ? "sublist-1_194:0#$from,$to" : "peermsglist-1_$peer#$from,$to";
    $ids = explode(',', $MC_Text->get($req));
    if (!$ids)
        return $orders;
    $count = array_shift($ids);
    return array_map('get_order', $ids);
}