<?php 
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

// Robust case-insensitive sidebar inclusion with fallback for Linux production environments
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
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Personnel Enrolment</h3>
            </div>

            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                    <div class="input-group">
                         
                    </div>
                </div>
            </div>
        </div>
        <style>
        .required {
            color: red;
        }
        .is-invalid {
        border: 1px solid red !important;
        background-color: #fff5f5 !important;
        }
        /* Add CSS to fix stepContainer height */
        .stepContainer {
            height: 50px !important;
            min-height: 50px !important;
            overflow: visible !important;
        }
        /* Success message styling */
        .swal2-popup.swal2-success .swal2-title {
            color: #28a745;
        }
        .swal2-popup.swal2-warning .swal2-title {
            color: #ffc107;
        }
        .swal2-popup.swal2-error .swal2-title {
            color: #dc3545;
        }
        /* Button styling */
        #submitBtn {
            background: linear-gradient(45deg, #0e7a2e, #1c6ea4);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        #submitBtn:hover {
            background: linear-gradient(45deg, #1c6ea4, #0e7a2e);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        #resetBtn {
            background: linear-gradient(45deg, #6c757d, #5a626);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        #resetBtn:hover {
            background: linear-gradient(45deg, #5a6268, #6c757d);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        /* Validation animation */
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
        .is-invalid {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5 !important;
            animation: shake 0.5s ease-in-out;
        }
        .is-invalid:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        /* Review step styling */
        .review-section-title {
            color: #1c6ea4;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e9ecef;
        }
        .review-section-title:first-child {
            margin-top: 0;
        }
        .review-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .review-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            width: 35%;
        }
        .review-table th,
        .review-table td {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            vertical-align: top;
        }
        .review-value-missing {
            color: #dc3545;
            font-style: italic;
        }
        </style>
        <div class="clearfix"></div>

        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>Enrolment <small>Data Capture</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                       <!-- <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-wrench"></i></a>
                            <ul class="dropdown-menu" role="menu">
                                <li><a href="#">Settings 1</a></li>
                                <li><a href="#">Settings 2</a></li>
                            </ul>
                        </li>
                        <li><a class="close-link"><i class="fa fa-close"></i></a></li>-->
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <!-- SweetAlert2 CDN -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    
                    <!-- Smart Wizard -->
                    <p>NB: Fields with * are compulsory.</p>
                    <div id="wizard" class="form_wizard wizard_horizontal">
                        <ul class="wizard_steps">
                            <li>
                                <a href="#step-1">
                                    <span class="step_no">1</span>
                                    <span class="step_descr">
                                        <small>Personal Info</small>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#step-2">
                                    <span class="step_no">2</span>
                                    <span class="step_descr">
                                        <small>Educational Qualification</small>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#step-3">
                                    <span class="step_no">3</span>
                                    <span class="step_descr">
                                        <small>Employment</small>
                                    </span>
                                </a>
                            </li>
                             <li>
                                <a href="#step-4">
                                    <span class="step_no">4</span>
                                    <span class="step_descr">
                                        <small>Financial</small>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#step-5">
                                    <span class="step_no">5</span>
                                    <span class="step_descr">
                                        <small>Review</small>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a href="#step-6">
                                    <span class="step_no">6</span>
                                    <span class="step_descr">
                                        <small>Finish</small>
                                    </span>
                                </a>
                            </li>
                        </ul>
                        <!-- Form with AJAX submission -->
                        <form id="enrolmentForm" class="form-horizontal form-label-left">
                            <fieldset>
                                <div id="step-1">
                                    <h4 class="StepTitle">Personal Information</h4>
                                   <div class="form-group">
  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">
    Service No <span class="required">*</span>
  </label>
  <div class="col-md-6 col-sm-6 col-xs-12">
    <input 
      type="text" 
      name="serviceNo" 
      id="serviceNo"
      required="required" 
      class="form-control col-md-7 col-xs-12" 
      placeholder="Service No"
      pattern="\d{5}" 
      minlength="5" 
      maxlength="5"
      title="Please enter exactly 5 digits."
    >
  </div>
</div>

                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="lname">Surname <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input type="text" name="lname" required="required" class="form-control col-md-7 col-xs-12"  placeholder="Surname" oninput="validateText(this)">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="fname">First Name <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="fname" required="required" class="form-control col-md-7 col-xs-12"  placeholder="First Name" oninput="validateText(this)">
                                        </div>
                                    </div>
                                                              
                                    <div class="form-group">
                                          <label class="control-label col-md-3 col-sm-3 col-xs-12" for="mname">Middle Name</label>
                                          <div class="col-md-6 col-sm-6 col-xs-12">
                                              <input type="text" name="mname" class="form-control col-md-7 col-xs-12"  placeholder="Middle Name" oninput="validateText(this)">
                                          </div>
                                    </div>              
                                    <div class="form-group">
                                      <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of Birth <span class="required">*</span>
                                      </label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input name="dob" class="date-picker form-control col-md-7 col-xs-12" required="required" type="date">
                                      </div>
                                    </div>
                                
                                    <div class="form-group">
                                      <label for="marital_status" class="col-sm-3 text-end control-label col-form-label">Marital Status<span class="required">*</span></label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <select name="marital_status" class="form-control shadow-none" required="required">
                                          <option value="">--Select Marital Status--</option>
                                          <option value="Single"> Single </option>
                                          <option value="Married"> Married </option>
                                          <option value="Divorced"> Divorced </option>
                                          <option value="Widower"> Widower </option>
                                          <option value="Widow"> Widow </option>
                                          <option value="Seperated"> Seperated </option>
                                        </select>
                                      </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="maiden_name">Maiden Name</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="maiden_name" class="form-control col-md-7 col-xs-12"  placeholder="Maiden Name" oninput="validateText(this)">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="gender" class="col-sm-3 text-end control-label col-form-label">Gender<span class="required">*</span></label>
                                      <div class="col-md-3 col-sm-6 col-xs-12">
                                        <select name="gender" class="form-control shadow-none" required="required">
                                          <option value="">--Select Gender--</option>
                                          <option value="Male"> Male </option>
                                          <option value="Female"> Female </option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="state_of_origin"  class="control-label col-md-3 col-sm-3 col-xs-12">State of Origin<span class="required">*</span></label>
                                      <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                        <select name="state_of_origin" id="state" onchange="toggleLGA(this);" class="form-control" required="required">
                                        <option value="">-- Select State --</option>
                                        <option value="FCT">Abuja FCT</option>
                                        <option value="Abia">Abia</option>
                                        <option value="Adamawa">Adamawa</option>
                                        <option value="AkwaIbom">Akwa Ibom</option>
                                        <option value="Anambra">Anambra</option>
                                        <option value="Bauchi">Bauchi</option>
                                        <option value="Bayelsa">Bayelsa</option>
                                        <option value="Benue">Benue</option>
                                        <option value="Borno">Borno</option>
                                        <option value="Cross River">Cross River</option>
                                        <option value="Delta">Delta</option>
                                        <option value="Ebonyi">Ebonyi</option>
                                        <option value="Edo">Edo</option>
                                        <option value="Ekiti">Ekiti</option>
                                        <option value="Enugu">Enugu</option>
                                        <option value="Gombe">Gombe</option>
                                        <option value="Imo">Imo</option>
                                        <option value="Jigawa">Jigawa</option>
                                        <option value="Kaduna">Kaduna</option>
                                        <option value="Kano">Kano</option>
                                        <option value="Katsina">Katsina</option>
                                        <option value="Kebbi">Kebbi</option>
                                        <option value="Kogi">Kogi</option>
                                        <option value="Kwara">Kwara</option>
                                        <option value="Lagos">Lagos</option>
                                        <option value="Nasarawa">Nasarawa</option>
                                        <option value="Niger">Niger</option>
                                        <option value="Ogun">Ogun</option>
                                        <option value="Ondo">Ondo</option>
                                        <option value="Osun">Osun</option>
                                        <option value="Oyo">Oyo</option>
                                        <option value="Plateau">Plateau</option>
                                        <option value="Rivers">Rivers</option>
                                        <option value="Sokoto">Sokoto </option>
                                        <option value="Taraba">Taraba</option>
                                        <option value="Yobe">Yobe</option>
                                        <option value="Zamfara">Zamfara</option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="lga" class="control-label col-md-3 col-sm-3 col-xs-12">LGA<span class="required">*</span></label>
                                      <div class="col-md-3 col-sm-3 col-xs-12">
                                        <select name="lga" id="lga" class="form-control select-lga col-md-7 col-xs-12" required="required"></select>
                                      </div>
                                    </div>
                                
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="town">Town<span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="town" class="form-control col-md-7 col-xs-12"  placeholder="Town" oninput="validateText(this)">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="email" class="control-label col-md-3 col-sm-3 col-xs-12">Email Address<span class="required">*</span></label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input type="text" class="form-control" name="email" placeholder="Email Address" required="required">
                                      </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="phone">Phone No <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="phone" maxlength="11" required="required" class="form-control col-md-7 col-xs-12" placeholder="Phone No" oninput="validateNumber(this)">
                                        </div>
                                    </div>
                          
                                    <div class="form-group">
                                      <label for="bloodGroup" class="col-sm-3 text-end control-label col-form-label">Blood Group<span class="required">*</span></label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <select name="bloodType" class="form-control shadow-none" required="required">
                                          <option value="">--Select Blood Type--</option>
                                          <option value="A+"> A+ </option>
                                          <option value="A-"> A- </option>
                                          <option value="B+"> B+ </option>
                                          <option value="B-"> B- </option>
                                          <option value="AB+"> AB+ </option>
                                          <option value="AB-"> AB- </option>
                                          <option value="O+"> O+ </option>
                                          <option value="O-"> O- </option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="genotype" class="col-sm-3 text-end control-label col-form-label">Genotype<span class="required">*</span></label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <select name="genotype" class="form-control shadow-none" required="required">
                                          <option value="">--Select Genotype--</option>
                                          <option value="AA"> AA </option>
                                          <option value="AS"> AS </option>
                                          <option value="AC"> AC </option>
                                          <option value="SS"> SS </option>
                                          <option value="SC"> SC </option>
                                          <option value="CC"> CC </option>
                                        </select>
                                      </div>
                                    </div>
                                <br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                Next of Kin Information
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="nok">Next of Kin Name<span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="nok" class="form-control col-md-7 col-xs-12" placeholder="NOK Name" oninput="validateText(this)">
                                        </div>
                                    </div>
                                  <div class="form-group">
                                          <label for="nok_relationship" class="col-sm-3 text-end control-label col-form-label">Relationship<span class="required">*</span></label>
                                          <div class="col-md-6 col-sm-6 col-xs-12">
                                              <select class="form-control col-md-7 col-xs-12" name="nok_relationship" id="nok_relationship" required>
                                                  <option value="">Select Relationship</option>
                                                  <option value="husband">Husband</option>
                                                  <option value="wife">Wife</option>
                                                  <option value="son">Son</option>
                                                  <option value="father">Father</option>
                                                  <option value="mother">Mother</option>
                                                  <option value="daughter">Daughter</option>
                                                  <option value="brother">Brother</option>
                                                  <option value="sister">Sister</option>
                                                  <option value="uncle">Uncle</option>
                                                  <option value="aunty">Aunty</option>
                                                  <option value="nephew">Nephew</option>
                                                  <option value="niece">Niece</option>
                                              </select>
                                          </div>
                                    </div>
                                  
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="nok_phone">NOK Phone No <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="nok_phone" maxlength="11" required="required" class="form-control col-md-7 col-xs-12" placeholder="NOK Phone No" oninput="validateNumber(this)">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                              <div id="step-2">
                                <h4 class="StepTitle">Highest Educational Qualification</h4>
                                                              
                                    <div class="form-group">
                                      <label
                                        for="qualification"
                                        class="col-sm-3 text-end control-label col-form-label"
                                        >Highest Qualification<span class="required">*</label
                                      >
                                      <div class="col-sm-9">
                                        <input type="text" class="form-control col-md-7 col-xs-12" name="qualification" placeholder="Highest Qualification" oninput="validateText(this)" required="required" />
                                      </div>
                                    </div>
                                    <div class="form-group row">
                                      <label
                                        for="institution"
                                        class="col-sm-3 text-end control-label col-form-label"
                                        >Name of Institution<span class="required">*</label
                                      >
                                      <div class="col-sm-9">
                                        <input type="text" class="form-control col-md-7 col-xs-12" name="institution" placeholder="Name of Institution" oninput="validateText(this)" required="required"/>
                                      </div>
                                    </div>
                                    <div class="form-group">
                                <label for="certificate_number" class="control-label col-md-3 col-sm-3 col-xs-12">
  Certificate Number
</label>
<div class="col-sm-9">
  <input
    type="text"
    class="form-control col-md-7 col-xs-12"
    id="certificate_number"
    name="certificate_number"
    placeholder="Certificate Number"
    oninput="validateCertificateNumber(this)"
    pattern="[A-Za-z0-9\-\/_\.' @#$%&*]+"
    title="Letters, numbers, hyphen (-), slash (/), underscore (_), apostrophe ('), dot (.), space, and special characters like @ # $ % & * are allowed."
  >
</div>
                                  <div class="form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Obtained <span class="required">*</span>
                                    </label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                      <input name="date_obtained" class="date-picker form-control col-md-7 col-xs-12" required="required" type="date">
                                    </div>
                                  </div>
                              
                              </div>
                            </fieldset>
                            <fieldset>
                                <div id="step-3">
                                    <h4 class="StepTitle">Employment Information</h4>
                                
                                    <div class="form-group">
                                      <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of First Appointment <span class="required">*</span>
                                      </label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input name="dofa" class="date-picker form-control col-md-7 col-xs-12" required="required" type="date">
                                      </div>
                                    </div>
                                    <div class="form-group">
                                      <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of Present Appointment <span class="required">*</span>
                                      </label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input name="dopa" class="col-md-6 col-sm-6 col-xs-12" required="required" type="date">
                                      </div>
                                    </div>
                                    <div class="form-group">
                                      <label for="currentRank" class="control-label col-md-3 col-sm-3 col-xs-12">Current Rank <span class="required">*</label>
                                      <div class="col-md-6 col-sm-6 col-xs-12"><select name="currentRank"  class="form-control col-md-7 col-xs-12" required="required">
                                        <option value="">--Select Rank--</option>
                                        <option value="Comptroller General">CGIS</option>
                                        <option value="Dep. Comptroller General">DCG</option>
                                        <option value="Asst. Comptroller General">ACG</option> 
                                        <option value="Comptroller">CIS</option>
                                        <option value="Deputy Comptroller">DCI</option>
                                        <option value="Assistant Comptroller">ACI</option>
                                        <option value="Chief Superintendent">CSI</option>
                                        <option value="Superintendent">SI</option>
                                        <option value="Deputy Superintendent">DSI</option>
                                        <option value="Asst. Superintendent 1">ASI-1</option>
                                        <option value="Asst. Superintendent 2">ASI-2</option>
                                        <option value="Inspector">II</option>
                                        <option value="Assistant Inspector">AII</option>
                                        <option value="Chief Immigration Asst.">CIA</option>
                                        <option value="Senior Immigration Asst.">SIA</option>
                                        <option value="Immigration Asst. 1">IA-1</option>
                                        <option value="Immigration Asst. 2">IA-2</option>
                                        <option value="Immigration Asst. 3">IA-3</option>
                                        </select>
                                      </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label
                                          for="cono1"
                                          class="control-label col-md-3 col-sm-3 col-xs-12"
                                          >Present Posting<span class="required">*</label
                                        >
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="presentPosting"  class="form-control col-md-7 col-xs-12 shadow-none" required="required">
                                                <option value="">--Select Place of Present Posting--</option>
                                                <option value="Other">Other</option>
                                                <option value="Service Headquarters">Service Headquarters (SHQ)</option>
                                                <option value="Zone A Lagos">Zone 'A' Lagos</option>
                                                <option value="Zone B Kaduna">Zone 'B' Kaduna</option>
                                                <option value="Zone C Bauchi">Zone 'C' Bauchi</option>
                                                <option value="Zone D Minna">Zone 'D' Minna</option>
                                                <option value="Zone E Owerri">Zone 'E' Owerri</option>
                                                <option value="Zone F Ibadan">Zone 'F' Ibadan</option>
                                                <option value="Zone G Benin City">Zone' G' Benin City</option>
                                                <option value="Zone H Makurdi">Zone 'H' Makurdi</option>
                                                <option value="FCT Command">FCT Command</option>
                                                <option value="Abia State Command">Abia State Command</option>
                                                <option value="Adamawa State Command">Adamawa State Command</option>
                                                <option value="Akwa Ibom State Command">Akwa Ibom State Command</option>
                                                <option value="Anambra State Command">Anambra State Command</option>
                                                <option value="Bauchi State Command">Bauchi State Command</option>
                                                <option value="Bayelsa State Command">Bayelsa State Command</option>
                                                <option value="Benue State Command">Benue State Command</option>
                                                <option value="Borno State Command">Borno State Command</option>
                                                <option value="Cross River State Command">Cross River State Command</option>
                                                <option value="Delta State Command">Delta State Command</option>
                                                <option value="Ebonyi State Command">Ebonyi State Command</option>
                                                <option value="Edo State Command">Edo State Command</option>
                                                <option value="Ekiti State Command">Ekiti State Command</option>
                                                <option value="Enugu State Command">Enugu State Command</option>
                                                <option value="Gombe State Command">Gombe State Command</option>
                                                <option value="Imo State Command">Imo State Command</option>
                                                <option value="Jigawa State Command">Jigawa State Command</option>
                                                <option value="Kaduna State Command">Kaduna State Command</option>
                                                <option value="Kano State Command">Kano State Command</option>
                                                <option value="Katsina State Command">Katsina State Command</option>
                                                <option value="Kebbi State Command">Kebbi State Command</option>
                                                <option value="Kogi State Command">Kogi State Command</option>
                                                <option value="Kwara State Command">Kwara State Command</option>
                                                <option value="Lagos State Command">Lagos State Command</option>
                                                <option value="Nasarawa State Command">Nasarawa State Command</option>
                                                <option value="Niger State Command">Niger State Command</option>
                                                <option value="Ogun State Command">Ogun State Command</option>
                                                <option value="Ondo State Command">Ondo State Command</option>
                                                <option value="Osun State Command">Osun State Command</option>
                                                <option value="Oyo State Command">Oyo State Command</option>
                                                <option value="Plateau State Command">Plateau State Command</option>
                                                <option value="Rivers State Command">Rivers State Command</option>
                                                <option value="Sokoto State Command">Sokoto State Command</option>
                                                <option value="Taraba State Command">Taraba State Command</option>
                                                <option value="Yobe State Command">Yobe State Command</option>
                                                <option value="Zamfara State Command">Zamfara State Command</option>
                                                <option value="Lagos Border Patrol Command">Lagos Border Patrol Command</option>
                                                <option value="Ikoyi Passport Command">Ikoyi Passport Command</option>
                                                <option value="MMIA">Murtala Mohammed International Airport (MMIA)</option>
                                                <option value="MAKIA">Mallam Aminu Kano International Airport (MAKIA)</option>
                                                <option value="PHIA">PortHarcourt International Airport (PHIA)</option>
                                                <option value="AIIA">Akanu Ibiam International Airport (AIIA)</option>
                                                <option value="NAIA">Nnamdi Azikiwe International Airport (NAIA)</option>
                                                <option value="Lagos Seaport & Marine Command">Lagos Seaport & Marine Command</option>
                                                <option value="Lagos Passport Command">Lagos Passport Command</option>  
                                                <option value="Seme Border Command">Seme Border Command</option> 
                                                <option value="Idiroko Border Command">Idiroko Border Command</option>
                                                <option value="Illela Border Command">Illela Border Command</option>
                                                <option value="Jibiya Border Command">Jibiya Border Command</option>
                                                <option value="ITSK">Immigration Training School Kano</option>
                                                <option value="ICSC">Immigration Command and Staff College</option>
                                                <option value="NITSA">Nigeria Immigration Training School Ahoada</option>
                                                <option value="NITSOL">Nigeria Immigration Training School Orlu</option>
                                                <option value="Mfum Border Command">Mfum Border Command</option>
                                                <option value="River Marine Command">River Marine Command</option>
                                                                
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Shown only when Present Posting = Service Headquarters (SHQ) -->
                                    <div class="form-group" id="postingTypeGroup" style="display:none;">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="posting_type_choice">Posting Type</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="posting_type_choice" id="posting_type_choice" class="form-control col-md-7 col-xs-12 shadow-none">
                                                <option value="">-- Select Directorate or Unit --</option>
                                                <option value="directorate">Directorate</option>
                                                <option value="unit">Unit</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group" id="directorateGroup" style="display:none;">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="directorate">Directorate</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="directorate" id="directorate" class="form-control col-md-7 col-xs-12 shadow-none">
                                                <option value="">--Select Directorate--</option>
                                                <option value="Human Resources Management Directorate">Human Resources Management Directorate</option>
                                                <option value="Finance and Accounts Directorate">Finance and Accounts Directorate</option>
                                                <option value="Planning, Research and Statistics Directorate">Planning, Research and Statistics Directorate</option>
                                                <option value="Passport and Other Travel Documents">Passport and Other Travel Documents</option>
                                                <option value="Investigation & Compliance Directorate">Investigation & Compliance Directorate</option>
                                                <option value="Border Management Directorate">Border Management Directorate</option>
                                                <option value="Migration Directorate">Migration Directorate</option>
                                                <option value="Visa and Residency Directorate">Visa and Residency Directorate</option>
                                                <option value="ICT and Cyber Security Directorate">ICT and Cyber Security Directorate</option>
                                                <option value="Works and Logistics Directorate">Works and Logistics Directorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group" id="unitGroup" style="display:none;">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="unit">Unit</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="unit" id="unit" class="form-control col-md-7 col-xs-12 shadow-none">
                                                <option value="">--Select Unit--</option>
                                                <option value="Legal">Legal</option>
                                                <option value="Internal Audits">Internal Audits</option>
                                                <option value="Procurement">Procurement</option>
                                                <option value="SERVICOM">SERVICOM</option>
                                                <option value="Provost">Provost</option>
                                                <option value="Press and Public Relations">Press and Public Relations</option>
                                                <option value="Gender Affairs">Gender Affairs</option>
                                                <option value="Anti-Corruption and Transparency">Anti-Corruption and Transparency</option>
                                                <option value="Innovation/Electronic Passport Management System (EPMS)">Innovation/Electronic Passport Management System (EPMS)</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Shown only when Directorate or Unit above is set to "Other" -->
                                    <div class="form-group" id="postingOtherGroup" style="display:none;">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="posting_other">Please Specify</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="posting_other" id="posting_other" class="form-control col-md-7 col-xs-12" placeholder="Please specify" maxlength="100" oninput="validateAlphabetsOnly(this)">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                              <div id="step-4">
                                <h4 class="StepTitle">Banking / Pension Details</h4>
                                                              
                                    <div class="form-group">
                                      <label
                                        for="ippis"
                                        class="col-sm-3 text-end control-label col-form-label"
                                        >IPPIS No<span class="required">*</label
                                      >
                                      <div class="col-sm-9">
                                        <input type="text" class="form-control col-md-7 col-xs-12" name="ippis" placeholder="IPPIS No" oninput="validateIPPIS(this)" required="required" />
                                      </div>
                                    </div>
                                
                                    <div class="form-group">
                                      <label for="bank" class="control-label col-md-3 col-sm-3 col-xs-12">Bank Name <span class="required">*</label>
                                      <div class="col-md-6 col-sm-6 col-xs-12"><select name="bank"  class="form-control col-md-7 col-xs-12" required="required">
                                        <option value="">--Select Bank--</option>
                                            <option value="Access Bank Plc">Access Bank Plc</option>
                                            <option value="Citibank Nigeria Limited">Citibank Nigeria Limited</option>
                                            <option value="Ecobank Nigeria Plc">Ecobank Nigeria Plc</option>
                                            <option value="Fidelity Bank Plc">Fidelity Bank Plc</option>
                                            <option value="First Bank Nigeria Limited">First Bank Nigeria Limited</option>
                                            <option value="First City Monument Bank Plc">First City Monument Bank Plc</option>
                                            <option value="Globus Bank Limited">Globus Bank Limited</option>
                                            <option value="Guaranty Trust Bank Plc">Guaranty Trust Bank Plc</option>
                                             <option value="Jaiz Bank Plc">Jaiz Bank Plc</option>
                                            <option value="Keystone Bank Limited">Keystone Bank Limited</option>
                                            <option value="Nova Commercial Bank Limited">Nova Commercial Bank Limited</option>
                                            <option value="NPF Microfinance Bank">NPF Microfinance Bank</option>
                                            <option value="Optimus Bank">Optimus Bank</option>
                                            <option value="Parallex Bank Ltd">Parallex Bank Ltd</option>
                                            <option value="Polaris Bank Plc">Polaris Bank Plc</option>
                                            <option value="Premium Trust Bank">Premium Trust Bank</option>
                                            <option value="Providus Bank">Providus Bank</option>
                                            <option value="Signature Bank Limited">Signature Bank Limited</option>
                                            <option value="Stanbic IBTC Bank Plc">Stanbic IBTC Bank Plc</option>
                                            <option value="Standard Chartered Bank Nigeria Ltd.">Standard Chartered Bank Nigeria Ltd.</option>
                                            <option value="Sterling Bank Plc">Sterling Bank Plc</option>
                                            <option value="SunTrust Bank Nigeria Limited">SunTrust Bank Nigeria Limited</option>
                                            <option value="Titan Trust Bank Ltd">Titan Trust Bank Ltd</option>
                                            <option value="Union Bank of Nigeria Plc">Union Bank of Nigeria Plc</option>
                                            <option value="United Bank For Africa Plc">United Bank For Africa Plc (UBA)</option>
                                            <option value="Titan Trust Bank Ltd">Unity Bank Plc</option>
                                            <option value="Union Bank of Nigeria Plc">Wema Bank Plc</option>
                                            <option value="United Bank For Africa Plc">Zenith Bank Plc</option>
                                        </select>
                                    </div>
                                </div>

                                   <div class="form-group">
                                    <label for="account_number" class="control-label col-md-3 col-sm-3 col-xs-12">Account No<span class="required">*</span></label>
                                    <div class="col-sm-9">
                                        <input
                                            type="text"
                                            class="form-control col-md-7 col-xs-12"
                                            id="account_number"
                                            name="account_number"
                                            placeholder="Account No"
                                            oninput="validateNumber(this)"
                                            title="Only numbers are allowed. Must be exactly 10 digits."
                                            maxlength="10"
                                            pattern="^\d{10}$"
                                            required
                                        >
                                    </div>
                                </div>
                                    
                                    <div class="form-group">
                                      <label for="pfa" class="control-label col-md-3 col-sm-3 col-xs-12">PFA <span class="required">*</label>
                                      <div class="col-md-6 col-sm-6 col-xs-12"><select name="pfa"  class="form-control col-md-7 col-xs-12" required="required">
                                        <option value="">--Select PFA--</option>
                                         <option value="ACCESS ARM Pensions">ACCESS Pensions</option>
                                          <option value="ACCESS ARM Pensions">ACCESS ARM Pensions</option>
                                          <option value="AIICO Pension Managers Limited">AIICO Pension Managers Limited</option>
                                          <option value="Anchor Pension Managers Limited">Anchor Pension Managers Limited</option>
                                          <option value="APT Pension Fund Managers Limited">APT Pension Fund Managers Limited</option>
                                          <option value="Cardinal Pension Limited">Cardinal Pension Limited</option>
                                          <option value="Citizens Pensions Limited">Citizens Pensions Limited</option>
                                          <option value="Crusader Sterling Pensions Limited">Crusader Sterling Pensions Limited</option>
                                          <option value="FCMB Pensions">FCMB Pensions</option>
                                          <option value="Fidelity Pension Manager Limited">Fidelity Pension Manager Limited</option>
                                          <option value="Guaranty Trust Pension Managers Limited">Guaranty Trust Pension Managers Limited</option>
                                          <option value="Leadway Pension PFA Ltd.">Leadway Pension PFA Ltd.</option>
                                          <option value="NLPC Pension Fund Administration Ltd.">NLPC Pension Fund Administration Ltd.</option>
                                          <option value="Nigerian University Pension Management Company (SUPPEN)">Nigerian University Pension Management Company (SUPPEN)</option>
                                          <option value="NPF Pensions">NPF Pensions</option>
                                          <option value="Norrenberger Pensions Limited">Norrenberger Pensions Limited</option>
                                          <option value="Oak Pensions Limited">Oak Pensions Limited</option>
                                          <option value="First Guaranty Pension Limited">First Guaranty Pension Limited</option>
                                          <option value="Pension Alliance Ltd (PAL)">Pension Alliance Ltd (PAL)</option>
                                          <option value="Premium Pension Limited">Premium Pension Limited</option>
                                          <option value="Sigma Pensions">Sigma Pensions</option>
                                          <option value="Stanbic IBTC Pension Managers">Stanbic IBTC Pension Managers</option>
                                          <option value="Tangerine APT Pensions Limited">Tangerine APT Pensions Limited</option>
                                          <option value="Trustfund Pensions Ltd.">Trustfund Pensions Ltd.</option>
                                          <option value="Veritas Glanvills Pensions Limited">Veritas Glanvills Pensions Limited</option>
                                          </select>
                                    </div>
                                    </div>

                                    <div class="form-group">
                                      <label
                                        for="rsa"
                                        class="col-sm-3 text-end control-label col-form-label"
                                        >RSA No<span class="required">*</label
                                      >
                                      <div class="col-sm-9">
                                        <input type="text" class="form-control col-md-7 col-xs-12" name="rsa" placeholder="RSA No" oninput="validateRSA(this)" required="required" />
                                      </div>
                                    </div>

                                    <div class="form-group row">
  <label
    for="nhf"
    class="col-sm-3 text-end control-label col-form-label"
  >
    NHF No.<span class="required">*</span>
  </label>
  <div class="col-sm-9">
    <input 
      type="text" 
      class="form-control col-md-7 col-xs-12" 
      id="nhf"
      name="nhf" 
      placeholder="NHF No" 
      required="required"
      pattern="[0-9\-]+"
      title="Only numbers and hyphen (-) are allowed."
    />
  </div>
</div>                        
                                </div>
                            </fieldset>
                            <fieldset>
                                <div id="step-5">
                                    <h4 class="StepTitle">Review Your Information</h4>
                                    <p>Please check the details below carefully. If anything is incorrect, click <strong>Previous</strong> to go back and fix it before submitting.</p>
                                    <div id="reviewContent"></div>
                                </div>
                            </fieldset>
                            <fieldset>
                                  <div id="step-6">
                                            <div class="border-top">
                                                <div class="card-body">
                                                    <button type="button" id="submitBtn" name="regEmployee" class="btn btn-primary">
                                                      Submit
                                                    </button>
                                                    <button type="button" id="resetBtn" class="btn btn-secondary">
                                                        Reset Form
                                                    </button>
                                                </div>
                                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="logged_in">Data Entry by:
                                              </label>
                                              <div class="col-md-6 col-sm-6 col-xs-12">
                                                <input type="text" name="logged_in" class="form-control col-md-7 col-xs-12" value="<?php echo $_SESSION['user_id']; ?>" readonly>
                                              </div>

                                            </div>
                            </fieldset>
                        </form>
                    </div>
                    <!-- End SmartWizard Content -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- footer content -->
<?php include 'footer-enrol.php'; ?>


<script>
    
   function validateText(input) {
    // Allow alphabets, space, hyphen, single quote, and dot
    input.value = input.value.replace(/[^a-zA-Z\s'\-.]/g, '');
    
    // Capitalize the first letter of each word
    input.value = input.value.replace(/\b\w/g, (char) => char.toUpperCase());
}


    // Function to validate and capitalize the certificate number
   function validateCertificateNumber(input) {
    // Allow letters, numbers, hyphen, slash, underscore, apostrophe, dot, space, and some special chars
    input.value = input.value.replace(/[^a-zA-Z0-9\-\/_\.' @#$%&*]/g, '');

    // Convert letters to uppercase
    input.value = input.value.toUpperCase();
  }
    function validateNumber(input) {
      // Remove non-numeric characters
      input.value = input.value.replace(/[^0-9]/g, '');
    }
    function validateAlphabetsOnly(input) {
      // Allow letters and spaces only — no numbers or special characters
      input.value = input.value.replace(/[^a-zA-Z\s]/g, '');
    }

    // Present Posting = Service Headquarters (SHQ) reveals a Directorate/Unit
    // choice; picking "Other" in either reveals a free-text field.
    function updateShqFields() {
      var presentPostingEl = document.querySelector('select[name="presentPosting"]');
      var postingTypeGroup = document.getElementById('postingTypeGroup');
      var postingTypeSelect = document.getElementById('posting_type_choice');
      var directorateGroup = document.getElementById('directorateGroup');
      var directorateSelect = document.getElementById('directorate');
      var unitGroup = document.getElementById('unitGroup');
      var unitSelect = document.getElementById('unit');
      var otherGroup = document.getElementById('postingOtherGroup');
      var otherInput = document.getElementById('posting_other');

      if (!presentPostingEl || !postingTypeGroup) return;

      var isSHQ = presentPostingEl.value === 'Service Headquarters';

      if (isSHQ) {
        postingTypeGroup.style.display = '';
        postingTypeSelect.setAttribute('required', 'required');
      } else {
        postingTypeGroup.style.display = 'none';
        postingTypeSelect.removeAttribute('required');
        postingTypeSelect.value = '';
      }

      var choice = isSHQ ? postingTypeSelect.value : '';

      if (choice === 'directorate') {
        directorateGroup.style.display = '';
        directorateSelect.setAttribute('required', 'required');
      } else {
        directorateGroup.style.display = 'none';
        directorateSelect.removeAttribute('required');
        directorateSelect.value = '';
      }

      if (choice === 'unit') {
        unitGroup.style.display = '';
        unitSelect.setAttribute('required', 'required');
      } else {
        unitGroup.style.display = 'none';
        unitSelect.removeAttribute('required');
        unitSelect.value = '';
      }

      var showOther = (choice === 'directorate' && directorateSelect.value === 'Other') ||
                      (choice === 'unit' && unitSelect.value === 'Other');

      if (showOther) {
        otherGroup.style.display = '';
        otherInput.setAttribute('required', 'required');
      } else {
        otherGroup.style.display = 'none';
        otherInput.removeAttribute('required');
        otherInput.value = '';
      }
    }

    (function() {
      var presentPostingEl = document.querySelector('select[name="presentPosting"]');
      var postingTypeEl = document.getElementById('posting_type_choice');
      var directorateEl = document.getElementById('directorate');
      var unitEl = document.getElementById('unit');
      if (presentPostingEl) presentPostingEl.addEventListener('change', updateShqFields);
      if (postingTypeEl) postingTypeEl.addEventListener('change', updateShqFields);
      if (directorateEl) directorateEl.addEventListener('change', updateShqFields);
      if (unitEl) unitEl.addEventListener('change', updateShqFields);
      updateShqFields();
    })();

    function validateAlphaNumeric(input) {
      // Remove non-alphanumeric characters
      input.value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    
      // Limit length to 12 characters
      //input.value = input.value.substring(0, 12);
    }

    // Function to validate all required fields in the current step
    function validateStep(step) {
        let isValid = true;
        const inputs = step.querySelectorAll('input[required], select[required], textarea[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid'); // Add a class to highlight invalid fields
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return isValid;
    }
    
    function validateIPPIS(input) {
    // Remove unwanted characters (keep only "NI" followed by digits)
    let value = input.value.toUpperCase(); // Force uppercase
    value = value.replace(/[^0-9NI]/g, ''); // Allow only N, I, and digits
    
    // Ensure format is "NI" + exactly 7 digits
    if (value.startsWith("NI")) {
      const digits = value.substring(2); // Get part after "NI"
      if (digits.length > 7) {
        value = "NI" + digits.substring(0, 7); // Trim to 7 digits
      }
    } else {
      // If it doesn't start with "NI", force it
      value = "NI" + value.replace(/[^0-9]/g, '').substring(0, 7);
    }
    
    input.value = value; // Update the input field
  }

  function validateRSA(input) {
    // Convert input to uppercase and remove unwanted characters
    let value = input.value.toUpperCase();
    value = value.replace(/[^0-9PEN]/g, ''); // Allow only P, E, N, and digits

    // Ensure format is "PEN" followed by numbers
    if (value.startsWith("PEN")) {
      const numbers = value.substring(3); // Get part after "PEN"
      value = "PEN" + numbers.replace(/[^0-9]/g, ''); // Ensure only numbers after "PEN"
    } else {
      // If it doesn't start with "PEN", force it
      value = "PEN" + value.replace(/[^0-9]/g, '');
    }

    input.value = value; // Update the input field
  }

  function validatePhoneNumber(input) {
      validateNumber(input);
  }

    // ---- Review step (step 5) ----
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    // Returns what the user actually sees for a field: the selected
    // option's label for <select>s, the raw value for everything else.
    function getFieldDisplayValue(name) {
        var el = document.querySelector('#enrolmentForm [name="' + name + '"]');
        if (!el) return '';
        if (el.tagName === 'SELECT') {
            // An empty value means the placeholder option is still selected —
            // treat that as "nothing chosen" rather than showing the
            // placeholder's own wording (e.g. "--Select Unit--") as if it
            // were a real answer.
            if (!el.value) return '';
            return (el.selectedIndex >= 0 && el.options[el.selectedIndex]) ? el.options[el.selectedIndex].text.trim() : '';
        }
        return (el.value || '').trim();
    }

    var reviewSections = [
        {
            title: 'Personal Information',
            fields: [
                ['Service No', 'serviceNo'],
                ['Surname', 'lname'],
                ['First Name', 'fname'],
                ['Middle Name', 'mname'],
                ['Date of Birth', 'dob'],
                ['Marital Status', 'marital_status'],
                ['Maiden Name', 'maiden_name'],
                ['Gender', 'gender'],
                ['State of Origin', 'state_of_origin'],
                ['LGA', 'lga'],
                ['Town', 'town'],
                ['Email Address', 'email'],
                ['Phone No', 'phone'],
                ['Blood Group', 'bloodType'],
                ['Genotype', 'genotype']
            ]
        },
        {
            title: 'Next of Kin',
            fields: [
                ['Next of Kin Name', 'nok'],
                ['Relationship', 'nok_relationship'],
                ['NOK Phone No', 'nok_phone']
            ]
        },
        {
            title: 'Educational Qualification',
            fields: [
                ['Highest Qualification', 'qualification'],
                ['Name of Institution', 'institution'],
                ['Certificate Number', 'certificate_number'],
                ['Date Obtained', 'date_obtained']
            ]
        },
        {
            title: 'Employment Information',
            fields: [
                ['Date of First Appointment', 'dofa'],
                ['Date of Present Appointment', 'dopa'],
                ['Current Rank', 'currentRank'],
                ['Present Posting', 'presentPosting'],
                ['Directorate', 'directorate'],
                ['Unit', 'unit'],
                ['Other (Please Specify)', 'posting_other']
            ]
        },
        {
            title: 'Banking / Pension Details',
            fields: [
                ['IPPIS No', 'ippis'],
                ['Bank Name', 'bank'],
                ['Account No', 'account_number'],
                ['PFA', 'pfa'],
                ['RSA No', 'rsa'],
                ['NHF No', 'nhf']
            ]
        }
    ];

    function populateReviewStep() {
        var html = '';
        reviewSections.forEach(function(section) {
            html += '<h5 class="review-section-title">' + escapeHtml(section.title) + '</h5>';
            html += '<table class="table table-bordered review-table"><tbody>';
            section.fields.forEach(function(field) {
                var label = field[0], name = field[1];
                var value = getFieldDisplayValue(name);
                var displayValue = value ? escapeHtml(value) : '<span class="review-value-missing">Not provided</span>';
                html += '<tr><th>' + escapeHtml(label) + '</th><td>' + displayValue + '</td></tr>';
            });
            html += '</tbody></table>';
        });
        document.getElementById('reviewContent').innerHTML = html;
    }

        // Attach event listeners to input fields
        document.querySelectorAll('input[name="serviceNo"], input[name="phone"], input[name="nok_phone"]').forEach(input => {
            input.addEventListener('input', () => validatePhoneNumber(input));
        });

        document.querySelectorAll('input[name="lname"], input[name="fname"], input[name="mname"], input[name="maiden_name"], input[name="town"], input[name="nok"], input[name="nok_relationship"]').forEach(input => {
            input.addEventListener('input', () => validateText(input));
        });

        document.querySelectorAll('input[name="certificate_number"]').forEach(input => {
            input.addEventListener('input', () => validateCertificateNumber(input));
        });

        // Fix for Smart Wizard stepContainer height issue and AJAX submission
        $(document).ready(function() {
            var isDuplicateServiceNo = false;
            
            // Check service number existence dynamically
            $('#serviceNo').on('input change blur', function() {
                validateNumber(this);
                var val = $(this).val();
                
                if (val.length === 5) {
                    $.ajax({
                        url: 'check_service_no',
                        type: 'POST',
                        data: { serviceNo: val },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                isDuplicateServiceNo = true;
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Service Number Exists',
                                    text: 'Service Number ' + val + ' already exists in the system! The form has been locked.',
                                    confirmButtonColor: '#d33'
                                });
                                
                                // Lock the form and wizard
                                $('#enrolmentForm').find('input, select, textarea').not('#serviceNo').prop('disabled', true);
                                $('#submitBtn').prop('disabled', true);
                                $('#serviceNo').addClass('is-invalid').css('border-color', '#d9534f');
                                $('.buttonNext, .buttonPrevious, .buttonFinish').addClass('buttonDisabled').css('pointer-events', 'none');
                            } else {
                                isDuplicateServiceNo = false;
                                
                                // Unlock the form and wizard
                                $('#enrolmentForm').find('input, select, textarea').prop('disabled', false);
                                $('#submitBtn').prop('disabled', false);
                                $('#serviceNo').removeClass('is-invalid').css('border-color', '');
                                $('.buttonNext, .buttonPrevious, .buttonFinish').removeClass('buttonDisabled').css('pointer-events', 'auto');
                            }
                        },
                        error: function() {
                            console.error('Error verifying service number');
                        }
                    });
                } else {
                    isDuplicateServiceNo = false;
                    
                    // Unlock form if it is shorter than 5 digits
                    $('#enrolmentForm').find('input, select, textarea').prop('disabled', false);
                    $('#submitBtn').prop('disabled', false);
                    $('#serviceNo').removeClass('is-invalid').css('border-color', '');
                    $('.buttonNext, .buttonPrevious, .buttonFinish').removeClass('buttonDisabled').css('pointer-events', 'auto');
                }
            });

            // Success toast configuration
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            // Handle form submission
            $('#submitBtn').click(function(e) {
                e.preventDefault();
                
                // Validate all steps before submission
                let allValid = true;
                
                // Check all required fields in all steps
                for (let i = 1; i <= 4; i++) {
                    const stepFields = $(`#step-${i}`).find('[required]');
                    stepFields.each(function() {
                        if (!$(this).val().trim()) {
                            $(this).addClass('is-invalid');
                            allValid = false;
                        }
                    });
                }
                
                if (!allValid) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete Form',
                        html: 'Please fill in all required fields in all steps.<br>Red highlighted fields are required.',
                        confirmButtonColor: '#0e7a2e',
                        confirmButtonText: 'OK'
                    });
                    
                    // Go to first step with invalid field
                    $('.wizard_steps a').removeClass('selected');
                    $('.wizard_steps li:first-child a').addClass('selected');
                    $('#wizard').smartWizard('goToStep', 1);
                    
                    return;
                }
                
                // Show loading animation
                Swal.fire({
                    title: 'Saving Record...',
                    html: 'Please wait while we save your data.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Get form data
                const formData = new FormData(document.getElementById('enrolmentForm'));
                formData.append('regEmployee', 'true');
                
                // Submit via AJAX
                $.ajax({
                    url: 'save_record.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log("Response from server:", response);
                        Swal.close();
                        
                        var res = response;
                        if (typeof response === 'string') {
                            try {
                                res = JSON.parse(response);
                            } catch(e) {
                                res = { success: false, message: response };
                            }
                        }
                        
                        if (res && res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Record Saved Successfully!',
                                html: '<strong>Employee record has been saved.</strong>',
                                confirmButtonColor: '#0e7a2e',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                window.location.href = 'myenrolments.php';
                            });
                        } else {
                            var errMsg = (res && res.message) ? res.message : 'Database save failed.';
                            Swal.fire({
                                icon: 'error',
                                title: 'Save Failed',
                                html: '<strong>' + errMsg + '</strong><br><br><small>The record was not saved to the database. Please check the error above.</small>',
                                confirmButtonColor: '#d33',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            html: 'Failed to save record.<br><br><small>Error: ' + error + '</small>',
                            confirmButtonColor: '#d33',
                        });
                    }
                });
            });
            
            // Reset form button
            $('#resetBtn').click(function() {
                Swal.fire({
                    title: 'Reset Form?',
                    text: 'Are you sure you want to reset all form data?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0e7a2e',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, reset it!',
                    cancelButtonText: 'No, keep data'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('enrolmentForm').reset();
                        $('#wizard').smartWizard('reset');
                        
                        Toast.fire({
                            icon: 'success',
                            title: 'Form reset successfully!'
                        });
                    }
                });
            });
            
            // Remove validation error when user starts typing
            $('#enrolmentForm').on('input', 'input, select, textarea', function() {
                if ($(this).val().trim()) {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Smart Wizard initialization.
            //
            // NOTE: build/js/custom.min.js already runs $('#wizard').smartWizard()
            // with no options on page load (its init_SmartWizard()). This plugin
            // version's $.fn.smartWizard() silently ignores a second call's options
            // once an instance already exists for the element (it merges a new
            // options object locally but never applies it to the existing
            // instance) — so passing onLeaveStep/onShowStep here directly would be
            // silently discarded. Instead, patch the callbacks onto whatever
            // instance already exists, falling back to a normal init only if none
            // does yet.
            var wizardCallbacks = {
                onLeaveStep: function(obj, context) {
                    if (isDuplicateServiceNo) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Form Locked',
                            text: 'You cannot proceed because the entered Service Number already exists in the system.',
                            confirmButtonColor: '#d33'
                        });
                        return false;
                    }
                    // Only validate when moving forward
                    if (context.toStep > context.fromStep) {
                        const currentStep = context.fromStep;
                        const stepElement = $('#step-' + currentStep);
                        const inputs = stepElement.find('[required]');
                        let stepValid = true;
                        
                        inputs.each(function() {
                            if (!$(this).val().trim()) {
                                $(this).addClass('is-invalid');
                                stepValid = false;
                                
                                // Add shake animation for first invalid field
                                if ($(this).is(':visible')) {
                                    $(this).focus();
                                }
                            }
                        });
                        
                        if (!stepValid) {
                            Toast.fire({
                                icon: 'warning',
                                title: 'Please fill all required fields'
                            });
                            return false;
                        }
                    }
                    
                    // Fix stepContainer height
                    setTimeout(function() {
                        $('.stepContainer').css('height', '50px');
                    }, 10);
                    
                    return true;
                },
                onShowStep: function(obj, context) {
                    // Remove invalid class from all fields in current step
                    const currentStep = context.toStep;
                    const stepElement = $('#step-' + currentStep);
                    stepElement.find('.is-invalid').removeClass('is-invalid');

                    // Populate the Review step with what's currently in the form.
                    // parseInt guards against the wizard passing step numbers as
                    // strings (e.g. "5"), which a strict === would silently miss.
                    if (parseInt(currentStep, 10) === 5) {
                        populateReviewStep();
                    }

                    // Fix stepContainer height
                    setTimeout(function() {
                        $('.stepContainer').css('height', '50px');
                    }, 10);
                }
            };

            var existingWizard = $('#wizard').data('smartWizard');
            if (existingWizard) {
                $.extend(existingWizard.options, wizardCallbacks);
            } else {
                $('#wizard').smartWizard(wizardCallbacks);
            }
            
            // Set initial height
            setTimeout(function() {
                $('.stepContainer').css('height', '50px');
            }, 100);
            
            // Fix height on window resize
            $(window).resize(function() {
                setTimeout(function() {
                    $('.stepContainer').css('height', '50px');
                }, 50);
            });
            
            // Monitor for any dynamic changes to stepContainer
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        if ($(mutation.target).hasClass('stepContainer')) {
                            $(mutation.target).css('height', '50px');
                        }
                    }
                });
            });
            
            // Start observing
            $('.stepContainer').each(function() {
                observer.observe(this, { attributes: true });
            });
        });

</script>