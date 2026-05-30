<?php
require_once __DIR__ . '/../api/services/pagination_service.php';
require_once __DIR__ . '/../api/services/csv_service.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$pagination = PaginationService::fromQuery(['page' => '-4', 'limit' => '0'], 10, 50);
assertSameValue(1, $pagination['page'], 'Negative page values should clamp to page 1.');
assertSameValue(10, $pagination['limit'], 'Zero limit should fall back to the default limit.');
assertSameValue(0, $pagination['offset'], 'Page 1 offset should be 0.');

$pagination = PaginationService::fromQuery(['page' => '3', 'limit' => '500'], 10, 50);
assertSameValue(3, $pagination['page'], 'Valid page values should be preserved.');
assertSameValue(50, $pagination['limit'], 'Large limit values should clamp to the max limit.');
assertSameValue(100, $pagination['offset'], 'Offset should be calculated from clamped values.');

$pagination = PaginationService::fromQuery(['page' => '999999999999999999999999999999', 'limit' => '100'], 10, 100);
assertSameValue(10000, $pagination['page'], 'Huge page values should clamp to the max page.');
assertSameValue(999900, $pagination['offset'], 'Offset should stay within the max page boundary.');

assertSameValue(3, PaginationService::totalPages(101, 50), 'Total pages should round up safely.');
assertSameValue(0, PaginationService::totalPages(0, 50), 'Empty result sets should report zero pages.');

assertSameValue("'=SUM(A1:A2)", CsvService::safeCell('=SUM(A1:A2)'), 'Formula-leading cells should be prefixed.');
assertSameValue("'+44123456789", CsvService::safeCell('+44123456789'), 'Plus-leading cells should be prefixed.');
assertSameValue("'-10", CsvService::safeCell('-10'), 'Minus-leading cells should be prefixed.');
assertSameValue("'@cmd", CsvService::safeCell('@cmd'), 'At-leading cells should be prefixed.');
assertSameValue('Normal text', CsvService::safeCell('Normal text'), 'Normal cells should remain unchanged.');

$row = CsvService::safeRow(['=A1', 123, null]);
assertSameValue(["'=A1", 123, ''], $row, 'Rows should sanitize text cells and keep numeric cells intact.');

echo "Unit helper tests passed\n";
