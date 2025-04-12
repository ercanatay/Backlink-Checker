<?php
// Start session to store results for download
session_start();

// Set maximum execution time (adjust as needed, e.g., 600 for 10 minutes)
// Moz API calls can take time.
ini_set('max_execution_time', 600);
ini_set('memory_limit', '256M'); // Increase memory limit if needed for large datasets
// Report simple running errors, but hide notices/warnings for cleaner output
error_reporting(E_ERROR | E_PARSE);
// Suppress DOMDocument warnings for malformed HTML
libxml_use_internal_errors(true);

// --- Moz API Configuration ---
// WARNING: Storing credentials directly in code is insecure for production.
// Consider using environment variables or a config file.
// Ensure these credentials are valid and active in your Moz account.
$mozAccessId = 'YOUR_CODE'; // Your new Access ID
$mozSecretKey = 'YOUR_CODE'; // Your new Secret Key
$mozApiEndpoint = 'https://lsapi.seomoz.com/v2/url_metrics';
// Base64 encode for the Authorization header (automatically calculated from above)
$mozCredentialsBase64 = base64_encode($mozAccessId . ':' . $mozSecretKey);

/**
 * Normalizes a URL for comparison purposes.
 * Removes scheme (http/https), www., trailing slash, and converts to lowercase.
 *
 * @param string $url The URL to normalize.
 * @return string The normalized URL.
 */
function normalizeUrl(string $url): string
{
    $url = trim(strtolower($url));
    // Remove scheme
    $url = preg_replace('#^https?://#', '', $url);
    // Remove www.
    $url = preg_replace('#^www\.#', '', $url);
    // Remove trailing slash
    $url = rtrim($url, '/');
    return $url;
}

/**
 * Extracts the domain name from a URL.
 *
 * @param string $url The URL to parse.
 * @return string|null The domain name or null on failure.
 */
function extractDomain(string $url): ?string
{
    // Add scheme if missing for parse_url to work reliably
    if (!preg_match('#^https?://#', $url)) {
        $url = 'http://' . $url;
    }
    $parsedUrl = parse_url($url);
    // Remove www. from host if present
    $host = $parsedUrl['host'] ?? null;
    if ($host !== null) {
        $host = preg_replace('#^www\.#', '', $host);
    }
    return $host;
}


/**
 * Generates and triggers a file download for the results.
 *
 * @param string $format The desired format ('csv', 'txt', 'xls').
 * @param array $data The results data array (should be pre-sorted).
 * @param string $rootDomain The root domain used for checks (for filename).
 */
function generateDownload(string $format, array $data, string $rootDomain): void
{
    $filename = "seo_results_" . preg_replace('/[^a-z0-9_-]/i', '_', $rootDomain) . "_" . date('Ymd_His');
    // English headers for download files - New Order Requested
    $headers = [
        'Domain', 'Backlink URL', 'Anchor Text', 'Domain Authority (DA)', 'Page Authority (PA)', 'Link Type', 'Noindex', 'Status', 'Error Message'
    ];

    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            // Add BOM for UTF-8 Excel compatibility
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['domain'] ?? '',
                    $row['url'] ?? '',
                    $row['anchor_text'] ?? 'N/A',
                    $row['da'] ?? 'N/A', // DA
                    $row['pa'] ?? 'N/A', // PA
                    $row['link_type'] ?? 'N/A',
                    ($row['noindex'] ?? false) ? 'Yes' : 'No',
                    $row['status'] ?? 'N/A',
                    $row['error_message'] ?? '',
                ]);
            }
            fclose($output);
            break;

        case 'txt':
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
            // Add BOM for UTF-8 Notepad compatibility
             echo "\xEF\xBB\xBF";
            echo implode("\t", $headers) . "\r\n"; // Tab separated
            foreach ($data as $row) {
                echo implode("\t", [
                    $row['domain'] ?? '',
                    $row['url'] ?? '',
                    $row['anchor_text'] ?? 'N/A',
                    $row['da'] ?? 'N/A', // DA
                    $row['pa'] ?? 'N/A', // PA
                    $row['link_type'] ?? 'N/A',
                    ($row['noindex'] ?? false) ? 'Yes' : 'No',
                    $row['status'] ?? 'N/A',
                    $row['error_message'] ?? '',
                ]) . "\r\n";
            }
            break;

        case 'xls':
            // Simple HTML table export, often opens in Excel but isn't a true XLSX.
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
             // Add BOM for UTF-8 Excel compatibility
            echo "\xEF\xBB\xBF";
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr><th>' . implode('</th><th>', $headers) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['domain'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['url'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['anchor_text'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['da'] ?? 'N/A') . '</td>'; // DA
                echo '<td>' . htmlspecialchars($row['pa'] ?? 'N/A') . '</td>'; // PA
                echo '<td>' . htmlspecialchars($row['link_type'] ?? 'N/A') . '</td>';
                echo '<td>' . (($row['noindex'] ?? false) ? 'Yes' : 'No') . '</td>';
                echo '<td>' . htmlspecialchars($row['status'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['error_message'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</body></html>';
            break;
    }
    exit; // Stop script execution after download starts
}

// --- Check if a download is requested ---
if (isset($_GET['download']) && isset($_SESSION['seo_results'])) {
    $format = $_GET['download'];
    $data = $_SESSION['seo_results']; // Assumes data in session is already sorted
    $rootDomainForFilename = $_SESSION['root_domain'] ?? 'domain'; // Get root domain for filename
    if (in_array($format, ['csv', 'txt', 'xls'])) {
        generateDownload($format, $data, $rootDomainForFilename);
    }
}


/**
 * Fetches URL content and checks for noindex tag and backlinks.
 * Extracts domain name.
 *
 * @param string $url The URL to check.
 * @param string $rootDomain The root domain to look for backlinks.
 * @return array An array containing the check results (excluding Moz).
 */
function checkUrlBasic(string $url, string $rootDomain): array
{
    $result = [
        'url' => $url, // Store the original URL
        'domain' => extractDomain($url), // Extract domain
        'status' => 'OK', // Initial status
        'noindex' => false,
        'backlink_found' => false, // Kept internally, but not displayed directly
        'link_type' => null, // 'dofollow' or 'nofollow'
        'anchor_text' => null,
        'error_message' => null,
        'pa' => 'Pending', // Placeholder
        'da' => 'Pending', // Placeholder
    ];

    // --- 1. Fetch HTML content using cURL ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SEOCheckerBot/1.0; +http://example.com/bot)');
    // Disable SSL verification - Use with caution in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || $html === false) {
        $result['status'] = 'Fetch Error';
        $result['error_message'] = $curlError ?: "HTTP Status Code: {$httpCode}";
        $result['pa'] = 'Error';
        $result['da'] = 'Error';
        return $result;
    }

    if (empty(trim($html))) {
        $result['status'] = 'Error';
        $result['error_message'] = 'Fetched content is empty.';
         $result['pa'] = 'Error';
        $result['da'] = 'Error';
        return $result;
    }

    // --- 2. Parse HTML using DOMDocument ---
    $dom = new DOMDocument();
    // Suppress warnings during loading of potentially malformed HTML
    if (!@$dom->loadHTML($html)) {
        $result['status'] = 'Parse Error';
        $result['error_message'] = 'Failed to parse HTML content.';
        libxml_clear_errors(); // Clear any errors stored by libxml
        $result['pa'] = 'Error';
        $result['da'] = 'Error';
        return $result;
    }
    libxml_clear_errors(); // Clear any errors stored by libxml even if loadHTML succeeded

    $xpath = new DOMXPath($dom);

    // --- 3. Check for noindex meta tag ---
    // Case-insensitive check for name='robots' and content containing 'noindex'
    $noindexQuery = "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='robots' and contains(translate(@content, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'noindex')]";
    $noindexNodes = $xpath->query($noindexQuery);
    if ($noindexNodes && $noindexNodes->length > 0) {
        $result['noindex'] = true;
    }

    // --- 4. Check for backlinks ---
    // Find anchor tags where href contains the root domain
    $backlinkQuery = "//a[contains(@href, '" . $rootDomain . "')]";
    $backlinkNodes = $xpath->query($backlinkQuery);

    if ($backlinkNodes && $backlinkNodes->length > 0) {
        $result['backlink_found'] = true; // Set flag
        // Get details from the first found link
        $firstLink = $backlinkNodes->item(0);
        $relAttribute = strtolower($firstLink->getAttribute('rel'));
        // Check if 'nofollow' exists in the rel attribute
        $result['link_type'] = str_contains($relAttribute, 'nofollow') ? 'nofollow' : 'dofollow';
        // Get the visible text content of the link
        $result['anchor_text'] = trim($firstLink->textContent);
    } else {
         $result['link_type'] = 'N/A'; // Explicitly set if no backlink found
         $result['anchor_text'] = 'N/A';
    }

    return $result;
}

/**
 * Fetches Moz DA and PA for a SINGLE URL.
 *
 * @param string $url The URL to fetch metrics for.
 * @param string $apiEndpoint Moz API endpoint.
 * @param string $credentialsBase64 Base64 encoded Moz credentials.
 * @return array Associative array containing ['pa' => value, 'da' => value, 'error' => message].
 */
function getMozMetricsSingle(string $url, string $apiEndpoint, string $credentialsBase64): array
{
    $result = ['pa' => 'N/A', 'da' => 'N/A', 'error' => null];

    // Prepare the payload for the Moz API (only one target)
    $payload = json_encode(['targets' => [$url]]);

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentialsBase64,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Timeout for the request
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // Timeout for connection
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    // Disable SSL verification - Use with caution in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // --- Error Handling ---
    $errorMsg = null;
    if ($curlError) {
        $errorMsg = "Moz API cURL Error: " . $curlError;
    } elseif ($response === false && $httpCode == 0) {
        $errorMsg = "Moz API Error: Could not connect. Check network/DNS.";
    } elseif ($httpCode == 401) {
        $errorMsg = "Moz API Error: 401 Unauthorized. Authentication failed! Check credentials.";
    } elseif ($httpCode == 400) {
        $errorMsg = "Moz API Error: 400 Bad Request. Invalid URL or payload.";
    } elseif ($httpCode == 429) {
        $errorMsg = "Moz API Error: 429 Too Many Requests. Rate limit exceeded.";
    } elseif ($httpCode >= 500) {
        $errorMsg = "Moz API Error: Server Error (Code {$httpCode}).";
    } elseif ($httpCode >= 400) {
        $errorMsg = "Moz API Error: Client Error (Code {$httpCode}).";
    } elseif ($response === false) {
        $errorMsg = "Moz API Error: Failed to get response body (HTTP {$httpCode}).";
    }

    // If an error occurred, return error state
    if ($errorMsg !== null) {
        $result['error'] = $errorMsg;
        $result['pa'] = 'Error';
        $result['da'] = 'Error';
        return $result;
    }

    // --- Process Successful Response ---
    $responseData = json_decode($response, true);

    // Check JSON decoding and structure
    if ($responseData === null || !isset($responseData['results']) || !is_array($responseData['results']) || empty($responseData['results'])) {
        $jsonError = json_last_error_msg();
        $result['error'] = "Moz API Error: Invalid JSON response (Error: {$jsonError}).";
        $result['pa'] = 'Error';
        $result['da'] = 'Error';
        return $result;
    }

    // Get data for the first (and only) result
    $metric = $responseData['results'][0];

    // Assign PA and DA
    if (isset($metric['page_authority'])) {
        $result['pa'] = round($metric['page_authority'], 2);
    } else {
        $result['pa'] = 'N/A';
    }

    if (isset($metric['domain_authority'])) {
        $result['da'] = round($metric['domain_authority'], 2);
    } else {
        $result['da'] = 'N/A';
    }

    return $result;
}


// --- Main Script Logic ---
$urlsToCheckInput = [];
$rootDomain = '';
$results = []; // Will be keyed by ORIGINAL URL initially, then converted to indexed array
$totalUrlsInput = 0;
$processedCount = 0;
$validUrlsForProcessing = [];
$formError = null;
$startTime = microtime(true); // For timing script execution

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get root domain and URLs from POST data, trim whitespace
    $rootDomain = isset($_POST['root_domain']) ? trim($_POST['root_domain']) : '';
    $urlsInputRaw = isset($_POST['urls']) ? trim($_POST['urls']) : '';

    // Proceed only if both root domain and URLs are provided
    if (!empty($rootDomain) && !empty($urlsInputRaw)) {
        // Basic sanitization for root domain (remove scheme for contains check)
        $rootDomain = filter_var($rootDomain, FILTER_SANITIZE_URL);
        $rootDomain = preg_replace('#^https?://#', '', $rootDomain);
        $rootDomain = preg_replace('#^www\.#', '', $rootDomain); // Also remove www.
        $rootDomain = rtrim($rootDomain, '/'); // Remove trailing slash
        $_SESSION['root_domain'] = $rootDomain; // Store for download filename

        // Split input URLs by newline, trim each line
        $urlsToCheckInput = preg_split('/\r\n|\r|\n/', $urlsInputRaw);
        $urlsToCheckInput = array_map('trim', $urlsToCheckInput);
        $totalUrlsInput = count($urlsToCheckInput); // Count before filtering

        // Filter for valid URLs and remove empty lines/duplicates
        $validUrlsForProcessing = array_filter($urlsToCheckInput, function($url) {
            // Basic URL validation
            return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
        });
        $validUrlsForProcessing = array_unique($validUrlsForProcessing); // Ensure unique URLs

        $processedCount = count($validUrlsForProcessing); // Count of URLs to actually process

        // --- Process Each Valid URL ---
        $tempResults = []; // Use a temporary array
        foreach ($validUrlsForProcessing as $url) {
            // 1. Perform basic checks (HTML fetch, parse, noindex, backlink, domain)
            $currentResult = checkUrlBasic($url, $rootDomain);

            // 2. If basic check is OK, fetch Moz metrics for this single URL
            if ($currentResult['status'] === 'OK') {
                $mozData = getMozMetricsSingle($url, $mozApiEndpoint, $mozCredentialsBase64);

                // Assign PA and DA from the fetched Moz data
                $currentResult['pa'] = $mozData['pa'];
                $currentResult['da'] = $mozData['da'];

                // If Moz data retrieval failed, update status and append error message
                if (!empty($mozData['error'])) {
                    $currentResult['status'] = 'Moz Error'; // Indicate the error source
                    // Append the Moz error message to any existing error message
                    $currentResult['error_message'] = ($currentResult['error_message'] ? $currentResult['error_message'] . '; ' : '') . $mozData['error'];
                }
            } else {
                 // Ensure PA/DA are set to 'Error' if basic check failed
                 $currentResult['pa'] = 'Error';
                 $currentResult['da'] = 'Error';
            }

            // Add the result for the current URL to the temporary results array
            $tempResults[] = $currentResult; // Add as indexed element
        }

        // --- Sort Results by DA Descending ---
        usort($tempResults, function ($a, $b) {
            $da_a = $a['da'] ?? null;
            $da_b = $b['da'] ?? null;

            // Handle non-numeric values (put them at the bottom)
            $is_a_numeric = is_numeric($da_a);
            $is_b_numeric = is_numeric($da_b);

            if ($is_a_numeric && $is_b_numeric) {
                // Both numeric, sort descending
                return $da_b <=> $da_a; // $b compared to $a for descending
            } elseif ($is_a_numeric) {
                // Only A is numeric, A comes first
                return -1;
            } elseif ($is_b_numeric) {
                // Only B is numeric, B comes first
                return 1;
            } else {
                // Both non-numeric, maintain original relative order (or treat as equal)
                return 0;
            }
        });

        // Assign sorted results to the final array and store in session
        $results = $tempResults; // $results is now the sorted indexed array
        $_SESSION['seo_results'] = $results; // Store sorted array

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($rootDomain)) {
        $formError = "Please enter the Root Domain to check.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($urlsInputRaw)) {
        $formError = "Please enter at least one URL to check.";
    }
}
$endTime = microtime(true); // Record end time
$executionTime = round($endTime - $startTime, 2); // Calculate execution duration

// Get results for display (from session if available, should be sorted)
$displayResults = isset($_SESSION['seo_results']) ? $_SESSION['seo_results'] : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP SEO Checker (Moz + Export) - Final Layout</title> <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/css/uikit.min.css" />
    <style>
        /* Basic styling for status indicators */
        .status-ok { color: green; }
        .status-error, .status-fetch-error, .status-parse-error, .status-moz-error { color: red; font-weight: bold; }
        .noindex-true { color: orange; }
        /* Updated Link Type Styles */
        .dofollow-link { color: green; font-weight: bold; }
        .nofollow-link { color: orange; font-weight: bold; }
        /* Table styling */
        .uk-table th { text-align: center; background-color: #f2f2f2; padding: 10px; border: 1px solid #ddd;}
        .uk-table td { text-align: center; vertical-align: middle; padding: 8px; border: 1px solid #ddd;}
        .uk-table td.url-col, .uk-table td.domain-col { text-align: left; word-break: break-all; } /* Allow long URLs/Domains to wrap */
        .uk-table td.anchor-col { text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } /* Truncate long anchor text */
        /* Summary box styling */
        .summary-box { background-color: #f8f8f8; border: 1px solid #e7e7e7; border-radius: 5px; }
        /* Download button spacing */
        .download-buttons .uk-button { margin: 5px; }
        /* PA/DA value styling */
        td.pa-da-val { font-weight: 500; }
        td.pa-da-val.na { color: #999; font-style: italic; } /* Style for N/A or Error values */
        /* DA Value Specific Style */
        td.da-value { color: darkgreen; font-weight: bold; }
        td.da-value.na { color: #999; font-style: italic; font-weight: normal; } /* Override bold for non-numeric DA */

    </style>
    
</head>
<body>

    <div class="uk-container uk-container-xlarge uk-margin-top uk-margin-bottom">

        <h1 class="uk-heading-medium uk-text-center">SEO Noindex, Backlink & Moz Checker</h1>

        <div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
            <h3 class="uk-card-title">Enter Details</h3>
            <?php if (!empty($formError)): ?>
                <div class="uk-alert-danger" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <p><?php echo htmlspecialchars($formError); ?></p>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="uk-margin">
                    <label class="uk-form-label" for="root_domain">Root Domain (e.g., example.com - no http/www)</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="root_domain" name="root_domain" type="text" placeholder="example.com" value="<?php echo isset($_POST['root_domain']) ? htmlspecialchars($_POST['root_domain']) : ''; ?>" required>
                    </div>
                </div>
                <div class="uk-margin">
                     <label class="uk-form-label" for="urls">URLs to Check (one per line)</label>
                    <div class="uk-form-controls">
                        <textarea class="uk-textarea" id="urls" name="urls" rows="10" placeholder="https://www.website1.com/page-a&#10;https://www.another-site.org/article-b" required><?php echo isset($_POST['urls']) ? htmlspecialchars($_POST['urls']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="uk-margin uk-text-center">
                     <button class="uk-button uk-button-primary uk-button-large" type="submit">Start Checking</button>
                </div>
            </form>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($formError)): ?>
            <div class="uk-margin-large-top">
                 <h2 class="uk-heading-small uk-text-center">Results</h2>

                <div class="uk-panel uk-padding summary-box uk-margin-medium-bottom">
                     <h4 class="uk-text-center uk-margin-remove">Processing Summary</h4>
                    <hr class="uk-divider-small uk-margin-auto">
                    <p class="uk-text-center uk-margin-small-top">
                        Checked <strong><?php echo $processedCount; ?></strong> valid and unique URLs out of <strong><?php echo $totalUrlsInput; ?></strong> lines entered.<br>
                        Root Domain Searched For Backlinks: <strong><?php echo htmlspecialchars($rootDomain); ?></strong><br>
                        Total Execution Time: <strong><?php echo $executionTime; ?> seconds</strong>
                    </p>
                    <?php if ($totalUrlsInput > $processedCount): ?>
                         <p class="uk-text-center uk-text-warning">Note: <?php echo ($totalUrlsInput - $processedCount); ?> invalid, empty, or duplicate lines were skipped.</p>
                    <?php endif; ?>
                     <p class="uk-text-center uk-text-meta">Status 'OK' means the page was fetched. 'Fetch/Parse Error' indicates issues accessing the page. 'Moz Error' means Moz data retrieval failed (check error icon).</p>
                </div>

                <?php if (!empty($displayResults)): ?>
                    <div class="uk-margin uk-text-center download-buttons">
                         <span class="uk-text-bold">Download Results:</span>
                         <a href="?download=csv" class="uk-button uk-button-secondary uk-button-small" uk-tooltip="title: Comma Separated Values; pos: bottom">CSV</a>
                        <a href="?download=xls" class="uk-button uk-button-secondary uk-button-small" uk-tooltip="title: Excel Compatible Table; pos: bottom">Excel (XLS)</a>
                        <a href="?download=txt" class="uk-button uk-button-secondary uk-button-small" uk-tooltip="title: Tab Separated Text; pos: bottom">TXT</a>
                    </div>

                    <div class="uk-overflow-auto">
                        <table class="uk-table uk-table-striped uk-table-hover uk-table-middle uk-table-responsive uk-table-divider">
                            <thead>
                                <tr>
                                     <th class="uk-width-medium">Domain</th>
                                     <th class="uk-width-large">Backlink URL</th>
                                     <th>Anchor Text</th>
                                     <th uk-tooltip="title: Moz Domain Authority (Sorted Desc); pos: top">DA</th>
                                     <th uk-tooltip="title: Moz Page Authority; pos: top">PA</th>
                                     <th>Link Type</th>
                                     <th>Noindex</th>
                                     <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($displayResults as $res): ?>
                                    <?php
                                        // Determine CSS class based on status
                                        $statusClass = 'status-ok';
                                        if ($res['status'] !== 'OK') {
                                            $statusClass = 'status-error';
                                            if (str_contains($res['status'], 'Fetch')) $statusClass = 'status-fetch-error';
                                            if (str_contains($res['status'], 'Parse')) $statusClass = 'status-parse-error';
                                            if (str_contains($res['status'], 'Moz')) $statusClass = 'status-moz-error';
                                        }

                                        // Determine Link Type class
                                        $linkTypeClass = '';
                                        if ($res['link_type'] === 'dofollow') {
                                            $linkTypeClass = 'dofollow-link';
                                        } elseif ($res['link_type'] === 'nofollow') {
                                            $linkTypeClass = 'nofollow-link';
                                        }

                                        // Determine PA/DA classes
                                        $paValue = $res['pa'] ?? 'N/A';
                                        $daValue = $res['da'] ?? 'N/A';
                                        $isPaNumeric = is_numeric($paValue);
                                        $isDaNumeric = is_numeric($daValue);
                                        $paClass = !$isPaNumeric ? 'na' : '';
                                        $daClass = !$isDaNumeric ? 'na' : 'da-value'; // Apply da-value only if numeric
                                    ?>
                                    <tr>
                                        <td class="domain-col"><?php echo htmlspecialchars($res['domain'] ?? 'N/A'); ?></td>
                                        <td class="url-col"><a href="<?php echo htmlspecialchars($res['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($res['url']); ?></a></td>
                                        <td class="anchor-col" uk-tooltip="title: <?php echo htmlspecialchars($res['anchor_text'] ?? 'N/A'); ?>; pos: top-left"><?php echo ($res['anchor_text'] !== null && $res['anchor_text'] !== 'N/A' && $res['anchor_text'] !== '') ? htmlspecialchars($res['anchor_text']) : 'N/A'; ?></td>
                                        <td class="pa-da-val <?php echo $daClass; ?>"><?php echo htmlspecialchars($daValue); ?></td>
                                        <td class="pa-da-val <?php echo $paClass; ?>"><?php echo htmlspecialchars($paValue); ?></td>
                                        <td class="<?php echo $linkTypeClass; ?>"><?php echo htmlspecialchars($res['link_type'] ?? 'N/A'); ?></td>
                                        <td class="<?php echo ($res['noindex']) ? 'noindex-true' : ''; ?>">
                                            <?php echo ($res['noindex']) ? 'Yes' : 'No'; ?>
                                        </td>
                                        <td class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($res['status']); ?>
                                            <?php if (!empty($res['error_message'])): ?>
                                                 <span class="uk-margin-small-left" uk-icon="warning" uk-tooltip="title: <?php echo htmlspecialchars(str_replace(';', '; ', $res['error_message'])); ?>; pos: top-right"></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                     <p class="uk-text-center uk-text-danger">No valid URLs were provided or no results to display.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/js/uikit-icons.min.js"></script>
</body>
</html>
