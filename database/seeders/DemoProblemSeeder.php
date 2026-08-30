<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Models\ProblemCategory;
use App\Models\User;
use Database\Seeders\Support\DemoBlueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the current month's delivery problems so the Pareto chart reproduces
 * the reference distribution (38 / 24 / 12 / 6 / 3 = 83 problems, cumulative
 * 46% / 75% / 89% / 96% / 100%), plus their corrective actions.
 *
 * CRITICAL severity is only ever assigned to problems whose material already
 * belongs to the problem-material set, which is what keeps the critical
 * material count pinned at seven.
 */
class DemoProblemSeeder extends Seeder
{
    public function run(): void
    {
        $month = Carbon::now()->startOfMonth();
        $categories = ProblemCategory::query()->pluck('id', 'code');
        $reporter = User::query()->orderBy('id')->value('id');

        $late = $this->pool($month, timeliness: TimelinessStatus::LATE);
        $short = $this->pool($month, quantity: QuantityStatus::SHORT);
        $general = $this->pool($month);

        $sources = [
            'LATE_DELIVERY' => $late,
            'SHORT_DELIVERY' => $short->isEmpty() ? $general : $short,
            'WRONG_MATERIAL' => $general,
            'DOCUMENT_PROBLEM' => $general,
            'SCHEDULE_PROBLEM' => $general,
        ];

        $severityAllowed = ['LATE_DELIVERY', 'SHORT_DELIVERY'];
        $rows = [];
        $sequence = 0;

        foreach (DemoBlueprint::PROBLEM_DISTRIBUTION as $code => $count) {
            $pool = $sources[$code];

            for ($i = 0; $i < $count; $i++) {
                $source = $pool[$i % $pool->count()];
                $sequence++;

                $severity = in_array($code, $severityAllowed, true)
                    ? $this->severityFor($i)
                    : $this->nonCriticalSeverityFor($i);

                $problemDate = $month->copy()->addDays($sequence % 26);
                $status = $this->statusFor($sequence);

                $rows[] = [
                    'ulid' => (string) Str::ulid(),
                    'problem_number' => sprintf('PRB-%s-%04d', $month->format('Ym'), $sequence),
                    'delivery_id' => $source->delivery_id,
                    'supplier_id' => $source->supplier_id,
                    'material_id' => $source->material_id,
                    'problem_category_id' => $categories[$code],
                    'problem_date' => $problemDate->toDateString(),
                    'description' => $this->descriptionFor($code),
                    'severity' => $severity->value,
                    'root_cause' => $this->rootCauseFor($code),
                    'status' => $status->value,
                    'pic' => 'PIC '.($sequence % 5 + 1),
                    'due_date' => $problemDate->copy()->addDays($severity->resolutionDays())->toDateString(),
                    'closed_at' => $status === ProblemStatus::CLOSED ? $problemDate->copy()->addDays(2) : null,
                    'created_by' => $reporter,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('delivery_problems')->insert($rows);

        $this->seedCorrectiveActions($reporter);
    }

    /**
     * Delivery lines from the current month, optionally narrowed to a status,
     * carrying everything a problem row needs.
     *
     * @return Collection<int, object>
     */
    private function pool(
        Carbon $month,
        ?TimelinessStatus $timeliness = null,
        ?QuantityStatus $quantity = null,
    ): Collection {
        $query = DB::table('delivery_items')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->whereBetween('deliveries.delivery_date', [
                $month->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->select(
                'delivery_items.delivery_id',
                'delivery_items.material_id',
                'deliveries.supplier_id',
            )
            ->orderBy('delivery_items.id');

        if ($timeliness !== null) {
            $query->where('delivery_items.timeliness_status', $timeliness->value);
        }

        if ($quantity !== null) {
            $query->where('delivery_items.quantity_status', $quantity->value);
        }

        return $query->get();
    }

    private function severityFor(int $index): ProblemSeverity
    {
        return match ($index % 5) {
            0 => ProblemSeverity::CRITICAL,
            1, 2 => ProblemSeverity::HIGH,
            3 => ProblemSeverity::MEDIUM,
            default => ProblemSeverity::LOW,
        };
    }

    private function nonCriticalSeverityFor(int $index): ProblemSeverity
    {
        return match ($index % 3) {
            0 => ProblemSeverity::HIGH,
            1 => ProblemSeverity::MEDIUM,
            default => ProblemSeverity::LOW,
        };
    }

    private function statusFor(int $sequence): ProblemStatus
    {
        return match ($sequence % 4) {
            0 => ProblemStatus::CLOSED,
            1 => ProblemStatus::IN_PROGRESS,
            default => ProblemStatus::OPEN,
        };
    }

    private function descriptionFor(string $code): string
    {
        return match ($code) {
            'LATE_DELIVERY' => 'Material diterima melewati schedule delivery date yang disepakati pada PO.',
            'SHORT_DELIVERY' => 'Quantity yang diterima lebih kecil dari quantity yang tertera pada PO.',
            'WRONG_MATERIAL' => 'Material yang dikirim tidak sesuai dengan spesifikasi pada PO.',
            'DOCUMENT_PROBLEM' => 'Surat jalan dan dokumen pendukung tidak lengkap saat penerimaan.',
            default => 'Pengiriman tidak mengikuti jadwal delivery yang telah disepakati.',
        };
    }

    private function rootCauseFor(string $code): string
    {
        return match ($code) {
            'LATE_DELIVERY' => 'Keterlambatan produksi di pihak supplier dan kendala transportasi.',
            'SHORT_DELIVERY' => 'Stok supplier tidak mencukupi pada saat pengiriman.',
            'WRONG_MATERIAL' => 'Kesalahan picking di gudang supplier.',
            'DOCUMENT_PROBLEM' => 'Proses administrasi supplier belum terstandarisasi.',
            default => 'Perencanaan pengiriman supplier tidak sinkron dengan schedule PO.',
        };
    }

    /**
     * One corrective action per problem; closed problems get a completed one so
     * the "closing requires a completed action" rule holds for seeded data too.
     */
    private function seedCorrectiveActions(?int $actionBy): void
    {
        $problems = DB::table('delivery_problems')
            ->select('id', 'problem_date', 'status')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($problems as $index => $problem) {
            $isClosed = $problem->status === ProblemStatus::CLOSED->value;
            $actionDate = Carbon::parse($problem->problem_date)->addDay();

            $rows[] = [
                'delivery_problem_id' => $problem->id,
                'action_date' => $actionDate->toDateString(),
                'action_by' => $actionBy,
                'description' => $isClosed
                    ? 'Supplier telah melakukan perbaikan proses dan menyerahkan komitmen delivery baru.'
                    : 'Klarifikasi ke supplier dan permintaan corrective action plan.',
                'status' => ($isClosed ? CorrectiveActionStatus::DONE : ($index % 2 === 0
                    ? CorrectiveActionStatus::IN_PROGRESS
                    : CorrectiveActionStatus::OPEN))->value,
                'due_date' => $actionDate->copy()->addDays(14)->toDateString(),
                'completed_at' => $isClosed ? $actionDate->copy()->addDays(3) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('corrective_actions')->insert($chunk);
        }
    }
}
