<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_persists_event_without_auth_context(): void
    {
        AuditLogger::log('test.event', ['foo' => 'bar'], true, 123);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'test.event',
            'success' => 1,
            'subject_id' => 123,
        ]);
    }
}
