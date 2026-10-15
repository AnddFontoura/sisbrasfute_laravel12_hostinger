<?php

namespace App\Service;

use App\Models\Court;
use App\Repository\CourtRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class CourtService extends BaseService
{
    public function __construct(
        protected CourtRepository $courtRepository,
        protected UploadService $uploadService
    ) {
    }

    public function getMyCourts()
    {
        return $this->courtRepository->myCourts(Auth::id());
    }

    public function getOwnedDetail(int $courtId): Court
    {
        $court = $this->courtRepository->getDetailById($courtId);

        $this->assertCourtFound($court, $courtId);

        app(CourtAuthorizationService::class)->assertCanEditData($courtId, Auth::id());

        return $court;
    }

    public function createCourt(array $data): Court
    {
        $uploadedPaths = [];

        try {
            return DB::transaction(function () use ($data, &$uploadedPaths) {
                $user = Auth::user();

                $court = $this->courtRepository->create([
                    'user_id' => $user->id,
                    'modality_id' => $data['courtModalityId'],
                    'city_id' => $data['courtCityId'],
                    'slug' => Str::slug($data['courtName']),
                    'name' => $data['courtName'],
                    'description' => $data['courtDescription'] ?? null,
                    'address_line' => $data['courtAddressLine'] ?? null,
                    'surface_type' => $data['courtSurfaceType'],
                    'status' => 'pending',
                ]);

                $this->syncContacts($court, $data['contacts'] ?? []);
                $this->syncPricingRules($court, $data['pricingRules'] ?? []);
                $this->syncScheduleConfigs($court, $data['scheduleConfigs'] ?? []);

                $uploadedPaths = $this->storePhotos($court, $data['photos'] ?? []);

                $this->courtRepository->createOwnerMembership($court->id, $user->id);

                if (!$user->hasRole('court-owner')) {
                    $role = Role::firstOrCreate(['name' => 'court-owner', 'guard_name' => 'web']);
                    $user->assignRole($role);
                }

                return $court;
            });
        } catch (\Throwable $exception) {
            $this->cleanupUploadedPhotos($uploadedPaths);

            Log::error('Court creation failed', [
                'user_id' => Auth::id(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function updateCourt(array $data, int $courtId): Court
    {
        $court = $this->courtRepository->getDetailById($courtId);

        $this->assertCourtFound($court, $courtId);

        app(CourtAuthorizationService::class)->assertCanEditData($courtId, Auth::id());

        $uploadedPaths = [];

        try {
            return DB::transaction(function () use ($court, $courtId, $data, &$uploadedPaths) {
                $dataToUpdate = [
                    'modality_id' => $data['courtModalityId'],
                    'city_id' => $data['courtCityId'],
                    'name' => $data['courtName'] ?? $court->name,
                    'slug' => Str::slug($data['courtName'] ?? $court->name),
                    'description' => $data['courtDescription'] ?? null,
                    'address_line' => $data['courtAddressLine'] ?? null,
                    'surface_type' => $data['courtSurfaceType'],
                ];

                $this->courtRepository->updateById($dataToUpdate, $courtId);

                $this->removePhotos($court, $data['removedPhotoIds'] ?? []);

                $court->contacts()->delete();
                $court->pricingRules()->delete();
                $court->scheduleConfigs()->delete();

                $this->syncContacts($court, $data['contacts'] ?? []);
                $this->syncPricingRules($court, $data['pricingRules'] ?? []);
                $this->syncScheduleConfigs($court, $data['scheduleConfigs'] ?? []);

                $uploadedPaths = $this->storePhotos($court, $data['photos'] ?? []);

                return $court->fresh();
            });
        } catch (\Throwable $exception) {
            $this->cleanupUploadedPhotos($uploadedPaths);

            Log::error('Court update failed', [
                'user_id' => Auth::id(),
                'court_id' => $courtId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function delete(int $courtId): void
    {
        $court = $this->courtRepository->firstById($courtId);

        $this->assertCourtFound($court, $courtId);

        app(CourtAuthorizationService::class)->assertCanManageTeam($courtId, Auth::id());

        $court->delete();
    }

    public function approve(int $courtId): Court
    {
        $court = $this->courtRepository->firstById($courtId);

        throw_if(!$court, new \Exception(
            __('error.court.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        throw_if(
            !in_array($court->status->value, ['pending', 'rejected'], true),
            new \Exception(
                __('error.court.invalid_status_transition'),
                Response::HTTP_CONFLICT
            )
        );

        $court->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return $court->fresh();
    }

    public function reject(int $courtId, ?string $reason): Court
    {
        $court = $this->courtRepository->firstById($courtId);

        throw_if(!$court, new \Exception(
            __('error.court.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        throw_if(
            !in_array($court->status->value, ['pending', 'approved'], true),
            new \Exception(
                __('error.court.invalid_status_transition'),
                Response::HTTP_CONFLICT
            )
        );

        $court->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $court->fresh();
    }

    private function assertCourtFound(?Court $court, int $courtId): void
    {
        throw_if(!$court, new \Exception(
            __('error.court.not_found'),
            Response::HTTP_NOT_FOUND
        ));
    }

    private function syncContacts(Court $court, array $contacts): void
    {
        if (empty($contacts)) {
            return;
        }

        $rows = [];
        foreach (array_values($contacts) as $position => $contact) {
            $rows[] = [
                'type' => $contact['type'],
                'value' => $contact['value'],
                'label' => $contact['label'] ?? null,
                'position' => $position,
            ];
        }

        $court->contacts()->createMany($rows);
    }

    private function syncPricingRules(Court $court, array $pricingRules): void
    {
        if (empty($pricingRules)) {
            return;
        }

        $rows = [];
        foreach ($pricingRules as $rule) {
            $rows[] = [
                'weekday' => $rule['weekday'],
                'start_time' => $rule['startTime'],
                'end_time' => $rule['endTime'],
                'price_cents' => $rule['priceCents'],
            ];
        }

        $court->pricingRules()->createMany($rows);
    }

    private function syncScheduleConfigs(Court $court, array $scheduleConfigs): void
    {
        if (empty($scheduleConfigs)) {
            return;
        }

        $rows = [];
        foreach ($scheduleConfigs as $config) {
            $isActive = filter_var($config['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $rows[] = [
                'weekday' => $config['weekday'],
                'start_time' => $isActive ? ($config['startTime'] ?? null) : null,
                'end_time' => $isActive ? ($config['endTime'] ?? null) : null,
                'reservation_duration' => $isActive ? ($config['reservationDuration'] ?? null) : null,
                'is_active' => $isActive,
            ];
        }

        $court->scheduleConfigs()->createMany($rows);
    }

    /**
     * @return string[] the folder-prefixed paths written during this call
     */
    private function storePhotos(Court $court, array $photos): array
    {
        if (empty($photos)) {
            return [];
        }

        $uploadedPaths = [];
        $position = (int) $court->photos()->max('position');
        $position = $court->photos()->exists() ? $position + 1 : 0;

        foreach ($photos as $file) {
            $path = $this->uploadService->uploadFileToFolder('public', 'courts', $file);
            $uploadedPaths[] = $path;

            $court->photos()->create([
                'photo_path' => $path,
                'position' => $position,
            ]);

            $position++;
        }

        return $uploadedPaths;
    }

    private function removePhotos(Court $court, array $removedPhotoIds): void
    {
        if (empty($removedPhotoIds)) {
            return;
        }

        $photos = $court->photos()->whereIn('id', $removedPhotoIds)->get();

        foreach ($photos as $photo) {
            if ($photo->photo_path) {
                $this->uploadService->deleteFileOnFolder('public', 'courts', basename($photo->photo_path));
            }

            $photo->delete();
        }
    }

    private function cleanupUploadedPhotos(array $uploadedPaths): void
    {
        foreach ($uploadedPaths as $path) {
            try {
                $this->uploadService->deleteFileOnFolder('public', 'courts', basename($path));
            } catch (\Throwable $exception) {
                Log::error('Failed to cleanup uploaded court photo', [
                    'path' => $path,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
