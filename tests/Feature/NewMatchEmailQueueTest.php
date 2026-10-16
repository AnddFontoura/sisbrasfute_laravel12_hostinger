<?php

namespace Tests\Feature;

use App\Jobs\SendNewMatchEmailsJob;
use App\Mail\NewMatchMail;
use App\Models\GamePosition;
use App\Models\Matches;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Service\MatchNotificationService;
use App\Service\MatchesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers moving the "new match" e-mail fan-out off the request path and onto
 * the paid RabbitMQ queue:
 *
 *  1. Creating a match dispatches a single SendNewMatchEmailsJob to the
 *     RabbitMQ connection/queue (instead of looping over players inline).
 *  2. When the job runs, each NewMatchMail is queued on RabbitMQ too, so the
 *     same worker that already drains that queue in production delivers them.
 */
class NewMatchEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_match_dispatches_a_single_rabbitmq_job(): void
    {
        Queue::fake();

        $emailQueue = config('queue.rabbitmq_queues.emails', 'emails');

        $teamId = $this->createTeamWithPlayers();

        app(MatchesService::class)->createOrUpdateMatch([
            'teamId' => $teamId,
            'matchType' => 'team_match',
            'myTeamIs' => 'home',
            'cityId' => DB::table('cities')->value('id'),
            'matchLocation' => 'Quadra central',
            'matchSchedule' => now()->addDay()->toDateTimeString(),
        ]);

        // Exactly one message is pushed for the e-mail fan-out, regardless of
        // how many players the team has — the per-player loop runs on a worker.
        Queue::assertPushed(SendNewMatchEmailsJob::class, 1);

        Queue::assertPushed(SendNewMatchEmailsJob::class, function (SendNewMatchEmailsJob $job) use ($emailQueue) {
            return $job->connection === 'rabbitmq' && $job->queue === $emailQueue;
        });
    }

    public function test_updating_a_match_does_not_dispatch_the_email_job(): void
    {
        Queue::fake();

        $teamId = $this->createTeamWithPlayers();

        $match = Matches::create([
            'created_by_team_id' => $teamId,
            'match_type' => 0,
            'my_team_is' => 0,
            'my_team_id' => $teamId,
            'city_id' => DB::table('cities')->value('id'),
            'my_team_name' => 'Test Team',
            'location' => 'Quadra',
            'schedule' => now()->addDay(),
        ]);

        app(MatchesService::class)->createOrUpdateMatch([
            'teamId' => $teamId,
            'matchType' => 'team_match',
            'myTeamIs' => 'home',
            'cityId' => DB::table('cities')->value('id'),
            'matchLocation' => 'Nova quadra',
            'matchSchedule' => now()->addDays(2)->toDateTimeString(),
        ], $match->id);

        Queue::assertNotPushed(SendNewMatchEmailsJob::class);
    }

    public function test_job_fans_out_new_match_mail_onto_the_rabbitmq_queue(): void
    {
        Mail::fake();

        $emailQueue = config('queue.rabbitmq_queues.emails', 'emails');

        $teamId = $this->createTeamWithPlayers(2);

        $match = Matches::create([
            'created_by_team_id' => $teamId,
            'match_type' => 0,
            'my_team_is' => 0,
            'my_team_id' => $teamId,
            'city_id' => DB::table('cities')->value('id'),
            'my_team_name' => 'Test Team',
            'location' => 'Quadra',
            'schedule' => now()->addDay(),
        ]);

        // Run the job body directly (as a worker would).
        (new SendNewMatchEmailsJob($match->id))
            ->handle(app(MatchNotificationService::class));

        // One mail per eligible player, each queued on the RabbitMQ e-mail queue.
        Mail::assertQueued(NewMatchMail::class, 2);
        Mail::assertQueued(NewMatchMail::class, function (NewMatchMail $mail) use ($emailQueue) {
            return $mail->connection === 'rabbitmq' && $mail->queue === $emailQueue;
        });
    }

    /**
     * Creates a team with N active, notifiable players (each with a user that
     * has an e-mail), plus the required state/city FK chain.
     */
    private function createTeamWithPlayers(int $playerCount = 3): int
    {
        $stateId = DB::table('states')->insertGetId([
            'name' => 'Test State',
            'short' => 'TS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'state_id' => $stateId,
            'name' => 'Test City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = User::factory()->create();

        $team = Team::create([
            'user_id' => $owner->id,
            'city_id' => $cityId,
            'slug' => 'test-team-' . uniqid(),
            'name' => 'Test Team',
            'description' => 'Team for e-mail queue tests',
        ]);

        $gamePosition = GamePosition::create([
            'name' => 'Goleiro',
            'short' => 'GOL',
        ]);

        for ($i = 0; $i < $playerCount; $i++) {
            $user = User::factory()->create();
            TeamPlayer::create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'game_position_id' => $gamePosition->id,
                'name' => 'Player ' . $i,
                'nickname' => 'p' . $i,
                'active' => true,
                'notify_match' => true,
            ]);
        }

        return $team->id;
    }
}
