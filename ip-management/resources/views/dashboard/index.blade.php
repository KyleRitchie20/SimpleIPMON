@extends('layouts.app')

@section('content')
<div style="padding: 2rem;">
    <h1>IP Management Dashboard</h1>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 8px;">
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Total Clients</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $stats['total_clients'] }}</div>
        </div>
        <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 8px;">
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Online</div>
            <div style="font-size: 2rem; font-weight: 700; color: #10b981;">{{ $stats['online_clients'] }}</div>
        </div>
        <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 8px;">
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Offline</div>
            <div style="font-size: 2rem; font-weight: 700; color: #ef4444;">{{ $stats['offline_clients'] }}</div>
        </div>
        <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 8px;">
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Avg RTT</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ round($stats['avg_rtt_all'] ?? 0, 0) }}ms</div>
        </div>
    </div>

    <button onclick="openAddModal()" style="padding: 0.75rem 1.5rem; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin-bottom: 1rem;">+ Add Client</button>

    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead style="background: #f9fafb;">
            <tr>
                <th style="padding: 1rem; text-align: left;">Status</th>
                <th style="padding: 1rem; text-align: left;">Name</th>
                <th style="padding: 1rem; text-align: left;">IP</th>
                <th style="padding: 1rem; text-align: left;">Last Heartbeat</th>
                <th style="padding: 1rem; text-align: left;">RTT (1h)</th>
                <th style="padding: 1rem; text-align: left;">Uptime</th>
                <th style="padding: 1rem; text-align: left;">Actions</th>
            </tr>
        </thead>
        <tbody id="clients-tbody">
            @forelse($clients as $client)
            <tr style="border-top: 1px solid #e5e7eb;">
                <td style="padding: 1rem;">
                    <span style="padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; background: {{ $client->is_online ? '#dcfce7' : '#fee2e2' }}; color: {{ $client->is_online ? '#16a34a' : '#dc2626' }};">
                        {{ $client->status }}
                    </span>
                </td>
                <td style="padding: 1rem; font-weight: 600;">{{ $client->name }}</td>
                <td style="padding: 1rem; font-family: monospace;">{{ $client->public_ip ?? 'Pending' }}</td>
                <td style="padding: 1rem;">{{ $client->last_heartbeat?->diffForHumans() ?? 'Never' }}</td>
                <td style="padding: 1rem;">{{ round($client->average_rtt_1h ?? 0, 1) }}ms</td>
                <td style="padding: 1rem;">{{ round($client->uptime_percentage ?? 0, 1) }}%</td>
                <td style="padding: 1rem;">
                    <a href="{{ route('clients.show', $client->id) }}" style="color: #2563eb; text-decoration: none; margin-right: 0.5rem;">View</a>
                    <a href="{{ route('clients.installer', $client->id) }}" style="color: #0284c7; text-decoration: none;">Installer</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding: 2rem; text-align: center; color: #999;">No clients. Click "+ Add Client" to register one.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Add Client Modal -->
<div id="add-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div style="background: white; padding: 2.5rem; border-radius: 16px; max-width: 450px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.25);">
        <h2 style="font-size: 1.75rem; font-weight: 700; color: #1f2937; margin: 0 0 1rem 0; text-align: center;">Register New Client</h2>
        <form id="add-client-form">
            @csrf
            <div>
                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Client Name</label>
                <input type="text" name="name" required placeholder="e.g. Office-Server-1" 
                       style="width: 100%; padding: 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem;">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="closeAddModal()" style="flex: 1; padding: 1rem; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">Cancel</button>
                <button type="submit" style="flex: 1; padding: 1rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Register Client</button>
            </div>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // FORCE MODAL CLOSED ON PAGE LOAD
    const modal = document.getElementById('add-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // ONLY BUTTON CLICK OPENS MODAL
    document.querySelectorAll('[onclick="openAddModal()"], button[onclick*="openAddModal"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (modal) modal.style.display = 'flex';
        });
    });
    
    // CLOSE BUTTONS
    document.querySelectorAll('[onclick="closeAddModal()"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (modal) modal.style.display = 'none';
        });
    });
    
    // FORM SUBMIT
    const form = document.getElementById('add-client-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = form.querySelector('input[name="name"]').value.trim();
            if (!name) return alert('Enter client name');
            
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Registering...';
            
            fetch('/api/install', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({name})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Client registered! UUID: ' + data.uuid);
                    if (modal) modal.style.display = 'none';
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'Failed'));
                }
            })
            .catch(() => alert('❌ Network error'))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Register Client';
            });
        });
    }
    
    // AUTO-REFRESH (modal stays closed)
    setInterval(() => location.reload(), 30000);
});
</script>





@endsection