<h1>Система заказов</h1>

<form class="form-horizontal">
  <legend>Добавить заказ</legend>
  <div class="form-group">
    <label for="inputTitle" class="col-sm-2 control-label">Заголовок</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" id="inputTitle" placeholder="Заголовок">
    </div>
  </div>
  <div class="form-group">
    <label for="inputDescription" class="col-sm-2 control-label">Описание</label>
    <div class="col-sm-10">
      <textarea class="form-control" rows="3" id="inputDescription" placeholder="Описание"></textarea>
    </div>
  </div>
  <div class="form-group">
    <label for="inputPrice" class="col-sm-2 control-label">Цена</label>
    <div class="col-sm-3">
      <div class="input-group">
        <input type="number" class="form-control" id="inputPrice" placeholder="Цена">
        <div class="input-group-addon">.00 руб</div>
      </div>
    </div>
    <div class="col-sm-3">
      <button id="add-order" type="submit" class="btn btn-success">Добавить</button>
    </div>
  </div>
</form>

<h3><?php echo($page['mine'] ? "Мои заказы" : "Последние заказы")?></h3>
<?php if (count($page['orders']) > 0) {?>
<hr>
<ul id="orders" class="list-unstyled">
  <?php foreach ($page['orders'] as $i) {
         include "_order.php";
     }?>
</ul>
<?php } else {?>
<p>Заказов пока нет</p>
<?php }?>
