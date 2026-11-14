// 3. Вебхук от Telegram (/start, /language и инлайн-кнопки)
if ($uri === '/webhook/telegram' && $method === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true) ?? [];
    $botClient = new TelegramBotClient($botToken);

    $chatId = $update['message']['chat']['id'] ?? null;
    $text = trim($update['message']['text'] ?? '');

    // 3.1. Команда /start (полная инструкция для подключения)
    if ($chatId && str_starts_with($text, '/start')) {
        $currentLocale = $localeStorage->get((int)$chatId);
        $welcome = buildWelcomeMessage((int)$chatId, $currentLocale);

        $startKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "🇺🇿 O'zbekcha", 'callback_data' => 'start_lang_uz'],
                    ['text' => '🇷🇺 Русский', 'callback_data' => 'start_lang_ru'],
                    ['text' => '🇬🇧 English', 'callback_data' => 'start_lang_en'],
                ]
            ]
        ];

        $botClient->sendMessage((int)$chatId, $welcome, $startKeyboard);
    }

    // 3.2. Команда /language (быстрая смена языка без инструкции)
    if ($chatId && (str_starts_with($text, '/language') || str_starts_with($text, '/lang'))) {
        $currentLocale = $localeStorage->get((int)$chatId);

        $prompt = match ($currentLocale) {
            Locale::UZ => "🌐 *Xabarnomalar tilini tanlang:*\nHozirgi til: *O'zbekcha* 🇺🇿",
            Locale::EN => "🌐 *Choose notification language:*\nCurrent language: *English* 🇬🇧",
            Locale::RU => "🌐 *Выберите язык уведомлений:*\nТекущий язык: *Русский* 🇷🇺",
        };

        $langKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "🇺🇿 O'zbekcha", 'callback_data' => 'set_lang_uz'],
                    ['text' => '🇷🇺 Русский', 'callback_data' => 'set_lang_ru'],
                    ['text' => '🇬🇧 English', 'callback_data' => 'set_lang_en'],
                ]
            ]
        ];

        $botClient->sendMessage((int)$chatId, $prompt, $langKeyboard);
    }

    // 3.3. Обработка нажатий на инлайн-кнопки
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $callbackId = (string)($callback['id'] ?? '');
        $cbChatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = (string)($callback['data'] ?? '');

        // Переключение языка прямо внутри стартовой инструкции (/start)
        if ($cbChatId && $messageId && str_starts_with($data, 'start_lang_')) {
            $langCode = substr($data, 11);
            $newLocale = Locale::tryFrom($langCode) ?? Locale::RU;

            $localeStorage->save((int)$cbChatId, $newLocale);

            $startKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "🇺🇿 O'zbekcha", 'callback_data' => 'start_lang_uz'],
                        ['text' => '🇷🇺 Русский', 'callback_data' => 'start_lang_ru'],
                        ['text' => '🇬🇧 English', 'callback_data' => 'start_lang_en'],
                    ]
                ]
            ];

            $updatedWelcome = buildWelcomeMessage((int)$cbChatId, $newLocale);
            $botClient->editMessageText((int)$cbChatId, (int)$messageId, $updatedWelcome, $startKeyboard);
            $botClient->answerCallbackQuery($callbackId);
        }

        // Быстрое переключение через команду /language
        if ($cbChatId && $messageId && str_starts_with($data, 'set_lang_')) {
            $langCode = substr($data, 9);
            $newLocale = Locale::tryFrom($langCode) ?? Locale::RU;

            $localeStorage->save((int)$cbChatId, $newLocale);

            [$confirmText, $toast] = match ($newLocale) {
                Locale::UZ => [
                    "✅ Xabarnomalar tili *O'zbekcha*ga o'zgartirildi 🇺🇿",
                    "🇺🇿 O'zbek tili o'rnatildi",
                ],
                Locale::EN => [
                    "✅ Notification language changed to *English* 🇬🇧",
                    "🇬🇧 Language set to English",
                ],
                Locale::RU => [
                    "✅ Язык уведомлений изменён на *Русский* 🇷🇺",
                    "🇷🇺 Установлен русский язык",
                ],
            };

            $botClient->editMessageText((int)$cbChatId, (int)$messageId, $confirmText);
            $botClient->answerCallbackQuery($callbackId, $toast);
        }
    }

    http_response_code(200);
    echo 'OK';
    exit;
}
