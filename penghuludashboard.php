<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

// URL Parameter Handling
$page = $_GET['page'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$village_filter = $_GET['village'] ?? '';
$selectedVillage = $village_filter !== '' ? (int)$village_filter : '';

// --- INITIALIZATION ---
$householdStats = [];
$totalSaraQualified = 0; 
$incidentResults = null;
$sosResults = null;
$totalVillagersCount = 0;
$success_msg = null;
$error_msg = null;

// --- DIRECT DATABASE QUERY FOR WEATHER & LOCATION (No Function) ---
// Initialize the weather config array with default keys
$weatherConfig = [
    'name' => 'Unknown',
    'district_id' => 0,
    'lat' => 0, 
    'lng' => 0,
    'id' => 1 // Direct targeting for Jitra record
];

// Execute direct query to fetch location data from tbl_subdistricts
$weatherQuery = "SELECT name, district_id, latitude, longitude FROM tbl_subdistricts WHERE id = 1";
$weatherResult = $conn->query($weatherQuery);

if ($weatherResult && $row = $weatherResult->fetch_assoc()) {
    $weatherConfig['name'] = $row['name'];
    $weatherConfig['district_id'] = $row['district_id'];
    $weatherConfig['lat'] = $row['latitude'];
    $weatherConfig['lng'] = $row['longitude'];
}

// --- ANNOUNCEMENT LOGIC ---
$subdistrict_id = 1; 
$managed_villages = [];
$v_res = $conn->query("SELECT id FROM tbl_villages WHERE subdistrict_id = $subdistrict_id");
if ($v_res) {
    while ($v_row = $v_res->fetch_assoc()) {
        $managed_villages[] = $v_row['id'];
    }
}

if (isset($_POST['submit_announcement']) && $page === 'announcement') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if (empty($managed_villages)) {
        $error_msg = "No villages found in your subdistrict.";
    } elseif ($title && $description && $type) {
        $placeholders = implode(',', array_fill(0, count($managed_villages), '?'));
        $types = str_repeat('i', count($managed_villages));

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM tbl_announcements
            WHERE village_id IN ($placeholders)
            AND created_at >= (NOW() - INTERVAL 5 MINUTE)
        ");
        $stmt->bind_param($types, ...$managed_villages);
        $stmt->execute();
        $stmt->bind_result($cooldown_count);
        $stmt->fetch();
        $stmt->close();

        if ($cooldown_count > 0) {
            $error_msg = "Please wait 5 minutes before posting another announcement.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_announcements (title, message, type, village_id) VALUES (?, ?, ?, ?)");
            foreach ($managed_villages as $v_id) {
                $stmt->bind_param("sssi", $title, $description, $type, $v_id);
                $stmt->execute();
            }
            $success_msg = "Announcement broadcasted to all villages successfully.";
            $stmt->close();
        }
    } else {
        $error_msg = "All fields are required.";
    }
}

// --- INCIDENT RESOLUTION LOGIC ---
if (isset($_POST['action']) && $_POST['action'] === 'resolve_incident' && isset($_POST['incident_id'])) {
    $incident_id = (int)$_POST['incident_id'];
    $update_stmt = $conn->prepare("UPDATE tbl_incidents SET status = 'Resolved' WHERE id = ? AND status = 'In Progress'");
    if ($update_stmt) {
        $update_stmt->bind_param("i", $incident_id);
        if ($update_stmt->execute()) {
            $success_msg = "Incident #$incident_id has been successfully resolved.";
        } else {
            $error_msg = "Failed to update incident status.";
        }
        $update_stmt->close();
    }
}

// UI Helpers
function getUrgencyClass($level) { return "urgency " . strtolower(trim($level)); }
function getStatusClass($status) { return "status " . strtolower(str_replace(' ', '-', trim($status))); }
function isActive($target) { global $page; return $page === $target ? 'active' : ''; }

// --- DATA FETCHING ---
if ($page === 'dashboard' || $page === 'incident' || $page === 'sos' || $page === 'household') {
    $resTotal = $conn->query("SELECT COUNT(*) AS total FROM tbl_villagers");
    if ($resTotal) { $totalVillagersCount = $resTotal->fetch_assoc()['total'] ?? 0; }
    $villagesQuery = "SELECT v.id, v.village_name, COUNT(vr.id) as population FROM tbl_villages v LEFT JOIN tbl_villagers vr ON v.id = vr.village_id GROUP BY v.id, v.village_name ORDER BY v.village_name ASC";
    $villagesResult = $conn->query($villagesQuery);
}

if ($page === 'incident') {
    $sql = "SELECT i.*, v.village_name FROM tbl_incidents i JOIN tbl_villages v ON i.village_id = v.id WHERE i.status = 'In Progress'";
    $params = []; $types = "";
    if ($search !== '') { $sql .= " AND (i.description LIKE ? OR i.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    if ($village_filter !== '') { $sql .= " AND i.village_id = ?"; array_push($params, $village_filter); $types .= "i"; }
    $sql .= " ORDER BY i.date_created DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) { if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $incidentResults = $stmt->get_result(); $stmt->close(); }
}

if ($page === 'sos') {
    $sql = "SELECT s.*, v.village_name, vr.name as villager_name FROM tbl_sos s JOIN tbl_villages v ON s.village_id = v.id JOIN tbl_villagers vr ON s.villager_id = vr.id WHERE 1=1"; 
    $params = []; $types = "";
    if ($search !== '') { $sql .= " AND (vr.name LIKE ? OR s.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    if ($selectedVillage !== '') { $sql .= " AND s.village_id = ?"; array_push($params, $selectedVillage); $types .= "i"; }
    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) { if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $sosResults = $stmt->get_result(); $stmt->close(); }
}

if ($page === 'household') {
    $statsQuery = "SELECT v.village_name, v.id as village_id, SUM(CASE WHEN h.family_group = 'B40' THEN 1 ELSE 0 END) as b40_count, SUM(CASE WHEN h.family_group = 'M40' THEN 1 ELSE 0 END) as m40_count, SUM(CASE WHEN h.family_group = 'T20' THEN 1 ELSE 0 END) as t20_count, SUM(CASE WHEN h.SARA = 'Approved' THEN 1 ELSE 0 END) as sara_approved_count FROM tbl_villages v LEFT JOIN tbl_villagers vr ON v.id = vr.village_id LEFT JOIN tbl_households h ON vr.id = h.villager_id GROUP BY v.id, v.village_name ORDER BY v.village_name ASC";
    $statsRes = $conn->query($statsQuery);
    if ($statsRes) { while($row = $statsRes->fetch_assoc()) { $householdStats[] = $row; $totalSaraQualified += (int)$row['sara_approved_count']; } }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Penghulu Dashboard | Digital Village Dashboard Management</title>
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
        .btn-broadcast { width: 100%; padding: 14px; border: none; background: #2563eb; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>

    <div class="sidebar">
        <div class="logo"><img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img"><p>DVDM</p></div>
        <div class="user-info-box">
            <div class="avatar"><a href="" class="avatar-upload" title="Upload Avatar"><i class="fas fa-user"></i></a></div>
            <div class="user-info">
                <div style="font-weight: bold;font-size: 15px;"><?php $r = ['0' => 'Ketua Kampung', '1' => 'Penghulu', '2' => 'Pejabat Daerah']; echo $r[$_SESSION['role']] ?? 'Unknown'; ?></div>
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
        <a href="registerpage.php"><i class="fa-solid fa-user-plus"></i> Register Account</a>
        <a href="logout.php" onclick="return confirm('Logout?')"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>

    <main class="right-content">
        <?php if($success_msg): ?><div style="padding:15px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:20px; border-left: 5px solid #22c55e;"><i class="fa-solid fa-check-circle me-2"></i><?= $success_msg ?></div><?php endif; ?>
        <?php if($error_msg): ?><div style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px; border-left: 5px solid #ef4444;"><i class="fa-solid fa-circle-exclamation me-2"></i><?= $error_msg ?></div><?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <!-- UPDATED WEATHER DATA HOOK TO MATCH PEJABAT DAERAH FORMAT -->
            <div id="dashboard" 
                 data-area-type="1" 
                 data-area-id="<?= $weatherConfig['id'] ?>"
                 data-location="<?= htmlspecialchars($weatherConfig['name']) ?>"
                 data-lat="<?= $weatherConfig['lat'] ?>" 
                 data-lng="<?= $weatherConfig['lng'] ?>">
            </div>

            <div class="dashboard-header">
                <h1>Village Dashboard</h1>
                <p class="subtitle">Overview of villager population across communities</p>
            </div>
            
            <div class="weather-card">
                <h3><?= htmlspecialchars(strtoupper($weatherConfig['name'])) ?></h3>    
                <div class="stat-icon" id="weatherIcon">--</div>
                <div class="stat-content">
                    <p>Current Weather</p>
                    <h2 id="weatherTemp">--°C</h2>
                    <div id="weatherDesc"></div>
                </div>
            </div>

            <div class="village-grid">
                <?php if ($villagesResult && $villagesResult->num_rows > 0): while ($v = $villagesResult->fetch_assoc()): ?>
                    <div class="village-card">
                        <div class="village-header"><h3><?= htmlspecialchars($v['village_name']) ?></h3><small>ID: <?= $v['id'] ?></small></div>
                        <div class="village-stats"><span>Total Villagers</span><b><?= $v['population'] ?></b></div>
                    </div>
                <?php endwhile; else: ?><p class="empty-state">No villages found.</p><?php endif; ?>
            </div>
            <script type="text/javascript" src="js/weather.js"></script>

        <?php elseif ($page === 'announcement'): ?>
            <div class="page-header"><h1>Broadcast Announcement</h1><p>Broadcasting messages to all villages in <?= htmlspecialchars($weatherConfig['name']) ?></p></div>
            <div class="table-card"><div class="announcement-form-container">
                <form method="POST">
                    <div class="form-group"><label>Title</label><input type="text" name="title" required placeholder="Enter announcement title..."></div>
                    <div class="form-group"><label>Announcement Type</label><select name="type"><option value="General">General News</option><option value="Warning">Weather Warning</option><option value="Emergency">Emergency Alert</option><option value="Event">Community Event</option></select></div>
                    <div class="form-group"><label>Message Content</label><textarea name="description" rows="5" required placeholder="Enter the detailed message here..."></textarea></div>
                    <button type="submit" name="submit_announcement" class="btn-broadcast"><i class="fa-solid fa-paper-plane me-2"></i> Broadcast to All Villages</button>
                </form>
            </div></div>

        <?php elseif ($page === 'map'): ?>
            <div class="page-header"><h1>Subdistrict Map</h1><p>Visual overview of <?= htmlspecialchars($weatherConfig['name']) ?></p></div>
            <div class="table-card" style="padding: 20px;"><div id="map"></div></div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Map initialization using database-fed coordinates
                    var map = L.map('map').setView([<?= $weatherConfig['lat'] ?>, <?= $weatherConfig['lng'] ?>], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);
                    L.marker([<?= $weatherConfig['lat'] ?>, <?= $weatherConfig['lng'] ?>]).addTo(map).bindPopup('<b><?= htmlspecialchars($weatherConfig['name']) ?></b>').openPopup();
                });
            </script>

        <?php elseif ($page === 'incident'): ?>
            <div class="page-header"><div><h1>Incidents</h1><p>Monitor reports from all villages</p></div>
                <form method="GET" style="display:inline-block;"><input type="hidden" name="page" value="incident"><?php if($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <select name="village" class="village-select" onchange="this.form.submit()">
                        <option value="">All Villages</option><?php $villagesResult->data_seek(0); while ($v = $villagesResult->fetch_assoc()): ?><option value="<?= $v['id'] ?>" <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['village_name']) ?></option><?php endwhile; ?>
                    </select>
                </form>
            </div>
            <form method="GET"><input type="hidden" name="page" value="incident"><input type="hidden" name="village" value="<?= $selectedVillage ?>"><div class="controls"><input type="text" name="search" class="search-box" placeholder="Search incidents..." value="<?= htmlspecialchars($search) ?>"><div class="control-buttons"><button class="btn-outline" type="submit">Filter</button></div></div></form>
            <div class="table-card">
                <table><thead><tr><th>Incident</th><th>Type</th><th>Urgency</th><th>Time Reported</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><?php if ($incidentResults && $incidentResults->num_rows > 0): while ($row = $incidentResults->fetch_assoc()): ?>
                        <tr><td><?= htmlspecialchars($row['description']) ?></td><td><?= htmlspecialchars($row['type']) ?></td><td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td><td><?= htmlspecialchars($row['date_created']) ?></td><td><span class="badge <?= getStatusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td><td><div class="action-container"><button type="button" class="btn btn-sm btn-view" data-bs-toggle="modal" data-bs-target="#resolveModal" onclick="populateBootstrapModal(<?= htmlspecialchars(json_encode($row)) ?>)">Action</button></div></td></tr>
                    <?php endwhile; else: ?><tr><td colspan="6" class="empty-state">No incidents to display</td></tr><?php endif; ?></tbody>
                </table>
            </div>

        <?php elseif ($page === 'sos'): ?>
            <div class="page-header"><div><h1>SOS Reports</h1><p>Monitoring emergency alerts</p></div>
                <form method="GET" style="display:inline-block;"><input type="hidden" name="page" value="sos"><?php if($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <select name="village" class="village-select" onchange="this.form.submit()">
                        <option value="">All Villages</option><?php $villagesResult->data_seek(0); while ($v = $villagesResult->fetch_assoc()): ?><option value="<?= $v['id'] ?>" <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['village_name']) ?></option><?php endwhile; ?>
                    </select>
                </form>
            </div>
            <form method="GET"><input type="hidden" name="page" value="sos"><input type="hidden" name="village" value="<?= $selectedVillage ?>"><div class="controls"><input type="text" name="search" class="search-box" placeholder="Search SOS..." value="<?= htmlspecialchars($search) ?>"><div class="control-buttons"><button class="btn-outline" type="submit">Filter</button></div></div></form>
            <div class="table-card">
                <table><thead><tr><th>Villager</th><th>Type</th><th>Urgency</th><th>Time Reported</th><th>Status</th></tr></thead>
                    <tbody><?php if ($sosResults && $sosResults->num_rows > 0): while ($row = $sosResults->fetch_assoc()): ?>
                        <tr><td class="fw-bold"><?= htmlspecialchars($row['villager_name']) ?></td><td><?= htmlspecialchars($row['type']) ?></td><td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td><td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td><td><span class="badge <?= getStatusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td></tr>
                    <?php endwhile; else: ?><tr><td colspan="5" class="empty-state">No SOS alerts found.</td></tr><?php endif; ?></tbody>
                </table>
            </div>

        <?php elseif ($page === 'household'): ?>
            <div class="page-header"><h1>Household Analysis</h1><p>Distribution and SARA assistance eligibility</p></div>
            <div style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; border-radius: 1rem; padding: 25px; margin-top: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;">
                <div><h2 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-transform: uppercase;">Sumbangan Asas Rahmah (SARA)</h2><h3 style="margin: 5px 0 0 0; font-size: 2rem; font-weight: 800;"><?= number_format($totalSaraQualified) ?> Households Approved</h3></div>
                <div style="font-size: 3rem; opacity: 0.3;"><i class="fa-solid fa-hand-holding-heart"></i></div>
            </div>
            <div class="village-grid" style="margin-top:20px;">
                <?php foreach ($householdStats as $village): ?>
                    <div class="village-card" style="background:white; padding:20px;">
                        <h5 style="margin:0; font-weight:bold;"><?= htmlspecialchars($village['village_name']) ?></h5>
                        <div style="height: 180px; position: relative; margin-top: 15px;"><canvas id="chart_<?= $village['village_id'] ?>"></canvas></div>
                        <div style="margin-top: 15px; padding: 12px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #dcfce7;">
                            <span style="font-size: 13px; font-weight: 600; color: #166534;">SARA Approved</span>
                            <div style="font-weight: 800; color: #14532d;"><?= number_format($village['sara_approved_count']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stats = <?= json_encode($householdStats) ?>;
                    stats.forEach(v => {
                        new Chart(document.getElementById(`chart_${v.village_id}`).getContext('2d'), {
                            type: 'bar',
                            data: { labels: ['B40', 'M40', 'T20'], datasets: [{ data: [v.b40_count, v.m40_count, v.t20_count], backgroundColor: ['rgba(239, 68, 68, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(16, 185, 129, 0.7)'], borderRadius: 5 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </main>

    <!-- Modal for Resolution -->
    <div class="modal fade" id="resolveModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Incident Resolution</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="detail-row"><div class="detail-label">Incident Description</div><div class="detail-value" id="md_desc"></div></div>
            <div class="detail-row"><div class="detail-label">Village</div><div class="detail-value" id="md_village"></div></div>
            <div style="background: #ecfdf5; padding: 10px; border-radius: 8px; margin-top: 15px; font-size: 13px;">Proceed to mark as <strong>Resolved</strong>.</div>
        </div>
        <div class="modal-footer"><form method="POST" style="margin: 0; width: 100%; display: flex; justify-content: flex-end; gap: 10px;"><input type="hidden" name="incident_id" id="md_id"><input type="hidden" name="action" value="resolve_incident"><button type="submit" class="btn-resolve" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm</button><button type="button" class="btn-secondary" data-bs-dismiss="modal" style="padding: 8px 16px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button></form></div>
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