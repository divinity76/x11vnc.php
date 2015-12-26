<?php
// warning: unless you know what you're doing, don't move any lines above/below init();
// (using global $vars; in init() )
error_reporting ( E_ALL );
$password_to_use_script = FALSE; // password to use the script. FALSE for no password.
$force_unix_username = FALSE; // string if you want to hardcode a username, FALSE otherwise.
$force_unix_password = FALSE; // string if you want to hardcode a password, FALSE otherwise.
ini_set ( "display_errors", "On" );
init ();
$vnc_connect_timeout = 20; // number of seconds x11vnc +wait for a connection before exiting for security reasons.
$vnc_port = findUnusedPort ( 35000, 36000 ); // port to use
$vnc_password = generateRandomVNCPassword ( 8 ); // warning, not more than 8 characters on some vnc clients...

if (strlen ( $unix_username ) < 1) {
	throw new RuntimeException ( 'no unix username!' );
}
if (strlen ( $unix_password ) < 1) {
	throw new RuntimeException ( 'no unix username!' );
}

$command = 'nohup x11vnc ';
$xauth = '/run/lightdm/root/:0';
$xauth = '/var/lib/lightdm/.Xauthority';
$args = array (
		'-xauth ' . escapeshellarg ( $xauth ),
		'-once',
		'-timeout ' . escapeshellarg ( $vnc_connect_timeout ),
		'-passwd ' . escapeshellarg ( $vnc_password ),
		'-rfbport ' . escapeshellarg ( $vnc_port ) 
);
$args = implode ( ' ', $args );
$command = $command . ' ' . $args . ' >/dev/stdout 2>&1';
// su: must be run from a terminal
$sucmd = 'su --command=' . escapeshellarg ( $command ) . ' ' . escapeshellarg ( $unix_username );
$fullcmd = 'script --return --quiet --command ' . escapeshellarg ( $sucmd ) . ' /dev/null';
// var_dump($fullcmd);die('AKBAR');

$descriptorspec = array (
		0 => array (
				"pipe",
				"r" 
		), // stdin
		1 => array (
				"pipe",
				"w" 
		), // stdout
		2 => array (
				"pipe",
				"w" 
		) 
) // stderr
;

$cwd = NULL;
$env = NULL;

$process = proc_open ( $fullcmd, $descriptorspec, $pipes, $cwd, $env );
if (! is_resource ( $process )) {
	throw new RuntimeException ( 'proc_open failed!' );
}
sleep ( 2 ); // WARNING SCARY: DO NOT UNCOMMENT THIS LINE, works around what i assume is a bug in bash.. if i send too fast, will just try to execute the password as a command!
fwrite ( $pipes [0], $unix_password );
fclose ( $pipes [0] );
$stdout = stream_get_contents ( $pipes [1] );
fclose ( $pipes [1] );
$stderr = stream_get_contents ( $pipes [2] );
fclose ( $pipes [2] );
$ret = proc_terminate ( $process, 9 ); // 9 is SIGKILL
var_dump ( "sent 9", $ret );
$status = proc_get_status ( $process );

var_dump ( 'fullcmd', $fullcmd, 'status', $status, 'stdout', $stdout, 'stderr', $stderr );
function generateRandomVNCPassword($length = 8, $charlist = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
	// echo base_convert(bin2hex(openssl_random_pseudo_bytes(16, $strong)), 16, 36);//viper7
	$listlen = strlen ( $charlist );
	$ret = '';
	for($i = 0; $i < $length; ++ $i) {
		$ret .= $charlist [mt_rand ( 0, $listlen - 1 )];
	}
	return $ret;
}
function hhb_tohtml($str) {
	return htmlentities ( $str, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true );
}
function findUnusedPort($minimum, $maximum) {
	if ($minimum > $maximum) {
		throw new InvalidArgumentException ( '$minimum MUST be <= $maximum' );
	}
	
	$sck = NULL;
	for($i = $minimum; $i < $maximum; ++ $i) {
		$sck = socket_create_listen ( $i );
		if (false !== $sck) {
			socket_close ( $sck );
			return $i;
		}
	}
	throw new RuntimeException ( 'failed to find an unused port between ' . $minimum . ' and ' . $maximum );
}
function init() {
	echo '<!DOCTYPE HTML><html><head><title>x11vnc.php</title></head><body>';
	register_shutdown_function ( function () {
		echo '</body></html>';
	} );
	if (! array_key_exists ( 'submit', $_POST )) {
		AskForPasswordHTML ();
		die ();
	}
	global $password_to_use_script;
	if (is_string ( $password_to_use_script )) {
		if (! array_key_exists ( 'password_to_use_script', $_POST )) {
			echo 'password to use this scirpt is not supplied!';
			AskForPasswordHTML ();
			die ();
		}
		if ($_POST ['password_to_use_script'] !== $password_to_use_script) {
			echo "wrong password supplied to use this script!";
			AskForPasswordHTML ();
			die ();
		}
	}
	global $force_unix_username, $unix_username;
	if (is_string ( $force_unix_username )) {
		$unix_username = $force_unix_username;
	} else {
		if (! array_key_exists ( 'unix_username', $_POST )) {
			echo "unix username not supplied!";
			AskForPasswordHTML ();
			die ();
		}
		$unix_username = ( string ) $_POST ['unix_username'];
	}
	global $force_unix_password, $unix_password;
	if (is_string ( $force_unix_password )) {
		$unix_password = $force_unix_password;
	} else {
		if (! array_key_exists ( 'unix_password', $_POST )) {
			echo "unix password not supplied!";
			AskForPasswordHTML ();
			die ();
		}
		$unix_password = ( string ) $_POST ['unix_password'];
	}
}
function AskForPasswordHTML() {
	echo '<form action="?" method="POST">';
	
	global $password_to_use_script;
	
	if (is_string ( $password_to_use_script )) {
		echo 'password to use this script: <input type="password" name="password_to_use_script" value="" /> <br/>';
	} else {
		echo "password to use this script has been disabled.<br/>";
	}
	global $force_unix_username;
	if (! is_string ( $force_unix_username )) {
		echo 'unix username: <input type="text" name="unix_username" value="" /><br/>';
	} else {
		echo 'unix username to use has been hardcoded.<br/>';
	}
	global $force_unix_password;
	if (! is_string ( $force_unix_password )) {
		echo 'unix password: <input type="password" name="unix_password" value="" /><br/>';
	} else {
		echo 'unix password to use has been hardcoded.<br/>';
	}
	echo 'start: <input type="submit" name="submit" value="submit" /></form>';
}
