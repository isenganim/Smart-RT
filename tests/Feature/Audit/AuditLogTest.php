<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;

it('records an audit log with actor and metadata', function () {
    $user = User::factory()->create();

    Audit::record(
        actor: $user,
        action: 'dashboard.opened',
        subjectType: 'dashboard',
        subjectId: null,
        metadata: ['source' => 'test']
    );

    $log = AuditLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($user->id);
    expect($log->action)->toBe('dashboard.opened');
    expect($log->metadata)->toBe(['source' => 'test']);
});
