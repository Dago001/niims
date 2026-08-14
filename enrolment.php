<?php
 session_start();
  
  if (!isset($_SESSION['username'])) {
      //require_once('login.php');
      //exit;
        header("Location: index");
        die();
  }
  
   require_once ('db/db.php');
   if ($_SESSION['role'] == "DataEntry") 
  {
    include 'sidebar-dataentry.php';
  }
  if ($_SESSION['role'] == "Supervisor")
  {
    include 'sidebar-supervisor.php';
  }
  if ($_SESSION['role'] == "SuperAdmin")
  {
    include 'sidebar.php';
  }
   include 'menu-footer-buttons.php';
   //include 'top-navigation.php';
?>
      <!-- ============================================================== -->
      <!-- End Left Sidebar - style you can find in sidebar.scss  -->
      <!-- ============================================================== -->
      <!-- ============================================================== -->
      <!-- Page wrapper  -->
      <!-- ============================================================== -->
      <div class="page-wrapper">
        <!-- ============================================================== -->
        <!-- Bread crumb and right sidebar toggle -->
        <!-- ============================================================== -->
        <div class="page-breadcrumb">
          <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
              <h4 class="page-title">NIS Identity Management System</h4>
              <div class="ms-auto text-end">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                      Library
                    </li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>
        <!-- ============================================================== -->
        <!-- End Bread crumb and right sidebar toggle -->
        <!-- ============================================================== -->
        <!-- ============================================================== -->
        <!-- Container fluid  -->
        <!-- ============================================================== -->
        <div class="container-fluid">
          <!-- ============================================================== -->
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">
            <div class="col-md-6">
              <div class="card">
                <form class="form-horizontal" enctype="multipart/form-data" action="print" method="POST">
                  <div class="card-body">
                    <h4 class="card-title">Personnel Info</h4>
                    <div class="form-group row">
                      <label
                        for="fname"
                        class="col-sm-3 text-end control-label col-form-label"
                        >First Name</label
                      >
                      <div class="col-sm-9">
                        <input type="text" class="form-control" name="fname" id="fname" placeholder="First Name Here" />
                      </div>
                    </div>
                      <div class="form-group row">
                        <label
                          for="mname"
                          class="col-sm-3 text-end control-label col-form-label"
                          >Middle Name</label
                        >
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="mname" id="mname" placeholder="Middle Name Here" />
                        </div>
                      </div>
                      <div class="form-group row">
                        <label
                          for="lname"
                          class="col-sm-3 text-end control-label col-form-label"
                          >Last Name</label
                        >
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="lname" id="lname" placeholder="Last Name Here" />
                        </div>
                      </div>
                    <div class="form-group row">
                      <label
                        for="serviceNo"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Service No.</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="text"
                          class="form-control"
                          id="serviceNo"
                          name="serviceNo"
                          placeholder="NIS Service No."
                        />
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="expectedRank" class="col-sm-3 text-end control-label col-form-label">Current Rank </label>
                      <div class="col-sm-6"><select name="currentRank"  class="form-select" id="currentRank">
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
                        <option value="Asst. Inspector">AII</option>
                        <option value="Chief Immigration Asst.">CIA</option>
                        <option value="Senior Immigration Asst.">SIA</option>
                        <option value="Immigration Asst. 1">IA-1</option>
                        <option value="Immigration Asst. 2">IA-2</option>
                        <option value="Immigration Asst. 3">IA-3</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-group row">
                        <label for="bloodGroup" class="col-sm-3 text-end control-label col-form-label">Blood Type</label>
                        <div class="col-md-6">
                        <select name="bloodType" class="form-select shadow-none" required="">
                          <option value="">--Select Gender--</option>
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

                    <div class="form-group row">
                      <label for="gender" class="col-sm-3 text-end control-label col-form-label">Gender</label>
                      <div class="col-md-6">
                      <select name="gender" class="form-select shadow-none" required="">
                        <option value="">--Select Gender--</option>
                        <option value="Male"> Male </option>
                        <option value="Female"> Female </option>
                      </select>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label
                        for="email"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Email Address</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="text"
                          class="form-control"
                          name="email"
                          id="email"
                          placeholder="Email Address"
                        />
                      </div>
                    </div>
                    <div class="form-group row">
                      <label
                        for="cono1"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Phone No</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="text"
                          class="form-control"
                          name="phone"
                          id="phone"
                          placeholder="Phone Number"
                        />
                      </div>
                    </div>
                    <div class="form-group row">
                      <label
                        for="cono1"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Current Location</label
                      >
                      <div class="col-md-9">
                          <select name="presentPosting"  class="form-select shadow-none">
                              <option value="">--Select Place of Present Posting--</option>
                              <option value="Other">Other</option>
                              <option value="Service Headquarters">Service Headquarters (SHQ)</option>
                              <option value="Zone A Lagos">Zone ‘A’ Lagos</option>
                              <option value="Zone B Kaduna">Zone ‘B’ Kaduna</option>
                              <option value="Zone C Bauchi">Zone ‘C’ Bauchi</option>
                              <option value="Zone D Minna">Zone ‘D’ Minna</option>
                              <option value="Zone E Owerri">Zone ‘E’ Owerri</option>
                              <option value="Zone F Ibadan">Zone ‘F’ Ibadan</option>
                              <option value="Zone G Benin City">Zone’ G’ Benin City</option>
                              <option value="Zone H Makurdi">Zone ‘H’ Makurdi</option>
                              <option value="FCT">FCT Command</option>
                              <option value="Abia">Abia State Command</option>
                              <option value="Adamawa">Adamawa State Command</option>
                              <option value="Akwa Ibom">Akwa Ibom State Command</option>
                              <option value="Anambra">Anambra State Command</option>
                              <option value="Bauchi">Bauchi State Command</option>
                              <option value="Bayelsa">Bayelsa State Command</option>
                              <option value="Benue">Benue State Command</option>
                              <option value="Borno">Borno State Command</option>
                              <option value="Cross River">Cross River State Command</option>
                              <option value="Delta">Delta State Command</option>
                              <option value="Ebonyi">Ebonyi State Command</option>
                              <option value="Edo">Edo State Command</option>
                              <option value="Ekiti">Ekiti State Command</option>
                              <option value="Enugu">Enugu State Command</option>
                              <option value="Gombe">Gombe State Command</option>
                              <option value="Imo">Imo State Command</option>
                              <option value="Jigawa">Jigawa State Command</option>
                              <option value="Kaduna">Kaduna State Command</option>
                              <option value="Kano">Kano State Command</option>
                              <option value="Katsina">Katsina State Command</option>
                              <option value="Kebbi">Kebbi State Command</option>
                              <option value="Kogi">Kogi State Command</option>
                              <option value="Kwara">Kwara State Command</option>
                              <option value="Lagos">Lagos State Command</option>
                              <option value="Nasarawa">Nasarawa State Command</option>
                              <option value="Niger">Niger State Command</option>
                              <option value="Ogun">Ogun State Command</option>
                              <option value="Ondo">Ondo State Command</option>
                              <option value="Osun">Osun State Command</option>
                              <option value="Oyo">Oyo State Command</option>
                              <option value="Plateau">Plateau State Command</option>
                              <option value="Rivers">Rivers State Command</option>
                              <option value="Sokoto">Sokoto State Command<option>
                              <option value="Taraba">Taraba State Command</option>
                              <option value="Yobe">Yobe State Command</option>
                              <option value="Zamfara">Zamfara State Command</option>
                           </select>
                      </div>
                    </div>

                    <div class="form-group row">
                        <label for="image" class="col-sm-3 text-end control-label col-form-label">Passport Photograph (JPG or PNG)</label>
                        <div class="col-sm-6">
                          <input name="photo" class="form-control" id="photo" type="file" />
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="signature" class="col-sm-3 text-end control-label col-form-label">Signature (JPG or PNG)</label>
                        <div class="col-sm-6">
                          <input name="signature" class="form-control" id="signature" type="file" />
                        </div>
                    </div>
                  </div>
                  <div class="border-top">
                    <div class="card-body">
                      <button type="submit" name="regEmployee" class="btn btn-primary">
                        Submit
                      </button>
                    </div>
                  </div>
                </form>
              </div>
             
            </div>
            
            </div>
          </div>
          <!-- editor -->
          <
          <!-- ============================================================== -->
          <!-- End PAge Content -->
          <!-- ============================================================== -->
          <!-- ============================================================== -->
          <!-- Right sidebar -->
          <!-- ============================================================== -->
          <!-- .right-sidebar -->
          <!-- ============================================================== -->
          <!-- End Right sidebar -->
          <!-- ============================================================== -->
        </div>
        <!-- ============================================================== -->
        <!-- End Container fluid  -->
        <!-- ============================================================== -->
        <!-- ============================================================== -->
        <!-- footer -->
        <!-- ============================================================== -->
        <?php include ('footer-enrol.php'); ?>
        <!-- ============================================================== -->
        <!-- End footer -->
        <!-- ============================================================== -->
      