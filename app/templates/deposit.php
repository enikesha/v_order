<?php if (!$page['balance']) {?>
<h1>Ошибка создания счёта</h1>
<?php } else {?>
<h1>Мой счёт</h1>
<h4>Баланс: <?php echo $page['balance']?> руб.</h4>
<!-- <h4>Заблокировано: <?php echo $page['locked']?> руб.</h4> -->

<p>
  <form class="form-inline">
    <div class="form-group">
      <label class="sr-only" for="inputAmount">Сумма (в рублях)</label>
      <div class="input-group">
        <input type="number" class="form-control" id="inputAmount" placeholder="Сумма">
        <div class="input-group-addon">.00 руб</div>
      </div>
    </div>
    <button id="deposit" type="submit" class="btn btn-primary">Пополнить</button>
  </form>
</p>
<?php }?>
