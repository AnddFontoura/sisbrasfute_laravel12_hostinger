<?php

namespace App\Service;

use App\Models\Team;
use App\Repository\TeamRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TeamService extends BaseService
{
    public function __construct(
        protected TeamRepository $teamRepository,
        protected UploadService $uploadService
    )
    {
    }

    public function updateTeam(array $data, int $teamId): Team
    {
        $team = $this->teamRepository->getById($teamId);

        throw_if(!isset($team), new \Exception(
            __('error.team.team_not_found'),
            Response::HTTP_FAILED_DEPENDENCY
        ));

        if(isset($data['teamLogo'])) {
            if (isset($team->logo_path)) {
                $this->uploadService->deleteFileOnFolder(
                    'public',
                    'logos',
                    $team->logo_path
                );
            }

            $logoPath = $this->uploadService->uploadFileToFolder(
                'public',
                'logos',
                $data['teamLogo']
            );

            $this->teamRepository->updateById(
                ['logo_path' => $logoPath],
                $teamId
            );
        }

        if (isset($data['teamBanner'])) {
            if (isset($team->banner_path)) {
                $this->uploadService->deleteFileOnFolder(
                    'public',
                    'banners',
                    $team->banner_path
                );
            }

            $bannerPath = $this->uploadService->uploadFileToFolder(
                'public',
                'banners',
                $data['teamBanner']
            );

            $this->teamRepository->updateById(
                ['banner_path' => $bannerPath],
                $teamId
            );
        }

        $socialProfiles = [
            'facebook' => $data['teamFacebook'] ?? '',
            'instagram' => $data['teamInstagram'] ?? '',
            'x' => $data['teamX'] ?? '',
            'tiktok' => $data['teamTiktok'] ?? '',
            'youtube' => $data['teamYoutube'] ?? '',
            'kwai' => $data['teamKwai'] ?? '',
        ];

        $dataToUpdate = [
            'city_id' => $data['teamCityId'],
            'modality_id' => $data['teamModalityId'],
            'slug' => Str::slug($data['teamName']),
            'name' => $data['teamName'],
            'gender' => $data['teamGender'],
            'description' => $data['teamDescription'] ?? null,
            'foundation_date' => $data['teamFoundationDate'] ?? null,
            'social_profiles' => $socialProfiles,
        ];

        $teamInfo = $this->teamRepository->updateById(
            $dataToUpdate,
            $teamId
        );

        return $teamInfo;
    }

    public function createTeam(array $data): Team
    {
        $logoPath = null;
        $bannerPath = null;

        if(isset($data['teamLogo'])) {
            $logoPath = $this->uploadService->uploadFileToFolder(
                'public',
                'logos',
                $data['teamLogo']
            );
        }

        if (isset($data['teamBanner'])) {
            $bannerPath = $this->uploadService->uploadFileToFolder(
                'public',
                'banners',
                $data['teamBanner']
            );
        }

        $socialProfiles = [
            'facebook' => $data['teamFacebook'] ?? '',
            'instagram' => $data['teamInstagram'] ?? '',
            'x' => $data['teamX'] ?? '',
            'tiktok' => $data['teamTiktok'] ?? '',
            'youtube' => $data['teamYoutube'] ?? '',
            'kwai' => $data['teamKwai'] ?? '',
        ];

        $dataToCreate = [
            'user_id' => Auth::id(),
            'city_id' => $data['teamCityId'],
            'modality_id' => $data['teamModalityId'],
            'slug' => Str::slug($data['teamName']),
            'name' => $data['teamName'],
            'gender' => $data['teamGender'],
            'description' => $data['teamDescription'] ?? null,
            'foundation_date' => $data['teamFoundationDate'] ?? null,
            'social_profiles' => $socialProfiles,
            'logo_path' => $logoPath,
            'banner_path' => $bannerPath,
        ];

        $teamInfo = $this->teamRepository->create(
            $dataToCreate
        );

        return $teamInfo;
    }
}
