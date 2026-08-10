<?php

namespace App\Service;

use App\Models\TeamFinanceReason;
use App\Repository\TeamFinanceReasonRepository;
use Symfony\Component\HttpFoundation\Response;

class TeamFinanceReasonService extends BaseService
{
    public function __construct(
        protected TeamFinanceReasonRepository $teamFinanceReasonRepository,
    ) {}

    public function listByTeam(int $teamId)
    {
        return $this->teamFinanceReasonRepository->getByTeamId($teamId);
    }

    public function create(int $teamId, array $data): TeamFinanceReason
    {
        throw_if(
            $this->teamFinanceReasonRepository->existsByNameAndTeam($data['name'], $teamId),
            new \Exception('Esta razão já está cadastrada para este time', Response::HTTP_UNPROCESSABLE_ENTITY)
        );

        return $this->teamFinanceReasonRepository->create([
            'team_id' => $teamId,
            'name' => trim($data['name']),
        ]);
    }

    public function findOrCreate(int $teamId, string $name): TeamFinanceReason
    {
        return $this->teamFinanceReasonRepository->findOrCreateByNameAndTeam($name, $teamId);
    }

    public function delete(int $teamId, int $reasonId): void
    {
        $reason = $this->teamFinanceReasonRepository->firstById($reasonId);

        throw_if(!$reason, new \Exception(
            'Razão não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        // Only allow deleting team-specific reasons (not global ones)
        throw_if($reason->team_id === null, new \Exception(
            'Não é possível remover razões padrão do sistema',
            Response::HTTP_FORBIDDEN
        ));

        throw_if($reason->team_id !== $teamId, new \Exception(
            'Razão não pertence a este time',
            Response::HTTP_FORBIDDEN
        ));

        $reason->delete();
    }
}
