
        <!-- footer content -->
        <footer>
          <div class="pull-right">
          All Rights Reserved by Nigeria Immigration Service. Designed and Developed by
          <a href="https://www.immigration.gov.ng">NIS Web Team</a>.
          </div>
          <div class="clearfix"></div>
        </footer>
        <!-- /footer content -->
      </div>
    </div>
  <!-- All Jquery -->
    <!-- ============================================================== -->
    
    <script src="js/lga.min.js" ></script>
    <!-- jQuery -->
    <script src="vendors/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="vendors/bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- FastClick -->
    <script src="vendors/fastclick/lib/fastclick.js"></script>
    <!-- NProgress -->
    <script src="vendors/nprogress/nprogress.js"></script>
    <!-- Chart.js -->
    <script src="vendors/Chart.js/dist/Chart.min.js"></script>
    <!-- gauge.js -->
    <script src="vendors/gauge.js/dist/gauge.min.js"></script>
    <!-- bootstrap-progressbar -->
    <script src="vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
    <!-- iCheck -->
    <script src="vendors/iCheck/icheck.min.js"></script>
    <!-- Skycons -->
    <script src="vendors/skycons/skycons.js"></script>
    <!-- Flot -->
    <script src="vendors/Flot/jquery.flot.js"></script>
    <script src="vendors/Flot/jquery.flot.pie.js"></script>
    <script src="vendors/Flot/jquery.flot.time.js"></script>
    <script src="vendors/Flot/jquery.flot.stack.js"></script>
    <script src="vendors/Flot/jquery.flot.resize.js"></script>
    <!-- Flot plugins -->
    <script src="vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
    <script src="vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
    <script src="vendors/flot.curvedlines/curvedLines.js"></script>
    <!-- DateJS -->
    <script src="vendors/DateJS/build/date.js"></script>
    <!-- JQVMap -->
    <script src="vendors/jqvmap/dist/jquery.vmap.js"></script>
    <script src="vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
    <script src="vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
    <!-- bootstrap-daterangepicker -->
    <script src="vendors/moment/min/moment.min.js"></script>
    <script src="vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- Custom Theme Scripts -->
    <script src="build/js/custom.min.js"></script>
    
    <script>
      // Service Worker Registration for Offline Mode and Light-Speed Asset Caching
      if ('serviceWorker' in navigator && (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register('sw.js').then(function(registration) {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
          }).catch(function(err) {
            console.warn('ServiceWorker registration failed: ', err);
          });
        });

        // Offline / Online Status Detection Warning Banner
        function updateOnlineStatus() {
          let banner = document.getElementById('offline-indicator');
          if (!navigator.onLine) {
            if (!banner) {
              banner = document.createElement('div');
              banner.id = 'offline-indicator';
              banner.style.position = 'fixed';
              banner.style.top = '0';
              banner.style.left = '0';
              banner.style.width = '100%';
              banner.style.backgroundColor = '#fee2e2';
              banner.style.color = '#ef4444';
              banner.style.borderBottom = '1px solid #fca5a5';
              banner.style.textAlign = 'center';
              banner.style.padding = '8px 15px';
              banner.style.fontSize = '12px';
              banner.style.fontWeight = '600';
              banner.style.zIndex = '99999';
              banner.innerHTML = '<i class="fa fa-exclamation-triangle"></i> You are currently offline. Running in Offline Mode (Serving Cached Content)';
              document.body.appendChild(banner);
              document.body.style.paddingTop = '36px';
            }
          } else {
            if (banner) {
              banner.remove();
              document.body.style.paddingTop = '0';
            }
          }
        }
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
      }
    </script>
	
  </body>
</html>
