<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TenantGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $count = 100;

        DB::disableQueryLog();
        activity()->disableLogging();

        $this->command->warn("Creating {$count} companies...");
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            $row = Company::factory()->make()->only([
                'name',
                'email',
                'address',
                'phone',
                'active',
            ]);
            $row['active'] = $row['active'] ?? true;

            $baseSlug = Str::slug((string) $row['name']);
            if ($baseSlug === '') {
                $baseSlug = 'company';
            }

            $slug = $baseSlug;
            while (TenantGroup::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.Str::random(6);
            }

            $tenantGroup = TenantGroup::query()->create([
                'name' => $row['name'],
                'code' => $this->makeTenantCode((string) $row['name'], $slug),
                'slug' => $slug,
                'active' => true,
            ]);

            $row['tenant_group_id'] = $tenantGroup->id;
            $row['created_at'] = now();
            $row['updated_at'] = now();

            Company::query()->insert($row);

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("{$count} companies created.");

        activity()->enableLogging();
    }

    private function makeTenantCode(string $name, string $slug): string
    {
        $base = Str::upper(Str::slug($slug !== '' ? $slug : $name, '_'));
        $base = $base !== '' ? Str::limit($base, 50, '') : 'TENANT_GROUP';
        $candidate = $base;
        $counter = 1;

        while (TenantGroup::query()->withTrashed()->where('code', $candidate)->exists()) {
            $suffix = '_'.$counter;
            $candidate = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}
