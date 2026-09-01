 <!-- top navigation -->
 <div class="top_nav">
          <div class="nav_menu">
            <nav>
              <div class="nav toggle">
                <a id="menu_toggle"><i class="fa fa-bars"></i></a>
              </div>

              <ul class="nav navbar-nav navbar-right">
                <li class="">
                  <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <?php
                    $navPhotoSrc = 'upload/default.png';
                    if (!empty($_SESSION['photo']) && file_exists($_SESSION['photo'])) {
                        $navPhotoSrc = $_SESSION['photo'];
                    } elseif (file_exists('upload/' . $_SESSION['user_id'] . '.png')) {
                        $navPhotoSrc = 'upload/' . $_SESSION['user_id'] . '.png';
                    } elseif (file_exists('images/' . $_SESSION['user_id'] . '.jpg')) {
                        $navPhotoSrc = 'images/' . $_SESSION['user_id'] . '.jpg';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($navPhotoSrc); ?>" alt="" onerror="this.onerror=null; this.src='upload/default.png';"><?php echo htmlspecialchars($_SESSION['username']); ?>
                    <span class=" fa fa-angle-down"></span>
                  </a>
                  <ul class="dropdown-menu dropdown-usermenu pull-right">
                    <li><a href="profile.php"> Profile</a></li>
                    <li><a href="changePassword.php">Change Password</a></li>
                    <li><a href="logout.php"><i class="fa fa-sign-out pull-right"></i> Log Out</a></li>
                  </ul>
                </li>

                <li role="presentation" class="dropdown">
                 <!-- <a href="javascript:;" class="dropdown-toggle info-number" data-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-envelope-o"></i>
                    <span class="badge bg-green">6</span>
                  </a>-->
                  <ul id="menu1" class="dropdown-menu list-unstyled msg_list" role="menu">
                  <!--  <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>-->
                    <li>
                      <div class="text-center">
                        <a>
                          <strong>See All Alerts</strong>
                          <i class="fa fa-angle-right"></i>
                        </a>
                      </div>
                    </li>
                  </ul>
                </li>
              </ul>
            </nav>
          </div>
        </div>
        <!-- /top navigation -->

        <!-- Client-side 20-minute Inactivity Auto-Logout -->
        <script>
        (function() {
            var idleTimer;
            var TIMEOUT_MS = 20 * 60 * 1000; // 20 minutes in milliseconds

            function resetIdleTimer() {
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    window.location.href = 'logout.php?reason=inactivity';
                }, TIMEOUT_MS);
            }

            // Bind user activity listeners
            window.onload = resetIdleTimer;
            document.onmousemove = resetIdleTimer;
            document.onkeypress = resetIdleTimer;
            document.onclick = resetIdleTimer;
            document.onscroll = resetIdleTimer;
            document.ontouchstart = resetIdleTimer;
        })();
        </script>