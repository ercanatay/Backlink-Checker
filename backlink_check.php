<?php
header('Content-Type: application/json');

function getKeywordsWithDOM($domain, $content) {
    $keywords = [];
    $doc = new DOMDocument();
    @$doc->loadHTML($content);
    $links = $doc->getElementsByTagName('a');
    foreach ($links as $link) {
        if (strpos($link->getAttribute('href'), $domain) !== false) {
            $keywords[] = $link->nodeValue;
        }
    }
    return $keywords;
}

function getKeywordsWithRegex($domain, $content) {
    $keywords = [];
    preg_match_all('/<a[^>]*href=[\'"]?' . preg_quote($domain, '/') . '[^\'"]*[\'"]?[^>]*>(.*?)<\/a>/', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $keywords[] = strip_tags($match[1]);
    }
    return $keywords;
}

$data = json_decode(file_get_contents('php://input'), true);
$domain = trim($data['domain']);
$backlinks = $data['backlinks'];
$results = [];

foreach ($backlinks as $backlink) {
    $ch = curl_init($backlink);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $content = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $keywords = [];
    $rel = '';

    if ($httpStatus == 200) {
        $keywords = getKeywordsWithDOM($domain, $content);
        if (empty($keywords)) {
            $keywords = getKeywordsWithRegex($domain, $content);
        }

        preg_match('/<a[^>]*href=[\'"]?' . preg_quote($domain, '/') . '[^\'"]*[\'"]?[^>]*rel=[\'"]?([^\'"\s]+)[\'"]?[^>]*>/', $content, $relMatch);
        if (count($relMatch) > 1) {
            $rel = (strpos($relMatch[1], 'nofollow') !== false) ? 'Nofollow' : 'DoFollow';
        } else {
            $rel = 'DoFollow';
        }
    }

    $results[] = [
        'backlink' => $backlink,
        'keywords' => $keywords,
        'rel' => $rel,
        'httpStatus' => $httpStatus
    ];
}

echo json_encode($results);


?>
