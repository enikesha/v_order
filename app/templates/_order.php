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
        <small><?php echo(strftime("%c", $i['time']))?></small>
      </div>
      <div class="col-sm-3">
        <div class="panel panel-<?php switch ($i['flags']) {
          case 0:
          echo('primary');
          break;
          case 2:
          echo('success');
          break;
          case 128:
          echo 'warning';
          }?>">
          <div class="panel-heading">
            <h3 class="panel-title"><?php echo(h($i['price']))?> руб.</h3>
          </div>
          <div class="panel-body text-center">
            <?php if ($i['flags'] == 0) {
              if ($page['member']['id'] == $i['uid']) {?>
            <button class="btn btn-warning" onclick="orderAct(event, 'cancel');">Отменить</button>
            <?php }?>
            <button class="btn btn-success<?php if ($page['member']['id'] == $i['uid']) echo ' hidden'?>" onclick="orderAct(event, 'commit');">Выполнить</button>
            <?php } elseif ($i['flags'] == 2) {?>
            <span>Выполнен</span>
            <?php } else {?>
            <span>Отменен</span>
            <?php }?>
          </div>
        </div>
      </div>
      <div class="col-sm-12"><hr></div>
    </div>
  </li>
