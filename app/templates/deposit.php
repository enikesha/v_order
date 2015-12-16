<?php if (!$page['balance']) {?>
<h1>Ошибка создания счёта</h1>
<?php } else {?>
<h1>Мой счёт</h1>
<h3>Баланс: <span class="balance"><?php echo $page['balance']?></span> руб.</h3>
<!-- <h3>Заблокировано: <?php echo $page['locked']?> руб.</h3> -->

<form class="form-inline">
  <fieldset id="deposit-form"<?php if ($page['deposit']) echo " disabled"?>>
    <div class="form-group">
      <label class="sr-only" for="inputAmount">Сумма (в рублях)</label>
      <div class="input-group">
        <input type="number" class="form-control" id="inputAmount" placeholder="Сумма">
        <div class="input-group-addon">.00 руб</div>
      </div>
    </div>
    <button id="deposit" type="submit" class="btn btn-primary">Пополнить</button>
  </fieldset>
</form>

<div id="verify-form"<?php if (!$page['deposit']) echo ' class="hidden""'?>><br>
  <h4>Введите код подтверждения (<span id="verify-code"><?php if ($page['deposit']) echo $page['deposit']['unlock']?></span>)</h4>
  <form class="form-inline">
    <div class="form-group">
      <label class="sr-only" for="inputVerify">Код подтверждения</label>
      <div class="input-group">
        <input type="number" class="form-control" id="inputVerify" placeholder="Код">
      </div>
    </div>
    <button id="verify" type="submit" class="btn btn-primary">Подтвердить</button>
  </form>
</div>

<?php }?>
