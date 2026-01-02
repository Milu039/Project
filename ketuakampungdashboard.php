<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

$page = $_GET['page'] ?? 'overview';

$area_id = (int)$_SESSION['area_id'];

if (isset($_POST['submit_announcement'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if ($title && $description && $type) {

        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE village_id = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->bind_param("i", $area_id);
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

            $stmt->bind_param("sssi", $title, $description, $type, $area_id);

            if ($stmt->execute()) {
                $success = "Announcement published successfully.";
            } else {
                $error = "Failed to publish announcement.";
            }

            $stmt->close();
        }
    } else {
        $error = "All fields are required.";
    }
}

if (isset($_POST['submit_household'])) {
    $email = trim($_POST['email']);
    $family_group = trim($_POST['family_group']);
    $family_member = (int) $_POST['family_members'];
    $sara = trim($_POST['sara']);

    // Check if email exists in tbl_villagers
    $check = $conn->prepare("SELECT id FROM tbl_villagers WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    $check->bind_result($villager_id);
    $check->fetch();

    if ($check->num_rows > 0) {
        // Insert into tbl_households
        $stmt = $conn->prepare("INSERT INTO tbl_households (villager_id, family_group, family_member, sara) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $villager_id, $family_group, $family_member, $sara);

        if ($stmt->execute()) {
            // Get the last inserted household ID
            $household_id = $stmt->insert_id;

            // Update tbl_villagers with household_id
            $update = $conn->prepare("UPDATE tbl_villagers SET household_id = ? WHERE id = ?");
            $update->bind_param("ii", $household_id, $villager_id);
            if ($update->execute()) {
                $success = "Household added and villager updated successfully!";
            } else {
                error_log($update->error);
                $error = "Household added but failed to update villager";
            }
            $update->close();
        } else {
            error_log($stmt->error);
            $error = "Error inserting household";
        }
        $stmt->close();
    } else {
        $error = "Error: The email does not belong to a registered villager.";
    }

    $check->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Ketua Kampung Dashboard | Digital Village Dashboard Management</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fa-solid fa-bars"></i>
    </button>
    <!--  Left Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img">
            <p>DVMD</p>
        </div>
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

        <a class="sidebar-link" href="?page=overview"><i class="fas fa-chart-line"></i> Overview</a>
        <a class="sidebar-link" href="?page=announcement"><i class="fas fa-bell"></i> Make Announcement</a>
        <a class="sidebar-link" href="?page=map"><i class="fas fa-map"></i> Map</a>
        <a class="sidebar-link" href="?page=history"><i class="fas fa-history"></i> History</a>
        <a class="sidebar-link" href="?page=household-level-record"><i class="fas fa-house"></i> Household-level record</a>
        <a href="registerpage.php"><i class="fas fa-user"></i> Register Villager</a>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')"> <i class="fas fa-right-from-bracket"></i> Logout </a>
    </div>

    <!--  Right Content -->
    <div class="right-content">
        <?php if ($page === 'overview'): ?>
            <div id="dashboard"
                data-area-type="<?= $_SESSION['role'] ?>"
                data-area-id="<?= $_SESSION['area_id'] ?>">
            </div>
            <script type="text/javascript" src="js/weather.js"></script>
            <h1>Ketua Kampung Dashboard</h1>
            <!-- Top Stats -->
            <div class="top-stats">
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="stat-content">
                        <p>Villagers</p>
                        <h2 id="villagerCount">
                            <?php
                            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tbl_villagers WHERE village_id = ?");
                            $stmt->bind_param("i", $area_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            $totalVillagers = $row['total'] ?? 0;

                            echo $totalVillagers;
                            ?></h2>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon" id="weatherIcon"></div>
                    <div class="stat-content">
                        <p>Weather</p>
                        <h2 id="weatherTemp">--°C</h2>
                        <div id="weatherDesc"></div>
                    </div>
                </div>
            </div>
            <!-- Reports Section -->
            <div class="reports">
                <div class="report-tabs">
                    <button class="tab-btn active" data-target="incident">Incident Report</button>
                    <button class="tab-btn" data-target="sos">SOS Report</button>
                </div>
                <!-- Incident List -->
                <div class="report-list active" id="incident">
                    <?php
                    $stmt = $conn->prepare("
                    SELECT * FROM tbl_incidents 
                    WHERE village_id = ? 
                    AND status IN ('Pending', 'In Progress', 'Progressing') 
                    ORDER BY date_created DESC
                    ");
                    $stmt->bind_param("i", $area_id);
                    $stmt->execute();
                    $resultSet = $stmt->get_result();
                    ?>
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Urgency Level</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php if ($resultSet->num_rows > 0):
                            while ($result = $resultSet->fetch_assoc()):
                                $createdTime = strtotime($result['date_created']);
                                $isNew = (time() - $createdTime) <= 24 * 60 * 60;
                        ?>
                                <tr>
                                    <td style="text-align: left;"><?= htmlspecialchars($result['type']) ?><?php if ($isNew) echo ' <span class="new-badge">NEW</span>'; ?></td>
                                    <td><?= htmlspecialchars($result['latitude']) ?>, <?= htmlspecialchars($result['longitude']) ?></td>

                                    <td>
                                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                                            <?= htmlspecialchars($result['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('d M Y', strtotime($result['date_created'])) ?></td>

                                    <td>
                                        <span class="badge status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>">
                                            <?= htmlspecialchars($result['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="btn btn-view" href="?page=incident_view&id=<?= $result['id'] ?>">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- SOS List -->
                <div class="report-list" id="sos">
                    <?php
                    $stmt = $conn->prepare("
                    SELECT * FROM tbl_sos 
                    WHERE village_id = ? 
                    AND status IN ('Pending', 'In Progress', 'Progressing') 
                    ORDER BY created_at DESC
                    ");
                    $stmt->bind_param('i', $area_id);
                    $stmt->execute();
                    $resultSet = $stmt->get_result()
                    ?>
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Urgency Level</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php if ($resultSet->num_rows > 0):
                            while ($result = $resultSet->fetch_assoc()):
                                $createdTime = strtotime($result['created_at']);
                                $isNew = (time() - $createdTime) <= 24 * 60 * 60;
                        ?>
                                <tr>
                                    <td style="text-align: left;"><?= htmlspecialchars($result['type']) ?><?php if ($isNew) echo ' <span class="new-badge">NEW</span>'; ?></td>
                                    <td><?= htmlspecialchars($result['latitude']) ?>, <?= htmlspecialchars($result['longitude']) ?></td>

                                    <td>
                                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                                            <?= htmlspecialchars($result['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('d M Y', strtotime($result['created_at'])) ?></td>

                                    <td>
                                        <span class="badge status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>">
                                            <?= htmlspecialchars($result['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="btn btn-view" href="?page=sos_view&id=<?= $result['id'] ?>">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        <?php elseif ($page === 'incident_view' && isset($_GET['id'])): ?>
            <?php
            $incidentId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT i.*, v.name FROM tbl_incidents i JOIN tbl_villagers v ON i.villager_id = v.id WHERE i.id = ?");
            $stmt->bind_param("i", $incidentId);
            $stmt->execute();
            $incident = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$incident) {
                echo "<div class='section'><p>Incident not found.</p> <a href='?page=overview' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=overview" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($incident['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($incident['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($incident['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($incident['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($incident['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($incident['name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($incident['latitude']) ?>, <?= htmlspecialchars($incident['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($incident['date_created'])) ?></p>
                            </div>
                        </div>

                        <?php if ($incident['status'] !== 'In Progress' && $incident['status'] !== 'Progressing'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/incident/update_incident.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                                    <button type="submit" name="incident_action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>

                                    <button type="submit" name="incident_action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times-circle"></i> Reject
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

        <?php elseif ($page === 'sos_view' && isset($_GET['id'])): ?>
            <?php
            $sosId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT so.*, v.name FROM tbl_sos so JOIN tbl_villagers v ON so.villager_id = v.id WHERE so.id = ?");
            $stmt->bind_param("i", $sosId);
            $stmt->execute();
            $sos = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$sos) {
                echo "<div class='section'><p>SOS not found.</p> <a href='?page=overview' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=overview" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($sos['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($sos['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($sos['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($sos['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($sos['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($sos['name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($sos['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($sos['latitude']) ?>, <?= htmlspecialchars($sos['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($sos['created_at'])) ?></p>
                            </div>
                        </div>

                        <?php if ($sos['status'] !== 'In Progress' && $sos['status'] !== 'Progressing'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/sos/update_sos.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $sos['id'] ?>">
                                    <button type="submit" name="sos_action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>

                                    <button type="submit" name="sos_action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="image-col">
                        <?php if (!empty($sos['image'])): ?>
                            <div class="image-wrapper">
                                <img src="uploads/<?= htmlspecialchars($sos['image']) ?>" alt="Evidence Photo">
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
        <?php endif ?>

        <?php if ($page == 'announcement'): ?>
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
                    <input type="text" name="title" required
                        placeholder="Enter announcement title">
                </div>
                <!-- DESCRIPTION -->
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5" required
                        placeholder="Enter announcement details"></textarea>
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
        <?php endif ?>

        <?php if ($page == 'map'): ?>
            <h1>Report Map</h1>
            <div id="map" style="height:500px; width:100%; border-radius:10px;"></div>
            <script src="js/reports.js"></script>
        <?php endif ?>

        <?php if ($page == 'history'): ?>
            <h1>Report History</h1>
            <div class="reports">
                <div class="report-tabs">
                    <button class="tab-btn active" data-target="incident">Incident Report</button>
                    <button class="tab-btn" data-target="sos">SOS Report</button>
                </div>
                <!-- Incident List -->
                <div class="report-list active" id="incident">
                    <?php
                    $stmt = $conn->prepare("
                    SELECT * FROM tbl_incidents 
                    WHERE village_id = ? 
                    AND status IN ('Resolved', 'Reject') 
                    ORDER BY date_created DESC
                    ");
                    $stmt->bind_param('i', $area_id);
                    $stmt->execute();
                    $resultSet = $stmt->get_result();
                    ?>
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Urgency Level</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php if ($resultSet->num_rows > 0):
                            while ($result = $resultSet->fetch_assoc()):
                        ?>
                                <tr>
                                    <td style="text-align: left;"><?= htmlspecialchars($result['type']) ?></td>
                                    <td><?= htmlspecialchars($result['latitude']) ?>, <?= htmlspecialchars($result['longitude']) ?></td>

                                    <td>
                                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                                            <?= htmlspecialchars($result['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('d M Y', strtotime($result['date_created'])) ?></td>

                                    <td>
                                        <span class="badge status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>">
                                            <?= htmlspecialchars($result['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="btn btn-view" href="?page=incident_history&id=<?= $result['id'] ?>">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- SOS List -->
                <div class="report-list" id="sos">
                    <?php
                    $stmt = $conn->prepare("
                    SELECT * FROM tbl_sos 
                    WHERE village_id = ? 
                    AND status IN ('Resolved', 'Reject') 
                    ORDER BY created_at DESC
                    ");
                    $stmt->bind_param('i', $area_id);
                    $stmt->execute();
                    $resultSet = $stmt->get_result();
                    ?>
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Urgency Level</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php if ($resultSet->num_rows > 0):
                            while ($result = $resultSet->fetch_assoc()):
                        ?>
                                <tr>
                                    <td style="text-align: left;"><?= htmlspecialchars($result['type']) ?></td>
                                    <td><?= htmlspecialchars($result['latitude']) ?>, <?= htmlspecialchars($result['longitude']) ?></td>

                                    <td>
                                        <span class="badge urgency <?= strtolower($result['urgency_level']) ?>">
                                            <?= htmlspecialchars($result['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('d M Y', strtotime($result['created_at'])) ?></td>

                                    <td>
                                        <span class="badge status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>">
                                            <?= htmlspecialchars($result['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="btn btn-view" href="?page=sos_history&id=<?= $result['id'] ?>">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        <?php elseif ($page === 'incident_history' && isset($_GET['id'])): ?>
            <?php
            $incidentId = intval($_GET['id']);
            $stmt = $conn->prepare("
            SELECT i.*, v.name
                FROM tbl_incidents i
                JOIN tbl_villagers v ON i.villager_id = v.id
                WHERE i.id = ?
                ");
            $stmt->bind_param('i', $incidentId);
            $stmt->execute();
            $incident = $stmt->get_result()->fetch_assoc();

            if (!$incident) {
                echo "<div class='section'><p>Incident not found.</p> <a href='?page=history' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=history" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($incident['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($incident['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($incident['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($incident['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($incident['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($incident['name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($incident['latitude']) ?>, <?= htmlspecialchars($incident['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($incident['date_created'])) ?></p>
                            </div>
                        </div>

                        <div style="margin-top: 30px;">
                            <form method="POST" action="management/incident/delete_incident.php">
                                <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                                <button type="submit" name="incident_action" value="delete" class="btn btn-delete">
                                    <i class="fas fa-trash-can"></i> Delete
                                </button>
                            </form>
                        </div>
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

        <?php elseif ($page === 'sos_history' && isset($_GET['id'])): ?>
            <?php
            $sosId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT s.*, v.name
                FROM tbl_sos s
                JOIN tbl_villagers v ON s.villager_id = v.id
                WHERE s.id = ?");
            $stmt->bind_param('i', $sosId);
            $stmt->execute();
            $sos = $stmt->get_result()->fetch_assoc();

            if (!$sos) {
                echo "<div class='section'><p>SOS not found.</p> <a href='?page=history' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=history" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($sos['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($sos['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($sos['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($sos['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($sos['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($sos['name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($sos['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($sos['latitude']) ?>, <?= htmlspecialchars($sos['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($sos['created_at'])) ?></p>
                            </div>
                        </div>

                        <?php if ($sos['status'] !== 'In Progress' && $sos['status'] !== 'Progressing'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/sos/delete_sos.php">
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
                        <?php if (!empty($sos['image'])): ?>
                            <div class="image-wrapper">
                                <img src="uploads/<?= htmlspecialchars($sos['image']) ?>" alt="Evidence Photo">
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
        <?php endif ?>

        <?php if ($page == 'household-level-record'): ?>
            <h1>Household records</h1>
            <a href="?page=inserthousehold" class="btn btn-insert" style="width:100px;text-align:center;">Insert</a>
            <?php
            $stmt = $conn->prepare("
                    SELECT h.*, v.name, v.address FROM tbl_households h
                    JOIN tbl_villagers v ON h.villager_id = v.id
                    ");
            $stmt->execute();
            $resultSet = $stmt->get_result();
            ?>
            <table>
                <tr>
                    <th>Villager's Name</th>
                    <th>Family Group</th>
                    <th>Family Member</th>
                    <th>Address</th>
                    <th>SARA</th>
                </tr>
                <?php if ($resultSet->num_rows > 0):
                    while ($result = $resultSet->fetch_assoc()):
                ?>
                        <tr>
                            <td style="text-align: left;"><?= htmlspecialchars($result['name']) ?></td>
                            <td><?= htmlspecialchars($result['family_group']) ?></td>
                            <td><?= htmlspecialchars($result['family_member']) ?></td>
                            <td><?= htmlspecialchars($result['address']) ?></td>
                            <td><?= htmlspecialchars($result['sara']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?= "<tr><td colspan='6' style='text-align:center;'>No reports found.</td></tr>"; ?>
                <?php endif; ?>
            </table>

        <?php elseif ($page === 'inserthousehold'): ?>
            <a href="?page=household-level-record" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <h1>Insert Household Records</h1>
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
            <form method="post" class="household-form" action="">
                <!-- EMAIL -->
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required
                        placeholder="Enter villager's email">
                </div>

                <!-- FAMILY GROUP -->
                <div class="form-group">
                    <label>Family Group</label>
                    <select name="family_group" required>
                        <option value="">-- Select Family Group --</option>
                        <option value="B40">B40</option>
                        <option value="M40">M40</option>
                        <option value="T20">T20</option>
                    </select>
                </div>

                <!-- FAMILY MEMBERS -->
                <div class="form-group">
                    <label>Number of Family Members</label>
                    <input type="number" name="family_members" min="1" required
                        placeholder="Enter number of family members">
                </div>

                <!-- SARA -->
                <div class="form-group">
                    <label>SARA</label>
                    <input type="text" name="sara" required
                        placeholder="Enter SARA information">
                </div>

                <!-- SUBMIT -->
                <button type="submit" name="submit_household">Submit Household</button>
            </form>
        <?php endif ?>
    </div>
    <?php include_once('includes/footer.php'); ?>
    <script type="text/javascript" src="js/sidebar.js"></script>
    <script>
        const tabs = document.querySelectorAll('.tab-btn');
        const lists = document.querySelectorAll('.report-list');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                lists.forEach(list => list.classList.remove('active'));

                // Activate clicked tab and target list
                tab.classList.add('active');
                document.getElementById(tab.dataset.target).classList.add('active');
            });
        });
    </script>
</body>

</html>