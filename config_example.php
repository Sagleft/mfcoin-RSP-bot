<?
	$config = [
		'bot' => [
			'token' => '',            //токен бота от BotFather,
			'link'  => '@sspgame_bot' //ссылка на бота
		],
		'db' => [
			'host'     => 'localhost',   //хост бд
			'name'     => 'rsp_game',    //имя бд
			'user'     => 'root',        //пользователь бд
			'password' => ''             //пароль
		],
		'wallet'  => [
			'user_id' => '', //ваш ser_id
			'api_key' => '', //ваш api_key
			'api_url' => 'https://mfinotaur.mfcoin.su/api?',
			'prefix'  => 'RSP_' //префикс для alias адресов пользователей. подробнее в MFinotaur API
		],
		'service' => [
			'comission'  => 0.05, //5% комиссия с победителя
			'qr_encoder' => 'http://qrcoder.ru/code/?',
			'bet_min'    => 1     //минимальная ставка в mfc
		]
	];
	