<?php
// Set timezone to Amsterdam/Netherlands
date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit(0);
}

require_once 'config.php';

$termsFile = __DIR__ . '/data/terms.html';

// Ensure data directory exists
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
	mkdir($dataDir, 0700, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	// Return terms content
	if (file_exists($termsFile)) {
		header('Content-Type: text/html; charset=utf-8');
		echo file_get_contents($termsFile);
	} else {
		header('Content-Type: text/html; charset=utf-8');
		echo '<h1>Licentievoorwaarden</h1><p>Nog geen licentievoorwaarden ingesteld.</p>';
	}
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$action = $input['action'] ?? '';
	$adminKey = $input['admin_key'] ?? '';
	
	// Verify admin key
	if ($adminKey !== ADMIN_KEY) {
		http_response_code(401);
		echo json_encode(['error' => 'Unauthorized']);
		exit;
	}
	
	if ($action === 'save') {
		$content = $input['content'] ?? '';
		
		// Sanitize content (basic HTML allowed)
		$allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><strong><b><em><i><u><ul><ol><li><a><span><div>';
		$cleanContent = strip_tags($content, $allowedTags);
		
		// Save to file
		if (file_put_contents($termsFile, $cleanContent) !== false) {
			echo json_encode(['success' => true, 'message' => 'Terms saved successfully']);
		} else {
			http_response_code(500);
			echo json_encode(['error' => 'Failed to save terms']);
		}
	} else {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid action']);
	}
	exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>