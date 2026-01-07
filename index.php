<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

const PRINTER_DEVICE = '/dev/usb/lp0';

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
            $printer->text("Unknown GitHub Event\n$githubEvent\n");
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
        $logo = EscposImage::load($logoPath);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->graphics($logo);
        $printer->feed(2);
    } catch (Throwable $e) {
        // ignore image errors
    }
}

function printIssue(Printer $printer, array $data): void
{
    $issue = $data['issue'] ?? [];
    $repo  = $data['repository'] ?? [];

    $title       = $issue['title'] ?? '(no title)';
    $body        = $issue['body'] ?? '';
    $createdAt  = $issue['created_at'] ?? '';
    $user        = $issue['user']['login'] ?? 'unknown';
    $repoName    = $repo['full_name'] ?? 'unknown';
    $labels      = $issue['labels'] ?? [];
    $issueNumber = (int)($issue['number'] ?? 0);
    $assignee    = $issue['assignee']['login'] ?? '';

    // printLogo($printer);
    printHeader($printer, 'New Issue', $user, $repoName, $issueNumber, $assignee);
    printLabels($printer, $labels);
    printTitle($printer, $title);
    printBody($printer, $body);
    printFooter($printer, $createdAt);
}

function printPullRequest(Printer $printer, array $data): void
{
    $pr   = $data['pull_request'] ?? [];
    $repo = $data['repository'] ?? [];

    $title       = $pr['title'] ?? '(no title)';
    $body        = $pr['body'] ?? '';
    $createdAt  = $pr['created_at'] ?? '';
    $user        = $pr['user']['login'] ?? 'unknown';
    $repoName    = $repo['full_name'] ?? 'unknown';
    $action      = $data['action'] ?? 'opened';
    $labels      = $pr['labels'] ?? [];
    $issueNumber = (int)($pr['number'] ?? 0);

    // printLogo($printer);
    printHeader($printer, "Pull Request [$action]", $user, $repoName, $issueNumber);
    printLabels($printer, $labels);
    printTitle($printer, $title);
    printBody($printer, $body);
    printFooter($printer, $createdAt);
}

function printLabels(Printer $printer, array $labels): void
{
    if (empty($labels)) {
        return;
    }

    $printer->setEmphasis(true);
    foreach ($labels as $label) {
        $name = $label['name'] ?? '';
        if ($name !== '') {
            $printer->text("[$name] ");
        }
    }
    $printer->text("\n");
    $printer->setEmphasis(false);
    $printer->feed(1);
}

function printWorkflowRunFailure(Printer $printer, array $data): void
{
    $workflow = $data['workflow_run'] ?? [];
    $repo     = $data['repository'] ?? [];

    $name      = $workflow['name'] ?? '(unknown workflow)';
    $runId     = $workflow['id'] ?? '';
    $timestamp = $workflow['updated_at'] ?? '';
    $repoName  = $repo['full_name'] ?? 'unknown';

    // printLogo($printer);
    printHeader($printer, 'Workflow Failed', '', $repoName, 0);

    $printer->text("Workflow: $name\n");
    $printer->text("Run ID: $runId\n");
    $printer->text("Status: FAILURE\n");
    $printer->feed(2);

    printFooter($printer, $timestamp);
}

function printHeader(
    Printer $printer,
    string $title,
    string $user,
    string $repo,
    int $issueId,
    string $assignee = ''
): void {
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2);
    $printer->setEmphasis(true);
    $printer->text("$title\n");
    $printer->feed(2);

    $printer->setTextSize(1, 1);
    $printer->setEmphasis(false);
    $printer->setJustification();

    if ($issueId > 0) {
        $printer->text("Issue: #$issueId\n");
    }
    if ($repo !== '') {
        $printer->text("Repo: $repo\n");
    }
    if ($user !== '') {
        $printer->text("Created by: @$user\n");
    }
    if ($assignee !== '') {
        $printer->text("Assigned to: @$assignee\n");
    }

    $printer->feed(2);
}

function printTitle(Printer $printer, string $title): void
{
    if ($title !== '') {
        $printer->setEmphasis(true);
        $printer->text("$title\n");
        $printer->setEmphasis(false);
        $printer->feed(2);
    }
}

function printBody(Printer $printer, string $body): void
{
    if ($body !== '') {
        $printer->text("$body\n");
        $printer->feed(2);
    }
}

function printFooter(Printer $printer, string $timestamp): void
{
    if ($timestamp !== '') {
        $printer->text("$timestamp\n");
        $printer->feed(2);
    }
    $printer->cut(Printer::CUT_PARTIAL);
}