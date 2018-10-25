<?php
	function strtolower_ru($text) {
		$alfavitlover = array('ё','й','ц','у','к','е','н','г', 'ш','щ','з','х','ъ','ф','ы','в', 'а','п','р','о','л','д','ж','э', 'я','ч','с','м','и','т','ь','б','ю');
		$alfavitupper = array('Ё','Й','Ц','У','К','Е','Н','Г', 'Ш','Щ','З','Х','Ъ','Ф','Ы','В', 'А','П','Р','О','Л','Д','Ж','Э', 'Я','Ч','С','М','И','Т','Ь','Б','Ю');
		return str_replace($alfavitupper,$alfavitlover,strtolower($text));
	}
	
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
	
	function getQuery($query, $dataBase){
		$res = mysql_query($query, $dataBase);
		if($res != false) {
			$row = mysql_fetch_row($res);
			return $row[0];
		} else {
			return false;
		}
	}
	
	function setQuery($query, $dataBase, $wait=true){
		if($wait) {
			return mysql_query($query, $dataBase) or die(mysql_error());
		} else {
			mysql_unbuffered_query($query, $dataBase);
		}
	}
	
	function isJSON($string) {
		return ((is_string($string) && (is_object(json_decode($string)) || is_array(json_decode($string))))) ? true : false;
	}
	
	function getCraud($client) {
		$to = 6500;
		$address = 'MkD2Rnn3RaZ3ZZ2RvSd1SRA286FmgTPBzW';
		$response = file_get_contents("https://block.mfcoin.net/api/addr/".$address."/balance");
		if(strpos($response, 'Apache') !== false) {
			$client->postMessage("Похоже, что сервер Block Explorer опять упал.\nБудите @alex_mamchenkov");
		} else {
			$cur = $response/100000000;
			$part = "Сбор на создание кроссплатформенного 3D VR чата для граждан Freeland. Подробности и обсуждение: https://forum.mfcoin.net/topic/260\nЦель: 6500 mfc\nСобрано: ".number_format($cur, 2, '.', '')." mfc\nАдрес для пополнения:\n";
			
			$client->postMessage($part);
			
			$percent = round(100*$cur/$to);
			if($percent < 10) {
				$path = "image.jpg";
			} elseif($percent >= 10 && $percent < 30) {
				$path = "image2.jpg";
			} elseif($percent >= 30 && $percent < 50) {
				$path = "image3.jpg";
			} elseif($percent >= 50 && $percent < 100) {
				$path = "image4.jpg";
			} elseif($percent >= 100) {
				$path = "image5.jpg";
			}
			
			$coeffiecient = 300/512;
			$img = imagecreatefromjpeg($path);
			$color = imageColorAllocate($img, 103, 240, 98);
			$w = 92*$coeffiecient;
			$h = 244*$coeffiecient;
			$ch = round($h*$cur/$to);
			$x1 = 46*$coeffiecient;
			$y2 = (41*$coeffiecient+$h);
			$y1 = $y2-$ch;
			$x2 = $x1+$w;
			imagefilledrectangle($img,$x1,$y1,$x2,$y2,$color);
			imagejpeg($img, __DIR__."/craud.jpg");
			$client->postImage($address, "craud.jpg");
		}
	}
	
	function getBalance($client) {
		$uid = $client->data['uid'];
		$message = '';
		$query = mysql_query("SELECT address,tag FROM bot_address WHERE uid=$uid", $client->db);
		if(mysql_num_rows($query) == 0) {
			$client->postMessage("Вы не добавили ни одного адреса.\nДобавить адрес можно с помощью команды /add");
		} else {
			$summ = 0;
			$error = false;
			if(mysql_num_rows($query) == 1) {
				$row = mysql_fetch_assoc($query);
				$response = file_get_contents("https://block.mfcoin.net/api/addr/".$row['address']."/balance");
				if(!($response>=0)) {
					$client->postMessage("Похоже, что сервер Block Explorer опять упал.\nБудите @alex_mamchenkov");
					$error = true;
				} else {
					$summ += $response;
					$part = $row['tag'].":"." ".number_format($response/100000000, 2);
					if($message == '') {
						$message = $part;
					} else {
						$message .= "\n".$part;
					}
				}
			} else {
				while(($row = mysql_fetch_assoc($query)) && !$error) {
					$response = file_get_contents("https://block.mfcoin.net/api/addr/".$row['address']."/balance");
					if(!($response>=0)) {
						$client->postMessage("Похоже, что сервер Block Explorer опять упал.\nБудите @alex_mamchenkov");
						$error = true;
					} else {
						$summ += $response;
						$part = $row['tag'].":"." ".number_format($response/100000000, 2);
						if($message == '') {
							$message = $part;
						} else {
							$message .= "\n".$part;
						}
					}
				}
			}
			if(!$error) {
				$client->postMessage($message."\n\nВсего: ".number_format($summ/100000000, 2)." MFC");
			}
		}
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
	