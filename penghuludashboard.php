<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- SECURITY: ACCESS CONTROL ---
// Ensure only Penghulu (Role 1) can access this page
if ($_SESSION['role'] != '1') {
    header("Location: loginpage.php");
    exit();
}

// --- SECURITY: AUTHORIZATION ---
// Using 'area_id' from session which corresponds to the 'area_id' column in tbl_users.
// For Penghulu, this ID typically represents their assigned Subdistrict/Mukim.
$user_area_id = filter_var($_SESSION['area_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$user_area_id) {
    die("Security Error: No subdistrict assigned to this account. Please contact the administrator.");
}

// URL Parameter Handling - Sanitized
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'dashboard';
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$village_filter = filter_input(INPUT_GET, 'village', FILTER_VALIDATE_INT) ?: '';
$selectedVillage = $village_filter;

// --- INITIALIZATION ---
$householdStats = [];
$totalSaraQualified = 0; 
$incidentResults = null;
$sosResults = null;
$totalVillagersCount = 0;
$success_msg = null;
$error_msg = null;

// --- AUTHORIZED ANNOUNCEMENT LOGIC ---
// Only broadcast to villages within the logged-in user's jurisdiction (area_id)
$managed_villages = [];
$v_stmt = $conn->prepare("SELECT id FROM tbl_villages WHERE subdistrict_id = ?");
$v_stmt->bind_param("i", $user_area_id);
$v_stmt->execute();
$v_res = $v_stmt->get_result();
while ($v_row = $v_res->fetch_assoc()) {
    $managed_villages[] = $v_row['id'];
}
$v_stmt->close();

if (isset($_POST['submit_announcement']) && $page === 'announcement') {
    // Input Validation: Sanitizing and length limiting
    $title = trim(mb_substr($_POST['title'], 0, 100));
    $description = trim(mb_substr($_POST['description'], 0, 1000));
    $type = $_POST['type'];

    if (empty($managed_villages)) {
        $error_msg = "No villages found in your jurisdiction.";
    } elseif ($title && $description && $type) {
        // Cooldown check for managed villages using Prepared Statement
        $placeholders = implode(',', array_fill(0, count($managed_villages), '?'));
        $types = str_repeat('i', count($managed_villages));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE village_id IN ($placeholders) AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->bind_param($types, ...$managed_villages);
        $stmt->execute();
        $stmt->bind_result($cooldown_count);
        $stmt->fetch();
        $stmt->close();

        if ($cooldown_count > 0) {
            $error_msg = "Please wait 5 minutes before broadcasting another announcement.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_announcements (title, message, type, village_id) VALUES (?, ?, ?, ?)");
            foreach ($managed_villages as $v_id) {
                $stmt->bind_param("sssi", $title, $description, $type, $v_id);
                $stmt->execute();
            }
            $success_msg = "Announcement broadcasted to all your villages.";
            $stmt->close();
        }
    }
}

// --- AUTHORIZED INCIDENT RESOLUTION ---
if (isset($_POST['action']) && $_POST['action'] === 'resolve_incident' && isset($_POST['incident_id'])) {
    $incident_id = (int)$_POST['incident_id'];
    
    // Security check: Ensures the incident belongs to a village in the user's jurisdiction
    $update_stmt = $conn->prepare("
        UPDATE tbl_incidents 
        SET status = 'Resolved' 
        WHERE id = ? AND status = 'In Progress' 
        AND village_id IN (SELECT id FROM tbl_villages WHERE subdistrict_id = ?)
    ");
    if ($update_stmt) {
        $update_stmt->bind_param("ii", $incident_id, $user_area_id);
        if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
            $success_msg = "Incident #$incident_id resolved.";
        } else {
            $error_msg = "Unauthorized or invalid update attempt.";
        }
        $update_stmt->close();
    }
}

// --- AUTHORIZED DATA FETCHING ---
// Get total villagers ONLY for this jurisdiction
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM tbl_villagers vr JOIN tbl_villages v ON vr.village_id = v.id WHERE v.subdistrict_id = ?");
$stmtCount->bind_param("i", $user_area_id);
$stmtCount->execute();
$totalVillagersCount = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$stmtCount->close();

// Get villages in this jurisdiction
$v_query_stmt = $conn->prepare("SELECT v.id, v.village_name, COUNT(vr.id) as population FROM tbl_villages v LEFT JOIN tbl_villagers vr ON v.id = vr.village_id WHERE v.subdistrict_id = ? GROUP BY v.id, v.village_name ORDER BY v.village_name ASC");
$v_query_stmt->bind_param("i", $user_area_id);
$v_query_stmt->execute();
$villagesResult = $v_query_stmt->get_result();

if ($page === 'incident') {
    $sql = "SELECT i.*, v.village_name FROM tbl_incidents i JOIN tbl_villages v ON i.village_id = v.id WHERE i.status = 'In Progress' AND v.subdistrict_id = ?";
    $params = [$user_area_id]; $types = "i";
    if ($search !== '') { $sql .= " AND (i.description LIKE ? OR i.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    if ($village_filter !== '') { $sql .= " AND i.village_id = ?"; array_push($params, $village_filter); $types .= "i"; }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute(); $incidentResults = $stmt->get_result(); $stmt->close();
    }
}

if ($page === 'sos') {
    $sql = "SELECT s.*, v.village_name, vr.name as villager_name FROM tbl_sos s JOIN tbl_villages v ON s.village_id = v.id JOIN tbl_villagers vr ON s.villager_id = vr.id WHERE v.subdistrict_id = ?"; 
    $params = [$user_area_id]; $types = "i";
    if ($search !== '') { $sql .= " AND (vr.name LIKE ? OR s.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute(); $sosResults = $stmt->get_result(); $stmt->close();
    }
}

if ($page === 'household') {
    $stats_stmt = $conn->prepare("
        SELECT 
            v.village_name, v.id as village_id,
            SUM(CASE WHEN h.family_group = 'B40' THEN 1 ELSE 0 END) as b40_count,
            SUM(CASE WHEN h.family_group = 'M40' THEN 1 ELSE 0 END) as m40_count,
            SUM(CASE WHEN h.family_group = 'T20' THEN 1 ELSE 0 END) as t20_count,
            SUM(CASE WHEN h.SARA = 'Approved' THEN 1 ELSE 0 END) as sara_approved_count
        FROM tbl_villages v
        LEFT JOIN tbl_villagers vr ON v.id = vr.village_id
        LEFT JOIN tbl_households h ON vr.id = h.villager_id
        WHERE v.subdistrict_id = ?
        GROUP BY v.id, v.village_name
    ");
    $stats_stmt->bind_param("i", $user_area_id);
    $stats_stmt->execute();
    $statsRes = $stats_stmt->get_result();
    while($row = $statsRes->fetch_assoc()) {
        $householdStats[] = $row;
        $totalSaraQualified += (int)$row['sara_approved_count'];
    }
    $stats_stmt->close();
}

// UI Helpers
function getUrgencyClass($level) { return "urgency " . strtolower(trim($level)); }
function getStatusClass($status) { return "status " . strtolower(str_replace(' ', '-', trim($status))); }
function isActive($target) { global $page; return $page === $target ? 'active' : ''; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Penghulu Dashboard | Secure DVDM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="css/penghuludashboard.css" rel="stylesheet" type="text/css" />
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <style>
        #map { height: 500px; width: 100%; border-radius: 12px; border: 1px solid #ddd; }
        .announcement-form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #374151; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .btn-broadcast { width: 100%; padding: 14px; border: none; background: #2563eb; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        .btn-broadcast:hover { background: #1d4ed8; }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>

    <div class="sidebar">
        <div class="logo"><img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img"><p>DVDM</p></div>
        <div class="user-info-box">
            <div class="avatar">
                <a class="avatar-upload" title="Upload Avatar">
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
                <div style="font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div style="font-size: 13px;opacity: 0.8;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
            </div>
        </div>
        <a href="?page=dashboard" class="<?= isActive('dashboard') ?>"><i class="fa-solid fa-table"></i> Dashboard</a>
        <a href="?page=announcement" class="<?= isActive('announcement') ?>"><i class="fa-solid fa-bell"></i> Announcement</a>
        <a href="?page=incident" class="<?= isActive('incident') ?>"><i class="fa-solid fa-triangle-exclamation"></i> Incident</a>
        <a href="?page=sos" class="<?= isActive('sos') ?>"><i class="fa-solid fa-bell"></i> SOS Report</a>
        <a href="?page=household" class="<?= isActive('household') ?>"><i class="fa-solid fa-house"></i> Household Level</a>
        <a href="?page=map" class="<?= isActive('map') ?>"><i class="fa-solid fa-map"></i> Subdistrict Map</a>
        <a href="logout.php" onclick="return confirm('Logout?')"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>

    <main class="right-content">
        <?php if($success_msg): ?><div class="alert alert-success" style="padding:15px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:20px; border-left: 5px solid #22c55e;"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if($error_msg): ?><div class="alert alert-danger" style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px; border-left: 5px solid #ef4444;"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <div id="dashboard"
                data-area-type="<?= $_SESSION['role'] ?>"
                data-area-id="<?= $_SESSION['area_id'] ?>">
            </div>
            <div class="dashboard-header"><h1>Dashboard Overview:</h1><p class="subtitle">Live population and weather data for your jurisdiction.</p></div>
            <div class="weather-card">
                <h3>WEATHER</h3>    
                <div class="stat-icon" id="weatherIcon">--</div>
                <div class="stat-content"><p>Current Weather</p><h2 id="weatherTemp">--°C</h2><div id="weatherDesc"></div></div>
            </div>
            <div class="village-grid">
                <?php if ($villagesResult && $villagesResult->num_rows > 0): while ($v = $villagesResult->fetch_assoc()): ?>
                    <div class="village-card">
                        <h3><?= htmlspecialchars($v['village_name']) ?></h3>
                        <div class="village-stats"><span>Total Villagers</span><b><?= (int)$v['population'] ?></b></div>
                    </div>
                <?php endwhile; endif; ?>
            </div>
            <script src="js/weather.js"></script>

        <?php elseif ($page === 'announcement'): ?>
            <div class="page-header"><h1>Broadcast Announcement</h1><p>Send messages to all villages in Mukim <?= htmlspecialchars($weatherConfig['name']) ?></p></div>
            <div class="table-card"><div class="announcement-form-container">
                <form method="POST">
                    <div class="form-group"><label>Title</label><input type="text" name="title" required placeholder="Enter title..."></div>
                    <div class="form-group"><label>Type</label><select name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="emergency">🚨 Emergency</option>
                        <option value="weather">🌧 Weather</option>
                        <option value="info">ℹ️ Information</option>
                        <option value="event">🎉 Event</option>
                    </select></div>
                    <div class="form-group"><label>Message Content</label><textarea name="description" rows="5" required placeholder="Enter message..."></textarea></div>
                    <button type="submit" name="submit_announcement" class="btn-broadcast"><i class="fa-solid fa-paper-plane me-2"></i> Broadcast to Subdistrict</button>
                </form>
            </div></div>

        <?php elseif ($page === 'map'): ?>
            <h1>Report Map</h1>
            <div id="map" style="height:500px; width:100%; border-radius:10px;"></div>
            <script src="js/reports.js"></script>

        <?php elseif ($page === 'incident'): ?>
            <div class="page-header"><div><h1>Incidents</h1><p>Active reports in your Mukim</p></div>
                <form method="GET" style="display:inline-block;"><input type="hidden" name="page" value="incident">
                    <select name="village" class="village-select" onchange="this.form.submit()">
                        <option value="">All Villages in Mukim</option>
                        <?php if($villagesResult): $villagesResult->data_seek(0); while ($v = $villagesResult->fetch_assoc()): ?>
                            <option value="<?= $v['id'] ?>" <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['village_name']) ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </form>
            </div>
            <form method="GET">
                <input type="hidden" name="page" value="incident">
                <input type="hidden" name="village" value="<?= htmlspecialchars($selectedVillage) ?>">
                <div class="controls">
                    <input type="text" name="search" class="search-box" placeholder="Search incidents..." value="<?= htmlspecialchars($search) ?>">
                    <div class="control-buttons"><button class="btn-outline" type="submit">Filter</button></div>
                </div>
            </form>
            <div class="table-card">
                <table>
                    <thead><tr><th>Incident</th><th>Village</th><th>Urgency</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($incidentResults && $incidentResults->num_rows > 0): while ($row = $incidentResults->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><?= htmlspecialchars($row['village_name']) ?></td>
                                <td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td>
                                <td><span class="badge <?= getStatusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><button class="btn btn-sm btn-view" data-bs-toggle="modal" data-bs-target="#resolveModal" onclick='populateBootstrapModal(<?= json_encode($row) ?>)'>Action</button></td>
                            </tr>
                        <?php endwhile; else: ?><tr><td colspan="5" class="empty-state">No active incidents in your jurisdiction.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'sos'): ?>
            <div class="page-header"><h1>SOS Alerts</h1><p>Emergency reports for Mukim <?= htmlspecialchars($weatherConfig['name']) ?></p></div>
            <div class="table-card">
                <table>
                    <thead><tr><th>Villager</th><th>Village</th><th>Type</th><th>Urgency</th></tr></thead>
                    <tbody>
                        <?php if ($sosResults && $sosResults->num_rows > 0): while ($row = $sosResults->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($row['villager_name']) ?></td>
                                <td><?= htmlspecialchars($row['village_name']) ?></td>
                                <td><?= htmlspecialchars($row['type']) ?></td>
                                <td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td>
                            </tr>
                        <?php endwhile; else: ?><tr><td colspan="4" class="empty-state">No active SOS alerts.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'household'): ?>
            <div class="page-header"><h1>Household Analysis</h1><p>SARA statistics for Mukim <?= htmlspecialchars($weatherConfig['name']) ?></p></div>
            <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; border-radius: 1rem; padding: 25px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div><h2 style="margin: 0; font-size: 1.1rem; opacity: 0.9;">SARA ASSISTANCE (MUKIM TOTAL)</h2><h3 style="margin: 5px 0 0 0; font-size: 2rem; font-weight: 800;"><?= (int)$totalSaraQualified ?> Households Approved</h3></div>
                <i class="fa-solid fa-hand-holding-heart" style="font-size: 3rem; opacity: 0.3;"></i>
            </div>
            <div class="village-grid">
                <?php foreach ($householdStats as $village): ?>
                    <div class="village-card" style="background:white; padding:20px;">
                        <h5 style="font-weight:bold;"><?= htmlspecialchars($village['village_name']) ?></h5>
                        <div style="height: 180px;"><canvas id="chart_<?= (int)$village['village_id'] ?>"></canvas></div>
                        <div style="margin-top: 15px; padding: 12px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 13px; font-weight: 600; color: #166534;">SARA Approved</span>
                            <div style="font-weight: 800; color: #14532d;"><?= (int)$village['sara_approved_count'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stats = <?= json_encode($householdStats) ?>;
                    stats.forEach(v => {
                        new Chart(document.getElementById(`chart_${v.village_id}`), {
                            type: 'bar',
                            data: { labels: ['B40', 'M40', 'T20'], datasets: [{ data: [v.b40_count, v.m40_count, v.t20_count], backgroundColor: ['rgba(239, 68, 68, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(16, 185, 129, 0.7)'], borderRadius: 5 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </main>

    <!-- Modal for Resolution (Procedural Security) -->
    <div class="modal fade" id="resolveModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Resolve Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="incident_id" id="md_id">
                <input type="hidden" name="action" value="resolve_incident">
                <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value" id="md_desc"></div></div>
                <div class="detail-row"><div class="detail-label">Village</div><div class="detail-value" id="md_village"></div></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-resolve" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function populateBootstrapModal(i) {
            document.getElementById('md_id').value = i.id;
            document.getElementById('md_desc').innerText = i.description;
            document.getElementById('md_village').innerText = i.village_name;
        }
    </script>
    <?php include_once('includes/footer.php'); ?>
    <script src="js/sidebar.js"></script>
</body>
</html>