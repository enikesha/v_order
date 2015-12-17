<?php

require_once "config.php";
require_once "lib/k_money.php";

$MC_Money = new Memcache;
$MC_Money->connect(MC_MONEY_HOST, MC_MONEY_PORT);

if (create_account("DEP", 1, DEP_TYPE_CREATE_SECRET, 1, 0x7F000001, k_code(DEP_ACCESS_SECRET), k_code(DEP_WITHDRAW_SECRET), '1-deposit')) {
    echo "DEP account created\n";
} else {
    echo "Error creating DEP account\n";
}

if (create_account("MAS", 1, null, 1, 0x7F000001, null, null, '')) {
    echo "MAS account created\n";
} else {
    echo "Error creating MAS account\n";
}
