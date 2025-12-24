<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clients Table
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('uuid', 36)->unique();
            $table->ipAddress('public_ip')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('is_online');
            $table->index('last_heartbeat');
        });

        // Client Heartbeats Table
        Schema::create('client_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->ipAddress('ip_address');
            $table->unsignedInteger('rtt_ms');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('client_id');
            $table->index('created_at');
            $table->index(['client_id', 'created_at']);
        });

        // Client Metrics Table
        Schema::create('client_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('metric_type', 50);
            $table->decimal('metric_value', 10, 2);
            $table->unsignedInteger('min_rtt_ms')->nullable();
            $table->unsignedInteger('max_rtt_ms')->nullable();
            $table->decimal('avg_rtt_ms', 10, 2)->nullable();
            $table->timestamp('recorded_at');
            
            $table->index('client_id');
            $table->index('recorded_at');
            $table->index(['client_id', 'metric_type', 'recorded_at']);
        });

        // Audit Logs Table (FIXED: onDelete('set null'))
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('action', 100);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // FIXED: Manual foreign key with correct PostgreSQL syntax
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('client_metrics');
        Schema::dropIfExists('client_heartbeats');
        Schema::dropIfExists('clients');
    }
};
