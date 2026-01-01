<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');

/* =======================
   ACCESS CONTROL
   ======================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: loginpage.php");
    exit();
}

/* =======================
   PAGE CONTROLLER
   ======================= */
$page = $_GET['page'] ?? 'overview';

/* =======================
   CLOSE INCIDENT
   ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_incident'])) {
    $id = intval($_POST['incident_id']);
    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE tbl_incident SET status='Closed' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $log = $conn->prepare("
    INSERT INTO tbl_audit_log (incident_id, action, performed_by)
    VALUES (?, 'Closed Incident', ?)
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

    <!-- Base + Dashboard CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/pejabatdaerahdashboard.css">

    <!-- Icons + Charts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="dashboard-page">

    <div class="dashboard-container">

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar">
            <div class="logo">DVMD</div>

            <a href="?page=overview" class="<?= $page === 'overview' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Overview
            </a>
            <a href="?page=villages" class="<?= $page === 'villages' ? 'active' : '' ?>">
                <i class="fas fa-house"></i> Villages
            </a>
            <a href="?page=incidents" class="<?= $page === 'incidents' ? 'active' : '' ?>">
                <i class="fas fa-triangle-exclamation"></i> Incidents
            </a>
            <a href="?page=analytics" class="<?= $page === 'analytics' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Analytics
            </a>

            <a href="registerpage.php?role=<?= $_SESSION['role'] ?>&id=<?= $_SESSION['district_id'] ?>" class="<?= $page === 'register' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Register Account
            </a>

            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="right-content">

            <?php if ($page === 'overview'): ?>

                <?php
                $v = $conn->query("SELECT COUNT(*) c FROM tbl_villages")->fetch_assoc()['c'];
                $i = $conn->query("SELECT COUNT(*) c FROM tbl_incident")->fetch_assoc()['c'];
                $c = $conn->query("SELECT COUNT(*) c FROM tbl_incident WHERE urgency_level='Critical'")->fetch_assoc()['c'];
                $cl = $conn->query("SELECT COUNT(*) c FROM tbl_incident WHERE status='Closed'")->fetch_assoc()['c'];

                $typeData = $conn->query("SELECT type, COUNT(*) total FROM tbl_incident GROUP BY type");
                $statusData = $conn->query("SELECT status, COUNT(*) total FROM tbl_incident GROUP BY status");

                $recent = $conn->query("
            SELECT i.type, i.urgency_level, i.status, v.village_name
            FROM tbl_incident i
            JOIN tbl_villages v ON i.village_id = v.id
            ORDER BY i.id DESC LIMIT 5
        ");
                ?>

                <section class="section">
                    <div class="page-title">District Overview</div>

                    <div class="top-stats">
                        <div class="stat-box blue">
                            <i class="fas fa-house"></i>
                            <span>Villages</span>
                            <b><?= $v ?></b>
                        </div>
                        <div class="stat-box orange">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Incidents</span>
                            <b><?= $i ?></b>
                        </div>
                        <div class="stat-box red">
                            <i class="fas fa-bolt"></i>
                            <span>Critical</span>
                            <b><?= $c ?></b>
                        </div>
                        <div class="stat-box green">
                            <i class="fas fa-check-circle"></i>
                            <span>Closed</span>
                            <b><?= $cl ?></b>
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
                    <h3>Recent Incidents</h3>
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
                                <td><?= $r['urgency_level'] ?></td>
                                <td><?= $r['status'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </section>

                <script>
                    new Chart(typeChart, {
                        type: 'pie',
                        data: {
                            labels: [<?php while ($t = $typeData->fetch_assoc())
                                            echo "'{$t['type']}',"; ?>],
                            datasets: [{
                                data: [<?php mysqli_data_seek($typeData, 0);
                                        while ($t = $typeData->fetch_assoc())
                                            echo "{$t['total']},"; ?>]
                            }]
                        }
                    });

                    new Chart(statusChart, {
                        type: 'doughnut',
                        data: {
                            labels: [<?php while ($s = $statusData->fetch_assoc())
                                            echo "'{$s['status']}',"; ?>],
                            datasets: [{
                                data: [<?php mysqli_data_seek($statusData, 0);
                                        while ($s = $statusData->fetch_assoc())
                                            echo "{$s['total']},"; ?>]
                            }]
                        }
                    });
                </script>

            <?php elseif ($page === 'villages'): ?>

                <?php
                $q = $conn->query("
            SELECT 
                v.id, 
                v.village_name,
                COUNT(i.id) As total,
                SUM(i.urgency_level='Critical') As critical,
                COUNT(DISTINCT va.id) As villagers
            FROM tbl_villages v
            LEFT JOIN tbl_incident i ON v.id = i.village_id
            LEFT JOIN tbl_villagers va ON v.id = va.id
            GROUP BY v.id
        ");
                ?>

                <section class="section">
                    <div class="page-title">Villages</div>

                    <div class="filter-bar">
                        <input type="text" id="searchVillage" placeholder="Search village...">
                        <select id="criticalFilter" onchange="filterVillage()">
                            <option value="">All</option>
                            <option value="critical">Has Critical</option>
                            <option value="normal">No Critical</option>
                        </select>
                    </div>

                    <table id="villageTable">
                        <tr>
                            <th>Village</th>
                            <th>Villagers</th>
                            <th>Total Incidents</th>
                            <th>Critical</th>
                            <th>Action</th>
                        </tr>
                        <?php while ($r = $q->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['village_name']) ?></td>
                                <td><?= $r['villagers'] ?></td>
                                <td><?= $r['total'] ?></td>
                                <td class="<?= $r['critical'] > 0 ? 'urgency-critical' : '' ?>">
                                    <?= $r['critical'] ?>
                                </td>
                                <td><a class="btn btn-view" href="?page=villagers&id=<?= $r['id'] ?>">View</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </section>

                <script>
                    function filterVillage() {
                        let text = searchVillage.value.toLowerCase();
                        let filter = criticalFilter.value;
                        document.querySelectorAll("#villageTable tr").forEach((row, i) => {
                            if (i === 0) return;
                            let critical = row.cells[2].innerText !== "0";
                            let show = row.innerText.toLowerCase().includes(text);
                            if (filter === "critical") show = show && critical;
                            if (filter === "normal") show = show && !critical;
                            row.style.display = show ? "" : "none";
                        });
                    }
                </script>

            <?php elseif ($page === 'villagers' && isset($_GET['id'])): ?>
                <?php
                $villageId = intval($_GET['id']);

                $village = $conn->query("
        SELECT village_name FROM tbl_villages WHERE id = $villageId
    ")->fetch_assoc();

                $villagers = $conn->query("
        SELECT * FROM tbl_villagers WHERE village_id = $villageId
    ");
                ?>

                <section class="section">
                    <div class="page-title">
                        Villagers – <?= htmlspecialchars($village['village_name']) ?>
                    </div>

                    <div class="filter-bar">
                        <input type="text" id="searchVillager" placeholder="Search villager...">
                    </div>

                    <?php if ($villagers->num_rows === 0): ?>
                        <p>No villagers registered in this village.</p>
                    <?php else: ?>
                        <table id="villagerTable">
                            <tr>
                                <th>Name</th>
                                <th>IC Number</th>
                                <th>Phone</th>
                                <th>Address</th>
                            </tr>

                            <?php while ($v = $villagers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['name']) ?></td>
                                    <td><?= htmlspecialchars($v['ic_number']) ?></td>
                                    <td><?= htmlspecialchars($v['phone']) ?></td>
                                    <td><?= htmlspecialchars($v['address']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>

                    <br>
                    <a href="?page=villages" class="btn btn-view">
                        ← Back to Villages
                    </a>
                </section>

                <script>
                    const searchVillager = document.getElementById("searchVillager");

                    searchVillager.addEventListener("keyup", () => {
                        let text = searchVillager.value.toLowerCase();
                        document.querySelectorAll("#villagerTable tr").forEach((row, i) => {
                            if (i === 0) return;
                            row.style.display = row.innerText.toLowerCase().includes(text) ? "" : "none";
                        });
                    });
                </script>

            <?php elseif ($page === 'incidents'): ?>

                <?php
                $r = $conn->query("
    SELECT i.*, v.village_name
    FROM tbl_incident i
    JOIN tbl_villages v ON i.village_id = v.id
");
                ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="page-title">All Incidents</h2>

                        <div class="table-actions">
                            <a href="export_incidents.php" class="btn btn-export">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>

                            <button onclick="window.print()" class="btn btn-print">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filter-bar">
                        <input type="text" id="searchIncident" placeholder="Search incident or type...">

                        <select id="typeFilter" onchange="filterIncident()">
                            <option value="">All Types</option>
                            <option value="Flood">Flood</option>
                            <option value="Fire">Fire</option>
                            <option value="Landslide">Landslide</option>
                        </select>

                        <select id="statusFilter" onchange="filterIncident()">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <table id="incidentTable">
                        <tr>
                            <th>Incident</th>
                            <th>Type</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php while ($x = $r->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($x['village_name']) ?></td>
                                <td><?= htmlspecialchars($x['type']) ?></td>
                                <td class="<?= $x['urgency_level'] === 'Critical' ? 'urgency-critical' : '' ?>">
                                    <?= $x['urgency_level'] ?>
                                </td>
                                <td><?= $x['status'] ?></td>
                                <td>
                                    <?php if ($x['status'] !== 'Closed'): ?>
                                        <form method="POST" onsubmit="return confirm('Close this incident?')">
                                            <input type="hidden" name="incident_id" value="<?= $x['id'] ?>">
                                            <button class="btn btn-close" name="close_incident">Close</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-closed">Closed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </section>

                <script>
                    function filterIncident() {
                        let text = searchIncident.value.toLowerCase();
                        let type = typeFilter.value.toLowerCase();
                        let status = statusFilter.value.toLowerCase();

                        document.querySelectorAll("#incidentTable tr").forEach((row, i) => {
                            if (i === 0) return;

                            let content = row.innerText.toLowerCase();
                            let rowType = row.cells[1].innerText.toLowerCase();
                            let rowStatus = row.cells[3].innerText.toLowerCase();

                            let show = content.includes(text);
                            if (type && rowType !== type) show = false;
                            if (status && rowStatus !== status) show = false;

                            row.style.display = show ? "" : "none";
                        });
                    }
                </script>

            <?php elseif ($page === 'analytics'): ?>

                <?php
                $c = $conn->query("SELECT COUNT(*) c FROM tbl_incident WHERE urgency_level='Critical'")
                    ->fetch_assoc()['c'];

                $p = $conn->query("SELECT COUNT(*) c FROM tbl_incident WHERE status='Pending'")
                    ->fetch_assoc()['c'];

                $cl = $conn->query("SELECT COUNT(*) c FROM tbl_incident WHERE status='Closed'")
                    ->fetch_assoc()['c'];

                $total = $c + $p + $cl;
                ?>

                <section class="section">
                    <div class="page-title">Analytics</div>

                    <?php if ($total == 0): ?>
                        <p>No incident data available yet.</p>
                    <?php else: ?>
                        <canvas id="analyticsChart" height="120"></canvas>
                    <?php endif; ?>
                </section>

                <?php if ($total > 0): ?>
                    <script>
                        new Chart(document.getElementById('analyticsChart'), {
                            type: 'bar',
                            data: {
                                labels: ['Critical', 'Pending', 'Closed'],
                                datasets: [{
                                    label: 'Number of Incidents',
                                    data: [<?= $c ?>, <?= $p ?>, <?= $cl ?>],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Incident Status Overview'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>
</body>

</html>