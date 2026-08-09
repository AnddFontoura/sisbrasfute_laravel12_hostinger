<?php

namespace App\Service;

use App\Repository\PlayerRepository;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

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

        // Handle photo upload
        $photoPath = null;
        if (isset($data['playerPhoto'])) {
            $photoPath = $this->uploadService->uploadFileToFolder('public', 'profile_photos', $data['playerPhoto']);
        }

        // Handle photo removal
        $removePhoto = isset($data['removePhoto']) && $data['removePhoto'] === '1';

        $socialProfiles = [
            'facebook' => $data['playerFacebook'] ?? '',
            'instagram' => $data['playerInstagram'] ?? '',
            'x' => $data['playerX'] ?? '',
            'tiktok' => $data['playerTiktok'] ?? '',
            'youtube' => $data['playerYoutube'] ?? '',
            'kwai' => $data['playerKwaii'] ?? '',
            'gda' => $data['playerGDA'] ?? '',
        ];

        $dataToCreateOrUpdate = [
            'user_id' => $user->id,
            'city_id' => $this->emptyToNull($data['playerCityId'] ?? null),
            'birth_city_id' => $this->emptyToNull($data['playerBirthCity'] ?? null),
            'name' => $this->emptyToNull($data['playerName'] ?? null),
            'nickname' => $this->emptyToNull($data['playerNickName'] ?? null),
            'uniform_size' => $this->emptyToNull($data['playerUniformSize'] ?? null),
            'height' => $this->emptyToNull($data['playerHeight'] ?? null),
            'weight' => $this->emptyToNull($data['playerWeight'] ?? null),
            'foot_size' => $this->emptyToNull($data['playerFootSize'] ?? null),
            'glove_size' => $this->emptyToNull($data['playerGloveSize'] ?? null),
            'gender' => $this->emptyToNull($data['playerGender'] ?? null),
            'birthdate' => $this->emptyToNull($data['playerBirthdate'] ?? null),
            'status' => $this->emptyToNull($data['playerStatus'] ?? null),
            'social_profiles' => $socialProfiles,
        ];

        // Only update photo if a new one was uploaded or removal was requested
        if ($photoPath) {
            $dataToCreateOrUpdate['photo'] = $photoPath;
        } elseif ($removePhoto) {
            $dataToCreateOrUpdate['photo'] = null;
        }
        // If neither, don't include 'photo' key — preserves existing photo

        $this->playerRepository->updateOrCreate($dataToCreateOrUpdate);
        $profile = $this->playerRepository->firstByUserId($user->id);

        if (isset($data['playerModalities'])) {
            $this->playerHasModalitiesService->updatePlayerModalities($data['playerModalities'], $profile->id);
        }

        if (isset($data['playerPositions'])) {
            $this->gamePositionService->updatePlayerGamePosition($data['playerPositions'], $profile->id);
        }
    }

    /**
     * Convert empty strings to null. FormData sends empty fields as "".
     */
    private function emptyToNull($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return $value;
    }

    public function checkIfPlayerExists($userId)
    {
        $hasProfile = $this->playerRepository->firstByUserId($userId);

        throw_if(!isset($hasProfile), new \Exception(
            __('error.player.player_profile_not_found'),
            Response::HTTP_FAILED_DEPENDENCY
        ));
    }
}
