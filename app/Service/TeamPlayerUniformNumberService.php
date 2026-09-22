<?php

namespace App\Service;

use App\Repository\TeamPlayerRepository;
use App\Repository\TeamUniformRepository;
use App\Repository\TeamPlayerUniformNumberRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TeamPlayerUniformNumberService extends BaseService
{
    public function __construct(
        protected TeamPlayerUniformNumberRepository $repository,
        protected TeamUniformRepository $teamUniformRepository,
        protected TeamPlayerRepository $teamPlayerRepository,
    ) {}

    /**
     * Lists the team's uniforms alongside the numbers the given user (as a
     * member of that team) owns for each one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $teamId, int $userId): array
    {
        $teamPlayer = $this->resolveTeamPlayer($teamId, $userId);

        $uniforms = $this->teamUniformRepository->getByTeamId($teamId);
        $numbers = $this->repository->getByTeamPlayer($teamPlayer->id)->groupBy('team_uniform_id');

        return $uniforms->map(function ($uniform) use ($numbers) {
            return [
                'id' => $uniform->id,
                'name' => $uniform->name,
                'photo' => $uniform->photo,
                'photo_url' => $uniform->photo_url,
                'price_cents' => $uniform->price_cents,
                'my_numbers' => $numbers->get($uniform->id, collect())
                    ->pluck('number')
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * Replaces the set of numbers the user owns for a given uniform.
     *
     * @param array<int, int|string> $rawNumbers
     * @return array<int, int>
     */
    public function setNumbers(int $teamId, int $userId, int $uniformId, array $rawNumbers): array
    {
        $teamPlayer = $this->resolveTeamPlayer($teamId, $userId);

        // Ensure the uniform belongs to this team.
        $uniform = $this->teamUniformRepository->firstById($uniformId);
        throw_if(!$uniform || $uniform->team_id !== $teamId, new \Exception(
            'Camisa não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        // Normalize: unique positive integers.
        $numbers = collect($rawNumbers)
            ->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->values();

        DB::transaction(function () use ($teamPlayer, $uniformId, $numbers) {
            // Replace strategy: drop existing, recreate the current set.
            $this->repository->deleteByPlayerAndUniform($teamPlayer->id, $uniformId);

            foreach ($numbers as $number) {
                $this->repository->create([
                    'team_player_id' => $teamPlayer->id,
                    'team_uniform_id' => $uniformId,
                    'number' => $number,
                ]);
            }
        });

        return $numbers->all();
    }

    private function resolveTeamPlayer(int $teamId, int $userId)
    {
        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $teamId);

        throw_if(!$teamPlayer, new \Exception(
            'Você não é membro deste time',
            Response::HTTP_FORBIDDEN
        ));

        return $teamPlayer;
    }
}
