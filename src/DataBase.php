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
			if(strlen($nick) > 72) {
				mb_internal_encoding("UTF-8");
				$nick = mb_substr($string, 0, 68).'...';
			}
			if(strtolower($nick) == 'admin' || strtolower($nick) == 'sagleft') {
				$nick = 'UserName';
			}
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
			$query = mysql_query("SELECT id FROM game WHERE player=".$uid, $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return true;
			}
		}
		
		public function findbetbyid($id){
			//найти активную ставку по id
			$query = mysql_query("SELECT g.id,g.player,g.bet_amount,g.bet_type,u.nick,u.tid,u.address,u.uid FROM game g INNER JOIN users u ON g.player=u.uid WHERE id=".$id." AND active='1'", $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return mysql_fetch_assoc($query);
			}
		}
		
		public function markInactiveBet($id) {
			//помечаем ставку как неактивную
			mysql_query("UPDATE game SET active='0' WHERE id=".$id, $this->db);
		}
		
		public function getgames($uid) {
			$query = mysql_query("SELECT g.id,g.bet_amount,u.nick FROM game g INNER JOIN users u ON g.player=u.uid WHERE u.uid!=".$uid." AND g.active='1'", $this->db);
			if(mysql_num_rows($query) == 0) {
				return false;
			} else {
				return $query;
			}
		}
		
		public function getbetamount($uid) {
			//получить сумму ставки
			$query = mysql_query("SELECT IFNULL(SUM(bet_amount),0) FROM game WHERE player=".$uid, $this->db);
			if(mysql_num_rows($query) == 0) {
				return 0;
			} else {
				$result = mysql_fetch_row($query);
				return $result[0];
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
			$query = mysql_query("DELETE FROM game WHERE player=".$uid, $this->db);
			if($query == false) {
				return false;
			} else {
				return true;
			}
		}
		
		public function unbetbyid($id) {
			//убрать ставку пользователя
			mysql_query("DELETE FROM game WHERE id=".$id, $this->db);
		}
	}
	