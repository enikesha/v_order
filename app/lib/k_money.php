<?php

/* Calculate 'code' from 'secret'. Code is 64-bit value from first half of
 * md5($secret) in hexadecimal form.
 *
 * @param string $secret (128-bit value as hexadecimal 32-char string)
 * @return string code
 */
function k_code($secret)
{
    return substr(md5($secret), 0, 16);
}

/*
 * Peforms XOR on hexadecimal char ('0' - 'f')
 *
 * @param string $a 
 * @param string $b
 * @return string $a xor $b
 */
function k_xor_hcyf ($a, $b) {
    $ai = ord($a);
    $bi = ord($b);
    if ($ai >= ord('a')) $ai -= 7;
    if ($bi >= ord('a')) $bi -= 7;

    $ai = ($ai ^ $bi) & 15;
    return $ai < 10 ? chr(ord('0') + $ai) : chr(ord('0') + 0x27 + $ai);
}

/* 
 * Performs XOR on 128-bit value in hexadecimal string form (32 char len)
 *
 * @param string $a 
 * @param string $b
 * @return string $a xor $b
 */
function k_xor_hex($a, $b)
{
    for ($i=0; $i<32; $i++) {
        $a[$i] = k_xor_hcyf($a[$i], $b[$i]);
    }
    return $a;
}

/*
 * Signature calculation from secret (used to create corresponding 'code') and string to sign.
 * Used to get 'auth_code'.
 *
 * @param string $secret (128-bit value as hexadecimal 32-char string)
 * @param string $to_sign string to sign with $secret
 * @return string auth_code signature
 */ 
function k_auth_code($secret, $to_sign)
{
    $md5_hex = md5($to_sign . k_code($secret));    
    return k_xor_hex($md5_hex, $secret);
}

/* 
 * Create new account.
 *
 * @param string $acc_type 'AAA' to 'ZZZ' account type
 * @param long $acc_id New account id
 * @param string optional $create_secret Create secret from account type if set
 * @param long $owner account owner
 * @param long $ip ip address
 * @param string optional $access_code Access code
 * @param string optional $withdraw_code Withdraw code
 * @param string optional $comment
 */
function create_account($acc_type, $acc_id, $create_secret, $owner, $ip, $access_code, $withdraw_code, $comment)
{
    global $MC_Money;

    $key = "account$acc_type{$acc_id}";
    $val = "$owner,$ip";
    if ($access_code != null) {
        $val .= ":$access_code";
        if ($withdraw_code != null)
            $val .= ":$withdraw_code";
    }
    if (strlen($comment) > 0)
        $val .= "\t$comment";

    if ($create_secret != null)
        $key .= '#' . k_auth_code($create_secret, "$acc_type{$acc_id}:$val");

    return $MC_Money->add($key, $val);
}

/*
 * Returns account balance, currency and locked amount
 *
 * @param string $acc_type Account type, 'AAA' to 'ZZZ'
 * @param long $acc_id Account ID
 * @param mixed $access_secret optional string access secret
 * @return string "NO_ACCOUNT" | "FORBIDDEN" | "$balance:$currency:$locked"
 */
function get_balance($acc_type, $acc_id, $access_secret)
{
    global $MC_Money;

    $key = "balance$acc_type{$acc_id}";
    if ($access_secret != null)
        $key .= '#' . k_auth_code($access_secret, "$acc_type{$acc_id}");

    return $MC_Money->get($key);
}

/* 
 * Prepares transaction party calculating 'auth_code' if needed
 *
 * @param string $acc_type Account type, 'AAA' to 'ZZZ'
 * @param long $acc_id Account ID
 * @param int $curr_id Currency ID
 * @param long $incr Amount to deposit account with (negative to withdraw)
 * @param int $temp_id Temporary transaction ID
 * @param int $declared_date Transaction date
 * @param string optional $secret to sign withdrawal if accout has 'withdraw_code'
 */
function k_transaction_party($acc_type, $acc_id, $curr_id, $incr, $temp_id, $declared_date, $secret)
{
    $to_sign = "$acc_type{$acc_id}:$curr_id:$incr:$temp_id:$declared_date";
    $auth_code = $secret == null ? null : k_auth_code($secret, $to_sign);
    return array($acc_type, $acc_id, $curr_id, $incr, $auth_code);
}

/* 
 * Crete new temp transaction 
 *
 * @param int $temp_id random transaction temporary id
 * @param array $parties array of [$acc_type, $acc_id, $curr_id, $incr, $auth_code], could be created with 'k_transaction_party'
 * @param int $ip
 * @param int $declared_date
 * @param string optional $comment
 */
function create_temp_transaction($temp_id, $parties, $ip, $declared_date, $comment)
{
    global $MC_Money;
    $tx = count($parties) . "\n";
    foreach ($parties as $p) {
        $tx .= $p[0] . $p[1] . ':' . $p[2] . ':' . $p[3];
        if ($p[4] != null)
            $tx .= ":{$p[4]}";
        $tx .= "\n";
    }
    $tx .= "$ip:$declared_date\n$comment";
    return $MC_Money->add("transaction$temp_id", $tx);
}
 

function delete_temp_transaction($temp_id)
{
    global $MC_Money;
    return $MC_Money->delete("transaction$temp_id");
}


function check_transaction($temp_id)
{
    global $MC_Money;
    return $MC_Money->get("check$temp_id");
}

function lock_transaction($temp_id)
{
    global $MC_Money;
    return $MC_Money->get("lock$temp_id");
}

function commit_transaction($temp_id)
{
    global $MC_Money;
    return $MC_Money->get("commit$temp_id");
}

function long_lock_transaction($temp_id, $seconds)
{
    global $MC_Money;
    return $MC_Money->get("long_lock$temp_id:$seconds");
}

function long_check_transaction($trans_id, $auth_code)
{
    global $MC_Money;
    return $MC_Money->get("long_check$trans_id#$auth_code");
}

function long_cancel_transaction($trans_id, $auth_code)
{
    global $MC_Money;
    return $MC_Money->get("long_cancel$trans_id#$auth_code");
}

function long_commit_transaction($trans_id, $auth_code)
{
    global $MC_Money;
    return $MC_Money->get("long_commit$trans_id#$auth_code");
}

function cancel_committed($trans_id, $sign_time, $ip, $auth_code, $comment)
{
    global $MC_Money;
    return $MC_Money->get("cancel_committed$trans_id,$sign_time,$ip#$auth_code;$comment");
}

function get_account_transactions($acc_type, $acc_id, $flags, $from, $to, $sign_time, $access_secret)
{
    global $MC_Money;

    $key = "account_transactions$acc_type{$acc_id},$flags,$from,$to";
    if ($access_secret != null)
        $key .= ",$sign_time#" . k_auth_code($access_secret, "$acc_type{$acc_id},$sign_time");

    return $MC_Money->get($key);
}

function get_transaction_data($trans_id, $sign_time, $secret)
{
    global $MC_Money;

    $key = "transaction$trans_id";
    if ($sign_time != null)
        $key .= ",$sign_time#" . k_auth_code($secret, "$trans_id,$sign_time");

    return $MC_Money->get($key);
}

function get_system_ready()
{
    global $MC_Money;

    return $MC_Money->get("system_ready");
}

function get_account_ready($acc_type, $acc_id)
{
    global $MC_Money;

    $key = "account_ready:$acc_type{$acc_id}";
    return $MC_Money->get($key);
}
