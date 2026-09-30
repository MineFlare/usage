<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\usage;

use Illuminate\Http\Request;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Location;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use GuzzleHttp\Client as GuzzleClient;

class usageExtensionController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private BlueprintExtensionLibrary $blueprint
    ) {
    }

    public function index(Request $request)
    {
        try {
            $stats = $this->collectStats();
        } catch (\Throwable $e) {
            $stats = $this->emptyStats($e->getMessage());
        }

        if ($request->query('format') === 'json' || $request->wantsJson()) {
            return response()->json($stats);
        }

        return $this->view->make('admin.extensions.usage.index', [
            'root' => '/admin/extensions/usage',
            'blueprint' => $this->blueprint,
            'stats' => $stats,
            'statsJson' => json_encode($stats),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        return redirect('/admin/extensions/usage');
    }

    public function post(Request $request): RedirectResponse
    {
        return redirect('/admin/extensions/usage');
    }

    private function emptyStats(string $error = ''): array
    {
        return [
            'generated_at' => date('c'),
            'error' => $error,
            'totals' => [
                'nodes' => 0,
                'nodes_online' => 0,
                'nodes_offline' => 0,
                'nodes_maintenance' => 0,
                'servers' => 0,
                'servers_suspended' => 0,
                'users' => 0,
                'admins' => 0,
                'locations' => 0,
                'allocations_total' => 0,
                'allocations_used' => 0,
                'wings_versions' => [],
            ],
            'memory' => [
                'total_mb' => 0,
                'allocated_mb' => 0,
                'used_mb' => 0,
                'free_after_alloc_mb' => 0,
                'free_after_used_mb' => 0,
            ],
            'disk' => [
                'total_mb' => 0,
                'allocated_mb' => 0,
                'used_mb' => 0,
                'free_after_alloc_mb' => 0,
                'free_after_used_mb' => 0,
            ],
            'cpu' => [
                'total' => 0,
                'total_threads' => 0,
                'allocated' => 0,
                'used' => 0,
                'free_after_alloc' => 0,
                'free_after_used' => 0,
                'note' => 'CPU is measured in percent of one thread (100 = 1 full thread). Total comes from online Wings nodes only.',
            ],
            'nodes' => [],
        ];
    }

    private function collectStats(): array
    {
        $nodes = Node::query()->with('location')->get();
        $servers = Server::query()->get();

        $totalMemoryMb = (int) $nodes->sum('memory');
        $totalDiskMb = (int) $nodes->sum('disk');
        $allocMemoryMb = (int) $servers->sum('memory');
        $allocDiskMb = (int) $servers->sum('disk');
        $allocCpu = (int) $servers->sum('cpu');

        $usedMemoryMb = 0.0;
        $usedDiskMb = 0.0;
        $usedCpu = 0.0;
        $totalCpu = 0;
        $onlineNodes = 0;
        $offlineNodes = 0;
        $wingsVersions = [];
        $nodeRows = [];

        $serversByNode = $servers->groupBy('node_id');
        $daemon = null;
        try {
            $daemon = app(DaemonConfigurationRepository::class);
        } catch (\Throwable $e) {
            $daemon = null;
        }

        foreach ($nodes as $node) {
            $nodeServers = $serversByNode->get($node->id, collect());
            $locationName = '—';
            try {
                if ($node->location) {
                    $locationName = $node->location->short ?: ($node->location->long ?: '—');
                }
            } catch (\Throwable $e) {
                $locationName = '—';
            }

            $row = [
                'id' => $node->id,
                'name' => $node->name,
                'fqdn' => $node->fqdn,
                'location' => $locationName,
                'maintenance' => (bool) $node->maintenance_mode,
                'online' => false,
                'wings' => null,
                'cpu_threads' => 0,
                'memory_total_mb' => (int) $node->memory,
                'disk_total_mb' => (int) $node->disk,
                'memory_overallocate' => (int) $node->memory_overallocate,
                'disk_overallocate' => (int) $node->disk_overallocate,
                'memory_allocated_mb' => (int) $nodeServers->sum('memory'),
                'disk_allocated_mb' => (int) $nodeServers->sum('disk'),
                'cpu_allocated' => (int) $nodeServers->sum('cpu'),
                'memory_used_mb' => 0.0,
                'disk_used_mb' => 0.0,
                'cpu_used' => 0.0,
                'servers' => $nodeServers->count(),
                'servers_running' => 0,
                'error' => null,
            ];

            try {
                $info = $this->safeSystemInformation($daemon, $node);
                $row['online'] = true;
                $onlineNodes++;

                $version = null;
                if (is_array($info) && !empty($info['version']) && is_string($info['version'])) {
                    $version = $info['version'];
                }
                $row['wings'] = $version;
                if ($version) {
                    $wingsVersions[$version] = true;
                }

                $threads = 0;
                if (is_array($info)) {
                    $threads = (int) (
                        $this->arrGet($info, 'system.cpu_threads')
                        ?? $this->arrGet($info, 'cpu_count')
                        ?? $this->arrGet($info, 'system.cpus')
                        ?? 0
                    );
                }
                $row['cpu_threads'] = $threads;
                $totalCpu += $threads * 100;

                $live = $this->fetchNodeLiveUsage($node);
                $row['memory_used_mb'] = $live['memory_mb'];
                $row['disk_used_mb'] = $live['disk_mb'];
                $row['cpu_used'] = $live['cpu'];
                $row['servers_running'] = $live['running'];

                $usedMemoryMb += $live['memory_mb'];
                $usedDiskMb += $live['disk_mb'];
                $usedCpu += $live['cpu'];
            } catch (\Throwable $e) {
                $row['online'] = false;
                $row['error'] = 'Wings unreachable';
                $offlineNodes++;
            }

            $nodeRows[] = $row;
        }

        $suspended = 0;
        try {
            $suspended = $servers->filter(function ($s) {
                return !empty($s->suspended) || (isset($s->status) && $s->status === 'suspended');
            })->count();
        } catch (\Throwable $e) {
            $suspended = 0;
        }

        $users = 0;
        $admins = 0;
        $locations = 0;
        $allocTotal = 0;
        $allocUsed = 0;
        try { $users = User::query()->count(); } catch (\Throwable $e) {}
        try { $admins = User::query()->where('root_admin', true)->count(); } catch (\Throwable $e) {}
        try { $locations = Location::query()->count(); } catch (\Throwable $e) {}
        try { $allocTotal = Allocation::query()->count(); } catch (\Throwable $e) {}
        try { $allocUsed = Allocation::query()->whereNotNull('server_id')->count(); } catch (\Throwable $e) {}

        return [
            'generated_at' => date('c'),
            'error' => null,
            'totals' => [
                'nodes' => $nodes->count(),
                'nodes_online' => $onlineNodes,
                'nodes_offline' => $offlineNodes,
                'nodes_maintenance' => $nodes->where('maintenance_mode', true)->count(),
                'servers' => $servers->count(),
                'servers_suspended' => $suspended,
                'users' => $users,
                'admins' => $admins,
                'locations' => $locations,
                'allocations_total' => $allocTotal,
                'allocations_used' => $allocUsed,
                'wings_versions' => array_keys($wingsVersions),
            ],
            'memory' => [
                'total_mb' => $totalMemoryMb,
                'allocated_mb' => $allocMemoryMb,
                'used_mb' => round($usedMemoryMb, 1),
                'free_after_alloc_mb' => max(0, $totalMemoryMb - $allocMemoryMb),
                'free_after_used_mb' => max(0, $totalMemoryMb - $usedMemoryMb),
            ],
            'disk' => [
                'total_mb' => $totalDiskMb,
                'allocated_mb' => $allocDiskMb,
                'used_mb' => round($usedDiskMb, 1),
                'free_after_alloc_mb' => max(0, $totalDiskMb - $allocDiskMb),
                'free_after_used_mb' => max(0, $totalDiskMb - $usedDiskMb),
            ],
            'cpu' => [
                'total' => $totalCpu,
                'total_threads' => (int) ($totalCpu / 100),
                'allocated' => $allocCpu,
                'used' => round($usedCpu, 1),
                'free_after_alloc' => max(0, $totalCpu - $allocCpu),
                'free_after_used' => max(0, $totalCpu - $usedCpu),
                'note' => 'CPU is measured in percent of one thread (100 = 1 full thread). Total comes from online Wings nodes only.',
            ],
            'nodes' => $nodeRows,
        ];
    }

    private function safeSystemInformation($daemon, Node $node): array
    {
        if (!$daemon) {
            throw new \RuntimeException('Daemon repository unavailable');
        }

        $daemon->setNode($node);

        try {
            $ref = new \ReflectionMethod($daemon, 'getSystemInformation');
            if ($ref->getNumberOfParameters() >= 1) {
                $result = $daemon->getSystemInformation(2);
            } else {
                $result = $daemon->getSystemInformation();
            }
        } catch (\ArgumentCountError $e) {
            $result = $daemon->getSystemInformation();
        }

        return is_array($result) ? $result : [];
    }

    private function arrGet(array $array, string $path)
    {
        $segments = explode('.', $path);
        $value = $array;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private function fetchNodeLiveUsage(Node $node): array
    {
        $out = [
            'memory_mb' => 0.0,
            'disk_mb' => 0.0,
            'cpu' => 0.0,
            'running' => 0,
        ];

        try {
            $listen = $node->daemonListen ?? $node->daemon_listen ?? 8080;
            $address = method_exists($node, 'getConnectionAddress')
                ? $node->getConnectionAddress()
                : ($node->scheme . '://' . $node->fqdn . ':' . $listen);

            $token = method_exists($node, 'getDecryptedKey')
                ? $node->getDecryptedKey()
                : '';

            $client = new GuzzleClient([
                'base_uri' => rtrim($address, '/') . '/',
                'timeout' => 3,
                'connect_timeout' => 2,
                'http_errors' => false,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            $response = $client->get('api/servers');
            $payload = json_decode((string) $response->getBody(), true);
            if (!is_array($payload)) {
                return $out;
            }

            $list = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : $payload;
            foreach ($list as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $util = [];
                if (isset($entry['utilization']) && is_array($entry['utilization'])) {
                    $util = $entry['utilization'];
                } elseif (isset($entry['resources']) && is_array($entry['resources'])) {
                    $util = $entry['resources'];
                }
                $state = $entry['state'] ?? ($util['state'] ?? 'offline');
                if ($state === 'running' || $state === 'starting') {
                    $out['running']++;
                }
                $out['memory_mb'] += ((float) ($util['memory_bytes'] ?? 0)) / 1024 / 1024;
                $out['disk_mb'] += ((float) ($util['disk_bytes'] ?? 0)) / 1024 / 1024;
                $out['cpu'] += (float) ($util['cpu_absolute'] ?? 0);
            }
        } catch (\Throwable $e) {
            // keep zeros
        }

        return $out;
    }
}
