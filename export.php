<?php
session_start();

require_once 'config/i18n.php';
require_once 'classes/User.php';
require_once 'classes/Kitten.php';

$userService = new User();
$kittenService = new Kitten();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$kitten_id = isset($_GET['kitten_id']) ? (int)$_GET['kitten_id'] : 0;

if (!$kitten_id) {
    header('Location: dashboard.php');
    exit;
}

// Check if user has access to this kitten
if (!$kittenService->hasAccess($kitten_id, $currentUser['id'])) {
    header('Location: dashboard.php');
    exit;
}

$kitten = $kittenService->getKittenById($kitten_id);
if (!$kitten) {
    header('Location: dashboard.php');
    exit;
}

// Function to create Excel-like CSV file
function createCSV($data, $headers) {
    $output = fopen('php://temp', 'w');
    
    // Write BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($output, $headers, ';');
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

// Function to create plain text file
function createTextFile($data, $title) {
    $text = "=== $title ===\n";
    $text .= __('export.created_at') . ': ' . date('d.m.Y H:i:s') . "\n\n";
    
    foreach ($data as $entry) {
        foreach ($entry as $key => $value) {
            $text .= "$key: $value\n";
        }
        $text .= str_repeat('-', 50) . "\n";
    }
    
    return $text;
}

// Function to generate weight statistics chart as image
function generateWeightChart($feedingData, $kittenName) {
    // Create a simple SVG chart
    $width = 800;
    $height = 400;
    $margin = 50;
    $chartWidth = $width - 2 * $margin;
    $chartHeight = $height - 2 * $margin;
    
    if (empty($feedingData)) {
        return null;
    }
    
    // Extract weight data
    $weights = [];
    $dates = [];
    foreach ($feedingData as $record) {
        if ($record['weight_grams'] > 0) {
            $weights[] = (int)$record['weight_grams'];
            $dates[] = $record['feeding_date'];
        }
    }
    
    if (empty($weights)) {
        return null;
    }
    
    $minWeight = min($weights);
    $maxWeight = max($weights);
    $weightRange = $maxWeight - $minWeight;
    if ($weightRange == 0) $weightRange = 1;
    
    // Create SVG
    $svg = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">';
    
    // Background
    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#f8f9fa"/>';
    
    // Title
    $svg .= '<text x="' . ($width/2) . '" y="30" text-anchor="middle" font-family="Arial" font-size="18" font-weight="bold" fill="#333">' . htmlspecialchars(__('statistics.chart.title')) . ' - ' . htmlspecialchars($kittenName) . '</text>';
    
    // Chart area
    $svg .= '<rect x="' . $margin . '" y="' . $margin . '" width="' . $chartWidth . '" height="' . $chartHeight . '" fill="white" stroke="#ddd"/>';
    
    // Grid lines and labels
    for ($i = 0; $i <= 5; $i++) {
        $y = $margin + ($chartHeight / 5) * $i;
        $weight = $maxWeight - ($weightRange / 5) * $i;
        
        // Grid line
        $svg .= '<line x1="' . $margin . '" y1="' . $y . '" x2="' . ($margin + $chartWidth) . '" y2="' . $y . '" stroke="#e0e0e0"/>';
        
        // Weight label
        $svg .= '<text x="' . ($margin - 10) . '" y="' . ($y + 5) . '" text-anchor="end" font-family="Arial" font-size="12" fill="#666">' . round($weight) . 'g</text>';
    }
    
    // Data points and line
    $points = '';
    for ($i = 0; $i < count($weights); $i++) {
        $x = $margin + ($chartWidth / (count($weights) - 1)) * $i;
        $y = $margin + $chartHeight - (($weights[$i] - $minWeight) / $weightRange) * $chartHeight;
        
        if ($i == 0) {
            $points = $x . ',' . $y;
        } else {
            $points .= ' ' . $x . ',' . $y;
        }
        
        // Data point
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="4" fill="#667eea"/>';
        
        // Date label (every few points to avoid crowding)
        if ($i % max(1, floor(count($weights) / 8)) == 0) {
            $date = date('d.m', strtotime($dates[$i]));
            $svg .= '<text x="' . $x . '" y="' . ($height - 10) . '" text-anchor="middle" font-family="Arial" font-size="10" fill="#666">' . $date . '</text>';
        }
    }
    
    // Line
    $svg .= '<polyline points="' . $points . '" fill="none" stroke="#667eea" stroke-width="2"/>';
    
    // Axis labels
    $svg .= '<text x="' . ($width/2) . '" y="' . ($height - 5) . '" text-anchor="middle" font-family="Arial" font-size="14" fill="#333">' . htmlspecialchars(__('statistics.axis.date')) . '</text>';
    $svg .= '<text x="15" y="' . ($height/2) . '" text-anchor="middle" font-family="Arial" font-size="14" fill="#333" transform="rotate(-90 15 ' . ($height/2) . ')">' . htmlspecialchars(__('statistics.axis.weight')) . '</text>';
    
    $svg .= '</svg>';
    
    return $svg;
}

// Handle export request
if (isset($_POST['export_data'])) {
    try {
        $config = require 'config/database.php';
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
            $config['username'],
            $config['password'],
            $config['options']
        );
        
        // Get feeding records
        $stmt = $pdo->prepare("
            SELECT feeding_date, weight_grams, food_amount_grams, food_type, 
                   heating_pad_refilled, stool_type, stool_consistency, 
                   stool_color, stool_color_other, fitness_level, notes
            FROM feeding_records 
            WHERE kitten_id = ? 
            ORDER BY feeding_date ASC
        ");
        $stmt->execute([$kitten_id]);
        $feedingRecords = $stmt->fetchAll();
        
        // Get veterinary records
        $stmt = $pdo->prepare("
            SELECT visit_date, veterinarian_name, diagnosis, vaccination,
                   next_vaccination_date, deworming, deworming_medication,
                   next_deworming_interval, tick_protection, tick_protection_medication,
                   next_tick_protection_interval, next_visit_date, cost_eur
            FROM veterinary_records 
            WHERE kitten_id = ? 
            ORDER BY visit_date ASC
        ");
        $stmt->execute([$kitten_id]);
        $vetRecords = $stmt->fetchAll();
        
        // Get images
        $stmt = $pdo->prepare("SELECT filename, original_name, caption FROM kitten_images WHERE kitten_id = ?");
        $stmt->execute([$kitten_id]);
        $images = $stmt->fetchAll();
        
        // Create temporary directory for export
        $tempDir = sys_get_temp_dir() . '/catcontrol_export_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        // Create CSV files
        if (!empty($feedingRecords)) {
            $feedingHeaders = [
                __('export.csv.feeding.headers.date_time'), __('export.csv.feeding.headers.weight'), __('export.csv.feeding.headers.food_amount'), __('export.csv.feeding.headers.food_type'),
                __('export.csv.feeding.headers.heat_bottle'), __('export.csv.feeding.headers.stool_type'), __('export.csv.feeding.headers.stool_consistency'),
                __('export.csv.feeding.headers.stool_color'), __('export.csv.feeding.headers.stool_color_other'), __('export.csv.feeding.headers.fitness'), __('export.csv.feeding.headers.notes')
            ];
            
            $feedingData = [];
            foreach ($feedingRecords as $record) {
                $feedingData[] = [
                    date('d.m.Y H:i', strtotime($record['feeding_date'])),
                    $record['weight_grams'] ?: '',
                    $record['food_amount_grams'] ?: '',
                    $record['food_type'] ?: '',
                    $record['heating_pad_refilled'] ? __('common.yes') : __('common.no'),
                    $record['stool_type'] ?: '',
                    $record['stool_consistency'] ?: '',
                    $record['stool_color'] ?: '',
                    $record['stool_color_other'] ?: '',
                    $record['fitness_level'] ?: '',
                    $record['notes'] ?: ''
                ];
            }
            
            file_put_contents($tempDir . '/' . __('export.files.feeding_csv_name'), createCSV($feedingData, $feedingHeaders));
            file_put_contents($tempDir . '/' . __('export.files.feeding_txt_name'), createTextFile($feedingData, __('feeding.title') . ' - ' . $kitten['name']));
        }
        
        if (!empty($vetRecords)) {
            $vetHeaders = [
                __('export.csv.vet.headers.visit_date'), __('export.csv.vet.headers.veterinarian'), __('export.csv.vet.headers.diagnosis'), __('export.csv.vet.headers.vaccination'),
                __('export.csv.vet.headers.next_vaccination'), __('export.csv.vet.headers.deworming'), __('export.csv.vet.headers.deworming_med'),
                __('export.csv.vet.headers.next_deworming'), __('export.csv.vet.headers.tick_protection'), __('export.csv.vet.headers.tick_protection_med'),
                __('export.csv.vet.headers.next_tick_protection'), __('export.csv.vet.headers.next_visit'), __('export.csv.vet.headers.cost_eur')
            ];
            
            $vetData = [];
            foreach ($vetRecords as $record) {
                $vetData[] = [
                    date('d.m.Y', strtotime($record['visit_date'])),
                    $record['veterinarian_name'] ?: '',
                    $record['diagnosis'] ?: '',
                    $record['vaccination'] ?: '',
                    $record['next_vaccination_date'] ? date('d.m.Y', strtotime($record['next_vaccination_date'])) : '',
                    $record['deworming'] ? __('common.yes') : __('common.no'),
                    $record['deworming_medication'] ?: '',
                    $record['next_deworming_interval'] ?: '',
                    $record['tick_protection'] ? __('common.yes') : __('common.no'),
                    $record['tick_protection_medication'] ?: '',
                    $record['next_tick_protection_interval'] ?: '',
                    $record['next_visit_date'] ? date('d.m.Y', strtotime($record['next_visit_date'])) : '',
                    $record['cost_eur'] ?: ''
                ];
            }
            
            file_put_contents($tempDir . '/' . __('export.files.vet_csv_name'), createCSV($vetData, $vetHeaders));
            file_put_contents($tempDir . '/' . __('export.files.vet_txt_name'), createTextFile($vetData, __('vet.table.title') . ' - ' . $kitten['name']));
        }
        
        // Create weight chart
        if (!empty($feedingRecords)) {
            $chartSVG = generateWeightChart($feedingRecords, $kitten['name']);
            if ($chartSVG) {
                file_put_contents($tempDir . '/' . __('export.files.weight_svg_name'), $chartSVG);
            }
        }
        
        // Copy images
        if (!empty($images)) {
            $imageDir = $tempDir . '/' . __('export.files.images_dir');
            mkdir($imageDir, 0755, true);
            
            foreach ($images as $image) {
                $sourcePath = 'uploads/kitten_images/' . $image['filename'];
                if (file_exists($sourcePath)) {
                    $targetName = $image['original_name'];
                    if ($image['caption']) {
                        $targetName = $image['caption'] . '_' . $targetName;
                    }
                    copy($sourcePath, $imageDir . '/' . $targetName);
                }
            }
        }
        
        // Create info file
        $infoText = __('export.info.title') . "\n";
        $infoText .= __('export.info.kitten') . ': ' . $kitten['name'] . "\n";
        $infoText .= __('export.info.birth_date') . ': ' . date('d.m.Y', strtotime($kitten['birth_date'])) . "\n";
        $infoText .= __('export.info.color') . ': ' . ($kitten['color'] ?: __('common.not_specified')) . "\n";
        $infoText .= __('export.info.mother') . ': ' . ($kitten['mother'] ?: __('common.not_specified')) . "\n";
        $infoText .= __('export.info.found_location') . ': ' . ($kitten['found_location'] ?: __('common.not_specified')) . "\n";
        $infoText .= __('export.info.export_created_at') . ': ' . date('d.m.Y H:i:s') . "\n\n";
        
        $infoText .= __('export.info.stats.title') . "\n";
        $infoText .= __('export.info.count.feedings') . ': ' . count($feedingRecords) . "\n";
        $infoText .= __('export.info.count.vet') . ': ' . count($vetRecords) . "\n";
        $infoText .= __('export.info.count.images') . ': ' . count($images) . "\n\n";
        
        if (!empty($feedingRecords)) {
            $weights = array_filter(array_column($feedingRecords, 'weight_grams'));
            if (!empty($weights)) {
                $firstWeight = reset($weights);
                $lastWeight = end($weights);
                $weightGain = $lastWeight - $firstWeight;
                
                $infoText .= __('export.info.weight.title') . "\n";
                $infoText .= __('export.info.first_weight') . ': ' . $firstWeight . "g\n";
                $infoText .= __('export.info.last_weight') . ': ' . $lastWeight . "g\n";
                $infoText .= __('export.info.gain') . ': ' . $weightGain . "g\n";
                $infoText .= __('export.info.measurements') . ': ' . count($weights) . "\n\n";
            }
        }
        
        file_put_contents($tempDir . '/' . __('export.files.info_txt_name'), $infoText);
        
        // Create ZIP file
        $zipName = 'catcontrol_export_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $kitten['name']) . '_' . date('Y-m-d') . '.zip';
        $zipPath = $tempDir . '/' . $zipName;
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception(__('export.error.cannot_create_zip'));
        }
        
        // Add all files to ZIP
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir() && $file->getFilename() !== $zipName) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        // Download the ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        readfile($zipPath);
        
        // Cleanup
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return;
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
        deleteDirectory($tempDir);
        
        exit;
        
    } catch (Exception $e) {
        $error = __('export.error.generic', ['message' => $e->getMessage()]);
    }
}

// Get custom background if set
$backgroundImage = 'assets/images/background.png';
if (!empty($currentUser['custom_background'])) {
    $customBg = 'uploads/backgrounds/' . $currentUser['custom_background'];
    if (file_exists($customBg)) {
        $backgroundImage = $customBg;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('app.name') ?> - <?= __('export.title') ?> - <?= htmlspecialchars($kitten['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-image: url('<?= $backgroundImage ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .overlay {
            background-color: rgba(255, 255, 255, 0.9);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .back-button {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .back-button:hover {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }
        
        .export-section {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .export-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 2em;
            text-align: center;
        }
        
        .export-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        
        .export-info h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .export-info ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .export-info li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .export-info li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        
        .kitten-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-card h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .info-card p {
            color: #666;
            line-height: 1.5;
        }
        
        .export-button {
            display: block;
            width: 100%;
            max-width: 400px;
            margin: 30px auto;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .export-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .export-button:active {
            transform: translateY(-1px);
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #ffc107;
            margin-bottom: 30px;
        }
        
        .warning h4 {
            margin-bottom: 10px;
            color: #856404;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #dc3545;
            margin-bottom: 30px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 10px;
            }
            
            .overlay {
                padding: 10px;
            }
            
            .export-section {
                padding: 20px;
            }
            
            .kitten-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="overlay">
        <div class="container">
            <a href="dashboard.php" class="back-button">← <?= __('menu.back_to_dashboard') ?></a>
            
            <div class="header">
                <h1>📦 <?= __('export.header.title') ?></h1>
                <p><?= __('export.header.kitten_label') ?>: <?= htmlspecialchars($kitten['name']) ?></p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="export-section">
                <h2>📊 <?= __('export.section.full_export') ?></h2>
                
                <div class="kitten-info">
                    <div class="info-card">
                        <h4>🐱 <?= __('export.kitten_info.title') ?></h4>
                        <p><strong><?= __('export.kitten_info.name') ?>:</strong> <?= htmlspecialchars($kitten['name']) ?></p>
                        <p><strong><?= __('export.kitten_info.birth_date') ?>:</strong> <?= date('d.m.Y', strtotime($kitten['birth_date'])) ?></p>
                        <p><strong><?= __('export.kitten_info.age') ?>:</strong> 
                            <?php
                                $birth = new DateTime($kitten['birth_date']);
                                $now = new DateTime();
                                $diff = $now->diff($birth);
                                echo __('export.kitten_info.age_format', ['days' => $diff->days, 'weeks' => floor($diff->days / 7)]);
                            ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <h4>📋 <?= __('export.more_details.title') ?></h4>
                        <p><strong><?= __('export.more_details.color') ?>:</strong> <?= htmlspecialchars($kitten['color'] ?: __('common.not_specified')) ?></p>
                        <p><strong><?= __('export.more_details.mother') ?>:</strong> <?= htmlspecialchars($kitten['mother'] ?: __('common.not_specified')) ?></p>
                        <p><strong><?= __('export.more_details.found_location') ?>:</strong> <?= htmlspecialchars($kitten['found_location'] ?: __('common.not_specified')) ?></p>
                    </div>
                </div>
                
                <div class="export-info">
                    <h3>📋 <?= __('export.what_exported.title') ?></h3>
                    <ul>
                        <li><?= __('export.what_exported.feeding') ?></li>
                        <li><?= __('export.what_exported.vet') ?></li>
                        <li><?= __('export.what_exported.weight_svg') ?></li>
                        <li><?= __('export.what_exported.images') ?></li>
                        <li><?= __('export.what_exported.summary') ?></li>
                        <li><?= __('export.what_exported.zip') ?></li>
                    </ul>
                </div>
                
                <div class="warning">
                    <h4>⚠️ <?= __('export.warning.title') ?></h4>
                    <p>• <?= __('export.warning.line1') ?></p>
                    <p>• <?= __('export.warning.line2') ?></p>
                    <p>• <?= __('export.warning.line3') ?></p>
                    <p>• <?= __('export.warning.line4') ?></p>
                </div>
                
                <form method="POST">
                    <button type="submit" name="export_data" class="export-button">
                        📦 <?= __('export.button.start') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>