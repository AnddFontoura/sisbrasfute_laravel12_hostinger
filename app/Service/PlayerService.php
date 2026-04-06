<?php

namespace App\Service;

use App\Repository\PlayerRepository;
use Illuminate\Support\Facades\Auth;

class PlayerService
{
    public function __construct(
        protected PlayerRepository $playerRepository,
        protected UploadService $uploadService,
        protected GamePositionService $gamePositionService,
        protected ModalityService $modalityService,
        protected PlayerHasModalitiesService $playerHasModalitiesService,
    ) {
    }

    public function saveOrUpdate(array $data)
    {
        $user = Auth::user();
        $photoPath = null;

        foreach ($data as $key => $playerInfo) {
            if (!$playerInfo) {
                unset($data[$key]);
            }
        }

        if (isset($data['playerPhoto'])) {
            $photoPath = $this->uploadService->uploadFileToFolder('public', 'profile_photos', $data['playerPhoto']);
        }

        $socialProfiles = [
            'facebook' => $data['playerFacebook'] ?? '',
            'instagram' => $data['playerInstagram'] ?? '',
            'x' => $data['playerX'] ?? '',
            'tiktok' => $data['playerTiktok'] ?? '',
            'youtube' => $data['playerYoutube'] ?? '',
            'kwai' => $data['playerKwai'] ?? '',
            'gda' => $data['playerGDA'] ?? '',
        ];

        $dataToCreateOrUpdate = [
            'user_id' => $user->id,
            'city_id' => $data['playerCityId'],
            'birth_city_id' => $data['playerBirthCity'] ?? null,
            'name' => $data['playerName'],
            'nickname' => $data['playerNickName'] ?? null,
            'uniform_size' => $data['playerUniformSize'] ?? null,
            'photo' => $photoPath,
            'height' => $data['playerHeight'] ?? null,
            'weight' => $data['playerWeight'] ?? null,
            'foot_size' => $data['playerFootSize'] ?? null,
            'glove_size' => $data['playerGloveSize'] ?? null,
            'gender' => $data['playerGender'] ?? null,
            'birthdate' => $data['playerBirthdate'] ?? null,
            'status' => $data['playerStatus'],
            'social_profiles' => $socialProfiles,
        ];

        $this->playerRepository->updateOrCreate($dataToCreateOrUpdate);
        $profile = $this->playerRepository->getByUserId($user->id);

        if (isset($data['playerModalities'])) {
            $this->playerHasModalitiesService->updatePlayerModalities($data['playerModalities'], $profile->id);
        }

        if (isset($data['playerGamePositions'])) {
            $this->gamePositionService->updatePlayerGamePosition($data['playerGamePositions'], $profile->id);
        }
    }
}
