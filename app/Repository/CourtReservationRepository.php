<?php

namespace App\Repository;

use App\Models\CourtReservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CourtReservationRepository extends BaseRepository
{
    public function __construct(CourtReservation $courtReservation)
    {
        $this->model = $courtReservation;
    }

    /**
     * Day counts for a month, keyed YYYY-MM-DD. Uses the raw column value as the
     * key (NOT a Carbon cast) — every insert writes reservation_date as Y-m-d so
     * the raw value is already YYYY-MM-DD on both MySQL and SQLite (design §7).
     *
     * @return array<string, int>
     */
    public function countByDay(int $courtId, string $monthStart, string $monthEnd): array
    {
        // whereBetween is inclusive of the month-end day: pad the upper bound so a
        // stored "YYYY-MM-DD 00:00:00" (SQLite date-cast) on the last day still falls
        // in range. The group key is normalised to the first 10 chars (Y-m-d) so the
        // response map matches the FE localKey on both engines.
        $counts = [];

        $this->model
            ->newQuery()
            ->select('reservation_date', DB::raw('COUNT(*) as total'))
            ->where('court_id', $courtId)
            ->whereBetween('reservation_date', [$monthStart, $monthEnd . ' 23:59:59'])
            ->groupBy('reservation_date')
            ->get()
            ->each(function ($row) use (&$counts) {
                $day = substr((string) $row->reservation_date, 0, 10);
                $counts[$day] = ($counts[$day] ?? 0) + (int) $row->total;
            });

        return $counts;
    }

    public function reservationsForDay(int $courtId, string $date): Collection
    {
        // whereDate is portable (DATE() on MySQL, date() on SQLite) so it matches
        // whether the column stores "Y-m-d" (MySQL) or "Y-m-d 00:00:00" (SQLite
        // date-cast). start_time alone keys the slot within a (court, date).
        return $this->model
            ->newQuery()
            ->where('court_id', $courtId)
            ->whereDate('reservation_date', $date)
            ->orderBy('start_time')
            ->get();
    }

    public function slotTaken(int $courtId, string $date, string $startTime): bool
    {
        return $this->model
            ->newQuery()
            ->where('court_id', $courtId)
            ->whereDate('reservation_date', $date)
            ->where('start_time', $startTime)
            ->exists();
    }

    public function insert(array $data): CourtReservation
    {
        return $this->model->create($data);
    }

    public function firstScoped(int $courtId, int $reservationId): ?CourtReservation
    {
        return $this->model
            ->newQuery()
            ->where('court_id', $courtId)
            ->where('id', $reservationId)
            ->first();
    }

    /**
     * The SOLE delete path. The model has NO SoftDeletes, so delete() is a true
     * hard delete (design §1).
     *
     * scope=one   -> delete just this reservation.
     * scope=future-> if it belongs to a recurrence group, delete that occurrence
     *                and every later one in the group; a null group degrades to one.
     */
    public function deleteGroupOrOne(CourtReservation $reservation, string $scope): int
    {
        if ($scope === 'future' && $reservation->recurrence_group_id !== null) {
            return $this->model
                ->newQuery()
                ->where('recurrence_group_id', $reservation->recurrence_group_id)
                ->where('reservation_date', '>=', $reservation->reservation_date->format('Y-m-d'))
                ->delete();
        }

        return (int) $reservation->delete();
    }
}
