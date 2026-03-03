<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SickLeaveCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Company::query()->exists()) {
            $this->command?->warn('Nincs cég, ezért SickLeaveCategories seeding kihagyva.');
            return;
        }

        $items = [
            [
                'name' => 'Saját betegség',
                'description' => 'A dolgozó saját keresőképtelensége vagy betegsége miatt rögzített távollét.',
                'order_index' => 10,
            ],
            [
                'name' => 'Gyermek ápolása',
                'description' => 'Beteg gyermek otthoni ápolása vagy felügyelete miatti távollét.',
                'order_index' => 20,
            ],
            [
                'name' => 'Hozzátartozó ápolása',
                'description' => 'Közeli hozzátartozó ápolása vagy gondozása miatt igénybe vett távollét.',
                'order_index' => 30,
            ],
            [
                'name' => 'Üzemi baleset',
                'description' => 'Munkavégzés közben bekövetkezett üzemi balesethez kapcsolódó keresőképtelenség.',
                'order_index' => 40,
            ],
            [
                'name' => 'Közúti baleset',
                'description' => 'Közúti baleset következtében kialakult keresőképtelenség vagy rehabilitációs időszak.',
                'order_index' => 50,
            ],
            [
                'name' => 'Karantén / járványügyi elkülönítés',
                'description' => 'Hatósági vagy orvosi elrendelés alapján szükséges elkülönítés miatti távollét.',
                'order_index' => 60,
            ],
            [
                'name' => 'Orvosi vizsgálat',
                'description' => 'Egészségügyi vizsgálat, kontroll vagy kezelés miatt rögzített rövid távollét.',
                'order_index' => 70,
            ],
            [
                'name' => 'Egyéb',
                'description' => 'Minden egyéb, külön nem kategorizált betegszabadság jellegű eset gyűjtőkategóriája.',
                'order_index' => 80,
            ],
        ];

        Company::query()
            ->select(['id'])
            ->chunkById(100, function ($companies) use ($items): void {
                foreach ($companies as $company) {
                    $companyId = (int) $company->id;

                    foreach ($items as $item) {
                        $code = $this->resolveUniqueCode($companyId, (string) $item['name']);

                        DB::table('sick_leave_categories')->updateOrInsert(
                            [
                                'company_id' => $companyId,
                                'code' => $code,
                            ],
                            [
                                'name' => $item['name'],
                                'description' => $item['description'],
                                'active' => true,
                                'order_index' => (int) $item['order_index'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            });
    }

    private function resolveUniqueCode(int $companyId, string $name): string
    {
        $baseSlug = Str::slug($name, '_');
        $baseCode = 'slc_'.($baseSlug !== '' ? $baseSlug : 'kategoria');

        $existing = DB::table('sick_leave_categories')
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->orderBy('id')
            ->value('code');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $candidate = $baseCode;
        $suffix = 2;

        while (
            DB::table('sick_leave_categories')
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->exists()
        ) {
            $candidate = "{$baseCode}_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
