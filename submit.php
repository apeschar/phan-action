<?php

exit(main($argv));

function main(array $argv): int {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    if (sizeof($argv) !== 2) {
        fprintf(STDERR, "Usage: %s phan_output.json\n", $argv[0]);
        return 1;
    }

    $infile = $argv[1];

    $contents = file_get_contents($infile);
    if ($contents === null) {
        throw new RuntimeException("Could not read input file: {$infile}");
    }

    $issues = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    $checkRun = [
        'name' => 'phan',
        'head_sha' => env('GITHUB_SHA'),
        'conclusion' => $issues > 0 ? 'failure' : 'success',
        'output' => [
            'title' => 'Phan static analysis',
            'summary' => sprintf('There are %d issues.', $issues),
            'annotations' => get_annotations($issues),
        ],
    ];

    $ch = curl_init();
    if (!curl_setopt_array($ch, [
        CURLOPT_URL => sprintf('https://api.github.com/repos/%s/check-runs', env('GITHUB_REPOSITORY')),
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github.v3+json',
            sprintf('Authorization: Bearer %s', env('GITHUB_TOKEN')),
            'Content-Type: application/json',
        ],
        CURLOPT_USERAGENT => 'apeschar/phan-action',
        CURLOPT_POSTFIELDS => json_encode($checkRun, flags: JSON_THROW_ON_ERROR),
        CURLOPT_FAILONERROR => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => true,
    ])) {
        throw new RuntimeException(sprintf(
            "curl_setopt_array: (%d) %s",
            curl_errno($ch),
            curl_error($ch),
        ));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException(sprintf(
            "Could not create check run: cURL: (%d) %s",
            curl_errno($ch),
            curl_error($ch),
        ));
    }

    return 0;
}

function get_annotations(array $issues): array {
    $result = [];
    foreach ($issues as $issue) {
        if ($issue['type'] !== 'issue') {
            fprintf(STDERR, "Warning: unknown issue type: %s\n", $issue['type']);
            continue;
        }
        $result[] = [
            'path' => $issue['location']['path'],
            'start_line' => $issue['location']['lines']['begin'],
            'end_line' => $issue['location']['lines']['end'],
            'annotation_level' => get_annotation_level($issue['severity']),
            'message' => $issue['description'],
            'title' => $issue['check_name'],
            'raw_details' => json_encode(
                $issue,
                flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
        ];
    }
    return $result;
}

function get_annotation_level(int $severity): string {
    if ($severity > 5) {
        return 'failure';
    }
    if ($severity > 0) {
        return 'warning';
    }
    return 'notice';
}

function env(string $name): string {
    $value = getenv($name);
    if (!$value) {
        throw new RuntimeException("Missing environment variable: {$name}");
    }
    return $value;
}
