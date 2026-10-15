<?php

namespace Tests\Unit;

use App\Enums\CourtStatus;
use App\Enums\CourtSurfaceType;
use App\Models\Court;
use App\Models\CourtPhoto;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourtModelTest extends TestCase
{
    public function test_photo_url_returns_a_url_for_a_set_photo_path(): void
    {
        Storage::fake('public');

        $photo = new CourtPhoto(['photo_path' => 'courts/example.jpg']);

        $this->assertSame(
            Storage::disk('public')->url('courts/example.jpg'),
            $photo->photo_url
        );
    }

    public function test_photo_url_is_null_when_no_photo_path(): void
    {
        Storage::fake('public');

        $photo = new CourtPhoto();

        $this->assertNull($photo->photo_url);
    }

    public function test_court_casts_surface_type_and_status_to_enums(): void
    {
        $court = new Court([
            'surface_type' => 'sintetico',
            'status' => 'approved',
        ]);

        $this->assertInstanceOf(CourtSurfaceType::class, $court->surface_type);
        $this->assertSame(CourtSurfaceType::Sintetico, $court->surface_type);

        $this->assertInstanceOf(CourtStatus::class, $court->status);
        $this->assertSame(CourtStatus::Approved, $court->status);
    }
}
