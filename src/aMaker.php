<?php
// Socket connection includes
include('Socket/Server.php');
include('Socket/Client.php');
// AMF Core library includes
require_once( 'amfphp/core/amf/app/Gateway.php');
require_once( AMFPHP_BASE . 'amf/io/AMFSerializer.php');
require_once( AMFPHP_BASE . 'amf/io/AMFDeserializer.php');
// YaBOB Class uncludes
require_once('YaBOB/AMF.php');
require_once('YaBOB/Login.php');
require_once('YaBOB/Register.php');
require_once('YaBOB/Handshake.php');
require_once('YaBOB/common/Createnewplayer.php');
require_once('curl.php');

error_reporting(E_ALL);
ini_set('display_errors','on');

echo "[REQUEST] Please enter the server number: ";
$server = trim(str_replace(PHP_EOL, '', fgets(STDIN)));

echo "[INFO] Fetching server infomation for Evony Server # {$server}".PHP_EOL;

$curl = NEW Curl;
$return = $curl->get("http://{$server}.evony.com/config.xml");
//var_dump($return);
$feed = NEW SimpleXMLElement($return);
$address = (String)$feed->server[0];
$port = (int)$feed->port;

//$address = "216.66.17.121";
//$port = 443;
echo "[INFO] Starting loop".PHP_EOL;

while(1){
	echo "[INFO] Creating Evony account generated via randomness".PHP_EOL;

	$UID = uniqid();
	$emailgen = "{$UID}@joeking.info";
	$password = "12345678";
	$s = NEW Socket\Client($address,$port);
	echo "[INFO] Connecting to {$address}:{$port} WhoRunIt",PHP_EOL;

	$AMF = NEW YaBOB_AMF();
	$amfHandshake = NEW YaBOB_Handshake();
	$amfReg = NEW YaBOB_Register;
	$regInfo = $amfReg->_($emailgen, $password); unset($amfReg);
	$regData = $AMF->AMFlength($regInfo).$regInfo;
	echo "[INFO] Waiting for a response".PHP_EOL;

	$s->write($amfHandshake); unset($amfHandshake);
	$s->write($regData);
	$in = @$s->read();

	$in = substr($in, 4);
	$response = $AMF->destructAMF($in);

	echo "[INFO] Recieved reply!",PHP_EOL;

	//var_dump($response);

	if(!isset($response->data)){
		echo "[ERROR] Look's like someone pissed in Evony's Cheerios today; Most likely just 1 hour IP banned by Evony.".PHP_EOL;
		exit("[EXIT] Unexpected Error");
	}

	if($response->data['errorMsg'] === "need create player"){
		echo "[INFO] Creating player using generated email: {$emailgen} and password: {$password}";
		$createplayer = NEW YaBOB_Common_Createnewplayer();
		$player = $createplayer->_($UID, '','','',11,'');
		$createplayer = $AMF->AMFlength($player).$player;
		$s->write($createplayer);
		$in = $s->read();

		while($in){
			$out = @$out.$in;
			$in = @$s->read();
			echo ".";
		}

		echo PHP_EOL;
		echo "[INFO] Recieved reply!",PHP_EOL;
		$in = substr($out, 4);
		$response = $AMF->destructAMF($in);
		//var_dump($response);
		
		//if(isset($response) && @$response->data['errorMsg'] === "NEED CAPTCHA"){
		While(@$response->data['errorMsg'] === "NEED CAPTCHA"){
			echo "[WARNING] OH SHIT EVONY GAVE YOU A CAPTCHA!".PHP_EOL;
			file_put_contents('captcha.png', hex2bin($response->data['captcha']));
			unset($in);unset($out);
			echo "[INFO] Opening captcha.png for manual user solving.".PHP_EOL;
			exec('captcha.png');
			echo "[REQUEST] Please solve the captcha; Just type in the captcha text: ";
			$captcha = fgets(STDIN);
			$createplayer = NEW YaBOB_Common_Createnewplayer();
			$player = $createplayer->_($UID,'Capital','BNH','1','1',trim(str_replace(PHP_EOL, '', $captcha)));
			$createplayer = $AMF->AMFlength($player).$player;
			$s->write($createplayer);
			$in = $s->read();
			while($in){
				$out = @$out.$in;
				$in = @$s->read();
			}
			$in = substr($out, 4);
			$response = $AMF->destructAMF($in);

			if(!isset($response->data)){
				echo "[ERROR] Look's like someone pissed in Evony's Cheerios today; Most likely just 1 hour IP banned by Evony.".PHP_EOL;
				exit("[EXIT] Unexpected Error");
			}
		}

		if($response->cmd === "common.createNewPlayer" && @$response->data['msg'] === "success"){
			echo "[SUCCESS] Player created with Email: {$emailgen} and Password: {$password} ";
			$x = intval($response->data['player']['castles'][0]['fieldId'] % 800);
			$y = intval($response->data['player']['castles'][0]['fieldId'] / 800);
			echo "Castle coords: {$x},{$y} PlayerID: {$response->data['player']['castles'][0]['id']}".PHP_EOL;
		}else if(isset($response) && $response->data['errorMsg'] === "All Valleys are already occupied, please choose another state. ") {
			echo "[INFO] No flats exist for your city to lay it's ass.".PHP_EOL;
			exit("[EXIT] Server returned: {$response->data['errorMsg']}");
		} else {
			exit("[EXIT] Unknown error: {$response->data['errorMsg']}");
		}
	}
	//var_dump($response);

	echo "[INFO] Closing connection".PHP_EOL;
	unset($s);unset($response);unset($in);unset($out);
	echo "[INFO] Connecting to {$address}:{$port}",PHP_EOL;

	$s = NEW Socket\Client($address,$port);
	$amfHandshake = NEW YaBOB_Handshake();
	$amfLogin = NEW YaBOB_Login();
	$loginInfo = $amfLogin->_($emailgen,$password); unset($amfLogin);
	$loginData = $AMF->AMFlength($loginInfo).$loginInfo;

	$s->write($amfHandshake); unset($amfHandshake);
	$s->write($loginData);
	echo "[INFO] Waiting for reply";
	$in = $s->read();

	while($in){
		$out = @$out.$in;
		$in = @$s->read();
		echo ".";
	}

	echo PHP_EOL;
	echo "[INFO] Recieved reply!",PHP_EOL;

	$out = substr($out, 4);
	$response = $AMF->destructAMF($out);
	
	if(!isset($response->data)){
		echo "[ERROR] Look's like someone pissed in Evony's Cheerios today; most likely just 1 hour IP banned by Evony.".PHP_EOL;
		exit("[EXIT] Unexpected Error");
	}

	if(@$response->data['msg'] === "login success"){
		echo "[SUCCESS] Server returned: {$response->data['msg']}".PHP_EOL;
		echo "[INFO] Saving player data".PHP_EOL;
		$playerformat = "SERVER: {$server}, EMAIL: {$emailgen}, PASSWORD: {$password}, PLAYERNAME: {$UID}, COORD: {$x},{$y}".PHP_EOL;
		file_put_contents("accounts.txt", $playerformat, FILE_APPEND);
	}else if(@$response->data['errorMsg'] === "need create player"){
		echo "[WARNING] Account creation failed!".PHP_EOL;
	} else {
		var_dump($response);
		exit("[EXIT] Server returned: {$response->data['errorMsg']}");
	}
	unset($s);unset($response);unset($in);unset($out);
	echo "[INFO] Waiting 5 second to prevnt ban.".PHP_EOL;
	sleep(5);
}
