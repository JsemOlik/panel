<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the duplicate join/leave rows written before PlayerPresenceService recorded state
 * transitions rather than matching console lines.
 *
 * A single real join emits both a chat broadcast and a network-thread login line, and a leave
 * emits both of its counterparts. Both are parsed deliberately, so presence survives a build that
 * decorates or suppresses either one — but the history table appended a row per line, so every
 * join and leave appeared twice in the player view.
 *
 * This collapses runs of the same event for the same player on the same server, keeping the
 * earliest row of each run. That is the same rule the service now applies when writing, so
 * existing history ends up consistent with anything recorded after this point.
 */
return new class extends Migration
{
    public function up(): void
    {
        $discard = [];
        $previous = [];

        // cursor() rather than chunk(): chunk() paginates by offset, so deleting rows while
        // iterating shifts every later page and silently skips records. Nothing is deleted until
        // the whole table has been walked.
        $rows = DB::table('server_player_sessions')
            ->select(['id', 'server_id', 'name', 'event'])
            ->orderBy('server_id')
            ->orderBy('name')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            $key = $row->server_id . "\0" . $row->name;

            // Only a run of the SAME event collapses. An alternating join/leave/join sequence is
            // real history and is left exactly as it is.
            if (($previous[$key] ?? null) === $row->event) {
                $discard[] = $row->id;

                continue;
            }

            $previous[$key] = $row->event;
        }

        foreach (array_chunk($discard, 500) as $ids) {
            DB::table('server_player_sessions')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        // Deleted duplicates cannot be reconstructed, and reinstating them would only restore the
        // doubled display this removed.
    }
};
