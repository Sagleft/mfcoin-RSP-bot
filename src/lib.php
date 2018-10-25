<?php
	function DataFilter($string) {
		$string = strip_tags($string);
		$string = stripslashes($string);
		$string = htmlspecialchars($string);
		$string = mysql_real_escape_string($string);
		$string = trim($string);
		return $string;
	}
	
	function isValidAddress($address) {
		if($address[0] == 'M' and strlen($address) == 34) {
			return true;
		} else {
			return false;
		}
	}
	
	function generateCode($length=6) {
		$chars = "_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRQSTUVWXYZ0123456789";
		$code = "";
		$clen = strlen($chars) - 1;
		while (strlen($code) < $length) {
			$code .= $chars[mt_rand(0,$clen)];
		}
		return $code;
	}
	
	function isJSON($string) {
		return ((is_string($string) && (is_object(json_decode($string)) || is_array(json_decode($string))))) ? true : false;
	}
	
	function cURL($url, $ref, $cookie, $p=null){
		$ch =  curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		if(isset($_SERVER['HTTP_USER_AGENT'])) {
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		}
		curl_setopt($ch, CURLOPT_REFERER, $ref);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		if($cookie != '') {
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		if ($p) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
		}
		$result =  curl_exec($ch);
		curl_close($ch);
		if ($result){
			return $result;
		} else {
			return '';
		}
	}
	