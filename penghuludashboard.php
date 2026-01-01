<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

$page = $_GET['page'] ?? 'dashboard';

function isActive($target) {
    global $page;
    return $page === $target ? 'active' : '';
}
?>

<!-- mysql database backend-->
<?php
include('includes/dbconnect.php');

function getTotalVillagers($conn) {
    $sql = "SELECT COUNT(*) AS total FROM tbl_villages";
    $result = $conn->query($sql);
    return $result->fetch_assoc()['total'] ?? 0;
}

function getIncidents($conn, $search, $village) {
    $sql = "
        SELECT description, type, urgency_level, status
        FROM tbl_incidents
        WHERE 1
    ";

    $params = [];
    $types  = '';

    if ($search !== '') {
        $sql .= " AND (description LIKE ? OR type LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'ss';
    }

    if ($village !== '') {
        $sql .= " AND village_id = ?";
        $params[] = $village;
        $types .= 'i';
    }

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();

    return $stmt->get_result();
}

function getVillages($conn) {
    $sql = "SELECT id, village_name FROM tbl_villages ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->get_result();
}
?>

<?php
$totalVillagers = getTotalVillagers($conn);
$villagesResult = getVillages($conn);
$selectedVillage = $_GET['village'] ?? '';
$search  = $_GET['search'] ?? '';
$village = $_GET['village'] ?? '';

if ($page === 'incident') {
    $incidentResults = getIncidents($conn, $search, $village);
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
                    $roleNames = ['0' => 'Ketua Kampung', '1' => 'Penghulu', '2' => 'Pejabat Daerah'];
                    echo $roleNames[$_SESSION['role']] ?? 'Unknown';
                    ?>
                </div>
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
        <a href="?page=register" class="<?= isActive('register') ?>">
            <i class="fa-solid fa-user-plus"></i> Register Account
        </a>
        <a href="logout.php" onclick="return confirm('Logout?')">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <!-- right content-->
    <main class="main-content">

        <?php if ($page === 'dashboard'): ?>

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
                        <h2><?= $totalVillagers?></h2>
                    </div>
                </div>
            </div>

            <!-- VILLAGE CARDS -->
            <div class="village-grid">

                <!-- Village 1 -->
                <div class="village-card">
                    <div class="village-header">
                        <div>
                            <h3>Greenwood Village</h3>
                            <small>Village ID: 1</small>
                        </div>
                        <div class="icon-badge">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                    </div>

                    <div class="village-stats">
                        <span>Total Villagers</span>
                        <b>245</b>
                    </div>
                </div>

                <!-- Village 2 -->
                <div class="village-card">
                    <div class="village-header">
                        <div>
                            <h3>Riverside Village</h3>
                            <small>Village ID: 2</small>
                        </div>
                        <div class="icon-badge">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                    </div>

                    <div class="village-stats">
                        <span>Total Villagers</span>
                        <b>312</b>
                    </div>
                </div>

            </div>

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
                        <button class="btn-primary" type="button">Report</button>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($incidentResults->num_rows > 0): ?>
                            <?php while ($row = $incidentResults->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td><?= htmlspecialchars($row['type']) ?></td>
                                    <td><?= htmlspecialchars($row['urgency_level']) ?></td>
                                    <td>
                                        <button class="btn-outline">View</button>
                                    </td>
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
                <select class="village-select">
                    <option>All Villages</option>
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
                        <!-- Empty State -->
                        <tr>
                            <td colspan="6" class="empty-state">
                                No SOS reports available
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'household'): ?>

            <div class="page-header">
                <div>
                    <h1>Household-level records</h1>
                    <p>Manage the household records of the villagers</p>
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

        <?php elseif ($page === 'register'): ?>

            <h1>NADMA Alerts</h1>
            <p>Read-only disaster information</p>
        <?php else: ?>

            <h1>Page not found</h1>

        <?php endif; ?>

    </main>
    
    <?php include_once('includes/footer.php'); ?>

</body>
</html>