<?php

declare(strict_types=1);

$normalizerFile = dirname(__DIR__) . '/Lib/VkResponseNormalizer.php';
if (!file_exists($normalizerFile)) {
    throw new RuntimeException('VkResponseNormalizer implementation is missing');
}

require_once $normalizerFile;

use Modules\ModuleNotifier\Lib\VkResponseNormalizer;

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertVkSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

assertVkSame(
    [
        'ok' => true,
        'result' => [
            'message_id' => 42,
            'text' => 'Call text',
        ],
    ],
    VkResponseNormalizer::sentMessage(42, 'Call text'),
    'VK success must preserve message ID and original text'
);

assertVkSame(
    '',
    VkResponseNormalizer::sentMessage(7, '')['result']['text'],
    'VK success must preserve empty text exactly'
);

$notifierSource = file_get_contents(dirname(__DIR__) . '/bin/Notifier.php');
if ($notifierSource === false) {
    throw new RuntimeException('Unable to read Notifier.php');
}

$importPosition = strpos(
    $notifierSource,
    'use Modules\\ModuleNotifier\\Lib\\VkResponseNormalizer;'
);
$errorPosition = strpos($notifierSource, "if (isset(\$body['error']))");
$normalizePosition = strpos(
    $notifierSource,
    "VkResponseNormalizer::sentMessage(\$body['response'] ?? 0, \$messageText)"
);

assertVkSame(
    true,
    $importPosition !== false,
    'Notifier must import the tested VK response normalizer'
);
assertVkSame(
    true,
    $errorPosition !== false
        && $normalizePosition !== false
        && $errorPosition < $normalizePosition,
    'Notifier must normalize only after handling VK API errors'
);

fwrite(STDOUT, "VkResponseNormalizerTest: OK\n");
