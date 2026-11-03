<?php

declare(strict_types=1);

namespace App\Infrastructure\Parser;

final class GoogleForwardingParser
{
    public function supports(string $sender, string $rawBody): bool
    {
        $content = mb_strtolower($sender . ' ' . $rawBody);
        return str_contains($content, 'forwarding-noreply@google.com')
            || str_contains($content, 'mail-settings.google.com/mail/vf');
    }

    /**
     * @return array{confirmUrl: ?string, code: ?string}
     */
    public function parse(string $rawBody): array
    {
        // 1. Извлекаем прямую ссылку подтверждения Google
        // Декодируем переводы строк и сущности HTML, которые часто разбивают длинные URL в raw email
        $cleanBody = html_entity_decode(str_replace(["\r\n", "\n", "\r", "="], '', $rawBody));
        preg_match('/(https:\/\/mail-settings\.google\.com\/mail\/vf-[a-zA-Z0-9_\-\.]+)/i', $cleanBody, $urlMatches);
        $confirmUrl = $urlMatches[1] ?? null;

        // Если не нашли очищенную, пробуем прямой поиск
        if (!$confirmUrl && preg_match('/(https:\/\/mail-settings\.google\.com\/mail\/vf[^\s<>"\'\)]+)/i', $rawBody, $fallbackUrlMatches)) {
            $confirmUrl = $fallbackUrlMatches[1];
        }

        // 2. Извлекаем числовой код подтверждения (обычно 9 цифр)
        preg_match('/(?:код подтверждения|confirmation code)[\s:]*([0-9]{6,12})/ui', $rawBody, $codeMatches);
        $code = $codeMatches[1] ?? null;

        return [
            'confirmUrl' => $confirmUrl,
            'code' => $code,
        ];
    }
}