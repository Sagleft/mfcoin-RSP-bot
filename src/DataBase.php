<?php
	class DataBase {
		private $db = null;
		
		public function __construct ($config) {
			//TODO: сделать проверку существования mysql_connect, если нет, использовать mysqli_connect
			$db = mysql_connect ($config['db']['host'], $config['db']['user'], $config['db']['password']);
			mysql_select_db ($config['db']['name'], $db);
			$this->db = $db;
		}
		
		public function getdb() {
			return $this->db;
		}
		
		public function savenewaddress($address, $uid) {
			$address = DataFilter($address);
			$uid = DataFilter($uid)+0;
			mysql_query("UPDATE users SET address='".$address."' WHERE uid=".$uid, $this->db);
		}
		
		public function finduser($tid) {
			$query = mysql_query("SELECT uid,nick,address FROM users WHERE tid=".($tid+0), $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return true;
			}
		}
		
		public function getuser($tid) {
			$query = mysql_query("SELECT uid,nick,address FROM users WHERE tid=".($tid+0), $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return mysql_fetch_assoc($query);
			}
		}
		
		public function newuser($tid, $nick) {
			$nick = DataFilter($nick);
			$tid = DataFilter($tid)+0;
			mysql_query("INSERT INTO users (nick,tid,address) VALUES ('$nick', $tid, 'test')", $this->db);
			$query = mysql_query("SELECT uid,nick,address FROM users WHERE tid=".$tid, $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return mysql_fetch_assoc($query);
			}
		}
		
		public function findbet($uid) {
			//найти была ли размещена ставка пользователем по идентификатору uid
			$query = mysql_query("SELECT id FROM game WHERE player=".$uid, $thid->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return true;
			}
		}
		
		public function setbet($amount, $type, $uid) {
			//добавить ставку пользователя
			$query = mysql_query("INSERT INTO game (player, bet_amount, bet_type) VALUES ($uid, $amount, '$type')", $this->db);
			if($query == false) {
				return false;
			} else {
				return true;
			}
		}
		
		public function unbet($uid) {
			//убрать ставку пользователя
		}
	}
	