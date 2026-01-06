<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

const PRINTER_DEVICE = '/dev/usb/lp0';
const MAX_CHARS_NORMAL = 48;   // normal font, full width
const MAX_CHARS_DOUBLE = 24;   // double-width text

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

try {
    $connector = new FilePrintConnector(PRINTER_DEVICE);
    $printer = new Printer($connector);

    $printer->initialize();

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
 * Prints the header with double-width "New Issue" and normal text for repo/user
 */
function printHeader(Printer $printer, string $user, string $repo): void
{
    // Big centered header
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2); // double width/height
    $printer->setEmphasis(true);
    $printer->text(wordwrap("New Issue", MAX_CHARS_DOUBLE) . "\n");
    $printer->feed(2);

    // Repo / User normal text
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->setTextSize(1, 1);
    $printer->setEmphasis(false);
    $printer->text(wordwrap("Repo: $repo", MAX_CHARS_NORMAL) . "\n");
    $printer->text(wordwrap("User: @$user", MAX_CHARS_NORMAL) . "\n");
    $printer->feed(2);
}

/**
 * Prints the issue title in double width
 */
function printTitle(Printer $printer, string $title): void
{
    $printer->setEmphasis(true);
    $printer->setTextSize(2, 2); // double width/height
    $printer->text(wordwrap($title, MAX_CHARS_DOUBLE) . "\n");
    $printer->setTextSize(1, 1); // back to normal
    $printer->setEmphasis(false);
    $printer->feed(2);
}

/**
 * Prints the body in normal font
 */
function printBody(Printer $printer, string $body): void
{
    if ($body !== '') {
        $printer->text(wordwrap($body, MAX_CHARS_NORMAL) . "\n");
        $printer->feed(2);
    }
}

/**
 * Prints footer and cuts
 */
function printFooter(Printer $printer, string $timestamp): void
{
    if ($timestamp !== '') {
        $printer->text(wordwrap($timestamp, MAX_CHARS_NORMAL) . "\n");
        $printer->feed(2);
    }
    $printer->cut(Printer::CUT_PARTIAL);
}