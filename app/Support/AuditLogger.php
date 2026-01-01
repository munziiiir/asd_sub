<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use App\Support\StrHelper;

class AuditLogger
{
    /**
     * @param string $event   e.g., auth.login, booking.created, payment.processed
     * @param array<string,mixed> $meta additional context (ids, amounts, codes)
     * @param bool $success success flag
     * @param object|int|string|null $subject optional subject (model or id)
     */
    public static function log(string $event, array $meta = [], bool $success = true, $subject = null): void
    {
        try {
            $actor = self::resolveActor();

            $subjectType = null;
            $subjectId = null;
            if (is_object($subject)) {
                $subjectType = get_class($subject);
                $subjectId = $subject->id ?? null;
            } elseif (is_int($subject) || is_string($subject)) {
                $subjectId = $subject;
            }

            AuditLog::create([
                'event' => $event,
                'actor_type' => $actor['type'] ?? null,
                'actor_id' => $actor['id'] ?? null,
                'actor_role' => $actor['role'] ?? null,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'success' => $success,
                'ip_address' => request()->ip(),
                'user_agent' => StrHelper::limit((string) request()->userAgent(), 500),
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // Never break primary flow because of logging.
            report($e);
        }
    }

    private static function resolveActor(): array
    {
        if ($admin = Auth::guard('admin')->user()) {
            return [
                'type' => 'admin',
                'id' => $admin->id,
                'role' => 'admin',
            ];
        }

        if ($staff = Auth::guard('staff')->user()) {
            return [
                'type' => 'staff',
                'id' => $staff->id,
                'role' => $staff->role ?? null,
            ];
        }

        if ($user = Auth::user()) {
            return [
                'type' => 'user',
                'id' => $user->id,
                'role' => 'guest',
            ];
        }

        return ['type' => 'guest', 'id' => null, 'role' => null];
    }
}
