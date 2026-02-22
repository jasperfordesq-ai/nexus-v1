<?php
// Test response meta shape for paginated endpoints
$ch = curl_init("http://localhost/api/auth/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["email"=>"jasper@hour-timebank.ie","password"=>"TestPass123!"]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json", "X-Tenant-ID: 2"]
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);
$token = $resp["access_token"] ?? null;
if (!$token) { echo "Login failed\n"; var_dump(array_keys($resp)); exit(1); }

// Test paginated endpoints
$tests = [
    "/api/v2/admin/users?page=1&per_page=3" => "users",
    "/api/v2/admin/vetting" => "vetting",
    "/api/v2/admin/feed/posts" => "feed_posts",
    "/api/v2/admin/comments" => "comments",
];

foreach ($tests as $url => $label) {
    $ch = curl_init("http://localhost" . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token,
            "X-Tenant-ID: 2",
            "Accept: application/json"
        ]
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);

    echo "=== $label ===\n";
    echo "top keys: " . implode(", ", array_keys($json)) . "\n";
    if (isset($json["meta"])) {
        echo "meta keys: " . implode(", ", array_keys($json["meta"])) . "\n";
        echo "meta: " . json_encode($json["meta"]) . "\n";
    }
    if (isset($json["data"]) && is_array($json["data"])) {
        echo "data: array of " . count($json["data"]) . " items\n";
    }
    echo "\n";
}

// Also test a stats endpoint (non-paginated)
$ch = curl_init("http://localhost/api/v2/admin/dashboard/stats");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $token,
        "X-Tenant-ID: 2",
        "Accept: application/json"
    ]
]);
$raw = curl_exec($ch);
curl_close($ch);
$json = json_decode($raw, true);
echo "=== dashboard/stats ===\n";
echo "top keys: " . implode(", ", array_keys($json)) . "\n";
if (isset($json["meta"])) echo "meta: " . json_encode($json["meta"]) . "\n";
if (isset($json["data"])) echo "data keys: " . implode(", ", array_keys($json["data"])) . "\n";
