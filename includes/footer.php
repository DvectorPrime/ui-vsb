  </main>

  <div id="toasts" class="toasts" role="status"></div>
  <div id="modalRoot"></div>

  <footer class="footer">
    © <?php echo date('Y'); ?> University of Ibadan Maintenance Directorate · Vehicle Service Booking Platform
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const menuBtn = document.getElementById('menuBtn');
      const nav = document.getElementById('nav');
      if (menuBtn && nav) {
        menuBtn.addEventListener('click', function () {
          const isOpen = nav.classList.toggle('open');
          menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
      }
    });
  </script>
</body>
</html>
