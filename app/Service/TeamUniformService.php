<?php

namespace App\Service;

use App\Models\TeamUniform;
use App\Repository\TeamUniformRepository;
use Symfony\Component\HttpFoundation\Response;

class TeamUniformService extends BaseService
{
    public function __construct(
        protected TeamUniformRepository $teamUniformRepository,
    ) {}

    public function listByTeam(int $teamId)
    {
        return $this->teamUniformRepository->getByTeamId($teamId);
    }

    public function create(int $teamId, array $data): TeamUniform
    {
        throw_if(
            $this->teamUniformRepository->existsByNameAndTeam($data['name'], $teamId),
            new \Exception('Este nome de camisa já está em uso neste time', Response::HTTP_UNPROCESSABLE_ENTITY)
        );

        return $this->teamUniformRepository->create([
            'team_id' => $teamId,
            'name' => trim($data['name']),
            'price_cents' => $this->normalizePrice($data['price_cents'] ?? null),
        ]);
    }

    public function update(int $teamId, int $uniformId, array $data): TeamUniform
    {
        $uniform = $this->findOwned($teamId, $uniformId);

        if (isset($data['name'])) {
            throw_if(
                $this->teamUniformRepository->existsByNameAndTeam($data['name'], $teamId, $uniformId),
                new \Exception('Este nome de camisa já está em uso neste time', Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (array_key_exists('price_cents', $data)) {
            $updateData['price_cents'] = $this->normalizePrice($data['price_cents']);
        }

        if (!empty($updateData)) {
            $uniform->update($updateData);
        }

        return $uniform->fresh();
    }

    public function delete(int $teamId, int $uniformId): void
    {
        $uniform = $this->findOwned($teamId, $uniformId);
        $uniform->delete();
    }

    /**
     * Resolves a uniform ensuring it belongs to the given team.
     */
    public function findOwned(int $teamId, int $uniformId): TeamUniform
    {
        $uniform = $this->teamUniformRepository->firstById($uniformId);

        throw_if(!$uniform || $uniform->team_id !== $teamId, new \Exception(
            'Camisa não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        return $uniform;
    }

    private function normalizePrice($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
