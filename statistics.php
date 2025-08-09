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

// Get feeding records with weight data
$weightData = [];
$feedingStats = [];
try {
    $config = require 'config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    // Get weight progression data
    $stmt = $pdo->prepare("
        SELECT feeding_date, weight_grams, food_type, food_amount_grams, fitness_level
        FROM feeding_records 
        WHERE kitten_id = ? AND weight_grams > 0
        ORDER BY feeding_date ASC
    ");
    $stmt->execute([$kitten_id]);
    $weightData = $stmt->fetchAll();
    
    // Get feeding statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_feedings,
            AVG(weight_grams) as avg_weight,
            MIN(weight_grams) as min_weight,
            MAX(weight_grams) as max_weight,
            SUM(food_amount_grams) as total_food,
            AVG(food_amount_grams) as avg_food,
            AVG(fitness_level) as avg_fitness,
            MIN(feeding_date) as first_feeding,
            MAX(feeding_date) as last_feeding
        FROM feeding_records 
        WHERE kitten_id = ?
    ");
    $stmt->execute([$kitten_id]);
    $feedingStats = $stmt->fetch();
    
} catch (PDOException $e) {
    $weightData = [];
    $feedingStats = [];
}

// Calculate additional statistics
$statistics = [];
if (!empty($weightData)) {
    $weights = array_column($weightData, 'weight_grams');
    $dates = array_column($weightData, 'feeding_date');
    
    $firstWeight = reset($weights);
    $lastWeight = end($weights);
    $weightGain = $lastWeight - $firstWeight;
    
    $firstDate = new DateTime(reset($dates));
    $lastDate = new DateTime(end($dates));
    $daysDiff = $firstDate->diff($lastDate)->days;
    $weeksDiff = $daysDiff / 7;
    
    $statistics = [
        'first_weight' => $firstWeight,
        'last_weight' => $lastWeight,
        'weight_gain' => $weightGain,
        'weight_gain_percent' => $firstWeight > 0 ? round(($weightGain / $firstWeight) * 100, 1) : 0,
        'days_tracked' => $daysDiff,
        'weeks_tracked' => round($weeksDiff, 1),
        'weight_gain_per_week' => $weeksDiff > 0 ? round($weightGain / $weeksDiff, 1) : 0,
        'measurement_count' => count($weights),
        'avg_weight' => round(array_sum($weights) / count($weights), 1),
        'weight_range' => $lastWeight - $firstWeight,
        'daily_gain' => $daysDiff > 0 ? round($weightGain / $daysDiff, 2) : 0
    ];
}

// Calculate age-based development milestones
function getKittenMilestones($birthDate) {
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    $ageInDays = $now->diff($birth)->days;
    $ageInWeeks = floor($ageInDays / 7);
    
    $milestones = [
        ['week' => 1, 'milestone' => 'Augen beginnen sich zu öffnen', 'description' => 'Die Kätzchen sind noch blind und taub'],
        ['week' => 2, 'milestone' => 'Augen vollständig geöffnet', 'description' => 'Gehör entwickelt sich, erste Bewegungen'],
        ['week' => 3, 'milestone' => 'Erste Gehversuche', 'description' => 'Kätzchen beginnen zu laufen und zu spielen'],
        ['week' => 4, 'milestone' => 'Zähne kommen durch', 'description' => 'Erste Milchzähne, beginnen feste Nahrung zu probieren'],
        ['week' => 5, 'milestone' => 'Sozialisierung beginnt', 'description' => 'Spielen mit Geschwistern, lernen von der Mutter'],
        ['week' => 6, 'milestone' => 'Entwöhnung startet', 'description' => 'Weniger Muttermilch, mehr feste Nahrung'],
        ['week' => 8, 'milestone' => 'Bereit für neues Zuhause', 'description' => 'Vollständig entwöhnt, sozialisiert'],
        ['week' => 12, 'milestone' => 'Erste Impfungen', 'description' => 'Grundimmunisierung sollte beginnen'],
        ['week' => 16, 'milestone' => 'Geschlechtsreife nähert sich', 'description' => 'Kastration sollte erwogen werden']
    ];
    
    return [
        'age_days' => $ageInDays,
        'age_weeks' => $ageInWeeks,
        'milestones' => $milestones,
        'current_phase' => $ageInWeeks <= 2 ? 'Neugeborenes' : 
                          ($ageInWeeks <= 4 ? 'Entwicklung der Sinne' :
                          ($ageInWeeks <= 8 ? 'Sozialisierung' : 
                          ($ageInWeeks <= 16 ? 'Jungtier' : 'Heranwachsend')))
    ];
}

$developmentInfo = getKittenMilestones($kitten['birth_date']);

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
    <title><?= __('app.name') ?> - Gewichtsstatistik - <?= htmlspecialchars($kitten['name']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
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
            max-width: 1400px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .chart-container h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
            text-align: center;
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        
        .development-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .development-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
            text-align: center;
        }
        
        .age-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #667eea;
        }
        
        .age-info h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .milestones {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .milestone {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .milestone.completed {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .milestone.current {
            background: #fff3cd;
            border-left-color: #ffc107;
            animation: pulse 2s infinite;
        }
        
        .milestone.upcoming {
            background: #f8f9fa;
            border-left-color: #6c757d;
        }
        
        .milestone h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1.1em;
        }
        
        .milestone p {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
        }
        
        .milestone .week-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-bottom: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .no-data h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .no-data p {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        
        .no-data a {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .no-data a:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .milestones {
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
                <h1>📊 Gewichtsstatistik</h1>
                <p>Kätzchen: <?= htmlspecialchars($kitten['name']) ?></p>
            </div>
            
            <?php if (empty($weightData)): ?>
                <div class="no-data">
                    <h3>📊 Noch keine Gewichtsdaten vorhanden</h3>
                    <p>Beginnen Sie mit der Erfassung von Fütterungsdaten, um die Gewichtsentwicklung zu verfolgen.</p>
                    <a href="feeding.php?kitten_id=<?= $kitten_id ?>">Erste Fütterung erfassen</a>
                </div>
            <?php else: ?>
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>⚖️ Aktuelle Gewichtszunahme</h3>
                        <div class="stat-value"><?= $statistics['weight_gain'] ?>g</div>
                        <div class="stat-label">seit Beginn der Aufzeichnung</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>📈 Gewichtszunahme pro Woche</h3>
                        <div class="stat-value"><?= $statistics['weight_gain_per_week'] ?>g</div>
                        <div class="stat-label">durchschnittlich</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>📏 Anzahl Messungen</h3>
                        <div class="stat-value"><?= $statistics['measurement_count'] ?></div>
                        <div class="stat-label">über <?= $statistics['days_tracked'] ?> Tage</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>🎯 Durchschnittsgewicht</h3>
                        <div class="stat-value"><?= $statistics['avg_weight'] ?>g</div>
                        <div class="stat-label">über den gesamten Zeitraum</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>🏃 Tägliche Zunahme</h3>
                        <div class="stat-value"><?= $statistics['daily_gain'] ?>g</div>
                        <div class="stat-label">pro Tag im Durchschnitt</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>📊 Gewichtsspanne</h3>
                        <div class="stat-value"><?= $statistics['first_weight'] ?>g - <?= $statistics['last_weight'] ?>g</div>
                        <div class="stat-label">von <?= date('d.m.Y', strtotime($weightData[0]['feeding_date'])) ?> bis <?= date('d.m.Y', strtotime(end($weightData)['feeding_date'])) ?></div>
                    </div>
                </div>
                
                <!-- Weight Chart -->
                <div class="chart-container">
                    <h2>📈 Gewichtsverlauf</h2>
                    <div class="chart-wrapper">
                        <canvas id="weightChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Development Milestones -->
            <div class="development-section">
                <h2>🌱 Entwicklungsphasen</h2>
                
                <div class="age-info">
                    <h3>Aktuelles Alter: <?= $developmentInfo['age_days'] ?> Tage (<?= $developmentInfo['age_weeks'] ?> Wochen)</h3>
                    <p><strong>Aktuelle Phase:</strong> <?= $developmentInfo['current_phase'] ?></p>
                </div>
                
                <div class="milestones">
                    <?php foreach ($developmentInfo['milestones'] as $milestone): ?>
                        <?php 
                            $status = 'upcoming';
                            if ($developmentInfo['age_weeks'] > $milestone['week']) {
                                $status = 'completed';
                            } elseif ($developmentInfo['age_weeks'] == $milestone['week']) {
                                $status = 'current';
                            }
                        ?>
                        <div class="milestone <?= $status ?>">
                            <div class="week-badge">Woche <?= $milestone['week'] ?></div>
                            <h4><?= $milestone['milestone'] ?></h4>
                            <p><?= $milestone['description'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($weightData)): ?>
    <script>
        // Prepare chart data
        const weightData = <?= json_encode($weightData) ?>;
        const chartData = weightData.map(record => ({
            x: record.feeding_date,
            y: parseInt(record.weight_grams)
        }));
        
        // Create weight chart
        const ctx = document.getElementById('weightChart').getContext('2d');
        const weightChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Gewicht (g)',
                    data: chartData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        callbacks: {
                            title: function(context) {
                                const date = new Date(context[0].parsed.x);
                                return date.toLocaleDateString('de-DE', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                            },
                            label: function(context) {
                                return `Gewicht: ${context.parsed.y}g`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'dd.MM'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Datum',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#333'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Gewicht (g)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#333'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });
        
        // Add animation for statistics cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Initially hide cards and observe them
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = `all 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    </script>
    <?php endif; ?>
</body>
</html>