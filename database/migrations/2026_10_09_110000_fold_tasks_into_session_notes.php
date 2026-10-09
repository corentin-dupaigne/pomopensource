<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Projects no longer have tasks: a session spent on a task now belongs
     * to the task's project, with the task's name as its note.
     *
     * The tasks table and task_id column stay for now, so nothing is lost
     * if this has to be looked at again; nothing reads them any more.
     */
    public function up(): void
    {
        DB::update(<<<'SQL'
            UPDATE focused_sessions
            SET project_id = (SELECT project_id FROM tasks WHERE tasks.id = focused_sessions.task_id),
                note = COALESCE(note, (SELECT name FROM tasks WHERE tasks.id = focused_sessions.task_id))
            WHERE task_id IS NOT NULL
            SQL);
    }

    /**
     * The task ids are kept, so the sessions still point to their tasks.
     */
    public function down(): void
    {
        //
    }
};
