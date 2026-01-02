<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. URL Parameter Handling
$page = $_GET['page'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$village_filter = $_GET['village'] ?? '';
$selectedVillage = $village_filter !== '' ? (int)$village_filter : '';

// 2. ADAPTED WEATHER LOGIC FROM KETUA KAMPUNG
// Handle village selection for the session
if (isset($_GET['select_village'])) {
    $_SESSION['active_weather_village_id'] = (int)$_GET['select_village'];
}

// Fetch coordinates for the active village
$active_vid = $_SESSION['active_weather_village_id'] ?? 0;
$subdistricts_lat = 0;
$subdistricts_lng = 0;

if ($active_vid > 0) {
    $stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_subdistricts WHERE id = ?");
    $stmt->bind_param("i", $active_vid);
    $stmt->execute();
    $stmt->bind_result($subdistricts_lat, $subdistricts_lng);
    $stmt->fetch();
    $stmt->close();
}

// 3. Helper for active sidebar links
function isActive($target) {
    global $page;
    return $page === $target ? 'active' : '';
}

// --- BACKEND DATA FETCHING ---

// Total Villagers Count
$totalVillagersCount = 0;
$resTotal = $conn->query("SELECT COUNT(*) AS total FROM tbl_villagers");
if ($resTotal) {
    $rowTotal = $resTotal->fetch_assoc();
    $totalVillagersCount = $rowTotal['total'] ?? 0;
}

// Village Population Data for Grid
$villagesQuery = "
    SELECT v.id, v.village_name, COUNT(vr.id) as population 
    FROM tbl_villages v
    LEFT JOIN tbl_villagers vr ON v.id = vr.village_id
    GROUP BY v.id, v.village_name
    ORDER BY v.village_name ASC
";
$villagesResult = $conn->query($villagesQuery);

// Incident Results (if applicable)
$incidentResults = null;
if ($page === 'incident') {
    $sql = "SELECT i.*, v.village_name FROM tbl_incidents i JOIN tbl_villages v ON i.village_id = v.id WHERE 1";
    $params = [];
    $types = "";
    if ($search !== '') {
        $sql .= " AND (i.description LIKE ? OR i.type LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s);
        $types .= "ss";
    }
    if ($selectedVillage !== '') {
        $sql .= " AND i.village_id = ?";
        array_push($params, $selectedVillage);
        $types .= "i";
    }
    $sql .= " ORDER BY i.date_created DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $incidentResults = $stmt->get_result();
}

// SOS Results (if applicable)
$sosResults = null;
if ($page === 'sos') {
    $sql = "SELECT s.*, v.village_name, vr.name as villager_name FROM tbl_sos s 
            JOIN tbl_villages v ON s.village_id = v.id 
            JOIN tbl_villagers vr ON s.villager_id = vr.id 
            WHERE 1";
    
    $params = [];
    $types = "";

    if ($search !== '') {
        // Updated to search by villager name or possibly emergency description if column exists
        $sql .= " AND (vr.name LIKE ? OR v.village_name LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s);
        $types .= "ss";
    }

    if ($selectedVillage !== '') {
        $sql .= " AND s.village_id = ?";
        array_push($params, $selectedVillage);
        $types .= "i";
    }

    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sosResults = $stmt->get_result();
}
?>

<!-- frontend -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Penghulu Dashboard | Digital Village Dashboard Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="css/penghuludashboard.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="sidebar">
        <div class="logo">
            <img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img">
            <p>DVDM</p>
        </div>

        <div class="user-info-box">
            <div class="avatar">
                <a href="" class="avatar-upload" title="Upload Avatar">
                    <i class="fas fa-user"></i>
                </a>
            </div>
            <div class="user-info">
                <div style="font-weight: bold;font-size: 15px;">
                    <?php
                    $roleNames = [
                        '0' => 'Ketua Kampung',
                        '1' => 'Penghulu',
                        '2' => 'Pejabat Daerah'
                    ];
                    echo $roleNames[$_SESSION['role']] ?? 'Unknown';
                    ?></div>
                <div style="font-size: 14px;"><?php echo $_SESSION['user_name']; ?></div>
                <div style="font-size: 13px;opacity: 0.8;"><?php echo $_SESSION['user_email']; ?></div>
            </div>
        </div>

        <a href="?page=dashboard" class="<?= isActive('dashboard') ?>">
            <i class="fa-solid fa-table"></i> Dashboard
        </a>
        <a href="?page=incident" class="<?= isActive('incident') ?>">
            <i class="fa-solid fa-triangle-exclamation"></i> Incident
        </a>
        <a href="?page=sos" class="<?= isActive('sos') ?>">
            <i class="fa-solid fa-bell"></i> SOS Report
        </a>
        <a href="?page=household" class="<?= isActive('household') ?>">
            <i class="fa-solid fa-house"></i> Household Level
        </a>
        <a href="registerpage.php?role=<?= $_SESSION['role'] ?>&id=<?=  $_SESSION['subdistrct_id'] ?>" class="<?= isActive('register') ?>">
            <i class="fa-solid fa-user-plus"></i> Register Account
        </a>
        <a href="logout.php" onclick="return confirm('Logout?')">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <!-- right content-->
    <main class="right-content">

        <?php if ($page === 'dashboard'): ?>
            <!-- Weather Data Hook: Passes dynamic coordinates to JS -->
            <div id="dashboard" 
                 data-village="<?= $active_vid ?>" 
                 data-lat="<?= $subdistricts_lat ?>" 
                 data-lng="<?= $subdistricts_lng ?>">
            </div>

            <div class="dashboard-header">
                <h1>Village Dashboard</h1>
                <p class="subtitle">Overview of villager population across communities</p>
            </div>

            <!-- TOTAL VILLAGERS CARD -->
            <div class="section total-villagers-card">
                <div class="total-card-content">
                    <div class="icon-box">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <span class="label">Total Villagers</span>
                        <h2><?= $totalVillagersCount ?></h2>
                    </div>
                </div>
            </div>
            
            <!-- COMPACT WEATHER CARD -->
                <div class="weather-card <?= $active_vid == 0 ? 'inactive-weather' : '' ?>">
                <h3>JITRA</h3>    
                <div class="stat-icon" id="weatherIcon">--</div>
                    <div class="stat-content">
                        <p>Current Weather</p>
                        <h2 id="weatherTemp">--°C</h2>
                        <div id="weatherDesc">
                            <?= $active_vid > 0 ? 'Loading weather...' : 'Select a village below' ?>
                        </div>
                    </div>
                </div>

            <!-- VILLAGE CARDS -->
            <div class="village-grid">
                <?php if ($villagesResult && $villagesResult->num_rows > 0): ?>
                    <?php while ($v = $villagesResult->fetch_assoc()): ?>
                        <div class="village-card <?= ($active_vid == $v['id']) ? 'active-village' : '' ?>">
                            <div class="village-header">
                                <div>
                                    <h3><?= htmlspecialchars($v['village_name']) ?></h3>
                                    <small>Village ID: <?= $v['id'] ?></small>
                                </div>
                                <div class="icon-badge">
                                    <i class="fa-solid fa-user-group"></i>
                                </div>
                            </div>

                            <div class="village-stats">
                                <span>Total Villagers</span>
                                <b><?= $v['population'] ?></b>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No villages found.</p>
                <?php endif; ?>
            </div>

            <script type="text/javascript" src="js/weather.js"></script>

        <?php elseif ($page === 'incident'): ?>
            
            <div class="page-header">
                <div>
                    <h1>Incidents</h1>
                    <p>Monitor and manage incident reports from the village</p>
                </div>

                <select name="village" class="village-select">
                    <option value="">All Villages</option>
                    <?php while ($v = $villagesResult->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>"
                            <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['village_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <form method="GET">
                <input type="hidden" name="page" value="incident">

                <div class="controls">
                    <input type="text"
                        name="search"
                        class="search-box"
                        placeholder="Search incidents..."
                        value="<?= $_GET['search'] ?? '' ?>">

                    <div class="control-buttons">
                        <button class="btn-outline" type="submit">Filter</button>
                    </div>
                </div>
            </form>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Incident</th>
                            <th>Type</th>
                            <th>Urgency</th>
                            <th>Time Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($incidentResults->num_rows > 0): ?>
                            <?php while ($row = $incidentResults->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td><?= htmlspecialchars($row['type']) ?></td>
                                    <td><?= htmlspecialchars($row['urgency_level']) ?></td>
                                    <td><?= htmlspecialchars($row['date_created']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    No incidents to display
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'sos'): ?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>SOS Reports</h1>
                    <p>View emergency SOS alerts submitted by villagers</p>
                </div>

                <!-- Same village dropdown -->
                <select name="village" class="village-select">
                    <option value="">All Villages</option>
                    <?php while ($v = $villagesResult->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>"
                            <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['village_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Filter Bar -->
            <div class="controls">
                <input type="text" class="search-box" placeholder="Search SOS reports...">

                <!-- No Report Button (RBAC enforced) -->
                <div class="control-buttons">
                    <button class="btn-outline">Filter</button>
                </div>
            </div>

            <!-- SOS Table -->
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>SOS ID</th>
                            <th>Village</th>
                            <th>Emergency Type</th>
                            <th>Urgency</th>
                            <th>Time Reported</th>
                        </tr>
                    </thead>

                    <tbody>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    No incidents to display
                                </td>
                            </tr>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'household'): ?>

            <div class="page-header">
                <div>
                    <h1>Household-level Analysis</h1>
                    <p>Manage the household records of the villagers</p>
                </div>

                <form method="GET" action="">
                    <input type="hidden" name="page" value="household">
                    <select name="village" class="village-select" onchange="this.form.submit()">
                        <option value="">All Villages</option>
                        <?php 
                        $villagesResult->data_seek(0);
                        while ($v = $villagesResult->fetch_assoc()): 
                        ?>
                            <option value="<?= $v['id'] ?>" <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['village_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <form method="GET">
                <input type="hidden" name="page" value="household">

                <div class="controls">
                    <input type="text"
                        name="search"
                        class="search-box"
                        placeholder="Search villagers by name..."
                        value="<?= htmlspecialchars($search) ?>">

                    <div class="control-buttons">
                        <button class="btn-outline" type="submit">Search</button>
                    </div>
                </div>
            </form>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Villager's Name</th>
                            <th>Phone Number</th>
                            <th>Address</th>
                            <th>Family Member</th>
                            <th>Family Group</th>
                            <th>SARA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Static Frontend Mockup (No Database Connection Yet) -->
                        <tr>
                            <td>John Doe</td>
                            <td>012-3456789</td>
                            <td>No. 123, Jalan Greenwood</td>
                            <td>4</td>
                            <td>B40</td>
                            <td>Approved</td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>019-8765432</td>
                            <td>Lot 45, Kampung Riverside</td>
                            <td>4</td>
                            <td>M40</td>
                            <td>Approved</td>
                        </tr>
                        <!-- Empty State Placeholder if Results were empty -->
                        <!-- 
                        <tr>
                            <td colspan="6" class="empty-state">No household records to display</td>
                        </tr> 
                        -->
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'register'): ?>

            <div class="page-header">
                <div>
                    <h1>Register</h1>
                    <p>Register new villagers or update existing records</p>
                </div>
            </div>

        <?php else: ?>

            <h1>Page not found</h1>

        <?php endif; ?>

    </main>
    
    <?php include_once('includes/footer.php'); ?>
    <script type="text/javascript" src="js/sidebar.js"></script>
</body>
</html>