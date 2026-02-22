<?php
// Temporary test script - run inside container
$ch = curl_init("http://localhost/api/auth/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["email"=>"jasper@hour-timebank.ie","password"=>"TestPass123!"]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json", "X-Tenant-ID: 2"]
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);
$token = $resp["data"]["access_token"] ?? $resp["access_token"] ?? null;
if (!$token) { echo "NO TOKEN\n"; exit(1); }
echo "TOKEN OK\n";

$endpoints = [
    "/api/v2/admin/dashboard/stats",
    "/api/v2/admin/users",
    "/api/v2/admin/vetting",
    "/api/v2/admin/vetting/stats",
    "/api/v2/admin/config",
    "/api/v2/admin/gamification/stats",
    "/api/v2/admin/feed/posts",
    "/api/v2/admin/community-analytics",
    "/api/v2/admin/matching/stats",
    "/api/v2/admin/matching/config",
    "/api/v2/admin/jobs",
    "/api/v2/admin/tools/health-check",
    "/api/v2/admin/timebanking/stats",
    "/api/v2/admin/volunteering",
    "/api/v2/admin/comments",
    "/api/v2/admin/reviews",
    "/api/v2/admin/reports",
    "/api/v2/admin/super/dashboard",
    "/api/v2/admin/enterprise/gdpr/dashboard",
    "/api/v2/admin/tools/redirects",
    "/api/v2/admin/newsletters",
    "/api/v2/admin/categories",
];

foreach ($endpoints as $url) {
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true);
    $keys = $json ? implode(",", array_keys($json)) : "PARSE_ERR";
    $datType = "none";
    if (isset($json["data"])) {
        if (is_array($json["data"])) {
            $datType = isset($json["data"][0]) ? "arr[".count($json["data"])."]" : "obj";
        } else {
            $datType = gettype($json["data"]);
        }
    }
    $meta = isset($json["meta"]) ? "Y" : "N";
    $pag = isset($json["pagination"]) ? "Y" : "N";

    printf("%3d %-45s data=%-10s meta=%s pag=%s keys=[%s]\n", $code, $url, $datType, $meta, $pag, substr($keys,0,60));
    if ($code >= 400) {
        $msg = $json["message"] ?? $json["error"] ?? substr($raw, 0, 80);
        echo "    ERR: $msg\n";
    }
}
