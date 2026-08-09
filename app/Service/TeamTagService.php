<?php

namespace App\Service;

use App\Models\TeamTag;
use App\Repository\TeamTagRepository;
use Symfony\Component\HttpFoundation\Response;

class TeamTagService extends BaseService
{
    public function __construct(
        protected TeamTagRepository $teamTagRepository,
    ) {}

    public function listByTeam(int $teamId)
    {
        return $this->teamTagRepository->getByTeamId($teamId);
    }

    public function create(int $teamId, array $data): TeamTag
    {
        throw_if(
            $this->teamTagRepository->existsByNameAndTeam($data['name'], $teamId),
            new \Exception('Este nome de tag já está em uso neste time', Response::HTTP_UNPROCESSABLE_ENTITY)
        );

        return $this->teamTagRepository->create([
            'team_id' => $teamId,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? '#6b7280',
        ]);
    }

    public function update(int $teamId, int $tagId, array $data): TeamTag
    {
        $tag = $this->teamTagRepository->firstById($tagId);

        throw_if(!$tag || $tag->team_id !== $teamId, new \Exception(
            'Tag não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        if (isset($data['name'])) {
            throw_if(
                $this->teamTagRepository->existsByNameAndTeam($data['name'], $teamId, $tagId),
                new \Exception('Este nome de tag já está em uso neste time', Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = trim($data['name']);
        if (isset($data['color'])) $updateData['color'] = $data['color'];

        if (!empty($updateData)) {
            $tag->update($updateData);
        }

        return $tag->fresh();
    }

    public function delete(int $teamId, int $tagId): void
    {
        $tag = $this->teamTagRepository->firstById($tagId);

        throw_if(!$tag || $tag->team_id !== $teamId, new \Exception(
            'Tag não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $this->teamTagRepository->deleteWithCascade($tagId);
    }
}
