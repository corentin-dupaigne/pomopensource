<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects and the "What are you working on?" note are now one field,
     * the subject, kept as a project:
     * - a session with a note and no project takes the note as its subject,
     *   the same project for the same text, whatever its case or spacing;
     * - a session with both keeps its note, which the day log shows under
     *   the subject;
     * - a user's projects whose names differ only by case or spacing become
     *   one, the oldest.
     * Accounts that had a project or a note are told once.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_subjects_notice')->default(false);
        });
        // A subject no longer current leaves the suggestions, not the history.
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
        });

        $clean = fn (string $name) => trim(preg_replace('/\s+/u', ' ', $name));
        $key = fn (string $name) => mb_strtolower($clean($name));

        // [user id][key] => project id, the oldest of each name.
        $subjects = [];
        foreach (DB::table('projects')->orderBy('id')->get(['id', 'user_id', 'name']) as $project) {
            $id = $subjects[$project->user_id][$key($project->name)] ??= $project->id;
            if ($id !== $project->id) {
                DB::table('focused_sessions')->where('project_id', $project->id)->update(['project_id' => $id]);
                DB::table('projects')->where('id', $project->id)->delete();
            }
        }

        // Also those whose project was deleted: they showed as general focus.
        $notes = DB::table('focused_sessions')
            ->whereNotNull('note')
            ->where(fn ($query) => $query->whereNull('project_id')
                ->orWhereNotIn('project_id', DB::table('projects')->select('id')))
            ->orderBy('id')
            ->get(['id', 'user_id', 'note']);

        foreach ($notes as $session) {
            $name = $clean($session->note);
            if ($name === '') {
                DB::table('focused_sessions')->where('id', $session->id)->update(['project_id' => null, 'note' => null]);
                continue;
            }
            $id = $subjects[$session->user_id][$key($name)] ??= DB::table('projects')->insertGetId([
                'user_id' => $session->user_id,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('focused_sessions')->where('id', $session->id)->update(['project_id' => $id, 'note' => null]);
        }

        // The latest used come first among the suggestions.
        DB::table('projects')->update([
            'updated_at' => DB::raw('COALESCE((SELECT MAX(started_at) FROM focused_sessions WHERE focused_sessions.project_id = projects.id), projects.updated_at)'),
        ]);

        DB::table('users')
            ->whereIn('id', DB::table('projects')->select('user_id'))
            ->update(['show_subjects_notice' => true]);
    }

    /**
     * Notes moved into subjects are not put back: the backup taken before
     * the release has them.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_subjects_notice');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
