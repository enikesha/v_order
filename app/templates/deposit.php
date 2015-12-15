<?php if (!$page['balance']) {?>
<h1>Ошибка создания счёта</h1>
<?php } else {?>
<h1>Мой счёт</h1>
<h4>Баланс: <?php echo $page['balance']?> руб.</h4>
<h4>Заблокировано: <?php echo $page['locked']?> руб.</h4>
<?php }?>
