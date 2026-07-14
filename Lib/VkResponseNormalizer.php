<?php

declare(strict_types=1);

namespace Modules\ModuleNotifier\Lib;

final class VkResponseNormalizer
{
    /**
     * @param mixed $messageId
     */
    public static function sentMessage($messageId, string $messageText): array
    {
        return [
            'ok' => true,
            'result' => [
                'message_id' => $messageId,
                'text' => $messageText,
            ],
        ];
    }
}
