<?php

namespace App\Http\Controllers;

use App\Models\Client;

class DashboardController
{
    public function index()
    {
        $clients = Client::orderBy('last_heartbeat', 'desc')->get();
        $stats = [
            'total_clients' => $clients->count(),
            'online_clients' => $clients->where('is_online', true)->count(),
            'offline_clients' => $clients->where('is_online', false)->count(),
            'avg_rtt_all' => $clients->avg('average_rtt_1h'),
        ];

        return view('dashboard.index', compact('clients', 'stats'));
    }

public function showClient($clientId)
{
    $client = Client::with(['heartbeats'])->findOrFail($clientId);
    
    // Get last 48 heartbeats (covers ~4 hours of 5min intervals)
    $recentHeartbeats = $client->heartbeats()
        ->latest()
        ->limit(48)
        ->orderBy('created_at')
        ->get();
    
    if ($recentHeartbeats->isEmpty()) {
        // NO DATA - Return empty arrays
        $chartData = [
            'rtt_labels' => [],
            'rtt_data' => [],
            'uptime_labels' => [],
            'uptime_data' => [],
            'overall_uptime' => 0
        ];
    } else {
        // REAL DATA - Group by hour:minute
        $rttData = [];
        $uptimeData = [];
        $labels = [];
        
        foreach ($recentHeartbeats as $heartbeat) {
            $label = $heartbeat->created_at->format('H:i');
            if (!in_array($label, $labels)) {
                $labels[] = $label;
                $rttData[] = round($heartbeat->rtt_ms);
                $uptimeData[] = 100; // Heartbeat received = 100% uptime
            }
        }
        
        $chartData = [
            'rtt_labels' => $labels,
            'rtt_data' => $rttData,
            'uptime_labels' => $labels,
            'uptime_data' => $uptimeData,
            'overall_uptime' => count($rttData) > 0 ? round((count($rttData) / 48) * 100, 1) : 0
        ];
    }
    
    return view('dashboard.client', compact('client', 'chartData'));
}



    

    public function downloadInstaller($clientId)
    {
        $client = Client::findOrFail($clientId);
        $installerContent = $this->generateInstaller($client);

        return response($installerContent, 200)
            ->header('Content-Type', 'application/x-php')
            ->header('Content-Disposition', 'attachment; filename="install_' . $client->name . '.php"');
    }

    private function generateInstaller($client): string
{
    $dashboardUrl = config('app.url', 'http://localhost:8000');
    $uuid = $client->uuid;
    
    $installerContent = <<<'PHP'
<?php
/**
 * IP Management System Client Installer
 * Client: %CLIENT_NAME%
 * Generated: %GENERATED_DATE%
 */

class IPManagementClient {
    private $uuid = '%UUID%';
    private $dashboardUrl = '%DASHBOARD_URL%';
    private $configFile = '/etc/ip-client.conf';
    private $logFile = '/var/log/ip-client.log';
    private $heartbeatInterval = 300; // 5 minutes

    public function __construct() {
        $this->log('IP Management Client initialized');
    }

    public function install() {
        $this->log('Starting installation process...');
        
        // Write config file
        $config = $this->getConfigContent();
        @file_put_contents($this->configFile, $config);
        @chmod($this->configFile, 0600);
        $this->log("Config file created at " . $this->configFile);

        // Setup cron job
        $this->setupCron();
        
        $this->log('Installation completed successfully');
        echo "✅ Installation complete!\n";
        echo "📁 Config: " . $this->configFile . "\n";
        echo "📊 Logs: " . $this->logFile . "\n";
        echo "⏰ Heartbeats every 5 minutes\n";
    }

    public function sendHeartbeat() {
        $publicIp = $this->getPublicIp();
        $rtt = $this->measureRoundTripTime();

        $data = [
            'ip_address' => $publicIp,
            'rtt_ms' => $rtt,
            'hostname' => gethostname() ?: php_uname('n'),
        ];

        $this->log("Sending heartbeat: IP={$publicIp}, RTT={$rtt}ms");

        $success = $this->sendToServer($data);
        
        if ($success) {
            $this->log('Heartbeat sent successfully');
        } else {
            $this->log('Failed to send heartbeat', 'ERROR');
        }
    }

    private function getPublicIp(): string {
        $services = [
            'https://api.ipify.org',
            'https://ipinfo.io/ip',
            'https://ifconfig.me',
        ];

        foreach ($services as $service) {
            $context = stream_context_create([
                'http' => ['timeout' => 5, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => false],
            ]);
            
            $response = @file_get_contents($service, false, $context);
            if ($response && filter_var(trim($response), FILTER_VALIDATE_IP)) {
                return trim($response);
            }
        }
        return 'UNKNOWN';
    }

    private function measureRoundTripTime(): int {
        $startTime = microtime(true);
        
        $context = stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false],
        ]);
        
        @file_get_contents($this->dashboardUrl . '/api/ping', false, $context);
        
        $rtt = (int) round((microtime(true) - $startTime) * 1000);
        return max(1, min($rtt, 60000)); // Clamp 1-60s
    }

    private function sendToServer(array $data): bool {
        $url = rtrim($this->dashboardUrl, '/') . '/api/heartbeat';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->uuid,
                ],
                'content' => json_encode($data),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => false],
        ]);

        $response = @file_get_contents($url, false, $context);
        return $response !== false;
    }

    private function setupCron(): void {
        $cronEntry = "*/5 * * * * /usr/bin/php " . escapeshellarg(__FILE__) . " heartbeat >> " . $this->logFile . " 2>&1\n";
        
        $crontab = shell_exec('crontab -l 2>/dev/null');
        if (strpos($crontab ?: '', __FILE__) === false) {
            $crontab = $crontab . $cronEntry;
            shell_exec('echo ' . escapeshellarg($crontab) . ' | crontab -');
            $this->log('Cron job installed');
        } else {
            $this->log('Cron job already exists');
        }
    }

    private function getConfigContent(): string {
        return "; IP Management Client Configuration\n" .
               "; Generated: " . date('Y-m-d H:i:s') . "\n\n" .
               "uuid = " . $this->uuid . "\n" .
               "dashboard_url = " . $this->dashboardUrl . "\n" .
               "heartbeat_interval = " . $this->heartbeatInterval . "\n";
    }

    private function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";
        
        echo $logEntry;
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'help';
    $client = new IPManagementClient();

    switch ($command) {
        case 'install':
            $client->install();
            break;
        case 'heartbeat':
            $client->sendHeartbeat();
            break;
        case 'test':
            echo "UUID: " . $client::$uuid . "\n";
            echo "Dashboard: " . $client::$dashboardUrl . "\n";
            $client->sendHeartbeat();
            break;
        default:
            echo "Usage: php " . basename(__FILE__) . " [install|heartbeat|test]\n";
            break;
    }
} else {
    die("Run from command line only\n");
}
?>
PHP;

    // REPLACE PLACEHOLDERS
    $installerContent = str_replace(
        ['%UUID%', '%DASHBOARD_URL%', '%CLIENT_NAME%', '%GENERATED_DATE%'],
        [$uuid, $dashboardUrl, $client->name, now()->format('Y-m-d H:i:s')],
        $installerContent
    );

    return $installerContent;
}

}