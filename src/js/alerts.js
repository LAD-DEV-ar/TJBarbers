
/* JS mínimo para cerrar y auto-dismiss */
(function(){
  document.addEventListener('click', function(e){
    if (e.target.matches('.alert-close')) {
      var a = e.target.closest('.alert');
      if (a) a.remove();
    }
  });

  // Auto-dismiss (5s)
  document.querySelectorAll('.alert').forEach(function(a){
    setTimeout(function(){ if (a && a.remove) a.remove(); }, 5000);
  });
})();
