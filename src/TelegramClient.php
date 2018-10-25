<?php
	class TelegramClient {
		var $token = '';
		var $api = 'https://api.telegram.org/bot';
		var $db = null;
		
		public $chatID = null; //tid в бд (telegram id)
		public $name = null;
		public $message = null;
		public $debug = null;
		public $type = null;
		
		public function __construct ($config, $db) {
			$this->db = $db;
			$this->token = $config['bot']['token'];
		}
		
		function getMessage() {
			//получаем сообщение
			$output = json_decode(file_get_contents('php://input'), TRUE);
			$chatID = DataFilter($output['message']['chat']['id'])+0;
			if($this->chatID) {
				exit("invalid chat id");
				//TODO: выдавать exception
			} else {
				$this->chatID = $chatID;
			}
			
			$this->name = DataFilter($output['message']['chat']['first_name']);
			$message = DataFilter($output['message']['text']);
			$this->message = $message;
			$this->type = DataFilter($output['message']['chat']['type']);
			//$this->debug = DataFilter($output['message']['entities']);
			return $message;
		}
		
		function postImage($info, $path, $external=false) {
			$chatID = $this->chatID;
			$url  = $this->api . $this->token . "/sendPhoto?chat_id=".$chatID;
			if($external) {
				$curl_file = new CURLFile($path);
			} else {
				$curl_file = new CURLFile(realpath($path));
			}
			
			$post_fields = array('chat_id' => $chatID,
				'caption' => $info,
				'photo' => $curl_file
			);
			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
			curl_setopt($ch, CURLOPT_URL, $url); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
			$output = curl_exec($ch);
		}
		
		function postMessage($message) {
			$queryURL = $this->api . $this->token . '/sendMessage?chat_id=' . $this->chatID . '&text=' . urlencode($message);
			file_get_contents($queryURL);
		}
		
		function postMessageByTID($tid, $message) {
			$queryURL = $this->api . $this->token . '/sendMessage?chat_id=' . $tid . '&text=' . urlencode($message);
			file_get_contents($queryURL);
		}
	}