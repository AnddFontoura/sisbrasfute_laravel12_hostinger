<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentMethod;
use App\Models\GamePosition;
use App\Models\MatchHasPlayer;
use App\Models\Matches;
use App\Models\MatchesHasGamePositions;
use App\Models\SystemConfig;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamReceivable;
use App\Models\User;
use App\Models\Wallet;
use App\Service\MatchPaymentService;
use App\Service\Payment\NullPaymentGateway;
use App\Service\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the multi-method payment flow (wallet / pix / boleto) for match
 * positions and wallet deposits, using the NullPaymentGateway so no external
 * calls are made.
 *
 * Position payments are now slot-based: services receive the position slot
 * (matches_has_game_positions row) and the team player being assigned.
 */
class PaymentMethodFlowTest extends TestCase
{
    use RefreshDatabase;

    private MatchPaymentService $matchPaymentService;
    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the fake gateway for deterministic charge ids.
        $this->app->bind(PaymentGatewayContract::class, NullPaymentGateway::class);

        // Fixed fee of R$5,00 so cost math is predictable.
        SystemConfig::updateOrCreate(['key' => 'fee_type'], ['value' => 'fixed']);
        SystemConfig::updateOrCreate(['key' => 'fee_value'], ['value' => '500']);

        $this->matchPaymentService = app(MatchPaymentService::class);
        $this->walletService = app(WalletService::class);
    }

    public function test_wallet_payment_confirms_position_immediately(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer, 'user' => $user] =
            $this->createMatchWithPosition(30.00);

        // R$30 position + R$5 fee = R$35; fund the wallet with R$50.
        $this->walletService->getOrCreateWallet($user->id);
        Wallet::where('user_id', $user->id)->update(['balance_cents' => 5000]);

        $assignment = $this->matchPaymentService->processPayment($match->id, $slot, $teamPlayer);

        $this->assertSame('paid', $assignment->payment_status);
        $this->assertSame(PaymentMethod::Wallet->value, $assignment->payment_method);
        // Assignment is linked to the specific position slot.
        $this->assertSame($slot->id, $assignment->match_has_game_position_id);

        // Balance debited by position + fee.
        $this->assertSame(1500, Wallet::where('user_id', $user->id)->first()->balance_cents);

        // Team receivable credited with the position value (not the fee), for the slot's team.
        $this->assertSame(3000, (int) TeamReceivable::where('match_id', $match->id)->sum('amount_cents'));
        $this->assertDatabaseHas('team_receivables', [
            'match_id' => $match->id,
            'team_id' => $slot->team_id,
            'amount_cents' => 3000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'match_payment',
            'status' => 'completed',
            'amount_cents' => 3000,
            'fee_cents' => 500,
            'team_id' => $slot->team_id,
        ]);
    }

    public function test_pix_payment_reserves_position_as_pending_and_confirms_on_webhook(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer] =
            $this->createMatchWithPosition(30.00);

        $result = $this->matchPaymentService->createPendingPositionPayment(
            $match->id,
            $slot,
            $teamPlayer,
            PaymentMethod::Pix,
            'http://return',
        );

        $assignment = $result['assignment'];
        $charge = $result['charge'];

        // Position reserved but NOT yet paid.
        $this->assertSame('pending', $assignment->payment_status);
        $this->assertSame(PaymentMethod::Pix->value, $assignment->payment_method);
        $this->assertSame($slot->id, $assignment->match_has_game_position_id);
        $this->assertNotEmpty($charge->chargeId);
        $this->assertNotNull($charge->pixCopiaECola);

        // Pending transaction created, no receivable yet.
        $this->assertDatabaseHas('wallet_transactions', [
            'gateway_reference' => $charge->chargeId,
            'type' => 'match_payment',
            'status' => 'pending',
        ]);
        $this->assertSame(0, TeamReceivable::where('match_id', $match->id)->count());

        // Webhook confirms the charge.
        $this->matchPaymentService->confirmPositionPayment($charge->chargeId);

        $this->assertSame('paid', $assignment->fresh()->payment_status);
        $this->assertDatabaseHas('wallet_transactions', [
            'gateway_reference' => $charge->chargeId,
            'status' => 'completed',
        ]);
        $this->assertSame(3000, (int) TeamReceivable::where('match_id', $match->id)->sum('amount_cents'));
    }

    public function test_boleto_payment_produces_digitable_line_and_pending_reservation(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer] =
            $this->createMatchWithPosition(30.00);

        $result = $this->matchPaymentService->createPendingPositionPayment(
            $match->id,
            $slot,
            $teamPlayer,
            PaymentMethod::Boleto,
            'http://return',
            ['name' => 'Fulano', 'document' => '12345678909'],
        );

        $charge = $result['charge'];

        $this->assertSame(PaymentMethod::Boleto->value, $charge->method);
        $this->assertNotNull($charge->boletoLinhaDigitavel);
        $this->assertSame('pending', $result['assignment']->payment_status);
    }

    public function test_canceling_pending_payment_releases_the_slot(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer] =
            $this->createMatchWithPosition(30.00);

        $result = $this->matchPaymentService->createPendingPositionPayment(
            $match->id,
            $slot,
            $teamPlayer,
            PaymentMethod::Pix,
            'http://return',
        );
        $charge = $result['charge'];

        $this->matchPaymentService->cancelPendingPositionPayment($charge->chargeId);

        // Reservation removed (soft-deleted) and transaction marked failed.
        $this->assertNull(MatchHasPlayer::where('payment_reference', $charge->chargeId)->first());
        $this->assertDatabaseHas('wallet_transactions', [
            'gateway_reference' => $charge->chargeId,
            'status' => 'failed',
        ]);
    }

    public function test_refund_of_paid_position_credits_wallet_regardless_of_method(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer, 'user' => $user] =
            $this->createMatchWithPosition(30.00);

        // Simulate a boleto-paid position (confirmed via webhook).
        $result = $this->matchPaymentService->createPendingPositionPayment(
            $match->id,
            $slot,
            $teamPlayer,
            PaymentMethod::Boleto,
            'http://return',
            ['name' => 'Fulano', 'document' => '12345678909'],
        );
        $this->matchPaymentService->confirmPositionPayment($result['charge']->chargeId);

        $balanceBefore = Wallet::where('user_id', $user->id)->first()->balance_cents;

        $this->matchPaymentService->processRefund($match->id, $user->id);

        // Refund credited the position value (R$30) to the wallet.
        $balanceAfter = Wallet::where('user_id', $user->id)->first()->balance_cents;
        $this->assertSame($balanceBefore + 3000, $balanceAfter);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'refund',
            'amount_cents' => 3000,
            'status' => 'completed',
            'team_id' => $slot->team_id,
        ]);
        // Position released.
        $this->assertNull(MatchHasPlayer::where('match_id', $match->id)->first());
    }

    public function test_refund_of_pending_position_releases_without_crediting_wallet(): void
    {
        ['match' => $match, 'slot' => $slot, 'teamPlayer' => $teamPlayer, 'user' => $user] =
            $this->createMatchWithPosition(30.00);

        $result = $this->matchPaymentService->createPendingPositionPayment(
            $match->id,
            $slot,
            $teamPlayer,
            PaymentMethod::Pix,
            'http://return',
        );

        $balanceBefore = Wallet::where('user_id', $user->id)->first()->balance_cents;

        $this->matchPaymentService->processRefund($match->id, $user->id);

        // No refund credited for an unpaid (pending) position.
        $this->assertSame($balanceBefore, Wallet::where('user_id', $user->id)->first()->balance_cents);
        $this->assertNull(MatchHasPlayer::where('match_id', $match->id)->first());
        $this->assertDatabaseHas('wallet_transactions', [
            'gateway_reference' => $result['charge']->chargeId,
            'status' => 'failed',
        ]);
    }

    public function test_enemy_team_member_pays_position_on_their_own_side(): void
    {
        ['match' => $match, 'enemySlot' => $enemySlot, 'enemyTeamPlayer' => $enemyTeamPlayer, 'enemyUser' => $enemyUser] =
            $this->createMatchWithPosition(20.00);

        $this->walletService->getOrCreateWallet($enemyUser->id);
        Wallet::where('user_id', $enemyUser->id)->update(['balance_cents' => 5000]);

        $assignment = $this->matchPaymentService->processPayment($match->id, $enemySlot, $enemyTeamPlayer);

        $this->assertSame('paid', $assignment->payment_status);
        $this->assertSame($enemySlot->id, $assignment->match_has_game_position_id);

        // Receivable is credited to the enemy team, not the creator team.
        $this->assertDatabaseHas('team_receivables', [
            'match_id' => $match->id,
            'team_id' => $enemySlot->team_id,
            'amount_cents' => 2000,
        ]);
    }

    public function test_deposit_webhook_credits_wallet_balance(): void
    {
        $user = User::factory()->create();

        $charge = $this->walletService->initiateDeposit(
            $user->id,
            10000,
            'http://return',
            PaymentMethod::Pix,
        );

        $this->assertSame(0, $this->walletService->getBalance($user->id));

        // Simulate the Pix webhook payload for this charge.
        $this->walletService->handleWebhook(['pix' => [['txid' => $charge->chargeId]]]);

        $this->assertSame(10000, $this->walletService->getBalance($user->id));
        $this->assertDatabaseHas('wallet_transactions', [
            'gateway_reference' => $charge->chargeId,
            'type' => 'deposit',
            'status' => 'completed',
        ]);
    }

    /**
     * Creates the full fixture chain for a two-team match with mirrored
     * position slots.
     *
     * @return array{
     *   match: Matches,
     *   slot: MatchesHasGamePositions,
     *   teamPlayer: TeamPlayer,
     *   user: User,
     *   enemySlot: MatchesHasGamePositions,
     *   enemyTeamPlayer: TeamPlayer,
     *   enemyUser: User
     * }
     */
    private function createMatchWithPosition(float $positionValueBrl): array
    {
        // State -> City (FK chain required by teams/matches).
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
        $member = User::factory()->create();
        $enemyMember = User::factory()->create();

        $team = Team::create([
            'user_id' => $owner->id,
            'city_id' => $cityId,
            'slug' => 'test-team-' . uniqid(),
            'name' => 'Test Team',
            'description' => 'Team for payment tests',
        ]);

        $enemyTeam = Team::create([
            'user_id' => $owner->id,
            'city_id' => $cityId,
            'slug' => 'enemy-team-' . uniqid(),
            'name' => 'Enemy Team',
            'description' => 'Enemy team for payment tests',
        ]);

        $teamPlayer = TeamPlayer::create([
            'user_id' => $member->id,
            'team_id' => $team->id,
            'name' => 'Test Member',
            'nickname' => 'tester',
        ]);

        $enemyTeamPlayer = TeamPlayer::create([
            'user_id' => $enemyMember->id,
            'team_id' => $enemyTeam->id,
            'name' => 'Enemy Member',
            'nickname' => 'enemy-tester',
        ]);

        $gamePosition = GamePosition::create([
            'name' => 'Goleiro',
            'short' => 'GOL',
        ]);

        $match = Matches::create([
            'created_by_team_id' => $team->id,
            'match_type' => 0,
            'my_team_is' => 0,
            'my_team_id' => $team->id,
            'enemy_team_id' => $enemyTeam->id,
            'city_id' => $cityId,
            'my_team_name' => $team->name,
            'enemy_team_name' => $enemyTeam->name,
            'location' => 'Test location',
            'schedule' => now()->addDay(),
            'status' => 1,
        ]);

        // Mirrored slots: one per team, each carrying its real team_id.
        $slot = MatchesHasGamePositions::create([
            'match_id' => $match->id,
            'game_position_id' => $gamePosition->id,
            'team_id' => $team->id,
            'team_reference' => 1,
            'value' => $positionValueBrl,
        ]);

        $enemySlot = MatchesHasGamePositions::create([
            'match_id' => $match->id,
            'game_position_id' => $gamePosition->id,
            'team_id' => $enemyTeam->id,
            'team_reference' => 2,
            'value' => $positionValueBrl,
        ]);

        return [
            'match' => $match,
            'slot' => $slot,
            'teamPlayer' => $teamPlayer,
            'user' => $member,
            'enemySlot' => $enemySlot,
            'enemyTeamPlayer' => $enemyTeamPlayer,
            'enemyUser' => $enemyMember,
        ];
    }
}
