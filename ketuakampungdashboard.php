<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

$page = $_GET['page'] ?? 'overview';

$village_id = (int)$_SESSION['village_id'];

$stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_villages WHERE id = ?");
$stmt->bind_param("i", $village_id);
$stmt->execute();
$stmt->bind_result($village_lat, $village_lng);
$stmt->fetch();
$stmt->close();

$sos_result = $conn->query("SELECT * FROM tbl_sos ORDER BY created_at DESC");

if (isset($_POST['submit_announcement'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if ($title && $description && $type) {

        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE village_id = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->bind_param("i", $village_id);
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

            $stmt->bind_param("sssi", $title, $description, $type, $village_id);

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

        <a class="sidebar-link" href="?page=overview"><i class="fas fa-chart-line"></i> Overview</a>
        <a class="sidebar-link" href="?page=annoucement"><i class="fas fa-bell"></i> Make Annoucement</a>
        <a class="sidebar-link" href="?page=incident-map"><i class="fas fa-map"></i> Incident Map</a>
        <a class="sidebar-link" href="?page=history"><i class="fas fa-history"></i> History</a>
        <a class="sidebar-link" href="?page=household-level-record"><i class="fas fa-house"></i> Household-level record</a>
        <a href="registerpage.php?role=<?= $_SESSION['role'] ?>&id=<?= $_SESSION['village_id'] ?>"><i class="fas fa-user"></i> Register Villager</a>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')"> <i class="fas fa-right-from-bracket"></i> Logout </a>
    </div>

    <!--  Right Content -->
    <div class="right-content">
        <?php if ($page === 'overview'): ?>
            <div id="dashboard" data-village="<?= $_SESSION['village_id'] ?? 0 ?>"></div>
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
                            $stmt->bind_param("i", $village_id);
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
                    $urgency1 = "Pending";
                    $urgency2 = "In Progress";

                    $stmt = $conn->prepare("SELECT * FROM tbl_incidents WHERE village_id = ? AND (urgency_level = ? OR urgency_level = ?) ORDER BY date_created DESC");
                    $stmt->bind_param("iss", $village_id, $urgency1, $urgency2);
                    $stmt->execute();
                    $inc_result = $stmt->get_result();
                    $inc_result->data_seek(0);
                    if ($inc_result->num_rows > 0):
                        while ($row = $inc_result->fetch_assoc()):
                            $createdTime = strtotime($row['date_created']);
                            $isNew = (time() - $createdTime) <= 24 * 60 * 60;
                            $urgencyClass = '';
                            switch (strtolower($row['urgency_level'])) {
                                case 'in progress':
                                    $urgencyClass = 'urgency-inprogress';
                                    break;
                                case 'pending':
                                    $urgencyClass = 'urgency-pending';
                                    break;
                                default:
                                    $urgencyClass = 'urgency-pending';
                            }

                            // Fetch villager name
                            $stmtVillager = $conn->prepare("SELECT name FROM tbl_villagers WHERE id = ?");
                            $stmtVillager->bind_param("i", $row['villager_id']);
                            $stmtVillager->execute();
                            $stmtVillager->bind_result($villagerName);
                            $stmtVillager->fetch();
                            $stmtVillager->close();
                    ?>
                            <div class="report-item"
                                data-id="<?= htmlspecialchars($row['id']) ?>"
                                data-type="<?= htmlspecialchars($row['type']) ?>"
                                data-description="<?= htmlspecialchars($row['description']) ?>"
                                data-location="<?= htmlspecialchars($row['location']) ?>"
                                data-date="<?= $row['date_created'] ?>"
                                data-image="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/no-image.png' ?>"
                                data-villager="<?= htmlspecialchars($villagerName) ?>"
                                data-status="<?= strtolower($row['urgency_level']) ?>">
                                <img src="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/no-image.png' ?>" alt="Incident Image">
                                <div class="report-content">
                                    <p><strong>Type:</strong> <?= htmlspecialchars($row['type']) ?><?php if ($isNew) echo ' <span class="new-badge">NEW</span>'; ?></p>
                                    <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                                    <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                                    <p><small>Reported at: <?= $row['date_created'] ?></small></p>
                                </div>
                                <div class="urgency-badge <?= $urgencyClass ?>"><?= ucfirst($row['urgency_level']) ?></div>
                            </div>
                        <?php endwhile;
                    else: ?>
                        <div class="report-item">No incidents found.</div>
                    <?php endif; ?>
                </div>

                <!-- Modal HTML -->
                <div id="incidentModal" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h2 id="modalType"></h2>
                        <p><strong>Description:</strong> <span id="modalDescription"></span></p>
                        <p><strong>Location:</strong> <span id="modalLocation"></span></p>
                        <p><strong>Posted by:</strong> <span id="modalVillager"></span></p>
                        <p><strong>Reported At:</strong> <span id="modalDate"></span></p>
                        <img id="modalImage" src="" alt="Incident Image" style="width:100%; max-height:300px; object-fit:cover;">
                        <div class="modal-buttons">
                            <button id="approveBtn" class="approve-btn">Approve</button>
                            <button id="rejectBtn" class="reject-btn">Reject</button>
                        </div>
                    </div>
                </div>
                <!-- SOS List -->
                <div class="report-list" id="sos">
                    <?php
                    $sos_result->data_seek(0); // reset pointer if needed
                    if ($sos_result->num_rows > 0):
                        while ($row = $sos_result->fetch_assoc()):
                    ?>
                            <div class="report-item">
                                <?= htmlspecialchars($row['message']) ?><br>
                                <small><?= $row['created_at'] ?></small>
                            </div>
                        <?php endwhile;
                    else: ?>
                        <div class="report-item">No SOS alerts.</div>
                    <?php endif; ?>
                </div>
            </div>
            <script type="text/javascript" src="js/weather.js"></script>
            <script type="text/javascript" src="js/modal.js"></script>
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
        <?php endif ?>

        <?php if ($page == 'annoucement'): ?>
            <h1>Make Annoucement</h1>
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

        <?php if ($page == 'incident-map'): ?>
            <h1>Incident Map</h1>
            <div id="map" style="height:500px; width:100%; border-radius:10px;"></div>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // create map
                    const villageLat = <?php echo $village_lat ?? 4.2105; ?>;
                    const villageLng = <?php echo $village_lng ?? 101.9758; ?>;
                    const map = L.map('map').setView([villageLat, villageLng], 17); // ketau 17, penghulu 14, pejabat 8

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map);

                    // colored alert level
                    const redIcon = new L.Icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    const yellowIcon = new L.Icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    // incident current no latitude and longitude
                    const incidents = [{
                            type: "Flood",
                            level: "critical",
                            lat: 3.1390,
                            lng: 101.6869,
                            description: "Severe flood reported near river"
                        },
                        {
                            type: "Fire",
                            level: "pending",
                            lat: 5.4141,
                            lng: 100.3288,
                            description: "Fire reported, awaiting verification"
                        },
                        {
                            type: "Landslide",
                            level: "critical",
                            lat: 2.1896,
                            lng: 102.2501,
                            description: "Road completely blocked"
                        }
                    ];

                    // pin or markers
                    incidents.forEach(i => {

                        let icon = yellowIcon;
                        if (i.level === "critical") icon = redIcon;

                        L.marker([i.lat, i.lng], {
                                icon
                            })
                            .addTo(map)
                            .bindPopup(`
                <strong>${i.type}</strong><br>
                Status: <b>${i.level.toUpperCase()}</b><br>
                ${i.description}
            `);
                    });
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 300);

                });
            </script>
        <?php endif ?>

        <?php if ($page == 'history'): ?>
            <h1>Incident History</h1>
            <!-- Incident List -->
            <div class="report-list active" id="incident">
                <?php
                $urgency1 = "Reject";
                $urgency2 = "Resolved";

                $stmt = $conn->prepare("SELECT * FROM tbl_incidents WHERE village_id = ? AND (urgency_level = ? OR urgency_level = ?) ORDER BY date_created DESC");
                $stmt->bind_param("iss", $village_id, $urgency1, $urgency2);
                $stmt->execute();
                $inc_result = $stmt->get_result();

                $inc_result->data_seek(0);
                if ($inc_result->num_rows > 0):
                    while ($row = $inc_result->fetch_assoc()):
                        $urgencyClass = '';
                        switch (strtolower($row['urgency_level'])) {
                            case 'reject':
                                $urgencyClass = 'urgency-reject';
                                break;
                            case 'resolved':
                                $urgencyClass = 'urgency-resolved';
                                break;
                            default:
                                $urgencyClass = 'urgency-pending';
                        }
                ?>
                        <div class="report-item">
                            <img src="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/no-image.png' ?>" alt="Incident Image">
                            <div class="report-content">
                                <p><strong>Type:</strong> <?= htmlspecialchars($row['type']) ?></p>
                                <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                                <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                                <p><small>Reported at: <?= $row['date_created'] ?></small></p>
                            </div>
                            <div class="urgency-badge <?= $urgencyClass ?>"><?= ucfirst($row['urgency_level']) ?></div>
                        </div>
                    <?php
                    endwhile;
                else: ?>
                    <div class="report-item">No incidents found.</div>
                <?php endif; ?>
            </div>


        <?php endif ?>

        <?php if ($page == 'household-level-record'): ?>
            <h1></h1>
        <?php endif ?>
    </div>
    <?php include_once('includes/footer.php'); ?>
    <script type="text/javascript" src="js/sidebar.js"></script>

</body>

</html>