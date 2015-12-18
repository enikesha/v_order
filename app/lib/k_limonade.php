<?php

# ============================================================================ #
#    0. PREPARE                                                                #
# ============================================================================ #

## CONSTANTS __________________________________________________________________
/**
 * Limonade version
 */

//define('LIMONADE',              '0.5.0');
  //define('LIM_NAME',              'Un grand cru qui sait se faire attendre');
define('LIM_START_MICROTIME',   microtime(true));
//define('LIM_SESSION_NAME',      'LIMONADE'.str_replace('.','x',LIMONADE));
define('LIM_SESSION_FLASH_KEY', '_lim_flash_messages');
  //if(function_exists('memory_get_usage'))
  //	define('LIM_START_MEMORY',      memory_get_usage());
define('E_LIM_HTTP',            32768);
define('E_LIM_PHP',             65536);
define('E_LIM_DEPRECATED',      35000);
define('NOT_FOUND',             404);
define('SERVER_ERROR',          500);
define('ENV_PRODUCTION',        10);
define('ENV_DEVELOPMENT',       100);
define('X-SENDFILE',            10);
define('X-LIGHTTPD-SEND-FILE',  20);


## MAIN PUBLIC FUNCTIONS _______________________________________________________

/**
 * Set and returns options values
 * 
 * If multiple values are provided, set $name option with an array of those values.
 * If there is only one value, set $name option with the provided $values
 *
 * @param string $name 
 * @param mixed  $values,... 
 * @return mixed option value for $name if $name argument is provided, else return all options
 */
function option($name = null, $values = null)
{
  static $options = array();
  $args = func_get_args();
  $name = array_shift($args);
  if(is_null($name)) return $options;
  if(!empty($args))
  {
    $options[$name] = count($args) > 1 ? $args : $args[0];
  }
  if(array_key_exists($name, $options)) return $options[$name];
  return;
}

/**
 * Set and returns params
 * 
 * Depending on provided arguments:
 * 
 *  * Reset params if first argument is null
 * 
 *  * If first argument is an array, merge it with current params
 * 
 *  * If there is a second argument $value, set param $name (first argument) with $value
 * <code>
 *  params('name', 'Doe') // set 'name' => 'Doe'
 * </code>
 *  * If there is more than 2 arguments, set param $name (first argument) value with
 *    an array of next arguments
 * <code>
 *  params('months', 'jan', 'feb', 'mar') // set 'month' => array('months', 'jan', 'feb', 'mar')
 * </code>
 * 
 * @param mixed $name_or_array_or_null could be null || array of params || name of a param (optional)
 * @param mixed $value,... for the $name param (optional)
 * @return mixed all params, or one if a first argument $name is provided
 */
function params($name_or_array_or_null = null, $value = null)
{
  static $params = array();
  $args = func_get_args();

  if(func_num_args() > 0)
  {
    $name = array_shift($args);
    if(is_null($name))
    {
      # Reset params
      $params = array();
      return $params;
    }
    if(is_array($name))
    {
      $params = array_merge($params, $name);
      return $params;
    }
    $nargs = count($args);
    if($nargs > 0)
    {
      $value = $nargs > 1 ? $args : $args[0];
      $params[$name] = $value;
    }
    return array_key_exists($name,$params) ? $params[$name] : null;
  }

  return $params;
}

/**
 * Running application
 *
 * @param string $env 
 * @return void
 */
function run($env = null)
{
  if(is_null($env)) $env = env();
   
  # 0. Set default configuration
  $root_dir  = dirname(app_file());
  $lim_dir   = dirname(__FILE__);
  $base_path = dirname(file_path($env['SERVER']['SCRIPT_NAME']));
  $base_file = basename($env['SERVER']['SCRIPT_NAME']);
  $base_uri  = file_path($base_path, (($base_file == 'index.php') ? '?' : $base_file.'?'));
  
  option('root_dir',           $root_dir);
  //option('limonade_dir',       file_path($lim_dir));
  //option('limonade_views_dir', file_path($lim_dir, 'limonade', 'views'));
  //option('limonade_public_dir',file_path($lim_dir, 'limonade', 'public'));
  option('public_dir',         file_path($root_dir, 'public'));
  option('views_dir',          file_path($root_dir, 'views'));
  option('controllers_dir',    file_path($root_dir, 'controllers'));
  option('lib_dir',            file_path($root_dir, 'lib'));
  //option('error_views_dir',    option('limonade_views_dir'));
  //option('base_path',          $base_path);
  option('base_uri',           $base_uri); // set it manually if you use url_rewriting
  //  option('env',                ENV_PRODUCTION);
  option('debug',              true);
  //option('session',            LIM_SESSION_NAME); // true, false or the name of your session
  option('encoding',           'utf-8');
  //option('signature',          LIM_NAME); // X-Limonade header value or false to hide it
  option('gzip',               false);
  option('x-sendfile',         0); // 0: disabled, 
                                   // X-SENDFILE: for Apache and Lighttpd v. >= 1.5,
                                   // X-LIGHTTPD-SEND-FILE: for Apache and Lighttpd v. < 1.5


  # 1. Set handlers
  # 1.1 Set error handling
#ifndef KittenPHP
  ini_set('display_errors', 1);
#endif
  //set_error_handler('error_handler_dispatcher', E_ALL ^ E_NOTICE);

  # 1.2 Register shutdown function
  register_shutdown_function('stop_and_exit');

  # 2. Set user configuration
  if (!function_exists('configure')) {
      function configure() {}
  }
  configure();
  
  # 2.1 Set gzip compression if defined
  if(is_bool(option('gzip')) && option('gzip'))
  {
    ini_set('zlib.output_compression', '1');
  }
  
  # 2.2 Set X-Limonade header
  //if($signature = option('signature')) send_header("X-Limonade: $signature");

  # 3. Loading libs
  //fallbacks_for_not_implemented_functions();

  # 4. Starting session
  //if(!defined('SID') && option('session'))
  //  {
  //    if(!is_bool(option('session'))) session_name(option('session'));
  //    if(!session_start()) trigger_error("An error occured while trying to start the session", E_USER_WARNING);
  //  }

  # 5. Set some default methods if needed
  if(!function_exists('route_missing'))
  {
    function route_missing($request_method, $request_uri)
    {
      halt(NOT_FOUND, "($request_method) $request_uri");
    }
  }

  if (!function_exists('initialize')) {
      function initialize() {}
  }
  initialize();

  # 6. Check request
  if($rm = request_method($env))
  {
    if(request_is_head($env)) ob_start(); // then no output

    if(!request_method_is_allowed($rm))
      halt(HTTP_NOT_IMPLEMENTED, "The requested method <code>'$rm'</code> is not implemented");

    # 6.1 Check matching route
    if($route = route_find($rm, request_uri($env)))
    {
      params($route['params']);

      # 6.3 Call before function
      if (!function_exists('before')) {
          function before($route) {}
      }
      before($route);

      # 6.4 Call matching controller function and output result
      return $route;
    }
    else route_missing($rm, request_uri($env));

  }
  else halt(HTTP_NOT_IMPLEMENTED, "The requested method <code>'$rm'</code> is not implemented");
}

/**
 * Stop and exit limonade application
 *
 * @access private 
 * @param boolean exit or not
 * @return void
 */
function stop_and_exit()
{
    stop_may_exit(true);
}

function stop_may_exit($exit = true)
{
    //call_if_exists('before_exit', $exit);
    //$headers = headers_list();
    if(request_is_head())
    { 
        ob_end_clean();
    }    
    //if(defined('SID')) session_write_close();
    if($exit) exit;
}


/**
 * Returns limonade environment variables:
 *
 * 'SERVER', 'FILES', 'REQUEST', 'SESSION', 'ENV', 'COOKIE', 
 * 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'
 * 
 * If a null argument is passed, reset and rebuild environment
 *
 * @param null @reset reset and rebuild environment
 * @return array
 */
function env($reset = null)
{
  static $env = array();
  if(func_num_args() > 0)
  {
    $args = func_get_args();
    if(is_null($args[0])) $env = array();
  }

  if(empty($env))
  {
      $env['SERVER'] = isset($_SERVER) ? $_SERVER : array();
      $env['FILES'] = isset($FILES) ? $_FILES : array();
      $env['REQUEST'] = isset($REQUEST) ? $_REQUEST : array();
      $env['SESSION'] = isset($_SESSION) ? $_SESSION : array();
      $env['ENV'] = isset($_ENV) ? $_ENV : array();
      $env['COOKIE'] = isset($_COOKIE) ? $_COOKIE : array();
      $env['GET'] = isset($_GET) ? $_GET : array();
      $env['POST'] = isset($_POST) ? $_POST : array();
      $env['PUT'] = isset($_PUT) ? $_PUT : array();
      //$env['DELETE'] =& $_DELETE;
      //$env['PATCH'] =& $_PATCH;

      $method = request_method($env);

      if (isset($_SERVER['CONTENT_TYPE']) && (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0))
      {
          // handle PUT/POST requests which have JSON in request body
          if ($method == 'PUT') {
              $env['PUT'] = json_decode(file_get_contents('php://input'), true);
          } elseif ($method == 'POST') {
              $env['POST'] = json_decode(file_get_contents('php://input'), true);
          }
      }
      elseif($method == 'PUT' || $method == 'DELETE')
      {
          if(array_key_exists('_method', $_POST) && $_POST['_method'] == $method)
          {
              foreach($_POST as $k => $v)
              {
                  if($k == "_method") continue;
                  $env['PUT'][$k] = $v;
              }
          }
          else
          {
              parse_str(file_get_contents('php://input'), $env['PUT']);
          }
      }
  }
  return $env;
}

# ============================================================================ #
#    2. ERROR                                                                  #
# ============================================================================ #
 
/**
 * Associate a function with error code(s) and return all associations
 *
 * @param string $errno 
 * @param string $function 
 * @return array
 */

//function error($errno = null, $function = null)
//{
//  static $errors = array();
//  if(func_num_args() > 0)
//  {
//    $errors[] = array('errno'=>$errno, 'function'=> $function);
//  }
//  return $errors;
//}

/**
 * Raise an error, passing a given error number and an optional message,
 * then exit.
 * Error number should be a HTTP status code or a php user error (E_USER...)
 * $errno and $msg arguments can be passsed in any order
 * If no arguments are passed, default $errno is SERVER_ERROR (500)
 *
 * @param int,string $errno Error number or message string
 * @param string,string $msg Message string or error number
 * @param mixed $debug_args extra data provided for debugging
 * @return void
 */
function halt($errno = SERVER_ERROR, $msg = '', $debug_args = null)
{
  $args = func_get_args();
  $error = array_shift($args);

  # switch $errno and $msg args
  # TODO cleanup / refactoring
  if(is_string($errno))
  {
   $msg = $errno;
   $oldmsg = array_shift($args);
   $errno = empty($oldmsg) ? SERVER_ERROR : $oldmsg;
  }
  else if(!empty($args)) $msg = array_shift($args);

  if(empty($msg) && $errno == NOT_FOUND) $msg = request_uri();
  if(empty($msg)) $msg = "";
  if(!empty($args)) $debug_args = $args;
  set('_lim_err_debug_args', $debug_args);

  error_handler_dispatcher($errno, $msg, null, null);

}

/**
 * Internal error handler dispatcher
 * Find and call matching error handler and exit
 * If no match found, call default error handler
 *
 * @access private
 * @param int $errno 
 * @param string $errstr 
 * @param string $errfile 
 * @param string $errline 
 * @return void
 */
function error_handler_dispatcher($errno, $errstr, $errfile, $errline)
{
  $back_trace = debug_backtrace();
  while(!empty($back_trace) && ($trace = array_shift($back_trace)))
  {
    if($trace['function'] == 'halt')
    {
      $errfile = $trace['file'];
      $errline = $trace['line'];
      break;
    }
  }  

  # Notices and warning won't halt execution
  if(error_wont_halt_app($errno))
  {
    error_notice($errno, $errstr, $errfile, $errline);
  	return;
  }
  else
  {
    # Other errors will stop application
      //static $handlers = array();
      //if(empty($handlers))
      //{
      //error(E_LIM_PHP, 'error_default_handler');
      //$handlers = error();
      //}
    
      //$is_http_err = http_response_status_is_valid($errno);
      switch ($errno)
      {
      default:
          error_default_handler($errno, $errstr, $errfile, $errline);
          //exit;
      }
      
      //while($handler = array_shift($handlers))
      //{
      //$e = is_array($handler['errno']) ? $handler['errno'] : array($handler['errno']);
      //while($ee = array_shift($e))
      //{
      //if($ee == $errno || $ee == E_LIM_PHP || ($ee == E_LIM_HTTP && $is_http_err))
      //{
      //  echo call_if_exists($handler['function'], $errno, $errstr, $errfile, $errline);
      //  exit;
      //}
      //}
      //}
  }
}


/**
 * Default error handler
 *
 * @param string $errno 
 * @param string $errstr 
 * @param string $errfile 
 * @param string $errline 
 * @return string error output
 */
function error_default_handler($errno, $errstr, $errfile, $errline)
{
  $is_http_err = http_response_status_is_valid($errno);
  $http_error_code = $is_http_err ? $errno : SERVER_ERROR;

  status($http_error_code);

  return $http_error_code == NOT_FOUND ?
            error_not_found_output($errno, $errstr, $errfile, $errline) :
            error_server_error_output($errno, $errstr, $errfile, $errline);                    
}

/**
 * Returns not found error output
 *
 * @access private
 * @param string $msg 
 * @return string
 */
function error_not_found_output($errno, $errstr, $errfile, $errline)
{
  if(!function_exists('not_found'))
  {
    /**
     * Default not found error output
     *
     * @param string $errno 
     * @param string $errstr 
     * @param string $errfile 
     * @param string $errline 
     * @return string
     */
    function not_found($errno, $errstr, $errfile=null, $errline=null)
    {
        //option('views_dir', option('error_views_dir'));
        $msg = h(rawurldecode($errstr));
        return error_html("<h1>Page not found:</h1><p><code>{$msg}</code></p>");
    }
  }
  return not_found($errno, $errstr, $errfile, $errline);
}

/**
 * Returns server error output
 *
 * @access private
 * @param int $errno 
 * @param string $errstr 
 * @param string $errfile 
 * @param string $errline 
 * @return string
 */
function error_server_error_output($errno, $errstr, $errfile, $errline)
{
  if(!function_exists('server_error'))
  {
    /**
     * Default server error output
     *
     * @param string $errno 
     * @param string $errstr 
     * @param string $errfile 
     * @param string $errline 
     * @return string
     */
    function server_error($errno, $errstr, $errfile=null, $errline=null)
    {
      $is_http_error = http_response_status_is_valid($errno);
      $html = render_error($errno, $errstr, $errfile, $errline, $is_http_error);	
      return error_html($html);
    }
  }
  return server_error($errno, $errstr, $errfile, $errline);
}


function render_error($errno, $errstr, $errfile, $errline, $is_http_error)
{
    ob_start();?>
    <h1><?php echo h(error_http_status($errno));?></h1>
    <?php if($is_http_error) { ?>
    <p><?php echo h($errstr)?></p>
    <?php } ?>
<?php
    return ob_get_clean();
}

function error_html($content)
{
    ob_start();?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Limonade, the fizzy PHP micro-framework</title>
</head>
<body>
  <div id="header">
    <h1>Error</h1>
  </div>
  
  <div id="content">
    <?php echo error_notices_render();?>
    <div id="main">
      <?php echo $content;?>
      <hr class="space">
    </div>
  </div>
</body>
</html>
<?php
    $html = ob_get_clean();
    html($html);
}

/**
 * Set a notice if arguments are provided
 * Returns all stored notices.
 * If $errno argument is null, reset the notices array
 *
 * @access private
 * @param string, null $str 
 * @return array
 */
function error_notice($errno = false, $errstr = null, $errfile = null, $errline = null)
{
  static $notices = array();
  if($errno) $notices[] = array('errno' => $errno, 'errstr' => $errstr, 'errfile' => $errfile, 'errline' => $errline);
  else if(is_null($errno)) $notices = array();
  return $notices;
}

/**
 * Returns notices output rendering and reset notices
 *
 * @return string
 */
function error_notices_render()
{
  if(option('debug') && option('env') > ENV_PRODUCTION)
  {
    $notices = error_notice();
    error_notice(null); // reset notices
    if (empty($notices))
        return '';

    ob_start();?>
<div class="lim-debug lim-notices">
  <h4> &#x2192; Notices and warnings</h4>
  <dl>
    <?php $cpt=1; foreach($notices as $notice) { ?>
    <dt>[<?php echo $cpt.'. '.error_type($notice['errno'])?>]</dt>
    <dd>
      <?php echo $notice['errstr']?> in <strong><code><?php echo $notice['errfile']?></code></strong>
      line <strong><code><?php echo $notice['errline']?></code></strong>
    </dd>
    <?php $cpt++; } ?>
  </dl>
  <hr>
</div>
<?php
    return ob_get_clean();
  }
}


/**
 * Set and returns error output layout
 *
 * @param string $layout 
 * @return string
 */
//function error_layout($layout = false)
//{
//  static $o_layout = 'default_layout.php';
//  if($layout !== false)
//  {
//    option('error_views_dir', option('views_dir'));
//    $o_layout = $layout;
//  }
//  return $o_layout;
//}


/**
 * Checks if an error is will halt application execution. 
 * Notices and warnings will not.
 *
 * @access private
 * @param string $num error code number
 * @return boolean
 */
function error_wont_halt_app($num)
{
  return $num == E_NOTICE ||
         $num == E_WARNING ||
         $num == E_CORE_WARNING ||
         $num == E_COMPILE_WARNING ||
         $num == E_USER_WARNING ||
         $num == E_USER_NOTICE ||
         $num == E_DEPRECATED ||
         $num == E_USER_DEPRECATED ||
         $num == E_LIM_DEPRECATED;
}



/**
 * return error code name for a given code num, or return all errors names
 *
 * @param string $num 
 * @return mixed
 */
function error_type($num = null)
{
  $types = array (
              E_ERROR              => 'ERROR',
              E_WARNING            => 'WARNING',
              E_PARSE              => 'PARSING ERROR',
              E_NOTICE             => 'NOTICE',
              E_CORE_ERROR         => 'CORE ERROR',
              E_CORE_WARNING       => 'CORE WARNING',
              E_COMPILE_ERROR      => 'COMPILE ERROR',
              E_COMPILE_WARNING    => 'COMPILE WARNING',
              E_USER_ERROR         => 'USER ERROR',
              E_USER_WARNING       => 'USER WARNING',
              E_USER_NOTICE        => 'USER NOTICE',
              E_STRICT             => 'STRICT NOTICE',
              E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR',
              E_DEPRECATED         => 'DEPRECATED WARNING',
              E_USER_DEPRECATED    => 'USER DEPRECATED WARNING',
              E_LIM_DEPRECATED     => 'LIMONADE DEPRECATED WARNING'
              );
  return is_null($num) ? $types : $types[$num];
}

/**
 * Returns http response status for a given error number
 *
 * @param string $errno 
 * @return int
 */
function error_http_status($errno)
{
  $code = http_response_status_is_valid($errno) ? $errno : SERVER_ERROR;
  return http_response_status($code);
}




# ============================================================================ #
#    3. REQUEST                                                                #
# ============================================================================ #
 
/**
 * Returns allowed request methods
 *
 * @return array
 */
function request_methods()
{
  return array("GET","POST","PUT","DELETE","HEAD","PATCH");
}

/**
 * Returns current request method for a given environment or current one
 *
 * @param string $env 
 * @return string
 */
function request_method($env = null)
{
  if(is_null($env)) $env = env();
  $m = array_key_exists('REQUEST_METHOD', $env['SERVER']) ? $env['SERVER']['REQUEST_METHOD'] : null;
  if($m == "POST" && array_key_exists('_method', $env['POST'])) 
    $m = strtoupper($env['POST']['_method']);
  if(!in_array(strtoupper($m), request_methods()))
  {
    trigger_error("'$m' request method is unknown or unavailable.", E_USER_WARNING);
    $m = false;
  }
  return $m;
}

/**
 * Checks if a request method or current one is allowed
 *
 * @param string $m 
 * @return bool
 */
function request_method_is_allowed($m = null)
{
  if(is_null($m)) $m = request_method();
  return in_array(strtoupper($m), request_methods());
}

/**
 * Checks if request method is GET
 *
 * @param string $env 
 * @return bool
 */
function request_is_get($env = null)
{
  return request_method($env) == "GET";
}

/**
 * Checks if request method is POST
 *
 * @param string $env 
 * @return bool
 */
function request_is_post($env = null)
{
  return request_method($env) == "POST";
}

/**
 * Checks if request method is PUT
 *
 * @param string $env 
 * @return bool
 */
function request_is_put($env = null)
{
  return request_method($env) == "PUT";
}

/**
 * Checks if request method is DELETE
 *
 * @param string $env 
 * @return bool
 */
function request_is_delete($env = null)
{
  return request_method($env) == "DELETE";
}

/**
 * Checks if request method is HEAD
 *
 * @param string $env 
 * @return bool
 */
function request_is_head($env = null)
{
  return request_method($env) == "HEAD";
}

/**
 * Checks if request method is PATCH
 *
 * @param string $env
 * @return bool
 */
function request_is_patch($env = null)
{
  return request_method($env) == "PATCH";
}


/**
 * Returns current request uri (the path that will be compared with routes)
 * 
 * (Inspired from codeigniter URI::_fetch_uri_string method)
 *
 * @return string
 */
function request_uri($env = null)
{
  static $uri = null;
  if(is_null($env))
  {
    if(!is_null($uri)) return $uri;
    $env = env();
  }

  if(array_key_exists('uri', $env['GET']))
  {
    $uri = $env['GET']['uri'];
  }
  else if(array_key_exists('u', $env['GET']))
  {
    $uri = $env['GET']['u'];
  }
  // bug: dot are converted to _... so we can't use it...
  // else if (count($env['GET']) == 1 && trim(key($env['GET']), '/') != '')
  // {
  //  $uri = key($env['GET']);
  // }
  else
  {
    $app_file = app_file();
    $path_info = isset($env['SERVER']['PATH_INFO']) ? $env['SERVER']['PATH_INFO'] : '';
    $query_string =  isset($env['SERVER']['QUERY_STRING']) ? $env['SERVER']['QUERY_STRING'] : '';
    // Is there a PATH_INFO variable?
    // Note: some servers seem to have trouble with getenv() so we'll test it two ways
    if (trim($path_info, '/') != '' && $path_info != "/".$app_file)
    {
      if(strpos($path_info, '&') !== 0)
      {
        # exclude GET params
        $params = explode('&', $path_info);
        $path_info = array_shift($params);
        # populate $_GET
        foreach($params as $param)
        {
          if(strpos($param, '=') > 0)
          {
            list($k, $v) = explode('=', $param);
            $env['GET'][$k] = $v;
          }
        }
      }
      $uri = $path_info;
    }
    // No PATH_INFO?... What about QUERY_STRING?
    elseif (trim($query_string, '/') != '' && $query_string[0] == '/')
    {
      $uri = $query_string;
      $get = $env['GET'];
      if(count($get) > 0)
      {
        # exclude GET params
        $keys  = array_keys($get);
        $first = array_shift($keys);
        if(strpos($query_string, $first) === 0) $uri = $first;
      }
    }
    elseif(array_key_exists('REQUEST_URI', $env['SERVER']) && !empty($env['SERVER']['REQUEST_URI']))
    {
      $request_uri = $env['SERVER']['REQUEST_URI'];
#ifndef KittenPHP
#      $request_uri = rtrim($env['SERVER']['REQUEST_URI'], '?/').'/';
#endif
      $base_path = $env['SERVER']['SCRIPT_NAME'];

      if($request_uri."index.php" == $base_path) $request_uri .= "index.php";

      $uri = $request_uri;
#ifndef KittenPHP
#      $uri = str_replace($base_path, '', $request_uri);
#endif

      #if(option('base_uri') && strpos($uri, option('base_uri')) === 0) {
      #  $uri = substr($uri, strlen(option('base_uri')));
      #}
      if(strpos($uri, '?') !== false) {
      	$uri = substr($uri, 0, strpos($uri, '?')) . '/';
      }
    }
    elseif($env['SERVER']['argc'] > 1 && trim($env['SERVER']['argv'][1], '/') != '')
    {
      $uri = $env['SERVER']['argv'][1];
    }
  }

  $uri = rtrim($uri, "/"); # removes ending /
  if(empty($uri))
  {
    $uri = '/';
  }
  else if($uri[0] != '/')
  {
    $uri = '/' . $uri; # add a leading slash
  }
  return rawurldecode($uri);
}



# ============================================================================ #
#    4. ROUTER                                                                 #
# ============================================================================ #
 
/**
 * An alias of {@link dispatch_get()}
 *
 * @return void
 */
function dispatch($path_or_array, $callback, $options = array())
{
  dispatch_get($path_or_array, $callback, $options);
}

/**
 * Add a GET route. Also automatically defines a HEAD route.
 *
 * @param string $path_or_array 
 * @param string $callback
 * @param array $options (optional). See {@link route()} for available options.
 * @return void
 */
function dispatch_get($path_or_array, $callback, $options = array())
{
  route("GET", $path_or_array, $callback, $options);
  route("HEAD", $path_or_array, $callback, $options);
}

/**
 * Add a POST route
 *
 * @param string $path_or_array 
 * @param string $callback
 * @param array $options (optional). See {@link route()} for available options.
 * @return void
 */
function dispatch_post($path_or_array, $callback, $options = array())
{
  route("POST", $path_or_array, $callback, $options);
}

/**
 * Add a PUT route
 *
 * @param string $path_or_array 
 * @param string $callback
 * @param array $options (optional). See {@link route()} for available options.
 * @return void
 */
function dispatch_put($path_or_array, $callback, $options = array())
{
  route("PUT", $path_or_array, $callback, $options);
}

/**
 * Add a DELETE route
 *
 * @param string $path_or_array 
 * @param string $callback
 * @param array $options (optional). See {@link route()} for available options.
 * @return void
 */
function dispatch_delete($path_or_array, $callback, $options = array())
{
  route("DELETE", $path_or_array, $callback, $options);
}

/**
 * Add a PATCH route
 *
 * @param string $path_or_array
 * @param string $callback
 * @param array $options (optional). See {@link route()} for available options.
 * @return void
 */
function dispatch_patch($path_or_array, $callback, $options = array())
{
  route("PATCH", $path_or_array, $callback, $options);
}


/**
 * Add route if required params are provided.
 * Delete all routes if null is passed as a unique argument
 * Return all routes
 * 
 * @see route_build()
 * @access private
 * @param string $method 
 * @param string|array $path_or_array 
 * @param callback $func
 * @param array $options (optional). Available options: 
 *   - 'params' key with an array of parameters: for parametrized routes.
 *     those parameters will be merged with routes parameters.
 * @return array
 */
function route()
{
  static $routes = array();
  $nargs = func_num_args();
  if( $nargs > 0)
  {
    $args = func_get_args();
    if($nargs === 1 && is_null($args[0])) $routes = array();
    else if($nargs < 3) trigger_error("Missing arguments for route()", E_USER_ERROR);
    else
    {
      $method        = $args[0];
      $path_or_array = $args[1];
      $func          = $args[2];
      $options       = $nargs > 3 ? $args[3] : array();

      $routes[] = route_build($method, $path_or_array, $func, $options);
    }
  }
  return $routes;
}

/**
 * An alias of route(null): reset all routes
 * 
 * @access private
 * @return void
 */
function route_reset()
{
  route(null);
}

/**
 * Build a route and return it
 *
 * @access private
 * @param string $method allowed http method (one of those returned by {@link request_methods()})
 * @param string|array $path_or_array 
 * @param callback $callback callback called when route is found. It can be
 *   a function, an object method, a static method or a closure.
 *   See {@link http://php.net/manual/en/language.pseudo-types.php#language.types.callback php documentation}
 *   to learn more about callbacks.
 * @param array $options (optional). Available options: 
 *   - 'params' key with an array of parameters: for parametrized routes.
 *     those parameters will be merged with routes parameters.
 * @return array array with keys "method", "pattern", "names", "callback", "options"
 */
function route_build($method, $path_or_array, $callback, $options = array())
{
  $method = strtoupper($method);
  if(!in_array($method, request_methods())) 
    trigger_error("'$method' request method is unkown or unavailable.", E_USER_WARNING);

  if(is_array($path_or_array))
  {
    $path  = array_shift($path_or_array);
    $names = $path_or_array[0];
  }
  else
  {
    $path  = $path_or_array;
    $names = array();
  }

  $single_asterisk_subpattern   = "(?:/([^\/]*))?";
  $double_asterisk_subpattern   = "(?:/(.*))?";
  $optionnal_slash_subpattern   = "(?:/*?)";
  $no_slash_asterisk_subpattern = "(?:([^\/]*))?";

  if($path[0] == "^")
  {
    if($path{strlen($path) - 1} != "$") $path .= "$";
     $pattern = "#".$path."#i";
  }
  else if(empty($path) || $path == "/")
  {
    $pattern = "#^".$optionnal_slash_subpattern."$#";
  }
  else
  {
    $parsed = array();
    $elts = explode('/', $path);

    $parameters_count = 0;

    foreach($elts as $elt)
    {
      if(empty($elt)) continue;

      $name = null; 

      # extracting double asterisk **
      if($elt == "**") {
        $parsed[] = $double_asterisk_subpattern;
        $name = $parameters_count;

      # extracting single asterisk *
      } elseif($elt == "*") {
        $parsed[] = $single_asterisk_subpattern;
        $name = $parameters_count;

      # extracting named parameters :my_param 
      } elseif($elt[0] == ":") {
        if(preg_match('/^:([^\:]+)$/', $elt, $matches))
        {
          $parsed[] = $single_asterisk_subpattern;
          $name = $matches[1];
        };

      } elseif(strpos($elt, '*') !== false) {
        $sub_elts = explode('*', $elt);
        $parsed_sub = array();
        foreach($sub_elts as $sub_elt)
        {
          $parsed_sub[] = preg_quote($sub_elt, "#");
          $name = $parameters_count;
        }
        // 
        $parsed[] = "/".implode($no_slash_asterisk_subpattern, $parsed_sub);

      } else {
        $parsed[] = "/".preg_quote($elt, "#");
      }

      /* set parameters names */ 
      if(is_null($name)) continue;
      if(!array_key_exists($parameters_count, $names) || is_null($names[$parameters_count]))
        $names[$parameters_count] = $name;
      $parameters_count++;
    }

    $pattern = "#^".implode('', $parsed).$optionnal_slash_subpattern."?$#i";
  }

  return array( "method"       => $method,
                "pattern"      => $pattern,
                "names"        => $names,
                "callback"     => $callback,
                "options"      => $options  );
}

/**
 * Find a route and returns it.
 * Parameters values extracted from the path are added and merged 
 * with the default 'params' option of the route
 * If not found, returns false.
 * Routes are checked from first added to last added.
 *
 * @access private
 * @param string $method 
 * @param string $path
 * @return array,false route array has same keys as route returned by 
 *  {@link route_build()} ("method", "pattern", "names", "callback", "options")
 *  + the processed "params" key
 */
function route_find($method, $path)
{
  $routes = route();
  $method = strtoupper($method);
  foreach($routes as $route)
  {
    if($method == $route["method"] && preg_match($route["pattern"], $path, $matches))
    {
      $options = $route["options"];
      $params = array_key_exists('params', $options) ? $options["params"] : array();
      if(count($matches) > 1)
      {
        array_shift($matches);
        $n_matches = count($matches);
        $names     = array_values($route["names"]);
        $n_names   = count($names);
        if( $n_matches < $n_names )
        {
          $a = array_fill(0, $n_names - $n_matches, null);
          $matches = array_merge($matches, $a);
        }
        else if( $n_matches > $n_names )
        {
          $names = range($n_names, $n_matches - 1);
        }
        $arr_comb = array_combine($names, $matches);
        $params = array_replace($params, $arr_comb);
      }
      $route["params"] = $params;
      return $route;
    }
  }
  return false;
}


/**
 * Call before_sending_header() if it exists, then send headers
 * 
 * @param string $header
 * @return void
 */
function send_header($header = null, $replace = true, $code = false)
{
    //    if(!headers_sent()) 
    //{
    //call_if_exists('before_sending_header', $header);
    header($header, $replace, $code);
    //}
}

/**
 * Returns html output with proper http headers
 *
 * @param string $content_or_func 
 * @param string $layout 
 * @param string $locals 
 * @return string
 */ 
function html($content)
{
  send_header('Content-Type: text/html; charset='.strtolower(option('encoding')));
  echo $content;
}
                                     # # #




# ============================================================================ #
#    6. HELPERS                                                                #
# ============================================================================ #

/**
 * Returns an url composed of params joined with /
 * A param can be a string or an array.
 * If param is an array, its members will be added at the end of the return url
 * as GET parameters "&key=value".
 *
 * @param string or array $param1, $param2 ... 
 * @return string
 */ 
function url_for($params = null)
{
  $paths  = array();
  $params = func_get_args();
  $GET_params = array();
  foreach($params as $param)
  {
    if(is_array($param))
    {
      $GET_params = array_merge($GET_params, $param);
      continue;
    }
    if(filter_var_url($param))
    {
      $paths[] = $param;
      continue;
    }
    $p = explode('/',$param);
    foreach($p as $v)
    {
      if($v != "") $paths[] = str_replace('%23', '#', rawurlencode($v));
    }
  }

  $path = rtrim(implode('/', $paths), '/');
  
  if(!filter_var_url($path)) 
  {
    # it's a relative URL or an URL without a schema
    $base_uri = option('base_uri');
    $path = file_path($base_uri, $path);
  }
  
  if(!empty($GET_params))
  {
    $is_first_qs_param = true;
    $path_as_no_question_mark = strpos($path, '?') === false;
      
    foreach($GET_params as $k => $v)
    {
      $qs_separator = $is_first_qs_param && $path_as_no_question_mark ? 
                        '?' : '&amp;'; 
      $path .= $qs_separator . rawurlencode($k) . '=' . rawurlencode($v);
      $is_first_qs_param = false;
    }
  }
  
  return $path;
}

/**
 * Check if a string is an url
 *
 * This implementation no longer requires 
 * {@link http://www.php.net/manual/en/book.filter.php the filter extenstion}, 
 * so it will improve compatibility with older PHP versions.
 *
 * @param string $str 
 * @return false, str   the string if true, false instead
 */
function filter_var_url($str)
{
  $regexp = '@^https?://([-[:alnum:]]+\.)+[a-zA-Z]{2,6}(:[0-9]+)?(.*)?$@';
  $options = array( "options" => array("regexp" => $regexp ));
  return preg_match($regexp, $str) ? $str : false;
}

/**
 * Create a file path by concatenation of given arguments.
 * Windows paths with backslash directory separators are normalized in *nix paths.
 *
 * @param string $path, ... 
 * @return string normalized path
 */
function file_path($path)
{
  $args = func_get_args();
  $ds = '/'; 
  $win_ds = '\\';
  $n_path = count($args) > 1 ? implode($ds, $args) : $path;
  if(strpos($n_path, $win_ds) !== false) $n_path = str_replace( $win_ds, $ds, $n_path );
  $n_path = preg_replace( "#$ds+#", $ds, $n_path);
  
  return $n_path;
}


/**
 * Returns application root file path
 *
 * @return string
 */
function app_file()
{
  static $file;
  if(empty($file))
  {
    $debug_backtrace = debug_backtrace();
    $stacktrace = array_pop($debug_backtrace);
    $file = $stacktrace['file'];
  }
  return file_path($file);
}


/**
 * An alias of {@link htmlspecialchars()}.
 * If no $charset is provided, uses option('encoding') value
 *
 * @param string $str 
 * @param string $quote_style 
 * @param string $charset 
 * @return void
 */
function h($str, $quote_style = ENT_NOQUOTES, $charset = null)
{
    // if(is_null($charset)) $charset = strtoupper(option('encoding'));
    return htmlspecialchars($str, $quote_style); //, $charset); 
}


/**
 * Set and returns template variables
 * 
 * If multiple values are provided, set $name variable with an array of those values.
 * If there is only one value, set $name variable with the provided $values
 *
 * @param string $name 
 * @param mixed  $values,... 
 * @return mixed variable value for $name if $name argument is provided, else return all variables
 */
function set($name = null, $values = null)
{
  global $page;
  $args = func_get_args();
  $name = array_shift($args);
  if(is_null($name)) return $page;
  if(!empty($args))
  {
    $page[$name] = count($args) > 1 ? $args : $args[0];
  }
  if(array_key_exists($name, $page)) return $page[$name];
  return $page;
}

if (!function_exists('trigger_error')) {
    function trigger_error($message, $errno)
    {
        echo $message;
        exit($errno);
    }
}


if (!function_exists('array_replace')) {
    function array_replace( $array, $array1 )
    {
        $args  = func_get_args();
        $count = func_num_args();
        
        for ($i = 0; $i < $count; ++$i)
        {
            if(is_array($args[$i]))
            {
                foreach ($args[$i] as $key => $val) $array[$key] = $val;
            }
            else
            {
                trigger_error(
                    __FUNCTION__ . '(): Argument #' . ($i+1) . ' is not an array',
                    E_USER_WARNING
                );
                return null;
            }
        }
        return $array;
    }
}

## HTTP utils  _________________________________________________________________


### Constants: HTTP status codes

define( 'HTTP_CONTINUE',                      100 );
define( 'HTTP_SWITCHING_PROTOCOLS',           101 );
define( 'HTTP_PROCESSING',                    102 );
define( 'HTTP_OK',                            200 );
define( 'HTTP_CREATED',                       201 );
define( 'HTTP_ACCEPTED',                      202 );
define( 'HTTP_NON_AUTHORITATIVE',             203 );
define( 'HTTP_NO_CONTENT',                    204 );
define( 'HTTP_RESET_CONTENT',                 205 );
define( 'HTTP_PARTIAL_CONTENT',               206 );
define( 'HTTP_MULTI_STATUS',                  207 );
                                              
define( 'HTTP_MULTIPLE_CHOICES',              300 );
define( 'HTTP_MOVED_PERMANENTLY',             301 );
define( 'HTTP_MOVED_TEMPORARILY',             302 );
define( 'HTTP_SEE_OTHER',                     303 );
define( 'HTTP_NOT_MODIFIED',                  304 );
define( 'HTTP_USE_PROXY',                     305 );
define( 'HTTP_TEMPORARY_REDIRECT',            307 );

define( 'HTTP_BAD_REQUEST',                   400 );
define( 'HTTP_UNAUTHORIZED',                  401 );
define( 'HTTP_PAYMENT_REQUIRED',              402 );
define( 'HTTP_FORBIDDEN',                     403 );
define( 'HTTP_NOT_FOUND',                     404 );
define( 'HTTP_METHOD_NOT_ALLOWED',            405 );
define( 'HTTP_NOT_ACCEPTABLE',                406 );
define( 'HTTP_PROXY_AUTHENTICATION_REQUIRED', 407 );
define( 'HTTP_REQUEST_TIME_OUT',              408 );
define( 'HTTP_CONFLICT',                      409 );
define( 'HTTP_GONE',                          410 );
define( 'HTTP_LENGTH_REQUIRED',               411 );
define( 'HTTP_PRECONDITION_FAILED',           412 );
define( 'HTTP_REQUEST_ENTITY_TOO_LARGE',      413 );
define( 'HTTP_REQUEST_URI_TOO_LARGE',         414 );
define( 'HTTP_UNSUPPORTED_MEDIA_TYPE',        415 );
define( 'HTTP_RANGE_NOT_SATISFIABLE',         416 );
define( 'HTTP_EXPECTATION_FAILED',            417 );
define( 'HTTP_UNPROCESSABLE_ENTITY',          422 );
define( 'HTTP_LOCKED',                        423 );
define( 'HTTP_FAILED_DEPENDENCY',             424 );
define( 'HTTP_UPGRADE_REQUIRED',              426 );

define( 'HTTP_INTERNAL_SERVER_ERROR',         500 );
define( 'HTTP_NOT_IMPLEMENTED',               501 );
define( 'HTTP_BAD_GATEWAY',                   502 );
define( 'HTTP_SERVICE_UNAVAILABLE',           503 );
define( 'HTTP_GATEWAY_TIME_OUT',              504 );
define( 'HTTP_VERSION_NOT_SUPPORTED',         505 );
define( 'HTTP_VARIANT_ALSO_VARIES',           506 );
define( 'HTTP_INSUFFICIENT_STORAGE',          507 );
define( 'HTTP_NOT_EXTENDED',                  510 );

/**
 * Output proper HTTP header for a given HTTP code
 *
 * @param string $code 
 * @return void
 */
function status($code = 500)
{
    //  if(!headers_sent())
    //{
    $str = http_response_status_code($code);
    send_header($str);
    //}
}

/**
 * Http redirection
 * 
 * Same use as {@link url_for()}
 * By default HTTP status code is 302, but a different code can be specified
 * with a status key in array parameter.
 * 
 * <code>
 * redirecto('new','url'); # 302 HTTP_MOVED_TEMPORARILY by default
 * redirecto('new','url', array('status' => HTTP_MOVED_PERMANENTLY));
 * </code>
 * 
 * @param string or array $param1, $param2... 
 * @return void
 */
function redirect_to($params)
{
  # [NOTE]: (from php.net) HTTP/1.1 requires an absolute URI as argument to » Location:
  # including the scheme, hostname and absolute path, but some clients accept
  # relative URIs. You can usually use $_SERVER['HTTP_HOST'],
  # $_SERVER['PHP_SELF'] and dirname() to make an absolute URI from a relative
  # one yourself.

  # TODO make absolute uri
    //  if(!headers_sent())
    //{
    $status = HTTP_MOVED_TEMPORARILY; # default for a redirection in PHP
    $params = func_get_args();
    $n_params = array();
    # extract status param if exists
    foreach($params as $param)
    {
      if(is_array($param))
      {
        if(array_key_exists('status', $param))
        {
          $status = $param['status'];
          unset($param['status']);
        }
      }
      $n_params[] = $param;
    }
    $uri = call_user_func_array('url_for', $n_params);
    $uri = htmlspecialchars_decode($uri, ENT_NOQUOTES);
    stop_may_exit(false);
    send_header('Location: '.$uri, true, $status);
    exit;
    //}
}

/**
 * Http redirection
 *
 * @deprecated deprecated since version 0.4. Please use {@link redirect_to()} instead.
 * @param string $url 
 * @return void
 */
function redirect($uri)
{
  # halt('redirect() is deprecated. Please use redirect_to() instead.', E_LIM_DEPRECATED);
  # halt not necesary... it won't be visible because of http redirection...
  redirect_to($uri);
}

/**
 * Returns HTTP response status for a given code.
 * If no code provided, return an array of all status
 *
 * @param string $num 
 * @return string,array
 */
function http_response_status($num = null)
{
  $status =  array(
      100 => 'Continue',
      101 => 'Switching Protocols',
      102 => 'Processing',

      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      207 => 'Multi-Status',
      226 => 'IM Used',

      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      306 => 'Reserved',
      307 => 'Temporary Redirect',

      400 => 'Bad Request',
      401 => 'Unauthorized',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Timeout',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Long',
      415 => 'Unsupported Media Type',
      416 => 'Requested Range Not Satisfiable',
      417 => 'Expectation Failed',
      422 => 'Unprocessable Entity',
      423 => 'Locked',
      424 => 'Failed Dependency',
      426 => 'Upgrade Required',

      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout',
      505 => 'HTTP Version Not Supported',
      506 => 'Variant Also Negotiates',
      507 => 'Insufficient Storage',
      510 => 'Not Extended'
  );
  if(is_null($num)) return $status;
  return array_key_exists($num, $status) ? $status[$num] : '';
}

/**
 * Checks if an HTTP response code is valid
 *
 * @param string $num 
 * @return bool
 */
function http_response_status_is_valid($num)
{
  $r = http_response_status($num);
  return !empty($r);
}

/**
 * Returns an HTTP response status string for a given code
 *
 * @param string $num 
 * @return string
 */
function http_response_status_code($num)
{
  $protocole = empty($_SERVER["SERVER_PROTOCOL"]) ? "HTTP/1.1" : $_SERVER["SERVER_PROTOCOL"];
  if($str = http_response_status($num)) return "$protocole $num $str";
}

/**
 * Check if the _Accept_ header is present, and includes the given `type`.
 *
 * When the _Accept_ header is not present `true` is returned. Otherwise
 * the given `type` is matched by an exact match, and then subtypes. You
 * may pass the subtype such as "html" which is then converted internally
 * to "text/html" using the mime lookup table.
 *
 * @param string $type
 * @param string $env 
 * @return bool
 */
function http_ua_accepts($type, $env = null)
{
  if(is_null($env)) $env = env();
  $accept = array_key_exists('HTTP_ACCEPT', $env['SERVER']) ? $env['SERVER']['HTTP_ACCEPT'] : null;
  
  if(!$accept || $accept === '*/*') return true;
  
  if($type)
  {
    // Allow "html" vs "text/html" etc
    if(!strpos($type, '/')) $type = mime_type($type);
    
    // Check if we have a direct match
    if(strpos($accept, $type) > -1) return true;
    
    // Check if we have type/*  
    $type_parts = explode('/', $type); 
    $type = $type_parts[0].'/*';
    return (strpos($accept, $type) > -1);
  }
  
  return false; 
}

