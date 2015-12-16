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
  </head>
  <body>
    <div id="vk_api_transport"></div>
    <script type="text/javascript">
      window.vkAsyncInit = function() {
          VK.init({
              apiId: <?php echo $page['VK_APP_ID']?>
          });
          VK.Auth.getLoginStatus(authInfo);
          VK.UI.button('login_button');
      };

      function authInfo(response) {
          if (response.session) {
              var session = response.session;
              VK.Api.call('users.get', {user_ids: response.session.mid, fields:"photo_200"}, function(r) { 
                  if(r.response) { 
                      var user = r.response[0];
                      session.first_name = user.first_name;
                      session.last_name = user.last_name;
                      session.photo = user.photo_200;
                      ajax.post("/auth", session, function(r){
                          document.location.reload();
                      });
                  } 
              }); 
          } else {
              alert('not auth');
          }
      }

      setTimeout(function() {
          var el = document.createElement("script");
          el.type = "text/javascript";
          el.src = "//vk.com/js/api/openapi.js";
          el.async = true;
          document.getElementById("vk_api_transport").appendChild(el);
      }, 0);
    </script>

    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="<?php echo url_for('/')?>">V-order</a>
        </div>
      </div>
    </nav>

    <div class="jumbotron">
      <div class="container">
        <div class="row">
          <div class="col-sm-3 sidebar">
            <img class="img-thumbnail" src="//vk.com/images/camera_200.png" alt="Anonymous">
          </div>

          <div class="col-sm-8 col-sm-offset-1">
            <h2>Система заказов</h2>
            <p>Для работы нужно аутентифицироваться ВКонтакте</p>
            <div id="login_button" onclick="VK.Auth.login(authInfo);"></div>
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
  </body>
</html>
