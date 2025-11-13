<?php

$parts = explode('/', $_SERVER['REQUEST_URI']);
array_shift($parts);
$host = basename(array_shift($parts));

if (!str_ends_with($host, 'c3re.de')) {
    exit();
}
$file = implode('/', $parts);
$content = file_get_contents('php://input');

$hostDir = __DIR__ . "/data/$host";
if (!is_dir($hostDir)) {
    mkdir($hostDir, 0777, true);
}
$fileStoragePath = $hostDir . '/' . md5($file);

file_put_contents($fileStoragePath . '.data', $content);
file_put_contents($fileStoragePath . '.path', $file);

// update page:
chdir(__DIR__ . '/data/');

$hosts = glob('*', GLOB_ONLYDIR);
// todo: filter old entries
natsort($hosts);

$content = '';

foreach ($hosts as $host) {
    if (filemtime($host) < time() - 7 * 24 * 60 * 60) {
        // skip hosts not modified in the last 7 days
        continue;
    }
    $content .= "# Host: $host\n\n";
    $services = glob("$host/*.path");

    usort($services, function ($a, $b) {
        return strnatcasecmp(file_get_contents($a), file_get_contents($b));
    });

    foreach ($services as $servicePathFile) {
        $service = basename(dirname(trim(file_get_contents($servicePathFile))));
        $content .= " - [$service](./Dienste-Intern/$service)\n";
    }
    $content .= "\n\n";
}

$lastContent = '';
if (!is_file('last_dienste-intern_index.md')) {
    touch('last_dienste-intern_index.md');
}

$fp = fopen('last_dienste-intern_index.md', 'a+');
fseek($fp, 0);

while (!flock($fp, LOCK_EX)) {
    usleep(10000);
}
$lastContent = fread($fp, max(1, filesize('last_dienste-intern_index.md')));

if ($content === $lastContent) {
    exit();
}

$endpoint = 'https://wiki.c3re.de/graphql';
$apiToken = file_get_contents(__DIR__ . '/.token');
$pageId = 225;

$result = updateWikiPage(
    $endpoint,
    $apiToken,
    $pageId,
    $content,
    'Dienste-Intern',
    '',
);

ftruncate($fp, 0);
fseek($fp, 0);
fwrite($fp, $content);
flock($fp, LOCK_UN);
fclose($fp);

/**
 * Update a page in Wiki.js using GraphQL API
 *
 * @param string $endpoint The GraphQL endpoint URL
 * @param string $apiToken The API token for authentication
 * @param int $pageId The ID of the page to update
 * @param string $content The new content in markdown format
 * @param string $title Optional: The page title
 * @param string $description Optional: The page description
 * @return array Response containing success status and message
 */
function updateWikiPage(
    $endpoint,
    $apiToken,
    $pageId,
    $content,
    $title = null,
    $description = null,
    $tags = null,
) {
    // Fetch current page data to get tags and other fields

    // Ensure we have at least empty strings/arrays
    if ($title === null) {
        $title = '';
    }
    if ($description === null) {
        $description = '';
    }
    if ($tags === null) {
        $tags = [];
    }

    // GraphQL mutation for updating a page
    $mutation = 'mutation UpdatePage($id: Int!, $content: String!, $title: String!, $description: String!, $tags: [String]!) {
        pages {
            update(
                id: $id
                content: $content
                title: $title
                description: $description
                editor: "markdown"
                isPublished: true
                isPrivate: false
                tags: $tags
                scriptCss: ""
                scriptJs: ""
            ) {
                responseResult {
                    succeeded
                    errorCode
                    slug
                    message
                }
                page {
                    id
                    path
                    title
                    updatedAt
                }
            }
        }
    }';

    // Build variables
    $variables = [
        'id' => (int) $pageId,
        'content' => $content,
        'title' => $title,
        'description' => $description,
        'tags' => $tags,
    ];

    // Prepare the GraphQL request
    $graphqlRequest = json_encode([
        'query' => $mutation,
        'variables' => $variables,
    ]);

    // Initialize cURL
    $ch = curl_init($endpoint);

    // Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $graphqlRequest,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Check for cURL errors
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $curlError,
        ];
    }

    // Parse the response
    $responseData = json_decode($response, true);

    // Debug output

    // Check HTTP status code
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'HTTP error: ' . $httpCode,
            'response' => $responseData,
        ];
    }

    // Check for GraphQL errors
    if (isset($responseData['errors'])) {
        return [
            'success' => false,
            'error' => 'GraphQL errors',
            'errors' => $responseData['errors'],
        ];
    }

    // Check the mutation result
    $result =
        $responseData['data']['pages']['update']['responseResult'] ?? null;

    if ($result && $result['succeeded']) {
        return [
            'success' => true,
            'message' => 'Page updated successfully',
            'page' => $responseData['data']['pages']['update']['page'] ?? null,
        ];
    } else {
        return [
            'success' => false,
            'error' => $result['message'] ?? 'Unknown error',
            'errorCode' => $result['errorCode'] ?? null,
        ];
    }
}
