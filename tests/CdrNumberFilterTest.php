<?php

declare(strict_types=1);

$filterFile = dirname(__DIR__) . '/Lib/CdrNumberFilter.php';
if (!file_exists($filterFile)) {
    throw new RuntimeException('CdrNumberFilter implementation is missing');
}

require_once $filterFile;

use Modules\ModuleNotifier\Lib\CdrNumberFilter;

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

$multiRowCall = [
    'rows' => [
        ['src_num' => '+79493042807', 'dst_num' => '2003'],
        ['src_num' => '+79493042807', 'dst_num' => '202'],
        ['src_num' => '+79493042807', 'dst_num' => '2001'],
    ],
];

assertSameValue(
    true,
    (new CdrNumberFilter(''))->allows($multiRowCall),
    'An empty filter must allow every call'
);

assertSameValue(
    true,
    (new CdrNumberFilter("3001\n2001"))->allows($multiRowCall),
    'A dst_num match in one row must allow the complete call'
);

assertSameValue(
    true,
    (new CdrNumberFilter('+7(949)304-28-07'))->allows($multiRowCall),
    'Non-digit characters must be ignored in configured and CDR numbers'
);

assertSameValue(
    true,
    (new CdrNumberFilter("2001 2001\n---"))->allows($multiRowCall),
    'Duplicate and empty normalized entries must be harmless'
);

assertSameValue(
    false,
    (new CdrNumberFilter('2002'))->allows($multiRowCall),
    'A call without a configured endpoint must be rejected'
);

assertSameValue(
    false,
    (new CdrNumberFilter('2001'))->allows([
        'rows' => [['src_num' => '12001', 'dst_num' => '20010']],
    ]),
    'Matching must be exact'
);

assertSameValue(
    true,
    (new CdrNumberFilter('74943042807'))->allows([
        'rows' => [['src_num' => '+7 (494) 304-28-07']],
    ]),
    'A src_num match must allow a call'
);

assertSameValue(
    false,
    (new CdrNumberFilter('2001'))->allows(['rows' => [[]]]),
    'Missing endpoint fields must be treated as empty'
);

$connectorSource = file_get_contents(dirname(__DIR__) . '/bin/ConnectorDB.php');
if ($connectorSource === false) {
    throw new RuntimeException('Unable to read ConnectorDB.php');
}

assertSameValue(
    true,
    strpos($connectorSource, 'use Modules\\ModuleNotifier\\Lib\\CdrNumberFilter;') !== false,
    'ConnectorDB must import CdrNumberFilter'
);
assertSameValue(
    true,
    strpos($connectorSource, "new CdrNumberFilter((string)(\$settings->numberFilter ?? ''))") !== false,
    'ConnectorDB must refresh the filter from module settings'
);

$filterPosition = strpos($connectorSource, 'if (!$this->numberFilter->allows($cdr))');
$sendPosition = strpos($connectorSource, '$this->sendEditMessage($cdr);');
assertSameValue(
    true,
    $filterPosition !== false && $sendPosition !== false && $filterPosition < $sendPosition,
    'ConnectorDB must reject a complete group before sending its notification'
);

$modelSource = file_get_contents(dirname(__DIR__) . '/Models/ModuleNotifier.php');
$formSource = file_get_contents(dirname(__DIR__) . '/App/Forms/ModuleNotifierForm.php');
$viewSource = file_get_contents(dirname(__DIR__) . '/App/Views/index.volt');
$russianMessages = require dirname(__DIR__) . '/Messages/ru.php';
$englishMessages = require dirname(__DIR__) . '/Messages/en.php';

assertSameValue(
    true,
    $modelSource !== false && strpos($modelSource, 'public $numberFilter;') !== false,
    'The settings model must persist numberFilter'
);
assertSameValue(
    true,
    $formSource !== false && strpos($formSource, "new TextArea('numberFilter'") !== false,
    'The form must expose numberFilter as a textarea'
);
assertSameValue(
    true,
    $viewSource !== false && strpos($viewSource, "form.render('numberFilter')") !== false,
    'The view must render numberFilter'
);
assertSameValue(
    true,
    isset($russianMessages['module_notifier_numberFilter'], $russianMessages['module_notifier_numberFilter_help']),
    'Russian number filter translations must exist'
);
assertSameValue(
    true,
    isset($englishMessages['module_notifier_numberFilter'], $englishMessages['module_notifier_numberFilter_help']),
    'English number filter translations must exist'
);
assertSameValue(
    true,
    strpos($russianMessages['module_notifier_numberFilter_help'] ?? '', 'Пустой список') !== false,
    'Russian help must explain empty-filter behavior'
);
assertSameValue(
    true,
    strpos($englishMessages['module_notifier_numberFilter_help'] ?? '', 'empty') !== false,
    'English help must explain empty-filter behavior'
);

fwrite(STDOUT, "CdrNumberFilterTest: OK\n");
