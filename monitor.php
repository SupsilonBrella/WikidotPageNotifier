<?php
/**
 * PHP 5.5 - Monitor Wikidot Latest Pages and Send Discord Webhook
 * Supports sending multiple new pages at once
 */

$config = json_decode(file_get_contents('config.json'), true);
$latestFile = 'latestPage.json';

// Load last latest page
$latestPage = file_exists($latestFile) ? json_decode(file_get_contents($latestFile), true) : null;

/**
 * Login to Wikidot
 */
function loginToWikidot($config) {
    $token7 = substr(md5(mt_rand()), 0, 8);
    list($username, $password) = explode(':', $config['wikidotLogin']);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.wikidot.com/default--flow/login__LoginPopupScreen");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'login' => $username,
        'password' => $password,
        'action' => 'Login2Action',
        'event' => 'login'
    ]));
    curl_setopt($ch, CURLOPT_COOKIE, "wikidot_token7=$token7");
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    curl_close($ch);

    if (strpos($response, 'The login and password do not match.') !== false) {
        die("Login failed: invalid username or password\n");
    }

    preg_match_all('/Set-Cookie: ([^;]+);/i', $headers, $matches);
    $cookies = implode('; ', $matches[1]) . "; wikidot_token7=$token7";

    return $cookies;
}

/**
 * Fetch latest RSS pages
 */
function fetchLatestPages($config, $cookie) {
    $rssUrl = "http://{$config['site']}.wikidot.com/feed/pages/pagename/most-recently-created/category/_default%2Cadult/tags/-admin/rating/%3E%3D-10/order/created_at+desc/limit/30/t/Most+Recently+Created";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rssUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    $rss = curl_exec($ch);
    curl_close($ch);

    return simplexml_load_string($rss);
}

/**
 * Parse author info
 */
function parseAuthor($description) {
    $dom = new DOMDocument();
    @$dom->loadHTML($description);
    $links = $dom->getElementsByTagName('a');
    if ($links->length > 1) {
        $name = $links->item(1)->nodeValue;
        $href = $links->item(1)->getAttribute('href');
        return [$name, $href];
    }
    return ['', ''];
}

/**
 * Send Discord webhook
 */
function sendWebhook($webhookURL, $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

/* ---- Main process ---- */

$cookie = loginToWikidot($config);
$rss = fetchLatestPages($config, $cookie);

if (!$rss || !isset($rss->channel->item[0])) {
    die("No RSS items found\n");
}

$newestLink = null;
$items = $rss->channel->item;
$newItems = [];

// Collect pages that are newer than last saved
foreach ($items as $item) {
    $link = (string)$item->link;
    if ($link === $latestPage) {
        break; // stop when old page is found
    }
    $newItems[] = $item;
}

// Send in chronological order (oldest to newest)
$newItems = array_reverse($newItems);

foreach ($newItems as $item) {
    $link = (string)$item->link;
    $title = (string)$item->title;
    $pubTime = strtotime($item->pubDate);
    list($authorName, $authorHref) = parseAuthor((string)$item->description);

    $payload = json_encode([
        'content' => null,
        'embeds' => [[
            'title' => $title,
            'description' => "Published at: " . date('Y-m-d H:i:s', $pubTime) . "\nAuthor: [$authorName]($authorHref)",
            'url' => $link,
            'color' => 5814783,
            'author' => ['name' => 'New Page on Wiki']
        ]],
        'attachments' => []
    ]);

    $status = sendWebhook($config['webhookURL'], $payload);
    echo "Sent: $title (HTTP $status)\n";

    $newestLink = $link;
}

// Update latest page record
if ($newestLink) {
    file_put_contents($latestFile, json_encode($newestLink));
}
?>
