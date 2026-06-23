    </main><!-- /main -->
  </div><!-- /main area -->
</div><!-- /flex wrapper -->

<script>
// Global CSRF token for AJAX
window.CSRF_TOKEN = '<?= csrf_token() ?>';

// SweetAlert2 defaults
const Toast = Swal.mixin({
  toast: true, position: 'top-end', showConfirmButton: false,
  timer: 3000, timerProgressBar: true
});

function confirmDelete(url, name = 'this item') {
  Swal.fire({
    title: 'Delete ' + name + '?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then(r => { if (r.isConfirmed) window.location.href = url; });
}

function ajaxPost(url, data, onSuccess, onError) {
  data._csrf = window.CSRF_TOKEN;
  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN },
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(onSuccess)
  .catch(onError || console.error);
}
</script>
</body>
</html>
