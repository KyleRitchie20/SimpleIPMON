<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Support\Facades\Log;

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
    try {
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

            // Calculate expected heartbeats based on time range
            $timeRangeMinutes = max(1, $recentHeartbeats->first()->created_at->diffInMinutes($recentHeartbeats->last()));
            $expectedHeartbeats = ceil($timeRangeMinutes / 5); // 5 minute intervals
            $actualHeartbeats = count($rttData);

            $chartData = [
                'rtt_labels' => $labels,
                'rtt_data' => $rttData,
                'uptime_labels' => $labels,
                'uptime_data' => $uptimeData,
                'overall_uptime' => $expectedHeartbeats > 0 ? round(($actualHeartbeats / $expectedHeartbeats) * 100, 1) : 0
            ];
        }

        return view('dashboard.client', compact('client', 'chartData'));

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        abort(404, 'Client not found');
    } catch (\Exception $e) {
        Log::error('Dashboard client view error: ' . $e->getMessage());
        abort(500, 'Error loading client data');
    }
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
 * Security Note: This script requires PHP 7.4+ and proper file permissions
 */

class IPManagementClient {
    private $uuid = '%UUID%';
    private $dashboardUrl = '%DASHBOARD_URL%';
    private $configFile = '/etc/ip-client.conf';
    private $logFile = '/var/log/ip-client.log';
    private $heartbeatInterval = 300; // 5 minutes
    private $maxRetries = 3;
    private $retryDelay = 2;

    public function __construct() {
        $this->validateEnvironment();
        $this->log('IP Management Client initialized');
    }

    /**
     * Validate the server environment
     */
    private function validateEnvironment(): void {
        if (!extension_loaded('openssl')) {
            $this->log('WARNING: OpenSSL extension not loaded - HTTPS connections may fail', 'WARNING');
        }
        if (!function_exists('shell_exec')) {
            $this->log('ERROR: shell_exec function is disabled - cron setup will fail', 'ERROR');
        }
    }

    public function install(): void {
        try {
            $this->log('Starting installation process...');

            // Validate we can write to config location
            $configDir = dirname($this->configFile);
            if (!is_writable($configDir)) {
                throw new RuntimeException("Cannot write to config directory: $configDir");
            }

            // Validate we can write to log location
            $logDir = dirname($this->logFile);
            if (!is_writable($logDir)) {
                throw new RuntimeException("Cannot write to log directory: $logDir");
            }

            // Write config file with proper permissions
            $config = $this->getConfigContent();
            if (file_put_contents($this->configFile, $config) === false) {
                throw new RuntimeException("Failed to write config file");
            }
            chmod($this->configFile, 0600);
            $this->log("Config file created at " . $this->configFile);

            // Setup cron job
            $this->setupCron();

            $this->log('Installation completed successfully');
            echo "✅ Installation complete!\n";
            echo "📁 Config: " . $this->configFile . "\n";
            echo "📊 Logs: " . $this->logFile . "\n";
            echo "⏰ Heartbeats every 5 minutes\n";
            echo "🔒 Run as root: sudo php " . basename(__FILE__) . " install\n";

        } catch (Exception $e) {
            $this->log('Installation failed: ' . $e->getMessage(), 'ERROR');
            echo "❌ Installation failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    public function sendHeartbeat(): void {
        try {
            $publicIp = $this->getPublicIp();
            $rtt = $this->measureRoundTripTime();

            $data = [
                'ip_address' => $publicIp,
                'rtt_ms' => $rtt,
                'hostname' => gethostname() ?: php_uname('n'),
            ];

            $this->log("Sending heartbeat: IP={$publicIp}, RTT={$rtt}ms");

            $success = $this->sendToServerWithRetry($data);

            if ($success) {
                $this->log('Heartbeat sent successfully');
            } else {
                $this->log('Failed to send heartbeat after retries', 'ERROR');
            }

        } catch (Exception $e) {
            $this->log('Heartbeat error: ' . $e->getMessage(), 'ERROR');
        }
    }

    private function sendToServerWithRetry(array $data, int $retry = 0): bool {
        try {
            $url = rtrim($this->dashboardUrl, '/') . '/api/heartbeat';

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $this->uuid,
                        'User-Agent: IPManagementClient/1.0',
                    ],
                    'content' => json_encode($data),
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response !== false) {
                return true;
            }

            if ($retry < $this->maxRetries) {
                $this->log("Retrying heartbeat ($retry/" . $this->maxRetries . ")...", 'WARNING');
                sleep($this->retryDelay);
                return $this->sendToServerWithRetry($data, $retry + 1);
            }

            return false;

        } catch (Exception $e) {
            $this->log('HTTP request failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function getPublicIp(): string {
        $services = [
            'https://api.ipify.org',
            'https://ipinfo.io/ip',
            'https://ifconfig.me',
        ];

        foreach ($services as $service) {
            try {
                $context = stream_context_create([
                    'http' => ['timeout' => 5, 'ignore_errors' => true],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ]);

                $response = @file_get_contents($service, false, $context);
                if ($response && filter_var(trim($response), FILTER_VALIDATE_IP)) {
                    return trim($response);
                }
            } catch (Exception $e) {
                $this->log('IP service failed: ' . $service . ' - ' . $e->getMessage(), 'WARNING');
            }
        }

        $this->log('All IP services failed, using localhost', 'WARNING');
        return '127.0.0.1';
    }

    private function measureRoundTripTime(): int {
        $startTime = microtime(true);

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'ignore_errors' => true],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            @file_get_contents($this->dashboardUrl . '/api/ping', false, $context);

        } catch (Exception $e) {
            $this->log('RTT measurement failed: ' . $e->getMessage(), 'WARNING');
        }

        $rtt = (int) round((microtime(true) - $startTime) * 1000);
        return max(1, min($rtt, 60000)); // Clamp 1-60s
    }

    private function setupCron(): void {
        try {
            $cronEntry = "*/5 * * * * /usr/bin/php " . escapeshellarg(__FILE__) . " heartbeat >> " . $this->logFile . " 2>&1\n";

            // Check if cron job already exists
            $crontab = @shell_exec('crontab -l 2>/dev/null');
            if (strpos($crontab ?: '', __FILE__) === false) {
                $crontab = $crontab . $cronEntry;
                $result = @shell_exec('echo ' . escapeshellarg($crontab) . ' | crontab -');
                if ($result === null) {
                    $this->log('Cron job installed');
                } else {
                    $this->log('Failed to install cron job', 'ERROR');
                }
            } else {
                $this->log('Cron job already exists');
            }
        } catch (Exception $e) {
            $this->log('Cron setup failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    private function getConfigContent(): string {
        return "; IP Management Client Configuration\n" .
               "; Generated: " . date('Y-m-d H:i:s') . "\n" .
               "; WARNING: Do not share this file\n" .
               "\n" .
               "uuid = " . $this->uuid . "\n" .
               "dashboard_url = " . $this->dashboardUrl . "\n" .
               "heartbeat_interval = " . $this->heartbeatInterval . "\n" .
               "; End of configuration\n";
    }

    private function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";

        echo $logEntry;

        try {
            $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
            if ($result === false) {
                error_log("Failed to write to log file: " . $this->logFile);
            }
        } catch (Exception $e) {
            error_log("Log error: " . $e->getMessage());
        }
    }
}

// CLI Usage with input validation
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'help';

    if (!in_array($command, ['install', 'heartbeat', 'test', 'help'])) {
        echo "❌ Invalid command: $command\n";
        $command = 'help';
    }

    $client = new IPManagementClient();

    switch ($command) {
        case 'install':
            if (posix_getuid() !== 0) {
                echo "❌ This command must be run as root\n";
                exit(1);
            }
            $client->install();
            break;
        case 'heartbeat':
            $client->sendHeartbeat();
            break;
        case 'test':
            echo "🔧 Testing IP Management Client\n";
            echo "UUID: " . $client->uuid . "\n";
            echo "Dashboard: " . $client->dashboardUrl . "\n";
            $client->sendHeartbeat();
            break;
        case 'help':
        default:
            echo "IP Management Client - Usage:\n";
            echo "  php " . basename(__FILE__) . " install    - Install client (requires root)\n";
            echo "  php " . basename(__FILE__) . " heartbeat  - Send heartbeat\n";
            echo "  php " . basename(__FILE__) . " test       - Test configuration\n";
            echo "  php " . basename(__FILE__) . " help       - Show this help\n";
            break;
    }
} else {
    die("❌ This script must be run from command line only\n");
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
