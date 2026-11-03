<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Exception\InvalidEmailPayloadException;
use App\Domain\Exception\UnsupportedNotificationException;
use App\Infrastructure\Contract\EmailParserInterface;
use App\Infrastructure\Contract\NotificationSenderInterface;

final readonly class ProcessEmailNotificationUseCase
{
    /**
     * @param iterable<EmailParserInterface> $parsers
     */
    public function __construct(
        private iterable $parsers,
        private NotificationSenderInterface $sender
    ) {}

    public function execute(string $toAddress, string $fromAddress, string $rawEmailBody): void
    {
        // 1. Извлекаем telegram_chat_id из адреса получателя: card_{chat_id}@domain.com
        if (!preg_match('/card_(\d+)@/i', $toAddress, $matches)) {
            throw new InvalidEmailPayloadException("Некорректный адрес получателя: {$toAddress}");
        }
        $chatId = $matches[1];

        // 2. Подбираем подходящий парсер (Chain of Responsibility / Strategy)
        $selectedParser = null;
        foreach ($this->parsers as $parser) {
            if ($parser->supports($fromAddress, $rawEmailBody)) {
                $selectedParser = $parser;
                break;
            }
        }

        if ($selectedParser === null) {
            throw new UnsupportedNotificationException("Не найден подходящий парсер для отправителя: {$fromAddress}");
        }

        // 3. Парсим и отправляем
        $transaction = $selectedParser->parse($rawEmailBody);
        $this->sender->sendTransactionAlert($chatId, $transaction);
    }
}