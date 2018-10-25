<?php
	class GameUser {
		public $nick         = '';   //имя пользователя
		public $uid          = null; //id пользователя в базе
		public $address      = null; //MFCoin-адрес
		public $wallet_alias = '';   //alias для адреса пользователя в MFinotaurAPI
		
		public function __construct ($user_data) {
			$this->nick     = $user_data['nick'];
			$this->uid      = $user_data['uid'];
			$this->address  = $user_data['address'];
		}
		
		public function setaddress($new_address) {
			$this->address = $new_address;
		}
	}
	