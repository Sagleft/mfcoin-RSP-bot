<?php
	/*     by Sagleft.
		https://tele.click/sagleft
		https://vk.com/sagleft
		sagleft.ru
		mfc-market.ru     */
	
	session_start();                                     //сессия для подключения к кошельку
	require_once __DIR__ . "/../config.php";             //параметры
	require_once __DIR__ . "/../src/lib.php";            //функции
	require_once __DIR__ . "/../src/GameUser.php";       //класс игрока
	require_once __DIR__ . "/../src/DataBase.php";       //база данных
	require_once __DIR__ . "/../src/TelegramClient.php"; //подключение к Telegram
	require_once __DIR__ . "/../src/wallet.php";         //подключение к MFinotaur API
	
	//подключаемся к БД
	$database = new DataBase($config);
	$db_link = $database->getdb();
	
	//подключаемся к Telegram
	$client = new TelegramClient($config, $db_link);
	
	//получаем сообщение, адресованное боту
	$message = $client->getMessage();
	//проверим, не пришло ли сообщение из группы
	$type = $client->type;
	if($type == 'group' || $type == 'supergroup') {
		//проверяем, направлено ли сообщение боту
		if((strpos($message, $config['bot']['link']) !== false) && ($message[0] == '/')) {
			$client->postMessage("Я не работаю в группах"); exit;
		}
	}
	
	//ищем пользователя в БД
	if($database->finduser($client->chatID)) {
		//пользователь найден
		$user_data = $database->getuser($client->chatID);
		if($user_data == false) {
			$client->postMessage("☠️ Произошла ошибка при загрузке пользователя");
			exit;
		} else {
			$user = new GameUser($user_data);
			$user_isNew = false;
		}
	} else {
		//пока не добавлен в базу
		$user_data = $database->newuser($client->chatID, $client->name);
		if($user_data == false) {
			$client->postMessage("☠️ Произошла ошибка при регистрации пользователя");
			exit;
		} else {
			$user = new GameUser($user_data);
			$user_isNew = true;
		}
	}
	//формируем alias адреса пользователя. чтобы внутри нашего аккаунта MFinotaurAPI можно было поддерживать несколько проектов - добавляется префикс адресов. wallet_alias пользователя будет вида RSP_52 если вы не меняли префикс в настройках
	$user->wallet_alias = $config['wallet']['prefix'].($user->uid);
	
	//подключаемся к MFCoin-кошельку
	try {
		$wallet = new MFinotaurWallet($config, $user->wallet_alias);
	} catch (Exception $e) {
		$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
	}
	
	//если пользователь новый, то запрашиваем получение нового адреса кошелька
	if($user_isNew) {
		try {
			$wallet->getnewaddress();                    //получаем новый MFCoin-адрес
			$user->setaddress($wallet->getaddress());    //говорим юзеру какой у него адрес
			$database->savenewaddress($wallet->getaddress(), $user->uid); //сохраняем адрес в бд
		} catch (Exception $e) {
			$client->postMessage("☠️ Исключение: ".($e->getMessage())); exit;
		}
	}
	
	$com_list = "\n\n ⚖️ /rules - правила игры\n 🏰 /games - выбрать с кем играть\n 🎲 /bet - разместить ставку\n 🏁 /unbet - 
	отменить ставку\n 💲 /balance - мой баланс\n 💾 /myaddress - мой MFCoin-адрес\n 💰 /withdraw - вывести средства\n © /about - о боте";
	
	//проверяем сообщение, адресованное боту
	//делим строку сообщения по пробелам, чтобы разделить команду и данные
	//$msg_array = explode(" ", $message); //где ожидается, что $msg_array[0] - команда для бота
	//explode заменил на preg_split, чтобы разделять по пробелу и нижнему подчеркиванию
	$msg_array = preg_split("/[ |_]/", $message);
	switch($msg_array[0]) {
		default:
			//команда не найдена
			$client->postMessage("🤷🏿‍♂️ Неверная команда");
			break;
		case '/rules':
			$client->postMessage("💬 Правила игры.\n\n💎 Игрок делает ставку в MFC и выбирает свой ход, так создается игровая комната. Любой другой игрок открывает список комнат, выбирает подходящую по сумме ставки и решает какой сделать ход. Далее по обычной схеме КНБ: камень бьет ножницы, ножницы режут бумагу, бумага накрывает камень.\n 💎 Победитель забирает себе ставку проигравшего в случае победы.\n 💎 Если произошла ничья, то игроки получают свои ставки обратно.\n 💎 Бот берет комиссию с победителя в размере 5%".$com_list);
			break;
		case '/unbet':
			if($database->findbet($user->uid)) {
				if($database->unbet($user->uid)) {
					$client->postMessage("💬 Управление ставкой.\n\n Ваша ставка успешно снята.".$com_list);
				} else {
					$client->postMessage("💬 Управление ставкой.\n\n Произошла ошибка при удалении ставки".$com_list);
				}
			} else {
				$client->postMessage("💬 Управление ставкой.\n\n Вы не делали ставку, отменять нечего.".$com_list);
			}
			break;
		case '/games':
			//получаем список открытых ставок (игровых полей)
			$games = $database->getgames($user->uid);
			if($games == false) {
				$client->postMessage("💬 Список игр.\n\n В данный момент никто не предложил ставок.".$com_list); exit;
			}
			$i = 1;
			$msg = "💬 Список игр.\n";
			while($game = mysql_fetch_assoc($games)) {
				$bet_amount = rtrim(rtrim($game['bet_amount'], '0'), '.');
				$msg .= "\n".$i.") ".$game['nick']." ставит ".$bet_amount." mfc: /accept game".$game['id']." ход";
				$i++;
			}
			$msg .= "\n\nВыберите ставку и введите команду с вашим ходом,\nнапример:\n/accept game520 ножницы\n\nВы можете выбрать камень, ножницы или бумагу";
			$client->postMessage($msg);
			break;
		case '/accept':
			if(!(count($msg_array) >= 3)) {
				//необходимо 3 параметра - команда, номер игры, ход (камень\ножницы\бумага)
				$client->postMessage("💬 Игра.\n\n Использование: \n/accept игра ход\n\nнапример:\n/accept game520 камень\nэта команда примет ставку игры под номером 520 и сообщит, что для хода вы выбрали камень. Также может быть: бумага или ножницы.".$com_list); exit;
			}
			$game_id       = str_replace('game', '', $msg_array[1])+0; //game520 -> 520
			$player_choice = DataFilter($msg_array[2]);
			switch($player_choice) {
				default:
					$client->postMessage("💬 Игра.\n\n Вы ввели неверный ход.\nВыберите: камень, ножницы или бумага.\nнапример\n/accept game520 камень".$com_list); exit;
					break;
				case 'камень':
					$player_choice = 'rock';
					break;
				case 'ножницы':
					$player_choice = 'scissors';
					break;
				case 'бумага':
					$player_choice = 'paper';
					break;
			}
			//найдем, есть ли запись о такой ставке
			$bet_info = $database->findbetbyid($game_id);
			if($bet_info == false) {
				$client->postMessage("💬 Игра.\n\n Ставка по данному ID игры не найдена или устарела.".$com_list); exit;
			}
			//проверяем, хватает ли средств, чтобы принять ставку
			try {
				//баланс кошелька
				$balance_info = $wallet->getbalance();
				//замороженный баланс
				$frozen = $database->getbetamount($user->uid);
				//вычтем замороженный баланс на ставку
				$balance_info['balance'] -= $frozen;
				//проверяем, хватает ли баланса на ставку
				if($bet_info['bet_amount'] > $balance_info['balance']) {
					$client->postMessage("💬 Принятие ставки.\n\n Недостаточно средств для принятия данной ставки.\n Доступно: ".$balance_info['balance']."mfc.".$com_list); exit;
				}
			} catch(Exception $e) {
				$client->postMessage("☠️ Исключение: ".($e->getMessage())); exit;
			}
			//ставка найдена. помечаем ее как неактивную, чтобы начать работу с ней
			$database->markInactiveBet($game_id);
			//игровая логика
			$game_result = '';
			//формально, можно и через двумерный массив и еще как-нибудь,
			//но это первое что пришло на ум
			//game_result оцениваем с позиции того кто принял ставку последним
			$msg2creator = '💬 Игра.'; //что скажем тому, кто создал ставку
			$msg2user    = '💬 Игра.'; //что скажем тому, кто ее принял
			//TODO: вышло как-то длинно, сократить
			switch($player_choice) {
				case 'rock':
					$msg2creator .= "\n\n> ".($user->nick).": камень!";
					$msg2user    .= "\n\n> Вы: камень!";
					switch($bet_info['bet_type']) {
						case 'rock':
							$msg2creator .= "\n> Вы: камень!\n\nНИЧЬЯ!";
							$msg2user    .= "\n> ".$bet_info['nick'].": камень!\n\nНИЧЬЯ!";
							$game_result = 'draw';
							break;
						case 'scissors':
							$msg2creator .= "\n> Вы: ножницы!\n\nЭх! Проигрыш";
							$msg2user    .= "\n> ".$bet_info['nick'].": ножницы!\n\nПОБЕДА!";
							$game_result = 'victory';
							break;
						case 'paper':
							$msg2creator .= "\n> Вы: бумага!\n\nУРА! ПОБЕДА!";
							$msg2user    .= "\n> ".$bet_info['nick'].": бумага!\n\nЭх, проигрыш";
							$game_result = 'defeat';
							break;
					}
					break;
				case 'scissors':
					$msg2creator .= "\n\n> ".($user->nick).": ножницы!";
					$msg2user    .= "\n\n> Вы: ножницы!";
					switch($bet_info['bet_type']) {
						case 'rock':
							$msg2creator .= "\n> Вы: камень!\n\n Победа!";
							$msg2user    .= "\n> ".$bet_info['nick'].": камень!\n\nПоражение";
							$game_result = 'defeat';
							break;
						case 'scissors':
							$msg2creator .= "\n> Вы: ножницы!\n\nНИЧЬЯ!";
							$msg2user    .= "\n> ".$bet_info['nick'].": ножницы!\n\nНИЧЬЯ!";
							$game_result = 'draw';
							break;
						case 'paper':
							$msg2creator .= "\n> Вы: бумага!\n\nЭх, жаль, не повезло";
							$msg2user    .= "\n> ".$bet_info['nick'].": ножницы!\n\nСногсшибательная победа!";
							$game_result = 'victory';
							break;
					}
					break;
				case 'paper':
					$msg2creator .= "\n\n> ".($user->nick).": бумага!";
					$msg2user    .= "\n\n> Вы: бумага!";
					switch($bet_info['bet_type']) {
						case 'rock':
							$msg2creator .= "\n> Вы: камень!\n\nЭх, жаль, не повезло";
							$msg2user    .= "\n> ".$bet_info['nick'].": камень!\n\nСногсшибательная победа!";
							$game_result = 'victory';
							break;
						case 'scissors':
							$msg2creator .= "\n> Вы: ножницы!\n\nУдача на вашей стороне";
							$msg2user    .= "\n> ".$bet_info['nick'].": ножницы!\n\nПолное поражение =(";
							$game_result = 'defeat';
							break;
						case 'paper':
							$msg2creator .= "\n> Вы: бумага!\n\nНИЧЬЯ!";
							$msg2user    .= "\n> ".$bet_info['nick'].": бумага!\n\nНИЧЬЯ! Судью на мыло!";
							$game_result = 'draw';
							break;
					}
					break;
			}
			$bet_info['bet_amount'] = rtrim(rtrim($bet_info['bet_amount'], '0'), '.');
			switch($game_result) {
				case 'draft':
					$msg2creator .= "\nВы получаете свою ставку обратно.";
					$msg2user    .= "\nВы получаете свою ставку обратно.";
					break;
				case 'victory':
					$msg2creator .= "\nВы проиграли ".$bet_info['bet_amount']." mfc";
					$msg2user    .= "\nВы выиграли ".$bet_info['bet_amount']." mfc";
					//создаем выплату победителю - текущему пользователю
					$payout = $bet_info['bet_amount'] * (1-$config['service']['comission']);
					$alias = $config['wallet']['wallet'].$bet_info['uid']; //алиас адреса автора ставки
					try {
						$wallet->withdrawFromAlias($alias, $user->address, $payout);
						//комиссия боту
						$payout = $bet_info['bet_amount'] * $config['service']['comission'];
						$wallet->withdrawFromAlias($alias, $config['service']['address'], $payout);
					} catch (Exception $e) {
						$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
					}
					break;
				case 'defeat':
					$msg2creator .= "\nВы выиграли ".$bet_info['bet_amount']." mfc";
					$msg2user    .= "\nВы проиграли ".$bet_info['bet_amount']." mfc";
					//создаем выплату победителю - автору ставки
					$payout = $bet_info['bet_amount'] * (1-$config['service']['comission']);
					$wallet->withdraw($bet_info['address'], $payout);
					try {
						//комиссия боту
						$payout = $bet_info['bet_amount'] * $config['service']['comission'];
						$wallet->withdraw($config['service']['address'], $payout);
					} catch (Exception $e) {
						$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
					}
					break;
			}
			$msg2creator .= $com_list;
			$msg2user    .= $com_list;
			//закрываем ставку
			$database->unbetbyid($game_id);
			//сообщаем сторонам о результатах игры
			$client->postMessageByTID($bet_info['tid'], $msg2creator);
			$client->postMessage($msg2user);
			break;
		case '/bet':
			if(!(count($msg_array) >= 3)) {
				//необходимо 3 параметра - команда, ставка, ход (камень\ножницы\бумага)
				$client->postMessage("💬 Создание ставки.\n\n Использование: \n/bet ставка ход\n\n например: \n/bet 1 камень\n или \n/bet_1_камень\nэта команда создаст ставку в 1 mfc и выберет камень как ваш ход. Также может быть: бумага или ножницы.\nБыстрые ставки:");
				$client->postMessage("/bet_5_камень");
				$client->postMessage("/bet_5_ножницы");
				$client->postMessage("/bet_5_бумага");
				$client->postMessage($com_list);
				exit;
			} else {
				//смотрим, не разместил ли пользователь ставку ранее
				if($database->findbet($user->uid) == true) {
					$client->postMessage("💬 Создание ставки.\n\n Вы разместили ставку ранее.\n Вы можете отменить свою ставку командой /unbet".$com_list); exit;
				}
				$bet_amount = DataFilter($msg_array[1])+0;
				$bet_type   = DataFilter($msg_array[2]);
				//проверяем минимальный размер ставки
				if(!($bet_amount >= $config['service']['bet_min'])) {
					$client->postMessage("💬 Создание ставки.\n\n Неверная ставка. Сумма ставки должна быть минимум ".$config['service']['bet_min']." mfc".$com_list); exit;
				}
				//проверяем хватает ли средств
				try {
					//баланс кошелька
					$balance_info = $wallet->getbalance();
					//замороженный баланс
					$frozen = $database->getbetamount($user->uid);
					//вычтем замороженный баланс на ставку
					$balance_info['balance'] -= $frozen;
					//проверяем, хватает ли баланса на ставку
					if($bet_amount > $balance_info['balance']) {
						$client->postMessage("💬 Создание ставки.\n\n Недостаточно средств для создания ставки.\n Доступно: ".$balance_info['balance']."mfc.".$com_list); exit;
					}
				} catch(Exception $e) {
					$client->postMessage("☠️ Исключение: ".($e->getMessage())); exit;
				}
				switch($bet_type) {
					default:
						$client->postMessage("💬 Создание ставки.\n\n Неверный ход.\nВы должны выбрать один из вариантов: камень, ножница или бумага".$com_list); exit;
						break;
					case 'камень':
						$bet_type = 'rock';
						break;
					case 'ножницы':
						$bet_type = 'scissors';
						break;
					case 'бумага':
						$bet_type = 'paper';
						break;
				}
				//размещаем ставку
				if($database->setbet($bet_amount, $bet_type, $user->uid)) {
					$client->postMessage("💬 Создание ставки.\n\n Ваша ставка успешно размещена! Когда другой игрок примет вашу ставку и сыграет, вы получите уведомление о результате.\n Если вы хотите отменить ставку, то воспользуйтесь командой /unbet".$com_list);
				} else {
					$client->postMessage("☠️ Произошла ошибка при создании ставки");
				}
			}
			break;
		case '/withdraw':
			if(!(count($msg_array) >= 3)) {
				//необходимо 3 параметра - команда, адрес, сумма
				$client->postMessage("💬 Вывод средств.\nИспользование: \n /withdraw адрес сумма".$com_list); exit;
			} else {
				$w_address = DataFilter($msg_array[1]);
				$w_amount  = DataFilter($msg_array[2])+0;
				if(!isValidAddress($w_address)) {
					$client->postMessage("💬 Вывод средств.\nВведен неверный MFCoin-адрес".$com_list);
					exit;
				}
				if(!($w_amount > 0.001)) {
					$client->postMessage("💬 Вывод средств.\nНеверная сумма для вывода. Минимум 0.001 mfc".$com_list);
					exit;
				}
				try {
					//баланс кошелька
					$balance_info = $wallet->getbalance();
					//замороженные средства
					$bet_amount = $database->getbetamount($user->uid);
					//вычтем замороженный баланс на ставку
					$balance_info['balance'] -= $bet_amount;
					if($balance_info['balance'] >= $w_amount) {
						$trid = $wallet->withdraw($w_address, $w_amount);
						$client->postMessage("💬 Вывод средств.\nСредства успешно отправлены.\nТранзакция: \n".$trid.$com_list);
					}
				} catch (Exception $e) {
					$client->postMessage("☠️ Исключение: ".($e->getMessage())); exit;
				}
			}
			break;
		case '/about':
			$client->postMessage("💬 Игровой бот-пример интеграции MFCoin в Telegram-ботов.\nИсходники: https://github.com/Sagleft/mfcoin-RSP-bot\nЛицензия: Apache-2.0\nАвтор: @Sagleft\n\nПонравился бот? Поставь ⭐️ на Github".$com_list);
			break;
		case '/start':
			//пользователь запускает бота
			$client->postMessage("💬 Приветствую тебя, ".($client->name).".\nСыграем?\nТвой адрес кошелька для пополнения:\n");
			$client->postMessage($user->address);
			$client->postMessage($com_list);
			break;
		case '/myaddress':
			//QR код адреса кошелька пользователя
			$client->postMessage("💬 Ваш MFCoin-адрес: \n".($user->address)."\n\n".$config['service']['qr_encoder'].($user->address)."&6&0".$com_list);
			break;
		case '/balance':
			//запрос баланса
			try {
				//баланс по кошельку
				$balance_info = $wallet->getbalance();
				//получаем замороженный баланс со ставки
				$bet_amount = $database->getbetamount($user->uid)+0;
				//вычтем замороженный баланс на ставку
				$balance_info['balance'] -= $bet_amount;
			} catch (Exception $e) {
				$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
			}
			$client->postMessage("💬 Твой баланс:\n⭐️ доступный: ".$balance_info['balance']." mfc\n❄️ Замороженный: ".$bet_amount." mfc\n🕓 ожидающий: ".$balance_info['awaiting']." mfc".$com_list);
			break;
	}
	