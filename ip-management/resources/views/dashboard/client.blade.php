@extends('layouts.app')

@section('content')
<div style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
    <!-- Header -->
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
        <a href="{{ route('dashboard') }}" style="display: inline-flex; align-items: center; padding: 0.75rem 1.25rem; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 8px; font-weight: 500;">
            ← Back to Dashboard
        </a>
        <div>
            <h1 style="font-size: 2.25rem; font-weight: 700; color: #1f2937; margin: 0;">{{ $client->name }}</h1>
            @php $status = $client->status ?? 'OFFLINE'; @endphp
            <span style="padding: 0.5rem 1rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; 
                background: {{ $status === 'ONLINE' ? '#dcfce7' : ($status === 'OFFLINE' ? '#fee2e2' : '#fef3c7') }}; 
                color: {{ $status === 'ONLINE' ? '#166534' : ($status === 'OFFLINE' ? '#dc2626' : '#d97706') }};">
                {{ $status }}
            </span>
        </div>
        <div style="margin-left: auto;">
            <a href="{{ route('clients.installer', $client->id) }}" style="padding: 1rem 2rem; background: linear-gradient(135deg, #0284c7, #0369a1); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                📥 Download Installer
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 0.875rem; color: #6b7280;">Status</div>
            <div style="font-size: 1.875rem; font-weight: 700; color: #1f2937;">{{ $status }}</div>
        </div>
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 0.875rem; color: #6b7280;">Public IP</div>
            <div style="font-size: 1.5rem; font-family: monospace;">{{ $client->public_ip ?? 'Not reported' }}</div>
        </div>
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 0.875rem; color: #6b7280;">RTT 1h Avg</div>
            @php $rtt = round($client->average_rtt_1h ?? 0, 1); @endphp
            <div style="font-size: 1.875rem; font-weight: 700; color: {{ $rtt > 100 ? '#ef4444' : '#10b981' }}">{{ $rtt }}ms</div>
        </div>
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 0.875rem; color: #6b7280;">Uptime 24h</div>
            @php $uptime = round($client->uptime_percentage ?? 0, 1); @endphp
            <div style="font-size: 1.875rem; font-weight: 700; color: {{ $uptime > 95 ? '#10b981' : ($uptime > 80 ? '#f59e0b' : '#ef4444') }}">{{ $uptime }}%</div>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <!-- PING CHART (RTT) -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">📊 Ping Chart (RTT)</h2>
                <p style="color: #6b7280; font-size: 0.875rem; margin: 0.25rem 0 0;">Last 24 hours - Average latency</p>
            </div>
            <div style="padding: 1.5rem;">
                <div style="height: 300px; position: relative;">
                     <canvas id="rttChart"></canvas>
                </div>
                <div style="text-align: center; margin-top: 1rem; color: #6b7280; font-size: 0.875rem;">
                    No ping data yet? Run the installer on client machine.
                </div>
            </div>
        </div>

        <!-- UPTIME CHART -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">📈 Uptime Chart</h2>
                <p style="color: #6b7280; font-size: 0.875rem; margin: 0.25rem 0 0;">Last 24 hours - Availability</p>
            </div>
            <div style="padding: 1.5rem;">
                <div style="height: 300px; position: relative;">
                 <canvas id="uptimeChart"></canvas>
                </div>
                <div style="text-align: center; margin-top: 1rem; color: #6b7280; font-size: 0.875rem;">
                    Uptime calculated from heartbeat success rate.
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Heartbeats & Config -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        <!-- Recent Heartbeats -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Recent Heartbeats (Last 20)</h2>
            </div>
            <div style="max-height: 500px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f9fafb;">
                        <tr>
                            <th style="padding: 1rem 1rem 1rem 1.5rem; text-align: left; font-weight: 600; color: #374151;">Time</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">IP Address</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">RTT</th>
                            <th style="padding: 1rem; text-align: right; font-weight: 600; color: #374151;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($client->heartbeats()->latest()->limit(20)->get() as $heartbeat)
                        <tr style="border-top: 1px solid #f3f4f6;">
                            <td style="padding: 1rem 1rem 1rem 1.5rem; color: #6b7280;">{{ $heartbeat->created_at->format('H:i:s') }}</td>
                            <td style="padding: 1rem; font-family: monospace;">
                                <code style="background: #f9fafb; padding: 0.25rem 0.5rem; border-radius: 4px;">{{ $heartbeat->ip_address }}</code>
                            </td>
                            <td style="padding: 1rem; font-family: monospace; font-weight: 500; 
                                color: {{ $heartbeat->rtt_ms > 100 ? '#ef4444' : ($heartbeat->rtt_ms > 50 ? '#f59e0b' : '#10b981') }};">
                                {{ $heartbeat->rtt_ms }}ms
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <span style="padding: 0.375rem 0.875rem; background: #dcfce7; color: #166534; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                    OK
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="padding: 3rem; text-align: center; color: #9ca3af;">
                                <div style="font-size: 1.125rem; margin-bottom: 1rem;">📡 No heartbeats yet</div>
                                <div>Run the installer: <code>php install_{{ strtolower($client->name) }}.php install</code></div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Configuration -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">⚙️ Configuration</h2>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 500; color: #374151; margin-bottom: 0.5rem; display: block;">Client UUID</label>
                    <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); padding: 1rem; border-radius: 8px; border: 1px solid #0ea5e9;">
                        <code style="font-family: monospace; font-size: 0.9rem; word-break: break-all; color: #0369a1;">
                            {{ $client->uuid }}
                        </code>
                    </div>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 500; color: #374151; margin-bottom: 0.5rem; display: block;">Registered</label>
                    <div style="color: #6b7280;">{{ $client->created_at->format('M j, Y H:i') }}</div>
                </div>
                <a href="{{ route('clients.installer', $client->id) }}" style="width: 100%; padding: 1.25rem; background: linear-gradient(135deg, #0284c7, #0369a1); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; text-align: center; display: block; font-size: 1.1rem;">
                    📥 Download Installer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let rttChart = null;
let uptimeChart = null;

const chartData = @json($chartData ?? []);
const rttLabels = chartData.rtt_labels || [];
const rttData = chartData.rtt_data || [];
const rttDataWithNulls = chartData.rtt_data_with_nulls || [];
const uptimeLabels = chartData.uptime_labels || [];
const uptimeData = chartData.uptime_data || [];
const uptimeDataWithNulls = chartData.uptime_data_with_nulls || [];
const hasMissedPings = chartData.has_missed_pings || false;

function destroyCharts() {
    [rttChart, uptimeChart].forEach(chart => {
        if (chart) {
            chart.destroy();
        }
    });
    rttChart = null;
    uptimeChart = null;
}

function createCharts() {
    destroyCharts();

    // RTT CHART - Show missed pings as red dots
    const ctxRTT = document.getElementById('rttChart');
    if (ctxRTT) {
        ctxRTT.style.height = '300px';

        // Separate successful and missed pings for better visualization
        const successfulRttData = rttDataWithNulls.map((value, index) =>
            value !== null ? value : null
        );

        const missedRttData = rttDataWithNulls.map((value, index) =>
            value === null ? 0 : null
        );

        rttChart = new Chart(ctxRTT, {
            type: 'line',
            data: {
                labels: rttLabels,
                datasets: [
                    {
                        label: 'Successful Pings',
                        data: successfulRttData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 3
                    },
                    {
                        label: 'Missed Pings',
                        data: missedRttData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0,
                        fill: false,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        borderWidth: 0,
                        pointStyle: 'triangle',
                        pointBackgroundColor: '#ef4444'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 200,
                        title: { display: true, text: 'Latency (ms)' }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    title: {
                        display: rttData.length === 0,
                        text: 'No ping data yet',
                        font: { size: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return 'Missed Ping';
                                }
                                return 'RTT: ' + context.parsed.y + 'ms';
                            }
                        }
                    }
                }
            }
        });
    }

    // UPTIME CHART - Show 0% for missed pings
    const ctxUptime = document.getElementById('uptimeChart');
    if (ctxUptime) {
        ctxUptime.style.height = '300px';

        // Separate successful and missed pings for uptime chart
        const successfulUptimeData = uptimeDataWithNulls.map((value, index) =>
            value === 100 ? value : null
        );

        const missedUptimeData = uptimeDataWithNulls.map((value, index) =>
            value === 0 ? value : null
        );

        uptimeChart = new Chart(ctxUptime, {
            type: 'line',
            data: {
                labels: uptimeLabels,
                datasets: [
                    {
                        label: 'Online',
                        data: successfulUptimeData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 3
                    },
                    {
                        label: 'Offline',
                        data: missedUptimeData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0,
                        fill: false,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        borderWidth: 0,
                        pointStyle: 'triangle',
                        pointBackgroundColor: '#ef4444'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: { callback: v => v + '%' },
                        title: { display: true, text: 'Uptime (%)' }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    title: {
                        display: uptimeData.length === 0,
                        text: 'No uptime data yet',
                        font: { size: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return 'Offline - Missed Ping';
                                }
                                return 'Online - ' + context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Add warning banner if there are missed pings
    if (hasMissedPings) {
        const warningBanner = document.createElement('div');
        warningBanner.style.backgroundColor = '#fef2f2';
        warningBanner.style.border = '1px solid #fecaca';
        warningBanner.style.borderRadius = '8px';
        warningBanner.style.padding = '1rem';
        warningBanner.style.marginTop = '1rem';
        warningBanner.style.display = 'flex';
        warningBanner.style.alignItems = 'center';
        warningBanner.style.gap = '0.75rem';
        warningBanner.innerHTML = `
            <div style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">⚠️ WARNING</div>
            <div style="color: #dc2626; font-weight: 500;">Missed pings detected! Red markers indicate periods where the client failed to send heartbeats.</div>
        `;

        // Insert warning banner before the charts section
        const chartsSection = document.querySelector('div[style*="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;"]');
        if (chartsSection) {
            chartsSection.parentNode.insertBefore(warningBanner, chartsSection);
        }
    }
}

// CREATE ON LOAD
document.addEventListener('DOMContentLoaded', createCharts);
</script>





<style>
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
@endsection
