<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

$page = $_GET['page'] ?? 'overview';

$area_id = (int) ($_SESSION['area_id'] ?? 0);

$villages = [];

// Fetch all villages in all subdistricts within this district
$stmt = $conn->prepare("
    SELECT v.id 
    FROM tbl_villages v
    JOIN tbl_subdistricts s ON v.subdistrict_id = s.id
    WHERE s.district_id = ?
");
$stmt->bind_param("i", $area_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $villages[] = $row['id'];
}
$stmt->close();

if (isset($_POST['submit_announcement'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if (empty($villages)) {
        $error = "No villages found in this district.";
        exit;
    }

    // Check 5-minute cooldown for any village in this district
    $placeholders = implode(',', array_fill(0, count($villages), '?'));
    $types = str_repeat('i', count($villages));

    if ($title && $description && $type) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM tbl_announcements
            WHERE village_id IN ($placeholders)
            AND created_at >= (NOW() - INTERVAL 5 MINUTE)
        ");
        $stmt->bind_param($types, ...$villages);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Please wait 5 minutes before posting another announcement.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO tbl_announcements (title, message, type, village_id)
                 VALUES (?, ?, ?, ?)"
            );

            foreach ($villages as $village_id) {
                $stmt->bind_param("sssi", $title, $description, $type, $village_id);
                $stmt->execute();
            }
            $success = "Announcement published successfully.";
            $stmt->close();
        }
    } else {
        $error = "All fields are required.";
    }
}

/* =======================
   CLOSE INCIDENT (RESOLVED)
   ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_incident'])) {
    $id = intval($_POST['incident_id']);
    $conn->begin_transaction();

    // UPDATED: Set status to 'Resolved' instead of 'Closed'
    $stmt = $conn->prepare("UPDATE tbl_incidents SET status='Resolved' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $log = $conn->prepare("
        INSERT INTO tbl_audit_log (incident_id, action, performed_by)
        VALUES (?, 'Resolved Incident', ?)
    ");
    $log->bind_param("is", $id, $_SESSION['user_email']);
    $log->execute();

    $conn->commit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Pejabat Daerah Dashboard</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/pejabatdaerahdashboard.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Pejabat Daerah Dashboard | Incidents</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="sidebar">
        <div class="logo">
            <img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img">
            <p>DVMD</p>
        </div>

        <div class="user-info-box">
            <div class="avatar">
                <a class="avatar-upload"><i class="fas fa-user"></i></a>
            </div>
            <div class="user-info">
                <div style="font-weight: bold;font-size: 15px;">
                    <?php
                    $roleNames = ['0' => 'Ketua Kampung', '1' => 'Penghulu', '2' => 'Pejabat Daerah'];
                    echo $roleNames[$_SESSION['role']] ?? 'Unknown';
                    ?>
                </div>
                <div style="font-size: 14px;"><?php echo $_SESSION['user_name']; ?></div>
            </div>
        </div>

        <a class="sidebar-link <?= $page === 'overview' ? 'active' : '' ?>" href="?page=overview">
            <i class="fas fa-chart-line"></i> Overview
        </a>

        <a class="sidebar-link <?= $page === 'announcement' ? 'active' : '' ?>" href="?page=announcement">
            <i class="fas fa-bell"></i> Make Announcement
        </a>

        <a class="sidebar-link <?= $page === 'villages' ? 'active' : '' ?>" href="?page=villages">
            <i class="fas fa-house"></i> Villages
        </a>

        <a class="sidebar-link <?= $page === 'report' ? 'active' : '' ?>" href="?page=report">
            <i class="fas fa-triangle-exclamation"></i> Report
        </a>

        <a class="sidebar-link <?= $page === 'map' ? 'active' : '' ?>" href="?page=map">
            <i class="fas fa-map"></i> Map
        </a>

        <a class="sidebar-link <?= $page === 'analytics' ? 'active' : '' ?>" href="?page=analytics">
            <i class="fas fa-chart-pie"></i> Analytics
        </a>

        <a class="sidebar-link" href="registerpage.php">
            <i class="fas fa-user-plus"></i> Register Account
        </a>

        <a class="sidebar-link" href="logout.php" onclick="return confirm('Logout?')">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <div class="right-content">
        <?php if ($page === 'overview'): ?>
            <div id="dashboard" data-area-type="<?= $_SESSION['role'] ?>" data-area-id="<?= $_SESSION['area_id'] ?>">
            </div>

            <script type="text/javascript" src="js/weather.js"></script>

            <?php
            // 1. Existing Counts
            $v = $conn->query("SELECT COUNT(*) c FROM tbl_villages")->fetch_assoc()['c'];
            $i = $conn->query("SELECT COUNT(*) c FROM tbl_incidents")->fetch_assoc()['c'];

            // UPDATED: Logic for High Urgency (Counting High + Critical)
            $high = $conn->query("
                    SELECT COUNT(*) c FROM tbl_incidents 
                    WHERE urgency_level IN ('High', 'Critical') 
                    AND status NOT IN ('Resolved', 'False Alarm')
                ")->fetch_assoc()['c'];

            // UPDATED: Count 'Resolved' instead of 'Closed'
            $cl = $conn->query("
                    SELECT COUNT(*) c FROM tbl_incidents 
                    WHERE status='Resolved'
                ")->fetch_assoc()['c'];

            $sos_active = $conn->query("
                            SELECT COUNT(*) c FROM tbl_sos 
                            WHERE status NOT IN ('Resolved', 'False Alarm')
                        ")->fetch_assoc()['c'];

            // 2. Weather Location
            $weather_loc = $conn->query("SELECT id FROM tbl_villages LIMIT 1")->fetch_assoc();
            $weather_village_id = $weather_loc ? $weather_loc['id'] : 0;

            // 3. Chart Data
            $typeData = $conn->query("SELECT type, COUNT(*) total FROM tbl_incidents GROUP BY type");
            $statusData = $conn->query("SELECT status, COUNT(*) total FROM tbl_incidents GROUP BY status");

            // UPDATED: Filter out Resolved/False Alarm for "Recent Incidents"
            $recent = $conn->query("
                    SELECT i.id, i.type, i.urgency_level, i.status, v.village_name
                    FROM tbl_incidents i
                    JOIN tbl_villages v ON i.village_id = v.id
                    WHERE i.status NOT IN ('Resolved', 'False Alarm')
                    ORDER BY i.id DESC LIMIT 5
                ");
            ?>

            <section class="section">
                <div class="page-title">District Overview</div>
                <?php
                    // UPDATED: Fetch Critical/High items from BOTH Incidents AND SOS tables
                    $alerts = $conn->query("
                    (SELECT i.type, v.village_name, i.date_created, 'Incident' as source
                    FROM tbl_incidents i 
                    JOIN tbl_villages v ON i.village_id = v.id 
                    WHERE (i.urgency_level = 'High' OR i.urgency_level = 'Critical') 
                    AND i.status NOT IN ('Resolved', 'False Alarm'))

                    UNION ALL

                    (SELECT s.type, v.village_name, s.created_at as date_created, 'SOS' as source
                    FROM tbl_sos s
                    JOIN tbl_villages v ON s.village_id = v.id
                    WHERE (s.urgency_level = 'High' OR s.urgency_level = 'Critical')
                    AND s.status NOT IN ('Resolved', 'False Alarm'))

                    ORDER BY date_created DESC
                ");
                    ?>

                <?php if ($alerts->num_rows > 0): ?>
                    <div class="alert-banner">
                        <div class="alert-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>CRITICAL ATTENTION REQUIRED (<?= $alerts->num_rows ?>)</span>
                        </div>
                        <div class="alert-list">
                            <?php while ($a = $alerts->fetch_assoc()): ?>
                                <div class="alert-item">
                                    <span class="alert-text">
                                        <b style="color: #b91c1c;"><?= htmlspecialchars($a['type']) ?></b>
                                        <span>in</span>
                                        <u style="color: #0f172a;"><?= htmlspecialchars($a['village_name']) ?></u>
                                    </span>
                                    <small>(Reported: <?= date('d M, h:i A', strtotime($a['date_created'])) ?>)</small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="top-stats">

                    <div class="stat-box red">
                        <div class="stat-icon">
                            <i class="fas fa-life-ring"></i>
                        </div>
                        <div class="stat-content">
                            <p>Active SOS</p>
                            <h2><?= $sos_active ?></h2>
                        </div>
                    </div>

                    <div class="stat-box blue">
                        <div class="stat-icon"><i class="fas fa-house"></i></div>
                        <div class="stat-content">
                            <p>Villages</p>
                            <h2><?= $v ?></h2>
                        </div>
                    </div>

                    <div class="stat-box orange">
                        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="stat-content">
                            <p>Incidents</p>
                            <h2><?= $i ?></h2>
                        </div>
                    </div>

                    <div class="stat-box red">
                        <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                        <div class="stat-content">
                            <p>High / Critical</p>
                            <h2><?= $high ?></h2>
                        </div>
                    </div>

                    <div class="stat-box green">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <p>Resolved</p>
                            <h2><?= $cl ?></h2>
                        </div>
                    </div>

                    <div class="stat-box purple">
                        <div class="stat-icon" id="weatherIcon"><i class="fas fa-cloud"></i></div>
                        <div class="stat-content">
                            <p>District Weather</p>
                            <h2 id="weatherTemp">--°C</h2>
                            <div id="weatherDesc" style="font-size:11px;">Loading...</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="chart-row">
                    <div class="chart-box">
                        <h3>Incident Distribution</h3>
                        <canvas id="typeChart"></canvas>
                    </div>
                    <div class="chart-box">
                        <h3>Incident Status</h3>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="section">
                <h3>Recent Active Incidents</h3>
                <table>
                    <tr>
                        <th>Village</th>
                        <th>Type</th>
                        <th>Urgency</th>
                        <th>Status</th>
                    </tr>
                    <?php while ($r = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['village_name']) ?></td>
                            <td><?= htmlspecialchars($r['type']) ?></td>
                            <td>
                                <span
                                    class="badge badge-<?= ($r['urgency_level'] == 'Critical' || $r['urgency_level'] == 'High') ? 'red' : 'blue' ?>">
                                    <?= $r['urgency_level'] ?>
                                </span>
                            </td>
                            <td><?= $r['status'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </section>

            <script>
                const typeChartInstance = new Chart(document.getElementById('typeChart'), {
                    type: 'pie',
                    data: {
                        labels: [<?php while ($t = $typeData->fetch_assoc())
                            echo "'{$t['type']}',"; ?>],
                        datasets: [{
                            data: [<?php mysqli_data_seek($typeData, 0);
                            while ($t = $typeData->fetch_assoc())
                                echo "{$t['total']},"; ?>],
                            backgroundColor: ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true
                    }
                });

                const statusChartInstance = new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: [<?php while ($s = $statusData->fetch_assoc())
                            echo "'{$s['status']}',"; ?>],
                        datasets: [{
                            data: [<?php mysqli_data_seek($statusData, 0);
                            while ($s = $statusData->fetch_assoc())
                                echo "{$s['total']},"; ?>],
                            // Colors for Pending, In Progress, Progressing, Resolved, False Alarm
                            backgroundColor: ['#555', '#2f5bea', '#7d3cff', '#1e824c', '#c0392b']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true
                    }
                });
            </script>

        <?php elseif ($page === 'announcement'): ?>
            <h1>Make Announcement</h1>
            <?php if (!empty($success)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert error">
                    <i class="fas fa-times-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <form method="post" class="announcement-form" action="">
                <!-- TITLE -->
                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" required placeholder="Enter announcement title">
                </div>
                <!-- DESCRIPTION -->
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5" required placeholder="Enter announcement details"></textarea>
                </div>
                <!-- TYPE -->
                <div class="form-group">
                    <label>Announcement Type</label>
                    <select name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="emergency">🚨 Emergency</option>
                        <option value="weather">🌧 Weather</option>
                        <option value="info">ℹ️ Information</option>
                        <option value="event">🎉 Event</option>
                    </select>
                </div>
                <!-- SUBMIT -->
                <button type="submit" name="submit_announcement">Publish Announcement</button>
            </form>
        <?php elseif ($page === 'villages'): ?>

            <?php
            $q = $conn->query("
                    SELECT 
                        v.id, 
                        v.village_name,
                        COUNT(i.id) AS total,
                        /* UPDATED: Check for 'Pending' instead of 'Submitted' */
                        COALESCE(SUM(i.status='Pending'), 0) AS pending_count,
                        COUNT(DISTINCT va.id) AS villagers
                    FROM tbl_villages v
                    LEFT JOIN tbl_incidents i ON v.id = i.village_id
                    LEFT JOIN tbl_villagers va ON v.id = va.village_id
                    GROUP BY v.id
                ");
            ?>

            <section class="section">
                <div class="page-title">Villages</div>

                <div class="filter-bar">
                    <input type="text" id="searchVillage" placeholder="Search village...">
                    <select id="criticalFilter" onchange="filterVillage()">
                        <option value="">All</option>
                        <option value="pending">Has Pending</option>
                        <option value="no pending">No Pending</option>
                    </select>
                </div>

                <table id="villageTable">
                    <tr>
                        <th>Village</th>
                        <th>Villagers</th>
                        <th>Total Incidents</th>
                        <th>Pending Incidents</th>
                        <th>Action</th>
                    </tr>
                    <?php while ($r = $q->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['village_name']) ?></td>
                            <td><?= $r['villagers'] ?></td>
                            <td><?= $r['total'] ?></td>
                            <td class="<?= $r['pending_count'] > 0 ? 'urgency-critical' : '' ?>">
                                <?= $r['pending_count'] ?>
                            </td>
                            <td><a class="btn btn-view" href="?page=villagers&id=<?= $r['id'] ?>">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </section>

            <script>
                const searchVillage = document.getElementById("searchVillage");
                const criticalFilter = document.getElementById("criticalFilter");

                searchVillage.addEventListener("keyup", filterVillage);
                criticalFilter.addEventListener("change", filterVillage);

                function filterVillage() {
                    let text = searchVillage.value.toLowerCase();
                    let filter = criticalFilter.value;

                    document.querySelectorAll("#villageTable tr").forEach((row, i) => {
                        if (i === 0) return;
                        let countText = row.cells[3].innerText.trim();
                        let count = parseInt(countText) || 0;
                        let hasPending = count > 0;

                        let show = row.innerText.toLowerCase().includes(text);

                        if (filter === "pending") show = show && hasPending;
                        if (filter === "no pending") show = show && !hasPending;

                        row.style.display = show ? "" : "none";
                    });
                }
            </script>

        <?php elseif ($page === 'villagers' && isset($_GET['id'])): ?>
            <?php
            $villageId = intval($_GET['id']);
            $village = $conn->query("SELECT village_name FROM tbl_villages WHERE id = $villageId")->fetch_assoc();
            $villagers = $conn->query("SELECT name, email, phone, address FROM tbl_villagers WHERE village_id = $villageId");
            ?>
            <section class="section">
                <div class="page-title">Villagers – <?= htmlspecialchars($village['village_name']) ?></div>
                <div class="filter-bar"><input type="text" id="searchVillager" placeholder="Search villager..."></div>
                <table id="villagerTable">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                    </tr>
                    <?php while ($v = $villagers->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['name']) ?></td>
                            <td><?= htmlspecialchars($v['email']) ?></td>
                            <td><?= htmlspecialchars($v['phone']) ?></td>
                            <td><?= htmlspecialchars($v['address']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
                <br><a href="?page=villages" class="btn btn-view">← Back to Villages</a>
            </section>
            <script>
                document.getElementById("searchVillager").addEventListener("keyup", function () {
                    let text = this.value.toLowerCase();
                    document.querySelectorAll("#villagerTable tr").forEach((row, i) => {
                        if (i > 0) row.style.display = row.innerText.toLowerCase().includes(text) ? "" : "none";
                    });
                });
            </script>

        <?php elseif ($page === 'report'): ?>

            <?php
            // UPDATED QUERY: Shows ALL incidents from the table (Removed District Filter)
            $stmt = $conn->prepare("
                        SELECT i.id, i.type, i.urgency_level, i.status, i.date_created AS reported_date,
                        v.village_name, 'incident' AS source
                        FROM tbl_incidents i
                        LEFT JOIN tbl_villages v ON i.village_id = v.id

                        UNION ALL

                        SELECT so.id, so.type, so.urgency_level, so.status, so.created_at AS reported_date,
                        v2.village_name, 'sos' AS source
                        FROM tbl_sos so
                        LEFT JOIN tbl_villages v2 ON so.village_id = v2.id

                        ORDER BY reported_date DESC
                    ");

            // No parameters needed since we are fetching ALL rows
            $stmt->execute();
            $resultSet = $stmt->get_result();
            ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="page-title">All Reports</h2>
                    <div class="table-actions">
                        <a href="export_incidents.php" class="btn btn-export"><i class="fas fa-file-csv"></i> Export
                            CSV</a>
                        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i>
                            Print</button>
                    </div>
                </div>

                <div class="filter-bar">
                    <input type="text" id="searchIncident" placeholder="Search...">

                    <select id="typeFilter" onchange="filterIncident()">
                        <option value="">All Types</option>
                        <option value="Floods">Floods</option>
                        <option value="Fires">Fires</option>
                        <option value="Landslides">Landslides</option>
                        <option value="SOS">SOS</option>
                        <option value="Other">Other</option>
                    </select>

                    <select id="statusFilter" onchange="filterIncident()">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Reject">Reject</option>
                    </select>
                </div>

                <div class="report-list active">
                    <table id="incidentTable">
                        <tr>
                            <th>Type</th>
                            <th>Village</th>
                            <th>Urgency Level</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php
                        if ($resultSet->num_rows > 0):
                            while ($result = $resultSet->fetch_assoc()):
                                $createdTime = strtotime($result['reported_date']);
                                $isNew = (time() - $createdTime) <= 24 * 60 * 60;
                                ?>

                                <tr>
                                    <td style="text-align: left;">
                                        <?= htmlspecialchars($result['type']) ?>             <?php if ($isNew)
                                                           echo ' <span class="new-badge">NEW</span>'; ?>
                                    </td>
                                    <td><?= htmlspecialchars($result['village_name'] ?? 'Unknown Village') ?></td>

                                    <td>
                                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                                            <?= htmlspecialchars($result['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('d M Y', strtotime($result['reported_date'])) ?></td>

                                    <td>
                                        <span class="badge status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>">
                                            <?= htmlspecialchars($result['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="btn btn-view"
                                            href="?page=view&source=<?= $result['source'] ?>&id=<?= $result['id'] ?>">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                        <?php endif ?>
                    </table>
                </div>

            </section>

            <script>
                const searchIncident = document.getElementById("searchIncident");
                const typeFilter = document.getElementById("typeFilter");
                const statusFilter = document.getElementById("statusFilter");

                searchIncident.addEventListener("keyup", filterIncident);
                typeFilter.addEventListener("change", filterIncident);
                statusFilter.addEventListener("change", filterIncident);

                function filterIncident() {
                    let text = searchIncident.value.toLowerCase();
                    let type = typeFilter.value.toLowerCase();
                    let status = statusFilter.value.toLowerCase();

                    document.querySelectorAll("#incidentTable tr").forEach((row, i) => {
                        if (i === 0) return;
                        let content = row.innerText.toLowerCase();
                        // Adjust column indices if needed: Type is usually index 0, Status index 4
                        let rowType = row.cells[0].innerText.toLowerCase();
                        let rowStatus = row.cells[4].innerText.toLowerCase();

                        let show = content.includes(text);
                        if (type && !rowType.includes(type)) show = false;
                        if (status && !rowStatus.includes(status)) show = false;

                        row.style.display = show ? "" : "none";
                    });
                }
            </script>

        <?php elseif ($page === 'view' && isset($_GET['source']) && isset($_GET['id'])): ?>

            <?php
            $source = $_GET['source'];
            if ($source === 'incident') {
                $id = intval($_GET['id']);
                $result = $conn->query("
                        SELECT i.*, v.village_name, date_created as reported_at
                        FROM tbl_incidents i
                        JOIN tbl_villages v ON i.village_id = v.id
                        WHERE i.id = $id
                    ")->fetch_assoc();

                if (!$result) {
                    echo "<div class='section'><p>Incident not found.</p> <a href='?page=report' class='btn'>Back</a></div>";
                    return;
                }
            } else {
                $id = intval($_GET['id']);
                $result = $conn->query("
                        SELECT so.*, v.village_name, created_at as reported_at
                        FROM tbl_sos so
                        JOIN tbl_villages v ON so.village_id = v.id
                        WHERE so.id = $id
                    ")->fetch_assoc();

                if (!$result) {
                    echo "<div class='section'><p>SOS not found.</p> <a href='?page=report' class='btn'>Back</a></div>";
                    return;
                }
            }

            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=report" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($result['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($result['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($result['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($result['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($result['village_name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($result['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($result['latitude']) ?>,
                                    <?= htmlspecialchars($result['longitude']) ?>
                                </p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($result['reported_at'])) ?></p>
                            </div>
                        </div>

                        <?php if ($result['status'] !== 'Resolved' && $result['status'] !== 'Reject'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/<?= $source ?>/update_<?= $source ?>.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $result['id'] ?>">
                                    <button type="submit" name="incident_action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check-circle"></i> Mark as Resolved
                                    </button>
                                    <button type="submit" name="incident_action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/<?= $source ?>/delete_<?= $source ?>.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $sos['id'] ?>">
                                    <button type="submit" name="incident_action" value="delete" class="btn btn-delete">
                                        <i class="fas fa-trash-can"></i> Delete
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="image-col">
                        <?php if (!empty($incident['image'])): ?>
                            <div class="image-wrapper">
                                <img src="uploads/<?= htmlspecialchars($incident['image']) ?>" alt="Evidence Photo">
                                <div class="img-caption">Evidence Photo</div>
                            </div>
                        <?php else: ?>
                            <div class="no-photo"><i class="fas fa-camera-slash"></i>
                                <p>No photo provided</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php elseif ($page === 'map'): ?>
            <h1>Report Map</h1>
            <div id="map" style="height:500px; width:100%; border-radius:10px;"></div>
            <script src="js/reports.js"></script>

        <?php elseif ($page === 'analytics'): ?>

            <?php
            // 1. Hotspots
            $villageData = $conn->query("
                    SELECT v.village_name, COUNT(i.id) as total
                    FROM tbl_villages v
                    LEFT JOIN tbl_incidents i ON v.id = i.village_id
                    GROUP BY v.id
                    ORDER BY total DESC LIMIT 10
                ");

            // 2. Trend
            $trendData = $conn->query("
                    SELECT DATE(date_created) as report_date, COUNT(*) as total
                    FROM tbl_incidents
                    WHERE date_created >= DATE(NOW()) - INTERVAL 7 DAY
                    GROUP BY DATE(date_created)
                    ORDER BY report_date ASC
                ");

            // 3. Urgency
            $urgencyData = $conn->query("
                    SELECT urgency_level, COUNT(*) as total
                    FROM tbl_incidents
                    GROUP BY urgency_level
                ");

            $vLabels = [];
            $vCounts = [];
            while ($row = $villageData->fetch_assoc()) {
                $vLabels[] = $row['village_name'];
                $vCounts[] = $row['total'];
            }

            $tLabels = [];
            $tCounts = [];
            while ($row = $trendData->fetch_assoc()) {
                $tLabels[] = date('d M', strtotime($row['report_date']));
                $tCounts[] = $row['total'];
            }

            $uLabels = [];
            $uCounts = [];
            while ($row = $urgencyData->fetch_assoc()) {
                $uLabels[] = $row['urgency_level'];
                $uCounts[] = $row['total'];
            }
            ?>

            <section class="section">
                <div class="page-title">Analytics & Trends</div>
                <?php if (empty($vCounts) && empty($tCounts)): ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-chart-bar" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No data available for analytics yet.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-row">
                        <div class="chart-box">
                            <h3>⚠️ Incident Hotspots</h3>
                            <canvas id="villageChart"></canvas>
                        </div>
                        <div class="chart-box">
                            <h3>📈 7-Day Trend</h3>
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-row" style="margin-top: 26px; grid-template-columns: 1fr;">
                        <div class="chart-box" style="max-width: 600px; margin: 0 auto; width: 100%;">
                            <h3>🔥 Urgency / Severity</h3>
                            <div style="height: 250px;"> <canvas id="urgencyChart"></canvas> </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <script>
                const vCtx = document.getElementById('villageChart');
                if (vCtx) {
                    new Chart(vCtx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($vLabels) ?>,
                            datasets: [{
                                label: 'Total',
                                data: <?= json_encode($vCounts) ?>,
                                backgroundColor: '#3b82f6',
                                borderRadius: 6
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false, // <--- ADD THIS LINE
                            plugins: {
                                legend: { display: false } // Hides the label if you want more space
                            }
                        }
                    });
                }

                const tCtx = document.getElementById('trendChart');
                if (tCtx) {
                    new Chart(tCtx, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($tLabels) ?>,
                            datasets: [{
                                label: 'Incidents',
                                data: <?= json_encode($tCounts) ?>,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // <--- ADD THIS LINE
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }

                // UPDATED: Chart colors for Low, Medium, High, Critical
                const uCtx = document.getElementById('urgencyChart');
                if (uCtx) {
                    new Chart(uCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?= json_encode($uLabels) ?>,
                            datasets: [{
                                data: <?= json_encode($uCounts) ?>,
                                backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#7f1d1d'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // <--- ADD THIS LINE
                            plugins: {
                                legend: { position: 'right' }
                            }
                        }
                    });
                }
            </script>
        <?php endif; ?>
    </div>
    <?php include_once('includes/footer.php'); ?>
    <script type="text/javascript" src="js/sidebar.js"></script>
</body>

</html>