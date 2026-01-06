<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

const PRINTER_DEVICE = '/dev/usb/lp0';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Error: Expecting a POST request';
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo 'Error: Invalid JSON payload';
    exit;
}

$issue = $data['issue'] ?? [];
$repo = $data['repository'] ?? [];

$title = $issue['title'] ?? '(no title)';
$body = $issue['body'] ?? '';
$createdAt = $issue['created_at'] ?? '';
$user = $issue['user']['login'] ?? 'unknown';
$repoName = $repo['full_name'] ?? 'unknown';

$connector = null;
$printer = null;

try {
    $connector = new FilePrintConnector(PRINTER_DEVICE);
    $printer = new Printer($connector);

    printHeader($printer, $user, $repoName);
    printTitle($printer, $title);
    printBody($printer, $body);
    printFooter($printer, $createdAt);

    http_response_code(200);
    echo 'Printed successfully';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Printing failed: ' . $e->getMessage();
} finally {
    if ($printer instanceof Printer) {
        $printer->close();
    }
}

/**
 * Prints the receipt header.
 */
function printHeader(Printer $printer, string $user, string $repo): void
{
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2);
    $printer->setUnderline();
    $printer->setEmphasis();
    $printer->text("New Issue\n");
    $printer->feed(2);

    $printer->setJustification();
    $printer->setTextSize(1, 1);
    $printer->setUnderline();
    $printer->setEmphasis(false);
    $printer->text("Repo: $repo\n");
    $printer->text("User: @$user\n");
    $printer->feed(2);
}

/**
 * Prints the issue title.
 */
function printTitle(Printer $printer, string $title): void
{
    $printer->setEmphasis();
    $printer->text($title . "\n");
    $printer->setEmphasis(false);
    $printer->feed(2);
}

/**
 * Prints the issue body.
 */
function printBody(Printer $printer, string $body): void
{
    if ($body !== '') {
        $printer->text($body . "\n");
        $printer->feed(2);
    }
}

/**
 * Prints the footer and cuts the paper.
 */
function printFooter(Printer $printer, string $timestamp): void
{
    if ($timestamp !== '') {
        $printer->text($timestamp . "\n");
        $printer->feed(2);
    }
    $printer->cut();
}
