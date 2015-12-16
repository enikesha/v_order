  <li data-id="<?php echo $i['id']?>">
    <div class="row">
      <div class="col-sm-2">
        <a href="https://vk.com/id<?php echo $i['uid']?>">
          <img class="img-thumbnail" src="<?php echo($i['info'] ? $i['info']['photo'] : '//vk.com/images/camera_200.png')?>" alt="<?php echo($i['info'] ? h("{$i['info']['first_name']} {$i['info']['last_name']}") : 'Anonymous')?>">
        </a>
      </div>
      <div class="col-sm-7">
        <p><a href="https://vk.com/id<?php echo $i['uid']?>"><?php echo($i['info'] ? h("{$i['info']['first_name']} {$i['info']['last_name']}") : 'Аноним')?></a></p>
        <h4><?php echo(h($i['title']))?></h4>
        <p><?php echo(h($i['description']))?></p>
        <p><?php echo(strftime("%c", $i['time']))?></p>
      </div>
      <div class="col-sm-3">
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><?php echo(h($i['price']))?> руб.</h3>
          </div>
          <div class="panel-body">
            <?php if ($page['member']['id'] == $i['uid']) {?>
            <button class="btn btn-warning">Отменить</button>
            <?php }?>
            <button class="btn btn-success">Выполнить</button>
          </div>
        </div>
      </div>
    </div>
    <hr>
  </li>
