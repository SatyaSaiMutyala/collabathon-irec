<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Load-shape data for verifying the 8k-concurrent-user requirement.
 * Not part of the demo seed — run explicitly:
 *
 *   php artisan db:seed --class=VolumeSeeder
 *
 * Uses chunked bulk inserts rather than Eloquent create() per row; 60k model
 * instantiations would take minutes and prove nothing about the query layer.
 */
class VolumeSeeder extends Seeder
{
    private const BROKERS = 8000;

    private const PROPERTIES = 2000;

    private const LEADS = 60000;

    public function run(): void
    {
        $this->command->info('Seeding volume data — this takes a minute.');

        $developerIds = Developer::pluck('id')->all();
        if ($developerIds === []) {
            $this->command->error('Run the base DatabaseSeeder first.');

            return;
        }

        $this->seedBrokers();
        $this->seedProperties($developerIds);
        $this->seedLeads();

        $this->command->info(sprintf(
            'Done. users=%d properties=%d leads=%d',
            DB::table('users')->count(),
            DB::table('properties')->count(),
            DB::table('leads')->count(),
        ));
    }

    private function seedBrokers(): void
    {
        $existing = User::where('email', 'like', 'load%@irec.test')->count();
        $needed = self::BROKERS - $existing;

        if ($needed <= 0) {
            return;
        }

        // One hash for all synthetic users — bcrypt is deliberately slow, and
        // hashing 8k times would dominate the run for no benefit.
        $password = bcrypt('password');
        $now = now();
        $cities = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'];

        foreach (array_chunk(range($existing + 1, self::BROKERS), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $rows[] = [
                    'name' => 'Load Broker ' . $i,
                    'email' => 'load' . $i . '@irec.test',
                    'password' => $password,
                    'role' => User::ROLE_BROKER,
                    'status' => $i % 10 === 0 ? User::STATUS_PENDING : User::STATUS_ACTIVE,
                    'mobile' => '+9715' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                    'created_at' => $now->copy()->subMinutes($i),
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($rows);
            $this->command->getOutput()->write('.');
        }
        $this->command->getOutput()->writeln(' brokers');
    }

    /** @param array<int,int> $developerIds */
    private function seedProperties(array $developerIds): void
    {
        $existing = Property::where('slug', 'like', 'load-%')->count();
        $needed = self::PROPERTIES - $existing;

        if ($needed <= 0) {
            return;
        }

        $now = now();
        $types = ['Residential', 'Commercial', 'Villa', 'Plotted Development'];
        $cities = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman'];
        $localities = ['Marina', 'Downtown', 'Business Bay', 'JLT', 'Corniche', 'Al Reem'];

        foreach (array_chunk(range($existing + 1, self::PROPERTIES), 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $min = 400_000 + ($i % 40) * 250_000;
                $rows[] = [
                    'developer_id' => $developerIds[$i % count($developerIds)],
                    'name' => 'Load Tower ' . $i,
                    'slug' => 'load-' . $i . '-' . Str::lower(Str::random(4)),
                    'project_type' => $types[$i % count($types)],
                    'project_status' => 'Under Construction',
                    'listing_status' => $i % 12 === 0 ? 'draft' : 'active',
                    'state' => 'UAE',
                    'city' => $cities[$i % count($cities)],
                    'locality' => $localities[$i % count($localities)],
                    'price_min' => $min,
                    'price_max' => $min + 800_000,
                    'price_per_sqft' => 1000 + ($i % 500),
                    'currency' => 'INR',
                    'views_count' => $i % 300,
                    'interests_count' => $i % 40,
                    'created_at' => $now->copy()->subHours($i % 2000),
                    'updated_at' => $now,
                ];
            }
            DB::table('properties')->insert($rows);
            $this->command->getOutput()->write('.');
        }
        $this->command->getOutput()->writeln(' properties');
    }

    private function seedLeads(): void
    {
        $existing = DB::table('leads')->count();
        if ($existing >= self::LEADS) {
            return;
        }

        $brokerIds = User::where('email', 'like', 'load%@irec.test')->pluck('id')->all();
        $properties = DB::table('properties')
            ->select('id', 'developer_id')
            ->where('listing_status', 'active')
            ->get();

        if ($brokerIds === [] || $properties->isEmpty()) {
            return;
        }

        $now = now();
        $statuses = ['viewed', 'interested', 'accepted', 'declined'];
        $made = 0;
        $rows = [];
        // The (property_id, broker_id) unique index means we must not repeat a pair.
        $seen = [];

        while ($made < self::LEADS - $existing) {
            $property = $properties[$made % $properties->count()];
            $brokerId = $brokerIds[($made * 7) % count($brokerIds)];
            $key = $property->id . ':' . $brokerId;

            if (isset($seen[$key])) {
                $made++;
                continue;
            }
            $seen[$key] = true;

            $status = $statuses[$made % 4];
            $created = $now->copy()->subMinutes($made % 120000);

            $rows[] = [
                'property_id' => $property->id,
                'broker_id' => $brokerId,
                'developer_id' => $property->developer_id,
                'status' => $status,
                'contact_unlocked' => $status !== 'viewed',
                'viewed_at' => $created,
                'interested_at' => $status !== 'viewed' ? $created : null,
                'created_at' => $created,
                'updated_at' => $created,
            ];

            $made++;

            if (count($rows) >= 2000) {
                DB::table('leads')->insertOrIgnore($rows);
                $rows = [];
                $this->command->getOutput()->write('.');
            }
        }

        if ($rows !== []) {
            DB::table('leads')->insertOrIgnore($rows);
        }
        $this->command->getOutput()->writeln(' leads');
    }
}
