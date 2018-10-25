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
			$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
		}
	}
	
	$com_list = "\n\n ⚖️ /rules - правила игры\n 🏰 /games - выбрать с кем играть\n 🎲 /bet - разместить ставку\n 💲 /balance - мой баланс\n 💾 /myaddress - мой MFCoin-адрес\n 💰 /withdraw - вывести средства\n © /about - о боте";
	
	//проверяем сообщение, адресованное боту
	//делим строку сообщения по пробелам, чтобы разделить команду и данные
	$msg_array = explode(" ", $message); //где ожидается, что $msg_array[0] - команда для бота
	switch($msg_array[0]) {
		default:
			//команда не найдена
			$client->postMessage("🤷🏿‍♂️ Неверная команда");
			break;
		case '/rules':
			$client->postMessage("💬 Правила игры.\n\n💎 Игрок делает ставку в MFC и выбирает свой ход, так создается игровая комната. Любой другой игрок открывает список комнат, выбирает подходящую по сумме ставки и решает какой сделать ход. Далее по обычной схеме КНБ: камень бьет ножницы, ножницы режут бумагу, бумага накрывает камень.\n 💎 Победитель забирает себе ставку проигравшего в случае победы.\n 💎 Если произошла ничья, то игроки получают свои ставки обратно.\n 💎 Бот берет комиссию с победителя в размере 5%".$com_list);
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
					$balance_info = $wallet->getbalance();
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
			$client->postMessage("💬 Игровой бот-пример интеграции MFCoin в Telegram-ботов.\nИсходники: https://github.com/Sagleft/mfcoin-RSP-bot\nЛицензия: Apache-2.0\nАвтор: @Sagleft".$com_list);
			break;
		case '/start':
			//пользователь запускает бота
			$client->postMessage("💬 Приветствую тебя, ".($client->name).".\nСыграем?\nТвой адрес кошелька для пополнения:\n".($user->address).$com_list);
			break;
		case '/myaddress':
			//QR код адреса кошелька пользователя
			$client->postMessage("💬 Ваш MFCoin-адрес: \n".($user->address)."\n\n".$config['service']['qr_encoder'].($user->address)."&6&0".$com_list);
			break;
		case '/balance':
			//запрос баланса
			try {
				$balance_info = $wallet->getbalance();
			} catch (Exception $e) {
				$client->postMessage("☠️ Исключение: ".($e->getMessage()));exit;
			}
			$client->postMessage("💬 Твой баланс:\n⭐️ доступный: ".$balance_info['balance']." mfc\n🕓 ожидающий: ".$balance_info['awaiting']." mfc".$com_list);
			break;
	}
	