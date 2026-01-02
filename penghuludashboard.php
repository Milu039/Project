<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

// URL Parameter Handling
$page = $_GET['page'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$village_filter = $_GET['village'] ?? '';
$selectedVillage = $village_filter !== '' ? (int)$village_filter : '';

// --- LOGIC FIX: Initialize variables at the top to prevent Undefined Variable warnings ---
$householdStats = [];
$incidentResults = null;
$sosResults = null;
$totalVillagersCount = 0;

if (isset($_POST['action']) && $_POST['action'] === 'resolve_incident' && isset($_POST['incident_id'])) {
    $incident_id = (int)$_POST['incident_id'];
    
    // Only update if current status is 'In Progress'
    $update_stmt = $conn->prepare("UPDATE tbl_incidents SET status = 'Resolved' WHERE id = ? AND status = 'In Progress'");
    $update_stmt->bind_param("i", $incident_id);
    
    if ($update_stmt->execute()) {
        $success_msg = "Incident #$incident_id has been successfully resolved.";
    } else {
        $error_msg = "Failed to update incident status.";
    }
    $update_stmt->close();
}

// Standardized CSS Class Helpers
function getUrgencyClass($level) {
    return "urgency " . strtolower(trim($level));
}

function getStatusClass($status) {
    return "status " . strtolower(str_replace(' ', '-', trim($status)));
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

// Helper for active sidebar links
function isActive($target) {
    global $page;
    return $page === $target ? 'active' : '';
}

// --- BACKEND DATA FETCHING ---

// Total Villagers Count
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

// Incident Results
if ($page === 'incident') {
    $sql = "SELECT i.*, v.village_name 
            FROM tbl_incidents i 
            JOIN tbl_villages v ON i.village_id = v.id 
            WHERE i.status = 'In Progress'";
    
    $params = [];
    $types = "";

    if ($search !== '') {
        $sql .= " AND (i.description LIKE ? OR i.type LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s);
        $types .= "ss";
    }
    if ($village_filter !== '') {
        $sql .= " AND i.village_id = ?";
        array_push($params, $village_filter);
        $types .= "i";
    }
    $sql .= " ORDER BY i.date_created DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $incidentResults = $stmt->get_result();
}

// SOS Results
if ($page === 'sos') {
    $sql = "SELECT s.*, v.village_name, vr.name as villager_name 
            FROM tbl_sos s 
            JOIN tbl_villages v ON s.village_id = v.id 
            JOIN tbl_villagers vr ON s.villager_id = vr.id
            WHERE 1=1"; // Placeholder to allow easy AND appending
    
    $params = [];
    $types = "";

    if ($search !== '') { 
        $sql .= " AND (vr.name LIKE ? OR s.type LIKE ?)"; 
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

// Household Analysis Backend Logic (Fixed structure: Moved outside of SOS block)
if ($page === 'household') {
    $statsQuery = "
        SELECT 
            v.village_name, 
            v.id as village_id,
            SUM(CASE WHEN h.family_group = 'B40' THEN 1 ELSE 0 END) as b40_count,
            SUM(CASE WHEN h.family_group = 'M40' THEN 1 ELSE 0 END) as m40_count,
            SUM(CASE WHEN h.family_group = 'T20' THEN 1 ELSE 0 END) as t20_count
        FROM tbl_villages v
        LEFT JOIN tbl_villagers vr ON v.id = vr.village_id
        LEFT JOIN tbl_households h ON vr.id = h.villager_id
        GROUP BY v.id, v.village_name
        ORDER BY v.village_name ASC
    ";
    $statsRes = $conn->query($statsQuery);
    if ($statsRes) {
        while($row = $statsRes->fetch_assoc()) {
            $householdStats[] = $row;
        }
    }
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
    <link href="css/penghuludashboard.css" rel="stylesheet" type="text/css" />
    <link href="css/style.css" rel="stylesheet" type="text/css" />
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
                <div style="font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div style="font-size: 13px;opacity: 0.8;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
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
        <a href="registerpage.php" class="<?= isActive('register') ?>">
            <i class="fa-solid fa-user-plus"></i> Register Account
        </a>
        <a href="logout.php" onclick="return confirm('Logout?')">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <main class="right-content">

        <?php if ($page === 'dashboard'): ?>
            <div id="dashboard" 
                 data-village="<?= $active_vid ?>" 
                 data-lat="<?= $subdistricts_lat ?>" 
                 data-lng="<?= $subdistricts_lng ?>">
            </div>

            <div class="dashboard-header">
                <h1>Village Dashboard</h1>
                <p class="subtitle">Overview of villager population across communities</p>
            </div>
            
            <div class="weather-card <?= $active_vid == 0 ? 'inactive-weather' : '' ?>">
                <h3>JITRA</h3>    
                <div class="stat-icon" id="weatherIcon">--</div>
                <div class="stat-content">
                    <p>Current Weather</p>
                    <h2 id="weatherTemp">--°C</h2>
                    <div id="weatherDesc"></div>
                </div>
            </div>

            <div class="village-grid">
                <?php if ($villagesResult && $villagesResult->num_rows > 0): ?>
                    <?php $villagesResult->data_seek(0); while ($v = $villagesResult->fetch_assoc()): ?>
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

                <form method="GET" style="display:inline-block;">
                    <input type="hidden" name="page" value="incident">
                    <?php if($search !== ''): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    
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
                <input type="hidden" name="page" value="incident">
                <input type="hidden" name="village" value="<?= $selectedVillage ?>">

                <div class="controls">
                    <input type="text"
                        name="search"
                        class="search-box"
                        placeholder="Search incidents..."
                        value="<?= htmlspecialchars($search) ?>">

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
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($incidentResults && $incidentResults->num_rows > 0): ?>
                            <?php while ($row = $incidentResults->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td><?= htmlspecialchars($row['type']) ?></td>
                                    <td>
                                        <span class="badge <?= getUrgencyClass($row['urgency_level']) ?>">
                                            <?= htmlspecialchars($row['urgency_level']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['date_created']) ?></td>
                                    <td>
                                        <span class="badge <?= getStatusClass($row['status']) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-container">
                                            <button type="button" class="btn btn-sm btn-view" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#resolveModal"
                                                    onclick="populateBootstrapModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                                                Action
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    No incidents to display
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'sos'): ?>

            <div class="page-header">
                <div>
                    <h1>SOS Reports</h1>
                    <p>Monitoring active emergency alerts from villagers</p>
                </div>

                <form method="GET" style="display:inline-block;">
                    <input type="hidden" name="page" value="sos">
                    <?php if($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
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
                <input type="hidden" name="page" value="sos">
                <input type="hidden" name="village" value="<?= $selectedVillage ?>">
                <div class="controls">
                    <input type="text" name="search" class="search-box" placeholder="Search by villager or emergency type..." value="<?= htmlspecialchars($search) ?>">
                    <div class="control-buttons">
                        <button class="btn-outline" type="submit">Filter</button>
                    </div>
                </div>
            </form>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Villager</th>
                            <th>Emergency Type</th>
                            <th>Urgency</th>
                            <th>Time Reported</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sosResults && $sosResults->num_rows > 0): ?>
                            <?php while ($row = $sosResults->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['villager_name']) ?></td>
                                    <td><?= htmlspecialchars($row['type']) ?></td>
                                    <td>
                                        <span class="badge <?= getUrgencyClass($row['urgency_level']) ?>">
                                            <?= htmlspecialchars($row['urgency_level']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= getStatusClass($row['status']) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">No SOS alerts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'household'): ?>
            <div class="page-header">
                <h1>Household-level Analysis</h1>
                <p>Income group distribution (B40, M40, T20) across all villages</p>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 20px;">
                <?php foreach ($householdStats as $village): ?>
                    <div class="col" style="flex: 1 1 300px;">
                        <div class="card h-100 border-0 shadow-sm rounded-4" style="background: white; border-radius: 1rem; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3" style="display: flex; justify-content: space-between;">
                                    <div>
                                        <h5 class="card-title fw-bold mb-0" style="margin: 0; font-weight: bold;"><?= htmlspecialchars($village['village_name']) ?></h5>
                                        <small class="text-muted">Village ID: <?= $village['village_id'] ?></small>
                                    </div>
                                    <div class="badge" style="background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 999px; font-size: 12px;">Analysis</div>
                                </div>
                                
                                <div style="height: 200px; position: relative; margin-top: 15px;">
                                    <canvas id="chart_<?= $village['village_id'] ?>"></canvas>
                                </div>

                                <div class="mt-4 border-top pt-3" style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 15px;">
                                    <div class="row text-center" style="display: flex; text-align: center;">
                                        <div class="col-4" style="flex: 1; border-right: 1px solid #eee;">
                                            <div class="small text-muted mb-1">B40</div>
                                            <div class="fw-bold fs-5 text-danger" style="color: #ef4444; font-weight: bold; font-size: 1.2rem;"><?= $village['b40_count'] ?></div>
                                        </div>
                                        <div class="col-4" style="flex: 1; border-right: 1px solid #eee;">
                                            <div class="small text-muted mb-1">M40</div>
                                            <div class="fw-bold fs-5 text-warning" style="color: #f59e0b; font-weight: bold; font-size: 1.2rem;"><?= $village['m40_count'] ?></div>
                                        </div>
                                        <div class="col-4" style="flex: 1;">
                                            <div class="small text-muted mb-1">T20</div>
                                            <div class="fw-bold fs-5 text-success" style="color: #10b981; font-weight: bold; font-size: 1.2rem;"><?= $village['t20_count'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stats = <?= json_encode($householdStats) ?>;
                    
                    stats.forEach(village => {
                        const ctx = document.getElementById(`chart_${village.village_id}`).getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: ['B40', 'M40', 'T20'],
                                datasets: [{
                                    label: 'Number of Households',
                                    data: [village.b40_count, village.m40_count, village.t20_count],
                                    backgroundColor: [
                                        'rgba(239, 68, 68, 0.7)',
                                        'rgba(245, 158, 11, 0.7)',
                                        'rgba(16, 185, 129, 0.7)'
                                    ],
                                    borderColor: [
                                        'rgb(239, 68, 68)',
                                        'rgb(245, 158, 11)',
                                        'rgb(16, 185, 129)'
                                    ],
                                    borderWidth: 1,
                                    borderRadius: 5
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { precision: 0 }
                                    }
                                }
                            }
                        });
                    });
                });
            </script>
        <?php else: ?>
            <h1>Page not found</h1>
        <?php endif; ?>

    </main>

    <!-- Modal for Resolution (Old Style Structure) -->
    <div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveModalLabel">Incident Resolution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="detail-row">
                        <div class="detail-label">Incident Description</div>
                        <div class="detail-value" id="md_desc"></div>
                    </div>
                    <div style="display: flex; gap: 20px;">
                        <div style="flex: 1;">
                            <div class="detail-row">
                                <div class="detail-label">Type</div>
                                <div class="detail-value" id="md_type"></div>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="detail-row">
                                <div class="detail-label">Urgency</div>
                                <div class="detail-value" id="md_urgency"></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Village</div>
                        <div class="detail-value" id="md_village"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status</div>
                        <div class="detail-value" id="md_status"></div>
                    </div>

                    <div style="background: #ecfdf5; color: #065f46; padding: 10px; border-radius: 8px; margin-top: 15px; font-size: 13px;">
                        <i class="fa-solid fa-info-circle"></i> Proceed to mark this incident as <strong>Resolved</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <div style="display: flex; justify-content: flex-end; gap: 10px; width: 100%;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="incident_id" id="md_id">
                            <input type="hidden" name="action" value="resolve_incident">
                            <button type="submit" class="btn-resolve" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm Resolve</button>
                            <button type="button" class="btn-secondary" data-bs-dismiss="modal" style="padding: 8px 16px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function populateBootstrapModal(incident) {
            document.getElementById('md_id').value = incident.id;
            document.getElementById('md_desc').innerText = incident.description;
            document.getElementById('md_type').innerText = incident.type;
            document.getElementById('md_urgency').innerText = incident.urgency_level;
            document.getElementById('md_village').innerText = incident.village_name;
            document.getElementById('md_status').innerText = incident.status;
        }
    </script>

    <?php include_once('includes/footer.php'); ?>
    <script type="text/javascript" src="js/sidebar.js"></script>
</body>
</html>