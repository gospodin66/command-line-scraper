<?php

include 'Timer.php';
$timer = new Timer();

$short = "p:s:x:";
$long  = array(
	"path:",
	"scheme:",
	"torprox:"
);
$opts  = getopt($short,$long);


if(count($opts) < 2){
	die("Assign [-p]--path <path> [-s]--scheme <http/s> (optional) [-x]--torprox <1/0>\n");
}

$domain = array_key_exists("path", $opts) ? trim($opts['path']) : trim($opts['p']);
$scheme = array_key_exists("scheme", $opts) ? trim($opts['scheme']) : trim($opts['s']);
$prox_opt = array_key_exists("torprox", $opts) ? trim($opts['torprox']) : trim($opts['x']);


if(!is_int($prox_opt))
	$prox_opt = intval($prox_opt);


if($prox_opt === 1){}

else $prox_opt = 0;

$parsed_url = parse_url($scheme."://".$domain);


if(file_exists($parsed_url['host'].".txt") && strlen(file_get_contents($parsed_url['host'].".txt")) > 200){
	unlink($parsed_url['host'].".txt");
	print "\33[93m".$parsed_url['host'].".txt cleared.\33[0m\n";
}

include_once('vars/vars.php');	// constants/delimiters
if(!file_exists(DATA_DIR)) mkdir(DATA_DIR);


$log = "\r\n************************************************************************\r\n";
$ch = curl_init();

//$prox_opt = isset($argv[2]) && $argv[2] === "1" ? 1 : 0;
$opts     = select_opts($scheme,$domain,$prox_opt);


curl_setopt_array($ch, $opts);
$page = curl_exec($ch);


if(curl_errno($ch)){ 	// check for execution errors
	$log .= "Scraper error: ".curl_error($ch)."\r\n";
	die("Scraper error: ".curl_error($ch)."\n");
}


$curlinfo = curl_getinfo($ch);
curl_close($ch);

$host_ip = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
$speed   = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
$log    .= "> Page size: [".strlen($page)."] bytes.\r\n\n";

//$log .= "Public IP: [".trim($publicIp)."]\r\n";
//print "\33[96m> Public IP: [".trim($publicIp)."]\33[0m\n";

$log .= "[".date("H:i:s")."] > Host IP: ".$host_ip." | down/up speed: ".$speed."\r\n";

file_put_contents(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".txt", $log, FILE_APPEND);

$log = "";

$doc = new DOMDocument();
@$doc->loadHTML($page);

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;

$string = "[".D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc)."]";
$string = str_replace("||", "\n", $string);
$string = substr_replace($string, '', -3, 2);

$json = str_replace("> ", "", $string);
$json = str_replace(D1, "", $json);
$json = str_replace(D2, "", $json);
$json = str_replace(D3, "", $json);
$json = str_replace(D4, "", $json);
$json = str_replace(D5, "", $json);


while(1){
	$time = date("H:i:s");
	print "\33[93mOptions:\ne -exit\np -print main webpage results\nf -follow webpage links\33[0m\n";
	switch (readline("> ")) {
	 	case 'p':
		print $string."\n";
		break;
	 	
		case 'e':
		break 2;

		case 'f':
		follow_links($opts,$doc,$parsed_url['host'],$scheme,$prox_opt);
		break;

	 	default:
		print "\033[31m> Invalid argument.\33[0m\n";
	 	break;
	 } 
}	

print "\x07";	// beep 
print "\033[32m\nFinished.\n\033[0m";

exit();

/****************************************************************************************/
/****************************************************************************************/


function extract_cookies($page,$domain){

	var_dump($page);
	
	/*

	preg_match_all('/^Cookie:\s*([^;]*)/mi', $page, $matches); // Set-Cookie
	$cookie = array();	// all cookies
	$log = "";
	foreach($matches[1] as $item) {

		$time = date("H:i:s");
	    parse_str($item, $cookie);
	    $_s_cookies = explode("#", file_get_contents('SessionCookies.txt'));
	    $_s_cookies = array_slice($_s_cookies, 4);	//	cut SessionCookies.txt header

	    // SessionCookies.txt position indexes
	   	$indexS=0;		
	    $indexcfduid=0;


	   	for($i=0;$i<count($_s_cookies);$i++){	
	   		
			if(!isset($cookie['__cfduid'])){
				if(preg_match('/'.$domain.'/', $_s_cookies[$i])){
					$cookie['__cfduid'] = trim(substr($_s_cookies[$i],-44));
					$indexcfduid = $i;
				}
			}
	   	}
	   	// 3 scenarios to avoid warnings when handling session cookies
	   	if(isset($cookie['S']) && isset($cookie['__cfduid'])){
	   		$log .= "[".$time."] > S: ".$cookie['S']."; __cfduid: ".$cookie['__cfduid']."\r\n";
	   	}
	   	else if (!isset($cookie['S']) && isset($cookie['__cfduid'])){
			$log .= "[".$time."] > S: ".trim(substr($_s_cookies[$indexS],36))."; __cfduid: ".$cookie['__cfduid']."\r\n";
	   	}
	   	else if (!isset($cookie['__cfduid']) && isset($cookie['S'])){
	   		$log .= "[".$time."] > S: ".$cookie['S']."; __cfduid: ".trim(substr($_s_cookies[$indexcfduid],53))."\r\n";
	   	}

	}
	file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);

	*/
}

/****************************************************************************************/

function follow_links($opts,$doc,$domain,$scheme,$prox_opt){		

	$links = $doc->getElementsByTagName('a');
	$crawled = array();
	$string = "[";
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/";

	foreach ($links as $a) {	// loop through elements
		
		//$publicIp = publicIp();
		$timer2 = new Timer();


		foreach ($a->attributes as $link) {	// loop through attributes

			// log delimiter
			$log = "\r\n-----------------------------------------------------------------\r\n";
			$url = trim($link->nodeValue);
			$time = date("H:i:s");

			// skip if link empty
			if($url == "#" || empty($url))
				break;	

			// TODO: ADD MORE FORMATS
			// do not download content!!!
			
			else if(preg_match("/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)/", $url))
			{
				print "\33[94m[".$time."] > Content URL >>> ".$url." >>> skipping..\33[0m\n";
				$log .= "[".$time."] > Content URL [".strlen($url)." bytes] >>> ".$url." >>> skipping..";
				file_put_contents(DATA_DIR."content_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
				break;
			}
			else if (preg_match("/(\.js$)|(\.php$)/", $url))
			{
				print "\33[95m[".$time."] > Script format >>> skipping..\n\33[0m";
				$log .= "[".$time."] > Script format >>> ".$url." >>> skipping..\r\n";
				break;
			}


			if(in_array($url, $crawled)){
			   break;  
			}
			$crawled[] = $url;

			//echo "> \33[96mPublic IP: [".trim($publicIp)."]\33[0m\n";
			//$log .= "> Public IP: [".trim($publicIp)."]\r\n";

			if(preg_match($url_regex, $url) === 0)
			{ 
				$log .= "[".$time."] > URL: <".$url.">\r\n";
				print "\033[95m> ".$url." >>> Invalid link format..\n> Adapting..\033[0m \n";

				// save link to relative file
				file_put_contents(DATA_DIR."rel_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);

				if($url[0] == "/" && substr($url, 0, 2) != "//")
				{
					$url = $scheme."://".$domain.$url;
				}

				else if(substr($url, 0, 2) == "./")
				{
					$url = $scheme."://".$domain."/".basename($url);
					var_dump($url);
				}

				else if(substr($url, 0, 3) == "../")
				{
					$url = $scheme."://".$domain."/".realpath($url).basename($url);
					var_dump($url);
					sleep(3);
				}

				else if (substr($url, 0, 11) == "javascript:")
				{
					$log .= "[".$time."] > javascript:  ".$url."\r\nScript format, skipping..\r\n";
					print "\033[95m>Script format, skipping..\033[0m\r\n";
					break;
				}

				else
				{
					$url = $scheme."://".$domain."/".$url;
				}

				echo "\33[32m> New url:  ".$url."\33[0m\n";
				$log .= "[".$time."] > New url:  ".$url."\r\n";
			}
			else
			{
				file_put_contents(DATA_DIR."abs_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
			}


			if(check_base_domain($domain,$url,$scheme) === false)
			{
				print "\033[95m> Invalid base domain, skipping..\33[0m\r\n";
				sleep(2);
				continue;
			}


			/***	cURL	**/
			$ch   = curl_init();
			$opts = select_opts($scheme,$url,$prox_opt);
			curl_setopt_array($ch, $opts);
			
			$page 	  = curl_exec($ch);
			$curlinfo = curl_getinfo($ch);
			$host_ip  = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
			$speed    = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
			$log     .= "[".$time."] > Host IP: ".$host_ip." | down/up speed: ".$speed."\n";
			$log     .= "\r\n> Page size: [".strlen($page)."] bytes.\r\n\n"; // strlen == bytes?!?

			if(curl_errno($ch)){ 		// check for execution errors
				print "Scraper error: ".curl_error($ch)."\n";
				$log = "[".$time."] Scraper error: ".curl_error($ch)."\r\n";
				curl_close($ch);
				break;
			}
			curl_close($ch);

			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
			$log = "";
			extract_cookies($page,$domain); // retrieve session cookies
				
			$doc = new DOMDocument();
			@$doc->loadHTML($page);

			$string .= D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc);
			$string = str_replace("||", "\n", $string);

			//print $string."\n";
			print "\033[32m> Finished.\n";
			print "\n\033[96m> Crawled webpages: ".(count($crawled)-1)."\33[0m\n";


			$log = "[".$time."] > Finished. >>> Crawled webpages: ".(count($crawled)-1);

			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
			print "Timer: ".$timer2->get_execution_time()." [s]\n";
		}
		unset($timer2); // timer for pages
	}

	unset($timer);
	print "\33[96mList of crawled pages:\n";
	print_r($crawled);
	print "\33[0m";

	while(1)
	{
		print "\33[93mOptions: \np print all results\nr return\ns save [size: ".strlen($string)." bytes]\33[0m\n";
		switch (readline("> ")) {
			case 'r':
			break 2;

			case 's':
			$string = substr_replace($string, ']', -2, 2);
			$json   = str_replace("> ", "", $string);
			$json   = str_replace(D1, "", $json);
			$json   = str_replace(D2, "", $json);
			$json   = str_replace(D3, "", $json);
			$json   = str_replace(D4, "", $json);
			$json   = str_replace(D5, "", $json);
			$json   = str_replace("\n", "", $json);
			
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".json", $json, FILE_APPEND);
			print "\33[32mSaved.\33[0m\n";
			print "\33[95mResult file >>> ".__DIR__."/".$domain.".json\33[0m\n";
			break 2;

			case 'p':
			echo $string."\n";
			break;

		 	default:
		 	print "\033[31m> Invalid argument.\33[0m\n";
		 	break;
		} 
	}
}

/****************************************************************************************/

function select_opts($scheme,$url,$use_prox){

	$torsocks5prox = '127.0.0.1:9150';	// 9150 Torsocks5 port

	if($use_prox === 1)
	{
		$opts = array(
			CURLOPT_URL 				=> preg_match('/^'.$scheme.'./', $url) ? $url : $scheme."://".$url,
		    CURLOPT_HEADER 				=> 1,
		    CURLOPT_VERBOSE 			=> 1,
		    CURLOPT_RETURNTRANSFER 		=> 1,
		    CURLOPT_CONNECTTIMEOUT 		=> 30,	
		    CURLOPT_TIMEOUT        		=> 30,	
		    CURLOPT_SSL_VERIFYPEER  	=> $scheme == 'https' ? 1 : 0,
		    CURLOPT_SSL_VERIFYHOST  	=> $scheme == 'https' ? 2 : 0,
		    CURLOPT_FOLLOWLOCATION		=> 1,
		    CURLOPT_MAXREDIRS 			=> 5,
			CURLOPT_COOKIEJAR			=> 'SessionCookies.txt',
			CURLOPT_COOKIEFILE			=> 'SessionCookies.txt',
			CURLOPT_PROXY 				=> $torsocks5prox, 
		    CURLOPT_PROXYTYPE			=> CURLPROXY_SOCKS5_HOSTNAME,
		    CURLOPT_HTTPHEADER			=> array(
		    	'X-Frame-Options: deny',
			    'User-Agent: '.setUserAgent(),
			    'Accept: */*',
			    'Cache-Control: no-cache',
		    )
		 );
	}
	else
	{
		$opts = array(
			CURLOPT_URL 				=> preg_match('/^'.$scheme.'./', $url) ? $url : $scheme."://".$url,
		    CURLOPT_HEADER 				=> 1,
		    CURLOPT_VERBOSE 			=> 1,
		    CURLOPT_RETURNTRANSFER 		=> 1,
		    CURLOPT_CONNECTTIMEOUT 		=> 30,
		    CURLOPT_TIMEOUT        		=> 30,
		    CURLOPT_SSL_VERIFYPEER  	=> $scheme == 'https' ? 1 : 0,
		    CURLOPT_SSL_VERIFYHOST  	=> $scheme == 'https' ? 2 : 0,
		    CURLOPT_FOLLOWLOCATION		=> 1,
		    CURLOPT_MAXREDIRS 			=> 5,
			CURLOPT_COOKIEJAR			=> 'SessionCookies.txt',
			CURLOPT_COOKIEFILE			=> 'SessionCookies.txt',
		    CURLOPT_HTTPHEADER			=> array(
		    	'X-Frame-Options: deny',
			    'User-Agent: '.setUserAgent(),
			    'Accept: */*',
			    'Cache-Control: no-cache',
		    )
		 );
	}

	return $opts;

	/********************/
    //CURLOPT_AUTOREFERER		=> 0,	// request origin
    //CURLOPT_BINARYTRANSFER 	=> 0,	// raw output
	//CURLOPT_FRESH_CONNECT		=> 1,
    //CURLOPT_POST 				=> 0,
	//CURLOPT_FORBID_REUSE		=> 1,
	//CURLOPT_UPLOAD			=> 1,
	/********************/
}

/****************************************************************************************/

function check_base_domain($base_domain, $link_domain, $scheme){	


	$link_domain = parse_url($link_domain);


	if(substr($base_domain, 0,4) === "www.")
		$base_domain = substr($base_domain,4);

	if(substr($link_domain['host'], 0,4) === "www.")
		$link_domain['host'] = substr($link_domain['host'],4);


	var_dump($base_domain, $link_domain['host']);


	// subdomain check
    if(strpos($link_domain['host'], $base_domain))
    	return true;


	return strcmp($base_domain, $link_domain['host']) === 0 ? true : false; 
}

/****************************************************************************************/

function header_links($doc){

	$links = $doc->getElementsByTagName('link');
	$fmtd = "";

	foreach ($links as $link) {	 // loop through elements
		$fmtd .= '> {"Element": "<'.$link->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($link->attributes as $attr) {	// loop through attributes
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes(trim($attr->nodeValue),'"').'"},||';
		}				
	}
	return $fmtd;
}

/****************************************************************************************/

function hrefs($doc){

	$links = $doc->getElementsByTagName('a');
	$fmtd = "";

	foreach ($links as $a) {	// loop through elements
		$fmtd .= '> {"Element": "<'.$a->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($a->attributes as $attr) {	// loop through attributes

			$fmtd .= ($attr->nodeValue == "#" || $attr->nodeName != "href" || empty($attr->nodeValue))
				     ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

/****************************************************************************************/

function metas($doc){

	$metas = $doc->getElementsByTagName('meta');
	$fmtd = "";

	foreach ($metas as $meta) {
		$fmtd .= '> {"Element": "<'.$meta->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($meta->attributes as $attr) {	

			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

/****************************************************************************************/

function imgs($doc){

	$imgs = $doc->getElementsByTagName('img');
	$fmtd = "";

	foreach ($imgs as $img) {
		$fmtd .= '> {"Element": "<'.$img->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($img->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

/****************************************************************************************/

function scripts($doc){

	$scripts = $doc->getElementsByTagName('script');
	$fmtd = "";

	foreach ($scripts as $script) {
		$fmtd .= '> {"Element": "<'.$script->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($script->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addslashes($attr->nodeValue).'"},||';
		}							
	}
	return $fmtd;
}

/****************************************************************************************/

function setUserAgent(){

	// Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0

    $agentBrowser = array(
            'Firefox',
            'Safari',
            'Opera',
            'Internet Explorer'
    );

    $agentOS = array(
            'Windows 7',
            'Windows 10',
            'Redhat Linux',
            'Ubuntu',
            'Fedora'
    );
    //randomly generate UserAgent
    return $agentBrowser[rand(0,3)].'/'.rand(1,8).'.'.rand(0,9).'('.$agentOS[rand(0,4)].' '.rand(1,7).'.'.rand(0,9).'; en-US;)';
}

/****************************************************************************************/

function publicIp(){
	$ch = curl_init();
	$torsocks5prox = '127.0.0.1:9150';	// 9150 Tor socks port
	curl_setopt($ch,CURLOPT_URL, 'https://ipinfo.io/ip'); // alt-'https://httpbin.org/ip', 'ipv4.icanhazip.com'
	//curl_setopt($ch,CURLOPT_VERBOSE, TRUE);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch,CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-Frame-Options: deny',
	    'User-Agent: '.setUserAgent(),
    	'Access-Control-Allow-Origin: null'
	));
	curl_setopt($ch,CURLOPT_PROXY, $torsocks5prox);
	$publicIp = curl_exec($ch);
	$curlinfo = curl_getinfo($ch);

	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}
?>
