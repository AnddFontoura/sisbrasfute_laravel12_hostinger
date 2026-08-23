<?php

namespace App\Http\Controllers;

use App\Models\MatchChallenge;
use App\Models\Matches;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MatchChallengeController extends Controller
{
    /**
     * List open friendly matches available for challenge.
     */
    public function openMatches(Request $request): JsonResponse
    {
        $query = Matches::with(['cityInfo.stateInfo', 'myTeamInfo'])
            ->where('challenge_status', 1)
            ->where('status', 1)
            ->orderBy('schedule', 'asc');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('state_id')) {
            $query->whereHas('cityInfo', function ($q) use ($request) {
                $q->where('state_id', $request->state_id);
            });
        }

        $matches = $query->paginate(12);

        return response()->json($matches, Response::HTTP_OK);
    }

    /**
     * List challenges for a specific match (host view).
     */
    public function index(int $matchId): JsonResponse
    {
        $match = Matches::find($matchId);

        if (!$match) {
            return response()->json(['message' => 'Partida não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        // Only the host team owner can see challenges
        $team = Team::find($match->created_by_team_id);
        if (!$team || $team->user_id !== Auth::id()) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        $challenges = MatchChallenge::with('challengerTeam')
            ->where('match_id', $matchId)
            ->whereIn('status', [
                MatchChallenge::STATUS_PENDING,
                MatchChallenge::STATUS_HOST_ACCEPTED,
                MatchChallenge::STATUS_CONFIRMED,
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($challenges, Response::HTTP_OK);
    }

    /**
     * Challenger team applies to a match.
     */
    public function challenge(Request $request, int $matchId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'challenger_team_id' => 'required|integer|exists:teams,id',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match = Matches::find($matchId);

        if (!$match) {
            return response()->json(['message' => 'Partida não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        if ($match->challenge_status !== 1) {
            return response()->json(['message' => 'Esta partida não está aberta para desafios.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $challengerTeamId = $request->challenger_team_id;

        // Verify the user owns the challenger team
        $challengerTeam = Team::find($challengerTeamId);
        if (!$challengerTeam || $challengerTeam->user_id !== Auth::id()) {
            return response()->json(['message' => 'Você não administra este time.'], Response::HTTP_FORBIDDEN);
        }

        // Cannot challenge your own match
        if ($match->created_by_team_id === $challengerTeamId) {
            return response()->json(['message' => 'Você não pode desafiar sua própria partida.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if already challenged
        $existing = MatchChallenge::where('match_id', $matchId)
            ->where('challenger_team_id', $challengerTeamId)
            ->whereNotIn('status', [MatchChallenge::STATUS_DECLINED, MatchChallenge::STATUS_CANCELLED])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Este time já enviou um desafio para esta partida.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $challenge = MatchChallenge::create([
            'match_id' => $matchId,
            'challenger_team_id' => $challengerTeamId,
            'message' => $request->message,
            'status' => MatchChallenge::STATUS_PENDING,
        ]);

        return response()->json($challenge->load('challengerTeam'), Response::HTTP_CREATED);
    }

    /**
     * Host accepts a challenge.
     */
    public function accept(int $matchId, int $challengeId): JsonResponse
    {
        $challenge = MatchChallenge::where('id', $challengeId)
            ->where('match_id', $matchId)
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Desafio não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (!$challenge->isPending()) {
            return response()->json(['message' => 'Este desafio não está mais pendente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify user is the host team owner
        $match = Matches::find($matchId);
        $team = Team::find($match->created_by_team_id);
        if (!$team || $team->user_id !== Auth::id()) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        $challenge->update([
            'status' => MatchChallenge::STATUS_HOST_ACCEPTED,
            'host_confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Desafio aceito! Aguardando confirmação do desafiante.',
            'challenge' => $challenge->fresh()->load('challengerTeam'),
        ], Response::HTTP_OK);
    }

    /**
     * Host declines a challenge.
     */
    public function decline(int $matchId, int $challengeId): JsonResponse
    {
        $challenge = MatchChallenge::where('id', $challengeId)
            ->where('match_id', $matchId)
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Desafio não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($challenge->isConfirmed()) {
            return response()->json(['message' => 'Não é possível recusar um desafio já confirmado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify user is the host team owner
        $match = Matches::find($matchId);
        $team = Team::find($match->created_by_team_id);
        if (!$team || $team->user_id !== Auth::id()) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        $challenge->update([
            'status' => MatchChallenge::STATUS_DECLINED,
        ]);

        return response()->json(['message' => 'Desafio recusado.'], Response::HTTP_OK);
    }

    /**
     * Challenger confirms (after host accepted) — locks the match.
     */
    public function confirm(int $matchId, int $challengeId): JsonResponse
    {
        $challenge = MatchChallenge::where('id', $challengeId)
            ->where('match_id', $matchId)
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Desafio não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (!$challenge->isHostAccepted()) {
            return response()->json(['message' => 'O anfitrião ainda não aceitou este desafio.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify user owns the challenger team
        $challengerTeam = Team::find($challenge->challenger_team_id);
        if (!$challengerTeam || $challengerTeam->user_id !== Auth::id()) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        // Confirm the challenge
        $challenge->update([
            'status' => MatchChallenge::STATUS_CONFIRMED,
            'challenger_confirmed_at' => now(),
        ]);

        // Lock the match: set enemy team and update challenge_status
        $match = Matches::find($matchId);
        $match->update([
            'enemy_team_id' => $challenge->challenger_team_id,
            'enemy_team_name' => $challengerTeam->name,
            'challenge_status' => 2, // confirmed
        ]);

        // Decline all other pending challenges for this match
        MatchChallenge::where('match_id', $matchId)
            ->where('id', '!=', $challengeId)
            ->whereIn('status', [MatchChallenge::STATUS_PENDING, MatchChallenge::STATUS_HOST_ACCEPTED])
            ->update(['status' => MatchChallenge::STATUS_DECLINED]);

        return response()->json([
            'message' => 'Desafio confirmado! A partida está marcada.',
            'match' => $match->fresh()->load(['myTeamInfo', 'enemyTeamInfo', 'cityInfo.stateInfo']),
        ], Response::HTTP_OK);
    }

    /**
     * Challenger cancels their own pending challenge.
     */
    public function cancel(int $matchId, int $challengeId): JsonResponse
    {
        $challenge = MatchChallenge::where('id', $challengeId)
            ->where('match_id', $matchId)
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Desafio não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($challenge->isConfirmed()) {
            return response()->json(['message' => 'Não é possível cancelar um desafio já confirmado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify user owns the challenger team
        $challengerTeam = Team::find($challenge->challenger_team_id);
        if (!$challengerTeam || $challengerTeam->user_id !== Auth::id()) {
            return response()->json(['message' => 'Sem permissão.'], Response::HTTP_FORBIDDEN);
        }

        $challenge->update([
            'status' => MatchChallenge::STATUS_CANCELLED,
        ]);

        return response()->json(['message' => 'Desafio cancelado.'], Response::HTTP_OK);
    }

    /**
     * List challenges sent by the user's teams.
     */
    public function myChallenges(): JsonResponse
    {
        $user = Auth::user();
        $teamIds = Team::where('user_id', $user->id)->pluck('id');

        $challenges = MatchChallenge::with(['match.cityInfo.stateInfo', 'match.myTeamInfo', 'challengerTeam'])
            ->whereIn('challenger_team_id', $teamIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($challenges, Response::HTTP_OK);
    }
}
