<?php

namespace Tests\Unit;

use App\Models\Court;
use App\Models\User;
use App\Service\CourtService;
use App\Service\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourtServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedModalityAndCity(): array
    {
        $modalityId = DB::table('modalities')->insertGetId([
            'name' => 'Society',
            'description' => 'Modalidade de teste',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateId = DB::table('states')->insertGetId([
            'name' => 'Test State',
            'short' => 'TS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'state_id' => $stateId,
            'name' => 'Test City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$modalityId, $cityId];
    }

    private function courtPayload(int $modalityId, int $cityId, int $photoCount = 1): array
    {
        $photos = [];
        for ($i = 0; $i < $photoCount; $i++) {
            $photos[] = UploadedFile::fake()->create("court-$i.jpg", 100, 'image/jpeg');
        }

        return [
            'courtName' => 'Quadra Teste',
            'courtDescription' => 'Uma quadra de teste',
            'courtModalityId' => $modalityId,
            'courtCityId' => $cityId,
            'courtAddressLine' => 'Rua Teste, 123',
            'courtSurfaceType' => 'sintetico',
            'contacts' => [
                ['type' => 'whatsapp', 'value' => '5511999999999', 'label' => 'Principal'],
            ],
            'photos' => $photos,
            'pricingRules' => [
                ['weekday' => 1, 'startTime' => '08:00', 'endTime' => '22:00', 'priceCents' => 10000],
            ],
            'scheduleConfigs' => [
                ['weekday' => 1, 'isActive' => true, 'startTime' => '08:00', 'endTime' => '22:00', 'reservationDuration' => 60],
            ],
        ];
    }

    public function test_court_owner_role_granted_once_and_idempotent_on_second_court(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $user = User::factory()->create();
        Auth::login($user);

        $service = app(CourtService::class);

        $service->createCourt($this->courtPayload($modalityId, $cityId));
        $this->assertTrue($user->fresh()->hasRole('court-owner'));

        // A second court must not fail nor duplicate the role grant.
        $service->createCourt($this->courtPayload($modalityId, $cityId));

        $this->assertSame(2, Court::count());
        $this->assertSame(
            1,
            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->count()
        );
    }

    public function test_db_rollback_leaves_no_court_when_photo_upload_throws(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $user = User::factory()->create();
        Auth::login($user);

        $uploadMock = $this->createMock(UploadService::class);
        $uploadMock->method('uploadFileToFolder')
            ->willThrowException(new \RuntimeException('upload failed'));

        $service = new CourtService(app(\App\Repository\CourtRepository::class), $uploadMock);

        try {
            $service->createCourt($this->courtPayload($modalityId, $cityId));
            $this->fail('Expected the upload failure to propagate.');
        } catch (\Throwable $exception) {
            $this->assertSame('upload failed', $exception->getMessage());
        }

        $this->assertSame(0, Court::count());
        $this->assertSame(0, DB::table('court_contacts')->count());
        $this->assertFalse($user->fresh()->hasRole('court-owner'));
    }

    public function test_approve_rejects_illegal_status_transition(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $user = User::factory()->create();
        Auth::login($user);

        $service = app(CourtService::class);
        $service->createCourt($this->courtPayload($modalityId, $cityId));
        $court = Court::first();

        // pending -> approved is allowed.
        $service->approve($court->id);

        // approved -> approved is illegal (409).
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(409);
        $service->approve($court->id);
    }

    public function test_reject_rejects_illegal_status_transition(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $user = User::factory()->create();
        Auth::login($user);

        $service = app(CourtService::class);
        $service->createCourt($this->courtPayload($modalityId, $cityId));
        $court = Court::first();

        $service->reject($court->id, 'bad');

        // rejected -> rejected is illegal (409).
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(409);
        $service->reject($court->id, 'again');
    }
}
