<?php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$segments = explode('/', trim($scriptPath, '/'));
$basePath = isset($segments[0]) && $segments[0] !== '' ? '/' . $segments[0] : '';
?>
  <footer class="footer">
    <p>&copy; 2026 <strong>JovemConnect</strong> - promovendo inclusao e oportunidades atraves da tecnologia e IA.</p>
    <div class="footer-links">
      <a href="#">Termos</a>
      <a href="#">Privacidade</a>
      <a href="#">Contato</a>
    </div>
  </footer>

  <script src="<?php echo $basePath; ?>/script.js"></script>
</body>
</html>
