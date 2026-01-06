<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

const PRINTER_DEVICE = '/dev/usb/lp0';
const MAX_CHARS_PER_LINE = 48; // fits 58mm paper

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Error: Expecting a POST request';
    exit;
}

$githubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo 'Error: Invalid JSON payload';
    exit;
}

try {
    $connector = new FilePrintConnector(PRINTER_DEVICE);
    $printer = new Printer($connector);

    $printer->initialize();

    switch ($githubEvent) {
        case 'issues':
            printIssue($printer, $data);
            break;

        case 'pull_request':
            printPullRequest($printer, $data);
            break;

        case 'workflow_run':
            if (($data['workflow_run']['conclusion'] ?? '') === 'failure') {
                printWorkflowRunFailure($printer, $data);
            }
            break;

        default:
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Unknown GitHub Event: $githubEvent\n");
            $printer->feed(2);
            break;
    }

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

function printLogo(Printer $printer, string $logoPath = './logo.png'): void
{
    if (!file_exists($logoPath)) {
        return;
    }

    try {
        $logo = EscposImage::load($logoPath, false);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->bitImage($logo);
        $printer->feed(1);
    } catch (Exception $e) {
        // ignore image errors
    }
}

/** Print issue
 * @throws Exception
 */
function printIssue(Printer $printer, array $data): void
{
    $issue = $data['issue'] ?? [];
    $repo = $data['repository'] ?? [];

    $title = $issue['title'] ?? '(no title)';
    $body = $issue['body'] ?? '';
    $createdAt = $issue['created_at'] ?? '';
    $user = $issue['user']['login'] ?? 'unknown';
    $repoName = $repo['full_name'] ?? 'unknown';
    $issueUrl = $issue['html_url'] ?? '';
    $labels = $issue['labels'] ?? [];

    printLogo($printer);
    printHeader($printer, "New Issue", $user, $repoName);
    printLabels($printer, $labels);
    printTitle($printer, $title);
    printBody($printer, $body);

    // QR code for the issue link
    if ($issueUrl !== '') {
        $printer->feed(1);
        $printer->qrCode($issueUrl, Printer::QR_ECLEVEL_L, 6);
        $printer->feed(2);
    }

    // Footer timestamp
    printFooter($printer, $createdAt);
}

/** Print pull request */
function printPullRequest(Printer $printer, array $data): void
{
    $pr = $data['pull_request'] ?? [];
    $repo = $data['repository'] ?? [];

    $title = $pr['title'] ?? '(no title)';
    $body = $pr['body'] ?? '';
    $createdAt = $pr['created_at'] ?? '';
    $user = $pr['user']['login'] ?? 'unknown';
    $repoName = $repo['full_name'] ?? 'unknown';
    $action = $data['action'] ?? 'opened';
    $labels = $pr['labels'] ?? [];

    printLogo($printer);
    printHeader($printer, "Pull Request [$action]", $user, $repoName);
    printLabels($printer, $labels);
    printTitle($printer, $title);
    printBody($printer, $body);
    printFooter($printer, $createdAt);
}

/** Print labels in pill style: [bug] [enhancement] */
function printLabels(Printer $printer, array $labels): void
{
    if (empty($labels)) {
        return;
    }

    $pillText = '';
    foreach ($labels as $label) {
        $pillText .= '[' . ($label['name'] ?? '') . '] ';
    }

    $printer->setJustification();
    $printer->setEmphasis();
    $printer->text(wordwrap(trim($pillText), MAX_CHARS_PER_LINE) . "\n");
    $printer->setEmphasis(false);
    $printer->feed(1);
}

/** Print failed workflow run */
function printWorkflowRunFailure(Printer $printer, array $data): void
{
    $workflow = $data['workflow_run'] ?? [];
    $repo = $data['repository'] ?? [];

    $name = $workflow['name'] ?? '(unknown workflow)';
    $runId = $workflow['id'] ?? '';
    $conclusion = $workflow['conclusion'] ?? 'failure';
    $timestamp = $workflow['updated_at'] ?? '';
    $repoName = $repo['full_name'] ?? 'unknown';

    printLogo($printer);
    printHeader($printer, "Workflow Failed", '', $repoName);

    $printer->setJustification();
    $printer->setTextSize(1, 1);
    $printer->setEmphasis(false);
    $printer->text("Workflow: $name\n");
    $printer->text("Run ID: $runId\n");
    $printer->text("Status: $conclusion\n");
    $printer->feed(2);

    printFooter($printer, $timestamp);
}

/** Header with title + repo/user */
function printHeader(Printer $printer, string $title, string $user, string $repo): void
{
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2);
    $printer->setEmphasis(true);
    $printer->text(wordwrap($title, MAX_CHARS_PER_LINE) . "\n");
    $printer->feed(2);

    if ($repo !== '' || $user !== '') {
        $printer->setJustification();
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        if ($repo !== '') $printer->text(wordwrap("Repo: $repo", MAX_CHARS_PER_LINE) . "\n");
        if ($user !== '') $printer->text(wordwrap("User: $user", MAX_CHARS_PER_LINE) . "\n");
        $printer->feed(2);
    }
}

/** Title / body / footer */
function printTitle(Printer $printer, string $title): void
{
    if ($title !== '') {
        $printer->setEmphasis(true);
        $printer->text(wordwrap($title, MAX_CHARS_PER_LINE) . "\n");
        $printer->setEmphasis(false);
        $printer->feed(2);
    }
}

function printBody(Printer $printer, string $body): void
{
    if ($body !== '') {
        $printer->text(wordwrap($body, MAX_CHARS_PER_LINE) . "\n");
        $printer->feed(2);
    }
}

function printFooter(Printer $printer, string $timestamp): void
{
    if ($timestamp !== '') {
        $printer->text(wordwrap($timestamp, MAX_CHARS_PER_LINE) . "\n");
        $printer->feed(2);
    }
    $printer->cut(Printer::CUT_PARTIAL);
}