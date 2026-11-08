/**
 * Premier University Library — Client Scripts
 */
document.addEventListener('DOMContentLoaded', function() {
  // Auto-dismiss alerts after 5 seconds if not persistent
  const alerts = document.querySelectorAll('.alert.show');
  alerts.forEach(function(al) {
    setTimeout(function() {
      al.style.transition = 'opacity 0.5s ease';
      al.style.opacity = '0';
      setTimeout(function() { al.style.display = 'none'; }, 500);
    }, 5000);
  });
});
