<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->migration = require database_path('migrations/2026_11_04_000000_fold_session_notes_into_subjects.php');
    // The test database already has the column: run up() from before it.
    Schema::table('users', fn ($table) => $table->dropColumn('show_subjects_notice'));
    Schema::table('projects', fn ($table) => $table->dropColumn('removed_at'));
});

test('notes become subjects, and projects that differ only by case become one', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $untouched = User::factory()->create();
    $chemistry = $user->projects()->create(['name' => 'Chemistry']);
    $duplicate = $user->projects()->create(['name' => 'chemistry ']);

    $session = fn (User $owner, array $values) => DB::table('focused_sessions')->insertGetId(
        $values + ['user_id' => $owner->id, 'started_at' => now(), 'minute_focused' => 25]
    );
    $both = $session($user, ['project_id' => $chemistry->id, 'note' => 'Unit 2']);
    $onDuplicate = $session($user, ['project_id' => $duplicate->id]);
    $note = $session($user, ['note' => 'biochem']);
    $sameNote = $session($user, ['note' => ' Biochem']);
    $noteOnProjectName = $session($user, ['note' => 'CHEMISTRY']);
    $othersNote = $session($other, ['note' => 'biochem']);
    $blankNote = $session($user, ['note' => '  ']);
    $nothing = $session($untouched, []);

    $this->migration->up();

    $row = fn ($id) => DB::table('focused_sessions')->find($id);
    $biochem = DB::table('projects')->where('user_id', $user->id)->where('name', 'biochem')->value('id');

    expect(DB::table('projects')->where('user_id', $user->id)->count())->toBe(2)
        ->and(DB::table('projects')->find($duplicate->id))->toBeNull()
        ->and($row($both))->project_id->toEqual($chemistry->id)->note->toBe('Unit 2')
        ->and($row($onDuplicate))->project_id->toEqual($chemistry->id)
        ->and($row($note))->project_id->toEqual($biochem)->note->toBeNull()
        ->and($row($sameNote))->project_id->toEqual($biochem)->note->toBeNull()
        ->and($row($noteOnProjectName))->project_id->toEqual($chemistry->id)->note->toBeNull()
        ->and($row($othersNote)->project_id)->not->toEqual($biochem)->not->toBeNull()
        ->and($row($blankNote))->project_id->toBeNull()->note->toBeNull()
        ->and($row($nothing))->project_id->toBeNull();

    $notice = fn (User $u) => (bool) DB::table('users')->where('id', $u->id)->value('show_subjects_notice');
    expect($notice($user))->toBeTrue()
        ->and($notice($other))->toBeTrue()
        ->and($notice($untouched))->toBeFalse();
});
