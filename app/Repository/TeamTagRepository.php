<?php

namespace App\Repository;

use App\Models\Matches;
use App\Models\TeamPlayerHasTag;
use App\Models\TeamTag;

class TeamTagRepository extends BaseRepository
{
    public function __construct(TeamTag $model)
    {
        $this->model = $model;
    }

    public function getByTeamId(int $teamId)
    {
        return $this->model
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();
    }

    public function existsByNameAndTeam(string $name, int $teamId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function deleteWithCascade(int $tagId): void
    {
        // Remove pivot entries
        TeamPlayerHasTag::where('team_tag_id', $tagId)->delete();

        // Nullify match references
        Matches::where('tag_id', $tagId)->update(['tag_id' => null]);

        // Soft delete the tag
        $this->model->where('id', $tagId)->first()?->delete();
    }
}
