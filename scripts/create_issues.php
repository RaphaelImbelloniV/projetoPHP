<?php
$sonarToken = getenv('SONAR_TOKEN');
$githubToken = getenv('GH_PAT');
$repo = getenv('GITHUB_REPOSITORY');

$sonarUrl = 'http://localhost:9000/api/issues/search?projectKeys=projetoSonar-mantis&types=BUG&statuses=OPEN';
$context = stream_context_create([
    'http' => [
        'header' => "Authorization: Basic " . base64_encode("$sonarToken:")
    ]
]);

$response = file_get_contents($sonarUrl, false, $context);
$data = json_decode($response, true);
$issues = $data['issues'] ?? [];

foreach ($issues as $bug) {
    $title = "SonarQube Bug: " . $bug['message'];

    // Verifica se a issue já existe
    $checkUrl = "https://api.github.com/repos/$repo/issues";
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => [
                "User-Agent: PHP",
                "Authorization: token $githubToken"
            ]
        ]
    ];
    $ctx = stream_context_create($opts);
    $existing = json_decode(file_get_contents($checkUrl, false, $ctx), true);
    $exists = false;
    foreach ($existing as $e) {
        if ($e['title'] === $title) {
            $exists = true;
            break;
        }
    }
    if ($exists) continue;

    // Cria nova issue
    $issue = [
        'title' => $title,
        'body' => "Arquivo: {$bug['component']}\nLinha: " . ($bug['line'] ?? 'N/A') . "\nDetalhes: {$bug['message']}\n\n[Ver no SonarQube](http://localhost:9000/code?id={$bug['component']}&open={$bug['key']})",
        'labels' => ['bug', 'sonarqube']
    ];
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'User-Agent: PHP',
                'Content-Type: application/json',
                "Authorization: token $githubToken"
            ],
            'content' => json_encode($issue)
        ]
    ];
    $context = stream_context_create($opts);
    file_get_contents($checkUrl, false, $context);

    echo "Issue criada: {$title}\n";
}
