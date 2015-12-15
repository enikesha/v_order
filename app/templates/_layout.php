<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?php echo $page['title'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

<!--
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/vendor/modernizr-2.8.3.min.js"></script>
    -->
    <script src="js/core.js"></script>
    <script src="js/ajax.js"></script>

  </head>
  <body>
    <header>
      <a href="<?php echo url_for('/')?>">Home</a>
    </header>

    <?php echo $page['content']?>
  </body>
</html>
