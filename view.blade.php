@php
  $s = isset($stats) && is_array($stats) ? $stats : [];
  $t = isset($s['totals']) && is_array($s['totals']) ? $s['totals'] : [];
  $m = isset($s['memory']) && is_array($s['memory']) ? $s['memory'] : [];
  $d = isset($s['disk']) && is_array($s['disk']) ? $s['disk'] : [];
  $c = isset($s['cpu']) && is_array($s['cpu']) ? $s['cpu'] : [];
  $nodeList = isset($s['nodes']) && is_array($s['nodes']) ? $s['nodes'] : [];
  $statsJsonSafe = isset($statsJson) ? $statsJson : '{}';

  $pct = function ($part, $total) {
      $total = (float) $total;
      if ($total <= 0) {
          return 0;
      }
      return round(((float) $part / $total) * 100, 1);
  };

  $fmtMb = function ($mb) {
      $mb = (float) $mb;
      if ($mb >= 1024 * 1024) {
          return number_format($mb / 1024 / 1024, 2) . ' TB';
      }
      if ($mb >= 1024) {
          return number_format($mb / 1024, 2) . ' GB';
      }
      return number_format($mb, 0) . ' MB';
  };

  $fmtCpu = function ($cpu) {
      return number_format((float) $cpu, 0) . '%';
  };
@endphp

<div class="usage-wrap">
  @if(!empty($s['error']))
    <div class="alert alert-warning">
      Usage collected a fallback dataset because something failed: {{ $s['error'] }}
    </div>
  @endif
  <div class="usage-hero">
    <button type="button" class="btn btn-sm btn-primary usage-refresh" id="usage-refresh-btn">
      <i class="fa fa-refresh"></i> Refresh
    </button>
    <p class="text-muted" style="margin:0 0 8px;">
      Combined resource usage across every node. Generated
      <span id="usage-generated">{{ $s['generated_at'] ?? '—' }}</span>.
    </p>
  </div>

  <div class="usage-stat-grid">
    <div class="usage-kpi">
      <span class="label">Nodes</span>
      <div class="value" id="kpi-nodes">{{ (int) ($t['nodes'] ?? 0) }}</div>
      <div class="sub"><span id="kpi-nodes-online">{{ (int) ($t['nodes_online'] ?? 0) }}</span> online · <span id="kpi-nodes-offline">{{ (int) ($t['nodes_offline'] ?? 0) }}</span> offline</div>
    </div>
    <div class="usage-kpi">
      <span class="label">Servers</span>
      <div class="value" id="kpi-servers">{{ (int) ($t['servers'] ?? 0) }}</div>
      <div class="sub"><span id="kpi-suspended">{{ (int) ($t['servers_suspended'] ?? 0) }}</span> suspended</div>
    </div>
    <div class="usage-kpi">
      <span class="label">Users</span>
      <div class="value" id="kpi-users">{{ (int) ($t['users'] ?? 0) }}</div>
      <div class="sub"><span id="kpi-admins">{{ (int) ($t['admins'] ?? 0) }}</span> administrators</div>
    </div>
    <div class="usage-kpi">
      <span class="label">Allocations</span>
      <div class="value" id="kpi-alloc-used">{{ (int) ($t['allocations_used'] ?? 0) }}</div>
      <div class="sub">of <span id="kpi-alloc-total">{{ (int) ($t['allocations_total'] ?? 0) }}</span> ports assigned</div>
    </div>
    <div class="usage-kpi">
      <span class="label">Locations</span>
      <div class="value" id="kpi-locations">{{ (int) ($t['locations'] ?? 0) }}</div>
      <div class="sub"><span id="kpi-maint">{{ (int) ($t['nodes_maintenance'] ?? 0) }}</span> nodes in maintenance</div>
    </div>
    <div class="usage-kpi">
      <span class="label">Wings versions</span>
      <div class="value" style="font-size:16px;" id="kpi-wings">
        {{ !empty($t['wings_versions']) ? implode(', ', $t['wings_versions']) : 'n/a' }}
      </div>
      <div class="sub">reported by online nodes</div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">Memory</h3>
        </div>
        <div class="box-body">
          <p><strong>Physical total:</strong> <span id="mem-total">{{ $fmtMb($m['total_mb'] ?? 0) }}</span></p>
          <p>Allocated <span id="mem-alloc-label">{{ $fmtMb($m['allocated_mb'] ?? 0) }}</span>
            ({{ $pct($m['allocated_mb'] ?? 0, $m['total_mb'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="mem-alloc-bar" style="width:{{ min(100, $pct($m['allocated_mb'] ?? 0, $m['total_mb'] ?? 0)) }}%;background:#3c8dbc;"></span></div>
          <p style="margin-top:12px;">Live used <span id="mem-used-label">{{ $fmtMb($m['used_mb'] ?? 0) }}</span>
            ({{ $pct($m['used_mb'] ?? 0, $m['total_mb'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="mem-used-bar" style="width:{{ min(100, $pct($m['used_mb'] ?? 0, $m['total_mb'] ?? 0)) }}%;background:#00a65a;"></span></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title">CPU</h3>
        </div>
        <div class="box-body">
          <p><strong>Online threads:</strong> <span id="cpu-threads">{{ (int) ($c['total_threads'] ?? 0) }}</span>
            (<span id="cpu-total">{{ $fmtCpu($c['total'] ?? 0) }}</span>)</p>
          <p>Allocated <span id="cpu-alloc-label">{{ $fmtCpu($c['allocated'] ?? 0) }}</span>
            ({{ $pct($c['allocated'] ?? 0, $c['total'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="cpu-alloc-bar" style="width:{{ min(100, $pct($c['allocated'] ?? 0, $c['total'] ?? 0)) }}%;background:#605ca8;"></span></div>
          <p style="margin-top:12px;">Live used <span id="cpu-used-label">{{ $fmtCpu($c['used'] ?? 0) }}</span>
            ({{ $pct($c['used'] ?? 0, $c['total'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="cpu-used-bar" style="width:{{ min(100, $pct($c['used'] ?? 0, $c['total'] ?? 0)) }}%;background:#f39c12;"></span></div>
          <p class="usage-note">{{ $c['note'] ?? '' }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="box box-warning">
        <div class="box-header with-border">
          <h3 class="box-title">Disk</h3>
        </div>
        <div class="box-body">
          <p><strong>Configured total:</strong> <span id="disk-total">{{ $fmtMb($d['total_mb'] ?? 0) }}</span></p>
          <p>Allocated <span id="disk-alloc-label">{{ $fmtMb($d['allocated_mb'] ?? 0) }}</span>
            ({{ $pct($d['allocated_mb'] ?? 0, $d['total_mb'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="disk-alloc-bar" style="width:{{ min(100, $pct($d['allocated_mb'] ?? 0, $d['total_mb'] ?? 0)) }}%;background:#3c8dbc;"></span></div>
          <p style="margin-top:12px;">Live used <span id="disk-used-label">{{ $fmtMb($d['used_mb'] ?? 0) }}</span>
            ({{ $pct($d['used_mb'] ?? 0, $d['total_mb'] ?? 0) }}%)</p>
          <div class="usage-bar"><span id="disk-used-bar" style="width:{{ min(100, $pct($d['used_mb'] ?? 0, $d['total_mb'] ?? 0)) }}%;background:#dd4b39;"></span></div>
        </div>
      </div>
    </div>
  </div>

  <h4 style="margin:8px 0 12px;">Allocated vs total</h4>
  <div class="usage-graphs">
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">Memory allocated</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-mem-alloc"></canvas>
          <div class="usage-chart-center"><strong id="chart-mem-alloc-pct">{{ $pct($m['allocated_mb'] ?? 0, $m['total_mb'] ?? 0) }}%</strong><span>allocated</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#3c8dbc;"></span>Allocated</span><span id="leg-mem-alloc">{{ $fmtMb($m['allocated_mb'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Unallocated</span><span id="leg-mem-alloc-free">{{ $fmtMb($m['free_after_alloc_mb'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">CPU allocated</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-cpu-alloc"></canvas>
          <div class="usage-chart-center"><strong id="chart-cpu-alloc-pct">{{ $pct($c['allocated'] ?? 0, $c['total'] ?? 0) }}%</strong><span>allocated</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#605ca8;"></span>Allocated</span><span id="leg-cpu-alloc">{{ $fmtCpu($c['allocated'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Free threads</span><span id="leg-cpu-alloc-free">{{ $fmtCpu($c['free_after_alloc'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">Disk allocated</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-disk-alloc"></canvas>
          <div class="usage-chart-center"><strong id="chart-disk-alloc-pct">{{ $pct($d['allocated_mb'] ?? 0, $d['total_mb'] ?? 0) }}%</strong><span>allocated</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#f39c12;"></span>Allocated</span><span id="leg-disk-alloc">{{ $fmtMb($d['allocated_mb'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Unallocated</span><span id="leg-disk-alloc-free">{{ $fmtMb($d['free_after_alloc_mb'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
  </div>

  <h4 style="margin:8px 0 12px;">Live used vs total</h4>
  <div class="usage-graphs">
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">Memory used</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-mem-used"></canvas>
          <div class="usage-chart-center"><strong id="chart-mem-used-pct">{{ $pct($m['used_mb'] ?? 0, $m['total_mb'] ?? 0) }}%</strong><span>used</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#00a65a;"></span>Used now</span><span id="leg-mem-used">{{ $fmtMb($m['used_mb'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Idle / free</span><span id="leg-mem-used-free">{{ $fmtMb($m['free_after_used_mb'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">CPU used</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-cpu-used"></canvas>
          <div class="usage-chart-center"><strong id="chart-cpu-used-pct">{{ $pct($c['used'] ?? 0, $c['total'] ?? 0) }}%</strong><span>used</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#f39c12;"></span>Used now</span><span id="leg-cpu-used">{{ $fmtCpu($c['used'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Idle</span><span id="leg-cpu-used-free">{{ $fmtCpu($c['free_after_used'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
    <div class="box usage-graph-card">
      <div class="box-header with-border"><h3 class="box-title">Disk used</h3></div>
      <div class="box-body">
        <div class="usage-chart-wrap">
          <canvas id="chart-disk-used"></canvas>
          <div class="usage-chart-center"><strong id="chart-disk-used-pct">{{ $pct($d['used_mb'] ?? 0, $d['total_mb'] ?? 0) }}%</strong><span>used</span></div>
        </div>
        <div class="usage-legend">
          <div><span><span class="usage-dot" style="background:#dd4b39;"></span>Used now</span><span id="leg-disk-used">{{ $fmtMb($d['used_mb'] ?? 0) }}</span></div>
          <div><span><span class="usage-dot" style="background:#e5e9f0;"></span>Free</span><span id="leg-disk-used-free">{{ $fmtMb($d['free_after_used_mb'] ?? 0) }}</span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border">
      <h3 class="box-title">Per-node breakdown</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover usage-table">
        <thead>
          <tr>
            <th>Node</th>
            <th>Status</th>
            <th>Servers</th>
            <th>Memory alloc / used / total</th>
            <th>CPU alloc / used / threads</th>
            <th>Disk alloc / used / total</th>
          </tr>
        </thead>
        <tbody id="usage-node-rows">
          @forelse($nodeList as $n)
            <tr>
              <td>
                <strong>{{ $n['name'] }}</strong><br>
                <small class="text-muted">{{ $n['fqdn'] }} · {{ $n['location'] }}</small>
              </td>
              <td>
                @if($n['online'])
                  <span class="usage-pill ok">Online</span>
                @else
                  <span class="usage-pill off">Offline</span>
                @endif
                @if($n['maintenance'])
                  <span class="usage-pill maint">Maintenance</span>
                @endif
                @if($n['wings'])
                  <div><small>Wings {{ $n['wings'] }}</small></div>
                @endif
              </td>
              <td>{{ $n['servers'] }} <small class="text-muted">({{ $n['servers_running'] }} live)</small></td>
              <td>
                {{ $fmtMb($n['memory_allocated_mb']) }} / {{ $fmtMb($n['memory_used_mb']) }} / {{ $fmtMb($n['memory_total_mb']) }}
                @if($n['memory_overallocate'])
                  <div><small class="text-muted">+{{ $n['memory_overallocate'] }}% overallocation</small></div>
                @endif
              </td>
              <td>
                {{ $fmtCpu($n['cpu_allocated']) }} / {{ $fmtCpu($n['cpu_used']) }} / {{ (int) $n['cpu_threads'] }} threads
              </td>
              <td>
                {{ $fmtMb($n['disk_allocated_mb']) }} / {{ $fmtMb($n['disk_used_mb']) }} / {{ $fmtMb($n['disk_total_mb']) }}
                @if($n['disk_overallocate'])
                  <div><small class="text-muted">+{{ $n['disk_overallocate'] }}% overallocation</small></div>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No nodes found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var initial = {!! $statsJsonSafe !!};

  function pct(part, total) {
    total = Number(total) || 0;
    if (total <= 0) return 0;
    return Math.round((Number(part) / total) * 1000) / 10;
  }

  function fmtMb(mb) {
    mb = Number(mb) || 0;
    if (mb >= 1024 * 1024) return (mb / 1024 / 1024).toFixed(2) + ' TB';
    if (mb >= 1024) return (mb / 1024).toFixed(2) + ' GB';
    return Math.round(mb).toLocaleString() + ' MB';
  }

  function fmtCpu(cpu) {
    return Math.round(Number(cpu) || 0).toLocaleString() + '%';
  }

  function doughnut(id, used, total, color) {
    var canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return null;
    var free = Math.max(0, (Number(total) || 0) - (Number(used) || 0));
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Used', 'Remaining'],
        datasets: [{
          data: [Math.max(0, Number(used) || 0), free || 1],
          backgroundColor: [color, '#e5e9f0'],
          borderWidth: 0,
          hoverOffset: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: { legend: { display: false }, tooltip: { enabled: true } }
      }
    });
  }

  var charts = {
    memAlloc: doughnut('chart-mem-alloc', initial.memory && initial.memory.allocated_mb, initial.memory && initial.memory.total_mb, '#3c8dbc'),
    cpuAlloc: doughnut('chart-cpu-alloc', initial.cpu && initial.cpu.allocated, initial.cpu && initial.cpu.total, '#605ca8'),
    diskAlloc: doughnut('chart-disk-alloc', initial.disk && initial.disk.allocated_mb, initial.disk && initial.disk.total_mb, '#f39c12'),
    memUsed: doughnut('chart-mem-used', initial.memory && initial.memory.used_mb, initial.memory && initial.memory.total_mb, '#00a65a'),
    cpuUsed: doughnut('chart-cpu-used', initial.cpu && initial.cpu.used, initial.cpu && initial.cpu.total, '#f39c12'),
    diskUsed: doughnut('chart-disk-used', initial.disk && initial.disk.used_mb, initial.disk && initial.disk.total_mb, '#dd4b39')
  };

  function setChart(chart, used, total) {
    if (!chart) return;
    var free = Math.max(0, (Number(total) || 0) - (Number(used) || 0));
    chart.data.datasets[0].data = [Math.max(0, Number(used) || 0), free || 1];
    chart.update();
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function setBar(id, used, total) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.width = Math.min(100, pct(used, total)) + '%';
  }

  function render(stats) {
    var t = stats.totals || {};
    var m = stats.memory || {};
    var d = stats.disk || {};
    var c = stats.cpu || {};

    setText('usage-generated', stats.generated_at || '—');
    setText('kpi-nodes', t.nodes || 0);
    setText('kpi-nodes-online', t.nodes_online || 0);
    setText('kpi-nodes-offline', t.nodes_offline || 0);
    setText('kpi-servers', t.servers || 0);
    setText('kpi-suspended', t.servers_suspended || 0);
    setText('kpi-users', t.users || 0);
    setText('kpi-admins', t.admins || 0);
    setText('kpi-alloc-used', t.allocations_used || 0);
    setText('kpi-alloc-total', t.allocations_total || 0);
    setText('kpi-locations', t.locations || 0);
    setText('kpi-maint', t.nodes_maintenance || 0);
    setText('kpi-wings', (t.wings_versions && t.wings_versions.length) ? t.wings_versions.join(', ') : 'n/a');

    setText('mem-total', fmtMb(m.total_mb));
    setText('mem-alloc-label', fmtMb(m.allocated_mb) + ' (' + pct(m.allocated_mb, m.total_mb) + '%)');
    setText('mem-used-label', fmtMb(m.used_mb) + ' (' + pct(m.used_mb, m.total_mb) + '%)');
    setBar('mem-alloc-bar', m.allocated_mb, m.total_mb);
    setBar('mem-used-bar', m.used_mb, m.total_mb);

    setText('cpu-threads', c.total_threads || 0);
    setText('cpu-total', fmtCpu(c.total));
    setText('cpu-alloc-label', fmtCpu(c.allocated) + ' (' + pct(c.allocated, c.total) + '%)');
    setText('cpu-used-label', fmtCpu(c.used) + ' (' + pct(c.used, c.total) + '%)');
    setBar('cpu-alloc-bar', c.allocated, c.total);
    setBar('cpu-used-bar', c.used, c.total);

    setText('disk-total', fmtMb(d.total_mb));
    setText('disk-alloc-label', fmtMb(d.allocated_mb) + ' (' + pct(d.allocated_mb, d.total_mb) + '%)');
    setText('disk-used-label', fmtMb(d.used_mb) + ' (' + pct(d.used_mb, d.total_mb) + '%)');
    setBar('disk-alloc-bar', d.allocated_mb, d.total_mb);
    setBar('disk-used-bar', d.used_mb, d.total_mb);

    setText('chart-mem-alloc-pct', pct(m.allocated_mb, m.total_mb) + '%');
    setText('chart-cpu-alloc-pct', pct(c.allocated, c.total) + '%');
    setText('chart-disk-alloc-pct', pct(d.allocated_mb, d.total_mb) + '%');
    setText('chart-mem-used-pct', pct(m.used_mb, m.total_mb) + '%');
    setText('chart-cpu-used-pct', pct(c.used, c.total) + '%');
    setText('chart-disk-used-pct', pct(d.used_mb, d.total_mb) + '%');

    setText('leg-mem-alloc', fmtMb(m.allocated_mb));
    setText('leg-mem-alloc-free', fmtMb(m.free_after_alloc_mb));
    setText('leg-cpu-alloc', fmtCpu(c.allocated));
    setText('leg-cpu-alloc-free', fmtCpu(c.free_after_alloc));
    setText('leg-disk-alloc', fmtMb(d.allocated_mb));
    setText('leg-disk-alloc-free', fmtMb(d.free_after_alloc_mb));
    setText('leg-mem-used', fmtMb(m.used_mb));
    setText('leg-mem-used-free', fmtMb(m.free_after_used_mb));
    setText('leg-cpu-used', fmtCpu(c.used));
    setText('leg-cpu-used-free', fmtCpu(c.free_after_used));
    setText('leg-disk-used', fmtMb(d.used_mb));
    setText('leg-disk-used-free', fmtMb(d.free_after_used_mb));

    setChart(charts.memAlloc, m.allocated_mb, m.total_mb);
    setChart(charts.cpuAlloc, c.allocated, c.total);
    setChart(charts.diskAlloc, d.allocated_mb, d.total_mb);
    setChart(charts.memUsed, m.used_mb, m.total_mb);
    setChart(charts.cpuUsed, c.used, c.total);
    setChart(charts.diskUsed, d.used_mb, d.total_mb);

    var body = document.getElementById('usage-node-rows');
    if (body && Array.isArray(stats.nodes)) {
      if (!stats.nodes.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No nodes found.</td></tr>';
      } else {
        body.innerHTML = stats.nodes.map(function (n) {
          var status = n.online
            ? '<span class="usage-pill ok">Online</span>'
            : '<span class="usage-pill off">Offline</span>';
          if (n.maintenance) status += ' <span class="usage-pill maint">Maintenance</span>';
          if (n.wings) status += '<div><small>Wings ' + n.wings + '</small></div>';
          return '<tr>' +
            '<td><strong>' + (n.name || '') + '</strong><br><small class="text-muted">' + (n.fqdn || '') + ' · ' + (n.location || '') + '</small></td>' +
            '<td>' + status + '</td>' +
            '<td>' + n.servers + ' <small class="text-muted">(' + n.servers_running + ' live)</small></td>' +
            '<td>' + fmtMb(n.memory_allocated_mb) + ' / ' + fmtMb(n.memory_used_mb) + ' / ' + fmtMb(n.memory_total_mb) +
              (n.memory_overallocate ? '<div><small class="text-muted">+' + n.memory_overallocate + '% overallocation</small></div>' : '') + '</td>' +
            '<td>' + fmtCpu(n.cpu_allocated) + ' / ' + fmtCpu(n.cpu_used) + ' / ' + (n.cpu_threads || 0) + ' threads</td>' +
            '<td>' + fmtMb(n.disk_allocated_mb) + ' / ' + fmtMb(n.disk_used_mb) + ' / ' + fmtMb(n.disk_total_mb) +
              (n.disk_overallocate ? '<div><small class="text-muted">+' + n.disk_overallocate + '% overallocation</small></div>' : '') + '</td>' +
            '</tr>';
        }).join('');
      }
    }
  }

  var btn = document.getElementById('usage-refresh-btn');
  function refresh() {
    if (btn) btn.disabled = true;
    fetch('/admin/extensions/usage?format=json', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(render).catch(function () {
      // ignore transient errors
    }).finally(function () {
      if (btn) btn.disabled = false;
    });
  }

  if (btn) btn.addEventListener('click', refresh);
  setInterval(refresh, 30000);
})();
</script>
