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
    <div id="vk_api_transport"></div>
    <script type="text/javascript">
      window.vkAsyncInit = function() {
          VK.init({
              apiId: <?php echo $page['VK_APP_ID']?>
          });
          VK.Auth.getLoginStatus(authInfo);
          VK.UI.button('login_button');
      };

      setTimeout(function() {
          var el = document.createElement("script");
          el.type = "text/javascript";
          el.src = "//vk.com/js/api/openapi.js";
          el.async = true;
          document.getElementById("vk_api_transport").appendChild(el);
      }, 0);
    </script>

    <h1>V_order</h1>
    <h2>Please, authenticate yourself</h2>
    <div id="login_button" onclick="VK.Auth.login(authInfo);"></div>

    <script type="text/javascript">
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
                          //document.location.reload();
                      });
                  } 
              }); 
          } else {
              alert('not auth');
          }
      }
    </script>
  </body>
</html>
