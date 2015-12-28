<?php
// warning: unless you know what you're doing, don't move any lines above/below
// init();
// (using global $vars; in init() )
error_reporting(E_ALL);
ini_set("display_errors", "On");
$password_to_use_script = FALSE; // password to use the script. FALSE for no
                                 // password.
$force_unix_username = FALSE; // string if you want to hardcode a username,
                              // FALSE otherwise.
$force_unix_password = FALSE; // string if you want to hardcode a password,
                              // FALSE otherwise.
header('X-Accel-Buffering: no'); // for nginx..
header('X-proxy-read-timeout: -1'); // for nginx..
init();
$vnc_connect_ip='example.org';//hostname or IP of the VNC server
$vnc_connect_timeout = 120; // number of seconds x11vnc +wait for a connection,
                           // before exiting for security reasons.
$vnc_port = findUnusedPort(35000, 36000); // port to use

$vnc_password = generateRandomVNCPassword(8); // warning, not more than 8
                                              // characters on some vnc
                                              // clients...
                                 
$vnc_xauth = '/run/lightdm/root/:0';
//$vnc_xauth = '/lightdm/.Xauthority';

if (strlen($unix_username) < 1) {
    throw new RuntimeException('no unix username!');
}
if (strlen($unix_password) < 1) {
    throw new RuntimeException('no unix username!');
}

$command = 'x11vnc ';
$args = array(
        '-xauth ' . escapeshellarg($vnc_xauth),
        '-once',
        '-timeout ' . escapeshellarg($vnc_connect_timeout),
        '-passwd ' . escapeshellarg($vnc_password),
        '-rfbport ' . escapeshellarg($vnc_port),
        //'-ssl',
        //'-http_ssl',
        //'-http',
);
$args = implode(' ', $args);
$command = $command . ' ' . $args;
// su: must be run from a terminal
$sucmd = 'su --command=' . escapeshellarg($command) . ' ' .
         escapeshellarg($unix_username);
$fullcmd = 'script --return --quiet --command ' . escapeshellarg($sucmd) .
         ' /dev/null';
// var_dump($fullcmd);die('AKBAR');

$descriptorspec = array(
        0 => array(
                "pipe",
                "rb"
        ), // stdin
        1 => array(
                "pipe",
                "wb"
        ), // stdout
        2 => array(
                "pipe",
                "wb"
        )// stderr
);

$cwd = NULL;
$env = NULL;
updateStatusText("starting x11vnc, fullcmd: ".$fullcmd);
$process = proc_open($fullcmd, $descriptorspec, $pipes, $cwd, $env);
if (! is_resource($process)) {
    throw new RuntimeException('proc_open failed!');
}
sleep(2); // WARNING SCARY: DO NOT UNCOMMENT THIS LINE, works around what i
          // assume is a bug in bash.. if i send too fast, will just try to
          // execute the password as a command!
fwrite($pipes[0], $unix_password);
fclose($pipes[0]);
ignore_user_abort(true);
echo '<pre>';
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$full_stdout_buffer="";
$vnc_started=false;
while (true) {
    set_time_limit(100);
    $status = proc_get_status($process);
    if (false === $status) {
        throw new RuntimeException('unable to get status of process!');
    }
    
    $stdout = my_stream_get_contents($pipes[1]);
    $stdoutlen = strlen($stdout);
    $stderr = my_stream_get_contents($pipes[2]);
    $stderrlen = strlen($stderr);
    if ($stdoutlen > 0) {
        $full_stdout_buffer.=$stdout;
        echo 'stdout: <span style="background-color:#' . substr(md5($stdout), 0, 
                6) . ';">' . hhb_tohtml(return_var_dump($stdout)) . '</span>' .
                 PHP_EOL;
        if(!$vnc_started && false!==stripos($full_stdout_buffer, 'VNC desktop is:')){
            $vnc_started=true;
             $newstatus='x11vnc has started. You have less than '.hhb_tohtml($vnc_connect_timeout).' seconds to connect: ';
             $newstatus.='host: '.hhb_tohtml($vnc_connect_ip).'<br/>';
             $newstatus.='port: '.hhb_tohtml($vnc_port).'<br/>';
             $newstatus.='password: '.hhb_tohtml($vnc_password).'<br/>';
             updateStatusHTML($newstatus);
//             $newstatus='Ok, x11vnc has started. You have less than '.hhb_tohtml($vnc_connect_timeout).' seconds to ';
//             $newstatus.='go to this link and press "Connect"...<br/>';
//             $theurl='http://.kanaka.github.io/noVNC/noVNC/vnc.html?'.http_build_query(array(
//                     'host'=>$vnc_connect_ip,
//                     'port'=>$vnc_port,
//                     'password'=>$vnc_password,
//                     'path'=>'websockify',//
//                     'encrypt'=>'0'                    
//             ));
//             $theurl=hhb_tohtml($theurl);
//             $newstatus.='<a href="'.$theurl.'">'.$theurl.'</a><br/>';
//             updateStatusHTML($newstatus);
        }
        myflush();
    }
    if ($stderrlen > 0) {
        echo 'stderr: <span style="background-color:#' . substr(md5($stderr), 0, 
                6) . ';">' . hhb_tohtml(return_var_dump($stderr)) . '</span>' .
                 PHP_EOL;
        myflush();
    }
    if ($status['running'] === false) {
        echo "x11vnc has stopped.";
        echo 'status: ' . hhb_tohtml(return_var_dump($status)) . PHP_EOL;
        echo '</pre>';
        myflush();
        fclose($pipes[1]);
        fclose($pipes[2]);
        break;
    }
    sleep(5);
    continue;
}
die('finished');

function generateRandomVNCPassword ($length = 8, 
        $charlist = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
{
    // echo base_convert(bin2hex(openssl_random_pseudo_bytes(16, $strong)), 16,
    // 36);//viper7
    $listlen = strlen($charlist);
    $ret = '';
    for ($i = 0; $i < $length; ++ $i) {
        $ret .= $charlist[mt_rand(0, $listlen - 1)];
    }
    return $ret;
}

function myflush ($loop = 100)
{
    for ($i = 0; $i < $loop; ++ $i) {
        echo '<script type="text/javascript" name="myflushscript">(function(){var all=document.getElementsByName("myflushscript"),i=0;for(;i<all.length;++i){all[i].parentNode.removeChild(all[i]);}})();</script>';
    }
    flush();
}

function return_var_dump(/*...*/){
    $args = func_get_args();
    ob_start();
    call_user_func_array('var_dump', $args);
    $ret = ob_get_clean();
    return $ret;
}

/*
 * problem: stream_get_contents block / is very slow.
 * I have tried
 * 1: stream_set_blocking, doesnt make a difference.
 * 2: stream_get_meta_data['unread_bytes'] = ITS BUGGED, ALWAYS SAYS 0.
 * 3: feof(): ALSO EFFING BLOCKING
 * 4: my_stream_get_contents hack... kinda working! :D
 */
function my_stream_get_contents ($handle, $timeout_seconds = 0.5)
{
    $ret = "";
    // feof ALSO BLOCKS:
    // while(!feof($handle)){$ret.=stream_get_contents($handle,1);}
    while (true) {
        $starttime = microtime(true);
        $new = stream_get_contents($handle, 1);
        $endtime = microtime(true);
        if (is_string($new) && strlen($new) >= 1) {
            $ret .= $new;
        }
        $time_used = $endtime - $starttime;
        // var_dump('time_used:',$time_used);
        if (($time_used >= $timeout_seconds) || ! is_string($new) ||
                 (is_string($new) && strlen($new) < 1)) {
            break;
        }
    }
    return $ret;
}

function hhb_tohtml ($str)
{
    return htmlentities($str, 
            ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', 
            true);
}

function findUnusedPort ($minimum, $maximum)
{
    if ($minimum > $maximum) {
        throw new InvalidArgumentException('$minimum MUST be <= $maximum');
    }
    
    $sck = NULL;
    for ($i = $minimum; $i < $maximum; ++ $i) {
        $sck = socket_create_listen($i);
        if (false !== $sck) {
            socket_close($sck);
            return $i;
        }
    }
    throw new RuntimeException(
            'failed to find an unused port between ' . $minimum . ' and ' .
                     $maximum);
}
function updateStatusText($newtext){
$html='<script type="text/javascript" name="UpdateStatusJS">';
$html.='(function(){var newtext=atob("'.base64_encode($newtext).'");document.getElementById("status").textContent=newtext;var rem=document.getElementsByName("UpdateStatusJS")[0];rem.parentNode.removeChild(rem);})();';        
$html.='</script>';
echo $html;
}
function updateStatusHTML($newhtml){
    $html='<script type="text/javascript" name="UpdateStatusJS">';
    $html.='(function(){var newhtml=atob("'.base64_encode($newhtml).'");document.getElementById("status").innerHTML=newhtml;var rem=document.getElementsByName("UpdateStatusJS")[0];rem.parentNode.removeChild(rem);})();';
    $html.='</script>';
    echo $html;
}
function init ()
{
    set_error_handler("hhb_exception_error_handler");
    
    echo '<!DOCTYPE HTML><html><head><title>x11vnc.php</title></head><body>';
    echo '<div id="TheStatusDiv">Look here for status! currently: <span id="status">loading the page</span></div>';
    register_shutdown_function(
            function  ()
            {
                echo '</body></html>';
            });
    if (! array_key_exists('submit', $_POST)) {
        AskForPasswordHTML();
        die();
    }
    global $password_to_use_script;
    if (is_string($password_to_use_script)) {
        if (! array_key_exists('password_to_use_script', $_POST)) {
            echo 'password to use this scirpt is not supplied!';
            AskForPasswordHTML();
            die();
        }
        if ($_POST['password_to_use_script'] !== $password_to_use_script) {
            echo "wrong password supplied to use this script!";
            AskForPasswordHTML();
            die();
        }
    }
    global $force_unix_username, $unix_username;
    if (is_string($force_unix_username)) {
        $unix_username = $force_unix_username;
    } else {
        if (! array_key_exists('unix_username', $_POST)) {
            echo "unix username not supplied!";
            AskForPasswordHTML();
            die();
        }
        $unix_username = (string) $_POST['unix_username'];
    }
    global $force_unix_password, $unix_password;
    if (is_string($force_unix_password)) {
        $unix_password = $force_unix_password;
    } else {
        if (! array_key_exists('unix_password', $_POST)) {
            echo "unix password not supplied!";
            AskForPasswordHTML();
            die();
        }
        $unix_password = (string) $_POST['unix_password'];
    }
}

function hhb_exception_error_handler ($errno, $errstr, $errfile, $errline)
{
    if (! (error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function AskForPasswordHTML ()
{
    echo '<form action="?" method="POST">';
    
    global $password_to_use_script;
    
    if (is_string($password_to_use_script)) {
        echo 'password to use this script: <input type="password" name="password_to_use_script" value="" /> <br/>';
    } else {
        echo "password to use this script has been disabled.<br/>";
    }
    global $force_unix_username;
    if (! is_string($force_unix_username)) {
        echo 'unix username: <input type="text" name="unix_username" value="" /><br/>';
    } else {
        echo 'unix username to use has been hardcoded.<br/>';
    }
    global $force_unix_password;
    if (! is_string($force_unix_password)) {
        echo 'unix password: <input type="password" name="unix_password" value="" /><br/>';
    } else {
        echo 'unix password to use has been hardcoded.<br/>';
    }
    echo 'start: <input type="submit" name="submit" value="submit" /></form>';
}