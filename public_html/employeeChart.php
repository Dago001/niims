<?php 
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

require_once ('db/db.php');

// Robust case-insensitive sidebar inclusion with fallback
$user_role_clean = strtolower(trim($_SESSION['role'] ?? ''));

if ($user_role_clean === 'superadmin' || $user_role_clean === 'super_admin' || $user_role_clean === 'super admin') {
    include 'sidebar-superadmin.php';
} elseif ($user_role_clean === 'admin') {
    include 'sidebar-admin.php';
} elseif ($user_role_clean === 'supervisor') {
    include 'sidebar-supervisor.php';
} elseif ($user_role_clean === 'dataentry' || $user_role_clean === 'data_entry' || $user_role_clean === 'data entry') {
    include 'sidebar-dataentry.php';
} elseif ($user_role_clean === 'report' || $user_role_clean === 'view') {
    include 'sidebar-view.php';
} elseif ($user_role_clean === 'cgi') {
    include 'sidebar-cgi.php';
} elseif (file_exists('sidebar-admin.php')) {
    include 'sidebar-admin.php';
} else {
    include 'sidebar.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// ============================================================================
// EFFICIENT ANALYTICS QUERIES
// ============================================================================

// 1. Overall Totals & Gender Counts
$tot_res = mysqli_query($conn, "SELECT 
    COUNT(*) as total_officers,
    SUM(CASE WHEN LOWER(TRIM(gender)) = 'male' THEN 1 ELSE 0 END) as male_count,
    SUM(CASE WHEN LOWER(TRIM(gender)) = 'female' THEN 1 ELSE 0 END) as female_count
FROM tbl_emppersonal");
$tot_row = mysqli_fetch_assoc($tot_res);

$numb  = intval($tot_row['total_officers'] ?? 0);
$numb2 = intval($tot_row['male_count'] ?? 0);
$numb3 = intval($tot_row['female_count'] ?? 0);

// Total System Users
$user_res = mysqli_query($conn, "SELECT COUNT(*) as total_users FROM tbl_users");
$user_row = mysqli_fetch_assoc($user_res);
$numb4 = intval($user_row['total_users'] ?? 0);

// 2. Rank & Gender Breakdown Query
$rank_query = "SELECT 
    emp2.currentRank,
    COUNT(*) as total_in_rank,
    SUM(CASE WHEN LOWER(TRIM(emp.gender)) = 'male' THEN 1 ELSE 0 END) as male_in_rank,
    SUM(CASE WHEN LOWER(TRIM(emp.gender)) = 'female' THEN 1 ELSE 0 END) as female_in_rank
FROM tbl_employment emp2
LEFT JOIN tbl_emppersonal emp ON emp2.serviceNo = emp.serviceNo
WHERE emp2.currentRank IS NOT NULL AND emp2.currentRank != ''
GROUP BY emp2.currentRank
ORDER BY 
    CASE emp2.currentRank
        WHEN 'Comptroller General' THEN 1
        WHEN 'Dep. Comptroller General' THEN 2
        WHEN 'Asst. Comptroller General' THEN 3
        WHEN 'Comptroller' THEN 4
        WHEN 'Deputy Comptroller' THEN 5
        WHEN 'Assistant Comptroller' THEN 6
        WHEN 'Chief Superintendent' THEN 7
        WHEN 'Superintendent' THEN 8
        WHEN 'Deputy Superintendent' THEN 9
        WHEN 'Asst. Superintendent 1' THEN 10
        WHEN 'Asst. Superintendent 2' THEN 11
        WHEN 'Inspector' THEN 12        
        WHEN 'Assistant Inspector' THEN 13
        WHEN 'Chief Immigration Asst.' THEN 14
        WHEN 'Senior Immigration Asst.' THEN 15
        WHEN 'Immigration Asst. 1' THEN 16
        WHEN 'Immigration Asst. 2' THEN 17
        WHEN 'Immigration Asst. 3' THEN 18
        ELSE 19
    END ASC, total_in_rank DESC";
$rank_res = mysqli_query($conn, $rank_query);

$rank_labels = [];
$rank_totals = [];
$rank_males  = [];
$rank_females = [];
$rank_rows   = [];

if ($rank_res) {
    while ($r = mysqli_fetch_assoc($rank_res)) {
        $rank_labels[] = $r['currentRank'];
        $rank_totals[] = intval($r['total_in_rank']);
        $rank_males[]  = intval($r['male_in_rank']);
        $rank_females[]= intval($r['female_in_rank']);
        $rank_rows[]   = $r;
    }
}

// 3. Blood Type Distribution
$blood_res = mysqli_query($conn, "SELECT bloodType, COUNT(*) as cnt FROM tbl_emppersonal WHERE bloodType IS NOT NULL AND bloodType != '' GROUP BY bloodType ORDER BY cnt DESC");
$blood_labels = [];
$blood_counts = [];
if ($blood_res) {
    while ($b = mysqli_fetch_assoc($blood_res)) {
        $blood_labels[] = $b['bloodType'];
        $blood_counts[] = intval($b['cnt']);
    }
}

// 4. Directorate Distribution
$dir_res = mysqli_query($conn, "SELECT directorate, COUNT(*) as cnt FROM tbl_employment WHERE directorate IS NOT NULL AND directorate != '' GROUP BY directorate ORDER BY cnt DESC LIMIT 6");
$dir_labels = [];
$dir_counts = [];
if ($dir_res) {
    while ($d = mysqli_fetch_assoc($dir_res)) {
        $dir_labels[] = $d['directorate'];
        $dir_counts[] = intval($d['cnt']);
    }
}
?>

<!-- page content -->
<div class="right_col" role="main">
  <div class="">
    <div class="page-title">
      <div class="title_left">
        <h3>Personnel Analytics & Statistical Reports</h3>
      </div>
    </div>
    <div class="clearfix"></div>

    <!-- TOP SUMMARY TILES -->
    <div class="row tile_count" style="margin-bottom: 10px;">
      <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
        <span class="count_top"><i class="fa fa-users"></i> Total Enrolled Officers</span>
        <div class="count text-primary" style="font-weight: 700; font-size: 32px;"><?php echo number_format($numb); ?></div>
        <span class="count_bottom"><i class="green">100%</i> Enrolled Officers</span>
      </div>
      <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
        <span class="count_top"><i class="fa fa-male"></i> Male Officers</span>
        <div class="count green" style="font-weight: 700; font-size: 32px;"><?php echo number_format($numb2); ?></div>
        <span class="count_bottom"><i class="green"><?php echo $numb > 0 ? round(($numb2/$numb)*100, 1) : 0; ?>%</i> of total workforce</span>
      </div>
      <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
        <span class="count_top"><i class="fa fa-female"></i> Female Officers</span>
        <div class="count" style="color: #3498db; font-weight: 700; font-size: 32px;"><?php echo number_format($numb3); ?></div>
        <span class="count_bottom"><i class="blue"><?php echo $numb > 0 ? round(($numb3/$numb)*100, 1) : 0; ?>%</i> of total workforce</span>
      </div>
      <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
        <span class="count_top"><i class="fa fa-user-shield"></i> Active System Users</span>
        <div class="count" style="color: #9b59b6; font-weight: 700; font-size: 32px;"><?php echo number_format($numb4); ?></div>
        <span class="count_bottom"><i class="purple">System Accounts</i></span>
      </div>
    </div>

    <!-- ROW 1: CHARTS -->
    <div class="row">
      <!-- Gender Distribution Pie Chart -->
      <div class="col-md-6 col-sm-6 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <h2>Gender Distribution <small>Male vs Female</small></h2>
            <div class="clearfix"></div>
          </div>
          <div class="x_content" style="text-align: center;">
            <div style="max-width: 320px; margin: 0 auto;">
              <canvas id="genderChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Blood Type Distribution Bar Chart -->
      <div class="col-md-6 col-sm-6 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <h2>Blood Type Distribution <small>Medical Profile</small></h2>
            <div class="clearfix"></div>
          </div>
          <div class="x_content">
            <canvas id="bloodChart" style="height: 250px;"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- ROW 2: RANK & DIRECTORATE CHARTS -->
    <div class="row">
      <!-- Rank Distribution Horizontal Bar Chart -->
      <div class="col-md-8 col-sm-12 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <h2>Workforce Rank Distribution <small>Personnel by Rank</small></h2>
            <div class="clearfix"></div>
          </div>
          <div class="x_content">
            <canvas id="rankChart" style="height: 280px;"></canvas>
          </div>
        </div>
      </div>

      <!-- Directorate Breakdown Pie Chart -->
      <div class="col-md-4 col-sm-12 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <h2>Directorate Breakdown <small>Top Directorates</small></h2>
            <div class="clearfix"></div>
          </div>
          <div class="x_content">
            <canvas id="dirChart" style="height: 280px;"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- ROW 3: DETAILED RANK STATISTICS TABLE -->
    <div class="row">
      <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <h2>Detailed Rank & Gender Breakdown Table</h2>
            <div class="clearfix"></div>
          </div>
          <div class="x_content">
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead>
                  <tr style="background: #2A3F54; color: #ffffff;">
                    <th>#</th>
                    <th>Rank Title</th>
                    <th class="text-center">Male Personnel</th>
                    <th class="text-center">Female Personnel</th>
                    <th class="text-center">Total Personnel</th>
                    <th class="text-center">% Share of Workforce</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $sr = 1;
                  if (!empty($rank_rows)):
                    foreach ($rank_rows as $row):
                      $r_total = intval($row['total_in_rank']);
                      $r_male  = intval($row['male_in_rank']);
                      $r_female= intval($row['female_in_rank']);
                      $pct     = $numb > 0 ? round(($r_total / $numb) * 100, 1) : 0;
                  ?>
                    <tr>
                      <td><?php echo $sr++; ?></td>
                      <td><strong><?php echo htmlspecialchars($row['currentRank']); ?></strong></td>
                      <td class="text-center"><span class="badge bg-green"><?php echo number_format($r_male); ?></span></td>
                      <td class="text-center"><span class="badge bg-blue"><?php echo number_format($r_female); ?></span></td>
                      <td class="text-center"><strong><?php echo number_format($r_total); ?></strong></td>
                      <td class="text-center">
                        <div class="progress" style="margin-bottom: 0; height: 16px;">
                          <div class="progress-bar progress-bar-success" role="progressbar" style="width: <?php echo $pct; ?>%;">
                            <?php echo $pct; ?>%
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php 
                    endforeach;
                  else:
                  ?>
                    <tr>
                      <td colspan="6" class="text-center">No rank distribution data available.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<!-- /page content -->

<!-- Include Chart.js if not already in footer -->
<script src="vendors/Chart.js/dist/Chart.min.js"></script>

<script>
$(document).ready(function() {
    // 1. Gender Pie Chart
    new Chart(document.getElementById("genderChart"), {
        type: "pie",
        data: {
            labels: ["Male", "Female"],
            datasets: [{
                backgroundColor: ["#1ABB9C", "#3498DB"],
                data: [<?php echo $numb2; ?>, <?php echo $numb3; ?>]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            title: {
                display: false,
                text: "Gender Distribution"
            }
        }
    });

    // 2. Blood Type Bar Chart
    new Chart(document.getElementById("bloodChart"), {
        type: "bar",
        data: {
            labels: <?php echo json_encode(!empty($blood_labels) ? $blood_labels : ['N/A']); ?>,
            datasets: [{
                label: "Personnel Count",
                backgroundColor: "#E74C3C",
                data: <?php echo json_encode(!empty($blood_counts) ? $blood_counts : [0]); ?>
            }]
        },
        options: {
            responsive: true,
            legend: { display: false },
            scales: {
                yAxes: [{ ticks: { beginAtZero: true } }]
            }
        }
    });

    // 3. Rank Bar Chart
    new Chart(document.getElementById("rankChart"), {
        type: "bar",
        data: {
            labels: <?php echo json_encode(!empty($rank_labels) ? $rank_labels : ['N/A']); ?>,
            datasets: [
                {
                    label: "Male",
                    backgroundColor: "#1ABB9C",
                    data: <?php echo json_encode(!empty($rank_males) ? $rank_males : [0]); ?>
                },
                {
                    label: "Female",
                    backgroundColor: "#3498DB",
                    data: <?php echo json_encode(!empty($rank_females) ? $rank_females : [0]); ?>
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                xAxes: [{ stacked: true }],
                yAxes: [{ stacked: true, ticks: { beginAtZero: true } }]
            }
        }
    });

    // 4. Directorate Chart
    new Chart(document.getElementById("dirChart"), {
        type: "doughnut",
        data: {
            labels: <?php echo json_encode(!empty($dir_labels) ? $dir_labels : ['General']); ?>,
            datasets: [{
                backgroundColor: ["#9B59B6", "#34495E", "#E67E22", "#F1C40F", "#2ECC71", "#1ABC9C"],
                data: <?php echo json_encode(!empty($dir_counts) ? $dir_counts : [0]); ?>
            }]
        },
        options: {
            responsive: true,
            legend: { position: 'bottom' }
        }
    });
});
</script>

<?php include 'footer-enrol.php'; ?>

          