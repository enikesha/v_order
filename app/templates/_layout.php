<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title><?php echo $page['title'] ?></title>

    <!-- Bootstrap -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/v-order.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script type="text/javascript">
      var VK_APP_ID = <?php echo $page['VK_APP_ID']?>;
    </script>
  </head>
  <body>

    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="/">V-order</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li<?php echo url_active('index')?>><a href="/">Заказы</a></li>
            <li<?php echo url_active('mine')?>><a href="/mine">Мои</a></li>
            <li<?php echo url_active('deposit')?>><a href="/deposit">Счёт</a></li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><?php echo h($page['info']['first_name'])?> <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <?php if (isset($page['balance'])) {?>
                <li class="dropdown-header">Баланс: <span class="balance"><?php echo $page['balance']?></span> руб.</li>
                <?php }?>
                <li><a href="#" id="logout">Выйти</a></li>
              </ul>
            </li>
          </ul>
        </div><!--/.navbar-collapse -->
      </div>
    </nav>

    <div class="jumbotron">
      <div class="container">
        <div class="row">
          <div class="col-sm-3 sidebar">
            <?php if ($page['info']) { ?>
            <div id="page_avatar">
              <img class="img-thumbnail" src="<?php echo $page['photo']?>" alt="<?php echo h($page['name'])?>">
            </div>
            <h4><?php echo h($page['name'])?></h4>
            <?php }?>
            <?php if (isset($page['balance'])) {?>
            <h5>Баланс: <span class="balance"><?php echo $page['balance']?></span> руб.</h5>
            <?php }?>
          </div>

          <div class="col-sm-8 col-sm-offset-1">
            <?php echo $page['content']?>
          </div>
        </div>
      </div>
    </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="/js/bootstrap.min.js"></script>
    <script src="/js/core.js"></script>
    <script src="/js/ajax.js"></script>
    <script src="/js/order.js"></script>
    <script type="text/javascript">
      pages.global();
      <?php if (isset($page['script'])) {
          echo $page['script'];
      }?>
    </script>
  </body>
</html>
