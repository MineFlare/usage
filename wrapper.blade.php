<script>
(function () {
  function addUsageTab() {
    var menu = document.querySelector('ul.sidebar-menu');
    if (!menu || document.getElementById('usage-ext-nav')) {
      return;
    }

    var item = document.createElement('li');
    item.id = 'usage-ext-nav';
    var path = window.location.pathname || '';
    if (path.indexOf('/admin/extensions/usage') !== -1) {
      item.className = 'active';
    }

    item.innerHTML = '<a href="/admin/extensions/usage"><i class="fa fa-pie-chart"></i> <span>Usage</span></a>';

    var overview = null;
    menu.querySelectorAll('li > a').forEach(function (link) {
      var href = (link.getAttribute('href') || '').replace(/\/$/, '');
      var text = (link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (text.indexOf('overview') !== -1 || /\/admin$/.test(href)) {
        overview = link.parentElement;
      }
    });

    if (overview && overview.parentNode) {
      if (overview.nextSibling) {
        overview.parentNode.insertBefore(item, overview.nextSibling);
      } else {
        overview.parentNode.appendChild(item);
      }
    } else {
      var header = menu.querySelector('li.header');
      if (header && header.nextSibling) {
        menu.insertBefore(item, header.nextSibling);
      } else {
        menu.insertBefore(item, menu.firstChild);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addUsageTab);
  } else {
    addUsageTab();
  }
})();
</script>
