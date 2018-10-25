<?php
	class MFinotaurWallet {
		public $address = '';
		public $alias   = '';
		
		private $api_key = '';
		private $api_url = '';
		private $user_id = '';
		//private $token   = '';
		
		public function __construct ($config, $alias) {
			$this->api_key = $config['wallet']['api_key'];
			$this->api_url = $config['wallet']['api_url'];
			$this->user_id = $config['wallet']['user_id'];
			$this->alias = $alias;
		}
		
		public function getaddress() {
			return $this->address;
		}
		
		public function withdrawFromAlias($alias, $address, $amount) {
			//$address - куда отправить
			$base_part = ($this->api_url).'method=sendfrom';
			$auth_part = '&alias='.$alias.'&api_key='.($this->api_key);
			$query_part = '&user_id='.($this->user_id).'&to_address='.$address.'&amount='.$amount.'&dir=receiver';
			//отправляем средства на адрес to_address всего amount монет, комиссия списывается с получателя. чтобы списывалась с вас - поставьте dir в положение self
			$json = cURL($base_part.$auth_part.$query_part, '', '', null);
			//TODO: сократить
			if(!isJSON($json)) {
				throw new Exception("Ошибка запроса вывода средств, полученный ответ - не json.");
			} else {
				$arr = json_decode($json, true);
				if($arr['status'] != 'success') {
					throw new Exception("Получена ошибка при запросе вывода средств: ".$arr['error']);
				} else {
					return $arr['data']['trid'];
				}
			}
		}
		
		public function withdraw($address, $amount) {
			$base_part = ($this->api_url).'method=sendfrom';
			$auth_part = '&alias='.($this->alias).'&api_key='.($this->api_key);
			$query_part = '&user_id='.($this->user_id).'&to_address='.$address.'&amount='.$amount.'&dir=receiver';
			//отправляем средства на адрес to_address всего amount монет, комиссия списывается с получателя. чтобы списывалась с вас - поставьте dir в положение self
			$json = cURL($base_part.$auth_part.$query_part, '', '', null);
			//TODO: сократить
			if(!isJSON($json)) {
				throw new Exception("Ошибка запроса вывода средств, полученный ответ - не json.");
			} else {
				$arr = json_decode($json, true);
				if($arr['status'] != 'success') {
					throw new Exception("Получена ошибка при запросе вывода средств: ".$arr['error']);
				} else {
					return $arr['data']['trid'];
				}
			}
		}
		
		public function getbalance() {
			$base_part = ($this->api_url).'method=getbalance';
			$auth_part = '&alias='.($this->alias).'&api_key='.($this->api_key);
			$query_part = '&user_id='.($this->user_id);
			
			$json = cURL($base_part.$auth_part.$query_part, '', '', null);
			//TODO: сократить
			if(!isJSON($json)) {
				throw new Exception("Ошибка запроса баланса, полученный ответ - не json.");
			} else {
				$arr = json_decode($json, true);
				if($arr['status'] != 'success') {
					throw new Exception("Получена ошибка при запросе баланса: ".$arr['error']);
				} else {
					return $arr['data'];
				}
			}
		}
		
		public function getnewaddress() {
			//части url
			$base_part = ($this->api_url).'method=getnewaddress';
			$auth_part = '&alias='.($this->alias).'&api_key='.($this->api_key);
			$query_part = '&user_id='.($this->user_id);
			
			$json = cURL($base_part.$auth_part.$query_part, '', '', null);
			if(!isJSON($json)) {
				throw new Exception("Ошибка запроса нового адреса, полученный ответ - не json");
			} else {
				$arr = json_decode($json, true);
				if($arr['status'] != 'success') {
					throw new Exception("Получена ошибка при запросе нового адреса: ".$arr['error']);
				} else {
					$this->address = $arr['data']['address'];
				}
			}
		}
	}
	