<?php
/*
Changes the User-Agent to "Edge" in order to allow shared Outlook ICS calendars to be retrieved with different apps. 
Any error messages are simply displayed as events.
Usage:
ics-proxy.php?url=<calendar-url>
*/

// 1. Check parameter
if (!isset($_GET['url'])) {
    sendIcsError("Missing parameter 'url'");
    exit;
}

$url = $_GET['url'];

// 2. Allow only Microsoft calendar links
$allowedDomains = [
    'outlook.live.com',
    'outlook.office365.com',
    'outlook.office.com',
    'office.com',
    'microsoft.com'
];

$parts = parse_url($url);
if (!$parts || !in_array($parts['host'], $allowedDomains, true)) {
    sendIcsError("Domain not allowed: " . htmlspecialchars($parts['host'] ?? 'unknown'));
    exit;
}

// 3. Fixed Edge User-Agent
$userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    . "AppleWebKit/537.36 (KHTML, like Gecko) "
    . "Chrome/118.0.0.0 Safari/537.36 "
    . "Edg/118.0.2088.61";

// 4. Fetch remote calendar
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    sendIcsError("Request failed: " . curl_error($ch));
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// 5. Handle invalid responses
if ($httpCode >= 400 || stripos($contentType, 'text/calendar') === false) {
    sendIcsError("Invalid response ($httpCode, $contentType)");
    exit;
}

// 6. Success: return ICS as-is
header("Content-Type: text/calendar; charset=utf-8");
echo $response;
exit;

// ======================================
// Helper: Build ICS file with error event
// ======================================
function sendIcsError(string $message): void {
    header("Content-Type: text/calendar; charset=utf-8");
    $now = time();
    $start = gmdate("Ymd\THis\Z", $now);
    $end   = gmdate("Ymd\THis\Z", $now + 3600); // +1 hour
    $uid = uniqid() . "@proxy-error";

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//ICS Proxy//EN\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:$uid\r\n";
    echo "DTSTAMP:$start\r\n";
    echo "DTSTART:$start\r\n";
    echo "DTEND:$end\r\n";
    echo "SUMMARY:Proxy Error\r\n";
    echo "DESCRIPTION:" . str_replace("\n", "\\n", $message) . "\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";
}
