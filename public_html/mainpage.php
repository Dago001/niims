<?php 
session_start();
if (!isset($_SESSION['username'])) {
  header('Location: index');
  exit;
}

require_once ('db/db.php');

// Initialize all rank count variables to prevent undefined variable errors
$numbcg = $numbdcg = $numbacg = $numbcis = $numbdci = $numbaci = 0;
$numbcsi = $numbsi = $numbdsi = $numbasi1 = $numbasi2 = $numbii = 0;
$numbaii = $numbcia = $numbsia = $numbia1 = $numbia2 = $numbia3 = 0;

if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "Report") {
    include 'sidebar-view.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Define dynamic active/retired criteria:
// - Retired: Age >= 60 years OR Service (DOFA) >= 35 years OR empStatus = 'Retired' (excluding deceased)
// - Active: Not retired and not deceased
$active_cond = "em.empStatus != 'Deceased' AND TIMESTAMPDIFF(YEAR, ep.dob, CURDATE()) < 60 AND TIMESTAMPDIFF(YEAR, em.dofa, CURDATE()) < 35 AND em.empStatus != 'Retired'";
$retired_cond = "em.empStatus != 'Deceased' AND (TIMESTAMPDIFF(YEAR, ep.dob, CURDATE()) >= 60 OR TIMESTAMPDIFF(YEAR, em.dofa, CURDATE()) >= 35 OR em.empStatus = 'Retired')";
$deceased_cond = "em.empStatus = 'Deceased'";

$sqluse = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE em.empStatus != 'Deceased'; ";
$sqluser = "SELECT * FROM tbl_users";
$sql_active = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE $active_cond; ";
$sqluse2 = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE ep.gender = 'Male' AND $active_cond; ";
$sqluse3 = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE ep.gender = 'Female' AND $active_cond; ";
$sqluse5 = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE $retired_cond; ";
$sqluse6 = "SELECT DISTINCT ep.serviceNo FROM `tbl_emppersonal` ep JOIN `tbl_employment` em ON ep.serviceNo = em.serviceNo WHERE $deceased_cond; ";

$retrieve = mysqli_query($conn, $sqluse);
$user_ret = mysqli_query($conn, $sqluser);
$retrieve_active = mysqli_query($conn, $sql_active);
$retrieve2 = mysqli_query($conn, $sqluse2);
$retrieve3 = mysqli_query($conn, $sqluse3);
$retrieve5 = mysqli_query($conn, $sqluse5);
$retrieve6 = mysqli_query($conn, $sqluse6);

$numb = mysqli_num_rows($retrieve); 
$numb_active = mysqli_num_rows($retrieve_active);
$numb2 = mysqli_num_rows($retrieve2); 
$numb3 = mysqli_num_rows($retrieve3);
$numb4 = mysqli_num_rows($user_ret);
$numb5 = mysqli_num_rows($retrieve5);
$numb6 = mysqli_num_rows($retrieve6);

$my_enrolled_count = 0;
if (isset($_SESSION['user_id'])) {
    $current_user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $sql_my_enrolled = "SELECT COUNT(*) as enrolled_count FROM tbl_emppersonal WHERE user_id = '$current_user_id'";
    $result_my_enrolled = mysqli_query($conn, $sql_my_enrolled);
    if ($result_my_enrolled) {
        $row_my_enrolled = mysqli_fetch_assoc($result_my_enrolled);
        $my_enrolled_count = $row_my_enrolled['enrolled_count'];
    }
}

$cgi = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Comptroller General' AND $active_cond";
$dcg = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Dep. Comptroller General' AND $active_cond";
$acg = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Asst. Comptroller General' AND $active_cond";
$cis = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Comptroller' AND $active_cond";
$dci = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Deputy Comptroller' AND $active_cond";
$aci = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Assistant Comptroller' AND $active_cond";
$csi = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Chief Superintendent' AND $active_cond";
$si = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Superintendent' AND $active_cond";
$dsi = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Deputy Superintendent' AND $active_cond";
$asi1 = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Asst. Superintendent 1' AND $active_cond";
$asi2 = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Asst. Superintendent 2' AND $active_cond";
$ii = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Inspector' AND $active_cond";
$aii = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Assistant Inspector' AND $active_cond";
$cia = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Chief Immigration Asst.' AND $active_cond";
$sia = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Senior Immigration Asst.' AND $active_cond";
$ia1 = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Immigration Asst. 1' AND $active_cond";
$ia2 = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Immigration Asst. 2' AND $active_cond";
$ia3 = "SELECT em.* FROM tbl_employment em JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo WHERE em.currentRank='Immigration Asst. 3' AND $active_cond";

$cg = mysqli_query($conn, $cgi);
$dcg1 = mysqli_query($conn, $dcg);
$acg1 = mysqli_query($conn, $acg);
$cis1 = mysqli_query($conn, $cis);
$dci1 = mysqli_query($conn, $dci);
$aci1 = mysqli_query($conn, $aci);
$csi1 = mysqli_query($conn, $csi);
$si1 = mysqli_query($conn, $si);
$dsi1 = mysqli_query($conn, $dsi);
$asi11 = mysqli_query($conn, $asi1);
$asi22 = mysqli_query($conn, $asi2);
$ii1 = mysqli_query($conn, $ii);
$aii1 = mysqli_query($conn, $aii);
$cia1 = mysqli_query($conn, $cia);
$sia1 = mysqli_query($conn, $sia);
$ia11 = mysqli_query($conn, $ia1);
$ia22 = mysqli_query($conn, $ia2);
$ia33 = mysqli_query($conn, $ia3);

$numbcg = mysqli_num_rows($cg); 
$numbdcg = mysqli_num_rows($dcg1); 
$numbacg = mysqli_num_rows($acg1);
$numbcis = mysqli_num_rows($cis1);
$numbdci = mysqli_num_rows($dci1);
$numbaci = mysqli_num_rows($aci1); 
$numbcsi = mysqli_num_rows($csi1); 
$numbsi = mysqli_num_rows($si1);
$numbdsi = mysqli_num_rows($dsi1);
$numbasi1 = mysqli_num_rows($asi11);
$numbasi2 = mysqli_num_rows($asi22); 
$numbii = mysqli_num_rows($ii1); 
$numbaii = mysqli_num_rows($aii1);
$numbcia = mysqli_num_rows($cia1);
$numbsia = mysqli_num_rows($sia1);
$numbia1 = mysqli_num_rows($ia11); 
$numbia2 = mysqli_num_rows($ia22); 
$numbia3 = mysqli_num_rows($ia33);

//Query for stacked chart
$stack = "SELECT ep.state_of_origin, ep.gender, COUNT(*) as count FROM tbl_emppersonal ep JOIN tbl_employment em ON ep.serviceNo = em.serviceNo WHERE $active_cond GROUP BY ep.state_of_origin, ep.gender ORDER BY ep.state_of_origin ASC";
$result_stack = mysqli_query($conn, $stack);

$label_stack = array();
$maleData = array();
$femaleData = array();

if ($result_stack && $result_stack->num_rows > 0) {
    // Initialize empty data for male and female counts for each state
    // We'll later fill in the counts for each state and gender
    while ($rows = $result_stack->fetch_assoc()) {
        $state = $rows["state_of_origin"];
        $gender = $rows["gender"];
        $count = $rows["count"];

        // Ensure the state is in the label stack
        if (!in_array($state, $label_stack)) {
            array_push($label_stack, $state);
            // Initialize 0 count for male and female for the new state
            $maleData[] = 0;
            $femaleData[] = 0;
        }

        // Find the index of the current state in the label_stack
        $stateIndex = array_search($state, $label_stack);

        // Assign count to male or female based on the gender
        if ($gender == "Male") {
            $maleData[$stateIndex] = $count;
        } elseif ($gender == "Female") {
            $femaleData[$stateIndex] = $count;
        }
    }
}

//Data for State of Origin
$query = "SELECT DISTINCT ep.state_of_origin, COUNT(*) as count 
          FROM tbl_emppersonal ep
          JOIN tbl_employment em ON ep.serviceNo = em.serviceNo
          WHERE $active_cond
          GROUP BY state_of_origin
          ORDER BY state_of_origin ASC";

$result = mysqli_query($conn, $query);
$labels = array();
$data = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $labels[] = $row['state_of_origin'];
        $data[] = $row['count'];
    }
}

// Data for Ranks
$query2 = "SELECT em.currentRank, COUNT(*) as count 
          FROM tbl_employment em
          JOIN tbl_emppersonal ep ON em.serviceNo = ep.serviceNo
          WHERE $active_cond
          GROUP BY em.currentRank";

$result2 = mysqli_query($conn, $query2);
$labels2 = array();
$data2 = array();

if ($result2) {
    while ($row2 = mysqli_fetch_assoc($result2)) {
        $labels2[] = $row2['currentRank'];
        $data2[] = $row2['count'];
    }
}

//Data for gender
$query3 = "SELECT ep.gender, COUNT(*) as count 
          FROM tbl_emppersonal ep
          JOIN tbl_employment em ON ep.serviceNo = em.serviceNo
          WHERE $active_cond
          GROUP BY ep.gender";

$result3 = mysqli_query($conn, $query3);
$labels3 = array();
$data3 = array();

if ($result3) {
    while ($row3 = mysqli_fetch_assoc($result3)) {
        $labels3[] = $row3['gender'];
        $data3[] = $row3['count'];
    }
}
?>
       
        <!-- page content -->
        <div class="right_col" role="main">
          <!-- top tiles -->
          <div class="row tile_count" style="display: flex; flex-wrap: wrap; justify-content: space-between;">
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-users"></i> Total Personnel</span>
              <div class="count" style="font-size: 30px; font-weight: bold;"><?php echo $numb; ?></div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-user-circle"></i> Active Personnel</span>
              <div class="count green" style="font-size: 30px; font-weight: bold;"><?php echo $numb_active; ?></div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-history"></i> Retired Personnel</span>
              <div class="count yellow" style="font-size: 30px; font-weight: bold;"><?php echo $numb5; ?></div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-user-times"></i> Deceased Personnel</span>
              <div class="count" style="font-size: 30px; font-weight: bold;"><?php echo $numb6; ?></div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-mars"></i> Active Males</span>
              <div class="count green" style="font-size: 30px; font-weight: bold;"><?php echo $numb2; ?></div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-venus"></i> Active Females</span>
              <div class="count blue" style="font-size: 30px; font-weight: bold;"><?php echo $numb3; ?></div>
            </div>
            <?php if ($_SESSION['role'] == "Supervisor" || $_SESSION['role'] == "Admin" || $_SESSION['role'] == "SuperAdmin"): ?>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-user-plus"></i> My Enrolments</span>
              <div class="count green" style="font-size: 30px; font-weight: bold;"><?php echo $my_enrolled_count; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === "SuperAdmin"): ?>
            <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count" style="flex: 1; min-width: 140px; margin-bottom: 10px;">
              <span class="count_top"><i class="fa fa-cogs"></i> System Users</span>
              <div class="count red" style="font-size: 30px; font-weight: bold;"><?php echo $numb4; ?></div>
            </div>
            <?php endif; ?>
          </div>
          
          <br />

          <div class="row">
   
<!-- Chart for Ranks -->
            <div class="col-md-8 col-sm-4 col-xs-12">
              <div class="x_panel">
                 <div class="x_title">
                  <h5>Rank Distribution</h5>
                  
                  <div class="clearfix"></div>
                </div>
                <div class="x_content">
                <canvas id="horChart" style="width:90%;max-width:500px; height:300px;"></canvas>

                  <script>
                    const xValues = ["CGI", "DCG","ACG", "CIS","DCI", "ACI","CSI", "SI","DSI", "ASI-1","ASI-2", "II","AII", "CIA","SIA", "IA-1","IA-2", "IA-3"];
                    const yValues = [
                      <?php echo $numbcg; ?>,
                      <?php echo $numbdcg; ?>,
                      <?php echo $numbacg; ?>,
                      <?php echo $numbcis; ?>,
                      <?php echo $numbdci; ?>,
                      <?php echo $numbaci; ?>,
                      <?php echo $numbcsi; ?>,
                      <?php echo $numbsi; ?>,
                      <?php echo $numbdsi; ?>,
                      <?php echo $numbasi1; ?>,
                      <?php echo $numbasi2; ?>,
                      <?php echo $numbii; ?>,
                      <?php echo $numbaii; ?>,
                      <?php echo $numbcia; ?>,
                      <?php echo $numbsia; ?>,
                      <?php echo $numbia1; ?>,
                      <?php echo $numbia2; ?>,
                      <?php echo $numbia3; ?>
                    ];
                    const barColors = ["green", "blue","rgb(120,110,20)", "rgb(109,11,20)","rgb(210,111,25)", "rgb(107,131,20)","rgb(100,11,120)", "rgb(100,111,120)","rgb(130,101,120)", "rgb(100,121,201)","rgb(100,211,120)", "rgb(230,211,120)","rgb(200,211,220)", "rgb(100,111,220)","rgb(100,101,140)", "rgb(230,111,220)","rgb(123,141,220)", "rgb(150,211,120)"];

                    new Chart("horChart", {
                    type: "bar",
                    data: {
                    labels: xValues,
                    datasets: [{
                        label: 'Employees',
                    backgroundColor: barColors,
                    data: yValues
                    }]
                    },
                    options: {
                    title: {
                    display: true,
                    text: "Number of Employees by Rank"
                    }
                    }
                    });
                  </script>                 
                </div>
              </div>
            </div>

<!-- Chart for Gender -->
            <div class="col-md-4 col-sm-4 col-xs-12">
              <div class="x_panel">
                <div class="x_title">
                  <h5>Gender Distribution</h5>
                  
                  <div class="clearfix"></div>
                </div>
                <div class="x_content">
                <canvas id="myChart" style="width:100%;max-width:900px; height:300px;"></canvas>

                 <script>
                        var ctx3 = document.getElementById('myChart').getContext('2d');
                        var chart3 = new Chart(ctx3, {
                            type: 'pie',
                            data: {
                                labels: <?php echo json_encode($labels3); ?>,
                                datasets: [{
                                    label: 'Number of Employees',
                                    data: <?php echo json_encode($data3); ?>,
                                    backgroundColor: getRandomColors(<?php echo count($labels3); ?>),
                                    borderColor: getRandomColors(<?php echo count($labels3); ?>),
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                title: {
                                    display: true,
                                    text: 'Number of Employees by Gender'
                                },
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });
                        
                        function getRandomColors(n) {
                            var colors = [];
                            for (var i = 0; i < n; i++) {
                                colors.push(getRandomColor());
                            }
                            return colors;
                        }
                    
                        function getRandomColor() {
                            var r = Math.floor(Math.random() * 156);
                            var g = Math.floor(Math.random() * 236);
                            var b = Math.floor(Math.random() * 256);
                            return 'rgba(' + r + ', ' + g + ', ' + b + ', 0.2)';
                        }
                    
                    </script>
                </div>
              </div>
            </div>
          </div>
          
         <!-- Chart for distribution by state and gender -->
          <div class="row">
              <div class="col-md-12">
                <div class="x_panel">
                <canvas id="rankChart" style="width:90%;max-width:900px"></canvas>

                <script>
                    const ctx4 = document.getElementById('rankChart').getContext('2d');
                    const chart4 = new Chart(ctx4, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($label_stack); ?>,
                            datasets: [{
                                label: 'Male',
                                data: <?php echo json_encode($maleData); ?>,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Female',
                                data: <?php echo json_encode($femaleData); ?>,
                                backgroundColor: 'rgba(155, 99, 132, 0.2)',
                                borderColor: 'rgba(155, 99, 132, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    mode: 'x'
                                }
                            },
                            responsive: true,
                            interaction: {
                                intersect: false
                            },
                            title: {
                                    display: true,
                                    text: 'Number of Employees by State of Origin and Gender'
                                },
                            scales: {
                                x: {
                                    stacked: true,
                                },
                                y: {
                                    stacked: true,
                                    
                                }
                            }
                        }
                    });
                </script>

                 
                </div>

              </div>
          </div>
<!-- Chart for State -->
          <div class="row">
              <div class="col-md-12">
                <div class="x_panel">
                <canvas id="stateChart2" style="width:90%;max-width:900px"></canvas>

                  <script>
                        var ctx = document.getElementById('stateChart2').getContext('2d');
                        var chart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($labels); ?>,
                                datasets: [{
                                    label: 'Number of Employees',
                                    data: <?php echo json_encode($data); ?>,
                                    backgroundColor: getRandomColors(<?php echo count($labels); ?>),
                                    borderColor: getRandomColors(<?php echo count($labels); ?>),
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                title: {
                                    display: true,
                                    text: 'Number of Employees by State of Origin'
                                },
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });
                        
                        function getRandomColors(n) {
                            var colors = [];
                            for (var i = 0; i < n; i++) {
                                colors.push(getRandomColor());
                            }
                            return colors;
                        }
                    
                        function getRandomColor() {
                            var r = Math.floor(Math.random() * 106);
                            var g = Math.floor(Math.random() * 216);
                            var b = Math.floor(Math.random() * 226);
                            return 'rgba(' + r + ', ' + g + ', ' + b + ', 0.2)';
                        }
                    
                    </script>

                </div>

              </div>
          </div>

        </div>
        <!-- /page content -->
<?php 
include 'footer.php';
?>