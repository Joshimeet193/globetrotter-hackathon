// GlobeTrotter - small UI polish, no framework needed.
document.addEventListener('DOMContentLoaded', function () {
  var navbar = document.querySelector('.navbar');
  // Shrink navbar + stronger shadow after scrolling a bit
  if (navbar) {
    var onScroll = function () {
      if (window.scrollY > 20) {
        navbar.classList.add('navbar-scrolled');
      } else {
        navbar.classList.remove('navbar-scrolled');
      }
    };
    window.addEventListener('scroll', onScroll);
    onScroll();
  }
  // Auto-close the mobile menu after tapping a link (Bootstrap doesn't do this by default)
  var navCollapse = document.querySelector('.navbar-collapse');
  if (navCollapse) {
    navCollapse.querySelectorAll('a.nav-link, a.navbar-profile-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (navCollapse.classList.contains('show') && window.bootstrap) {
          var instance = window.bootstrap.Collapse.getInstance(navCollapse) ||
                          new window.bootstrap.Collapse(navCollapse);
          instance.hide();
        }
      });
    });
  }
});
