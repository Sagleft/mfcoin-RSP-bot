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
			$client->postMessage("Я не работаю в группах");
			exit;
		}
	}
	
	//ищем пользователя в БД
	if($database->finduser($client->chatID)) {
		//пользователь найден
		$user_data = $database->getuser($client->chatID);
		if($user_data == false) {
			$client->postMessage("Произошла ошибка при загрузке пользователя");
			exit;
		} else {
			$user = new GameUser($user_data);
			$user_isNew = false;
		}
	} else {
		//пока не добавлен в базу
		$user_data = $database->newuser($client->chatID, $client->name);
		if($user_data == false) {
			$client->postMessage("Произошла ошибка при регистрации пользователя");
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
		$client->postMessage("Исключение: ".($e->getMessage()));
	}
	
	//если пользователь новый, то запрашиваем получение нового адреса кошелька
	if($user_isNew) {
		try {
			$wallet->getnewaddress();                    //получаем новый MFCoin-адрес
			$user->setaddress($wallet->getaddress());    //говорим юзеру какой у него адрес
			$database->savenewaddress($wallet->getaddress(), $user->uid); //сохраняем адрес в бд
		} catch (Exception $e) {
			$client->postMessage("Исключение: ".($e->getMessage()));
		}
	}
	
	//проверяем сообщение, адресованное боту
	switch($message) {
		default:
			//команда не найдена
			$client->postMessage("Неверная команда");
			break;
		case '/about':
			$client->postMessage("Игровой бот-пример интеграции MFCoin в Telegram-ботов.\nИсходники: https://github.com/Sagleft/mfcoin-RSP-bot\nЛицензия: Apache-2.0\nАвтор: @Sagleft");
			break;
		case '/start':
			//пользователь запускает бота
			$client->postMessage("Приветствую тебя, ".($client->name).".\nСыграем?\nТвой адрес кошелька для пополнения:\n".($user->address));
			break;
		case '/balance':
			//запрос баланса
			try {
				$balance_info = $wallet->getbalace();
			} catch (Exception $e) {
				$client->postMessage("Исключение: ".($e->getMessage()));
			}
			$client->postMessage("Твой баланс:\nдоступный: ".$balance_info['balance']." mfc\nожидающий: ".$balance_info['awaiting']." mfc");
			break;
	}
	