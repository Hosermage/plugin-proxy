<?php
// Disclaimer:
//	Use this script only if you have the php "Memcached" library installed, it is not
//	to be confused with "Memcache" library.  There is a separate version for Memcache library.

// You must define these with real values
$memcache_server = 'localhost';
$memcache_port = '11211';
$beacon_host = '';
$js_host = '';
$tracking_code = '';

// Perform requested actions
$action = $_GET['action'];
switch ($action) {
	case 'rxn_pageview':
	case 'rxn_impression':
		_beacon_call($beacon_host, $tracking_code);
		break;
	case 'rxn_preview':
		_preview_pull($js_host, $_GET['rxn_preview']);
		break;
	default:
		_serve_js($js_host, $tracking_code);
}
exit;


function _serve_js($host, $key)
{
	$url = $host . '/js/v3/' . $key . '/abx.js';
	$memcache_key = 'ABX::'.$key;
	$error_flag = false;
	$fetch_flag = true;
	$write_flag = true;
	$js_data = '';
	$memcache = new Memcached();
	if ($memcache->addServer($memcache_server, $memcache_port))
	{
		$js_data = $memcache->get($memcache_key);
		if (!is_empty($js_data))
		{
			$fetch_flag = false;
			$write_flag = false;
		}
	} else {
		$error_flag = true;
		$write_flag = false;
	}
	if ($fetch_flag)
	{
		$js_data = _curl_get($url);
		if ($write_flag)
		{
			$memcache->set($memcache_key, $js_data, time()+3600);
		}
	}
	header('Cache-Control: max-age=3600');
	header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+3600));
	header('Content-type: text/javascript');
	if ($error_flag)
	{
		echo "// Unable to connect to memcache server, please check server configuration\n";
	}
	echo $js_data;
}

function _preview_pull($host, $key)
{
	$url = $host . '/js/' . $key . '/preview.js';
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');
	header('Content-type: text/javascript');
	echo _curl_get($url);
}

function _beacon_call($host, $key)
{
	$user_agent = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
	$ip = '';
	if ( ! empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	elseif ( ! empty($_SERVER['HTTP_X_FORWARDED']))
		$ip = $_SERVER['HTTP_X_FORWARDED'];
	elseif ( ! empty($_SERVER['HTTP_FORWARDED_FOR']))
		$ip = $_SERVER['HTTP_FORWARDED_FOR'];
	elseif ( ! empty($_SERVER['HTTP_FORWARDED']))
		$ip = $_SERVER['HTTP_FORWARDED'];
	elseif ( ! empty($_SERVER['REMOTE_ADDR']))
		$ip = $_SERVER['REMOTE_ADDR'];

	$url = $host . '/abl.php?tracking_code=' . $key;
	$url .= '&ua=' . urlencode($user_agent);
	$url .= '&ip=' . urlencode($ip);
	$url .= '&' . $_SERVER['QUERY_STRING'];
	_curl_get($url);

	header("Cache-Control: no-cache, no-store, must-revalidate");
	header("Pragma: no-cache");
	header("Expires: 0");
	header('Content-Type: image/gif');
	echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
}

function _curl_get($url)
{
	$curl_output = '';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	$curl_output = curl_exec($ch);
	curl_close($ch);
	return $curl_output;
}
