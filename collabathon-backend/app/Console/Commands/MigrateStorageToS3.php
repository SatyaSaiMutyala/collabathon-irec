<?php

namespace App\Console\Commands;

use App\Support\FileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies files already sitting in storage/app/public up to S3.
 *
 * Everything written before the switch to S3 lives on the server's own disk, and the
 * paths recorded in the database are relative (`properties/12/cover.jpg`) — the same
 * strings the S3 disks use. So this is a straight copy, not a rewrite: nothing in the
 * database changes, and once the files are in the bucket the existing rows resolve to
 * them because App\Support\FileStorage now looks there.
 *
 * Each file's destination disk is decided by the same prefix rule the application uses
 * (FileStorage::diskFor), so a PAN scan that was public on the old server lands in the
 * private half of the bucket. That is the one thing this command does that is not a
 * pure copy, and it is the point of running it rather than `aws s3 sync`.
 *
 * Safe to re-run. A file already present in the bucket with the same size is skipped,
 * so an interrupted run resumes, and a second run after more uploads only moves the
 * new ones. Nothing is deleted locally — deleting is a separate decision to make once
 * the bucket has been verified.
 */
class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : List what would be copied without writing anything}
                            {--overwrite : Re-upload files that already exist in the bucket}';

    protected $description = 'Copy existing local uploads into the configured S3 disks';

    public function handle(): int
    {
        if (config('filesystems.disks.uploads.driver') !== 's3') {
            $this->error('The uploads disk is not on S3. Set FILESYSTEM_DISK=s3 (and the AWS_* values) first.');

            return self::FAILURE;
        }

        // Read the source through a disk built here rather than through `public`:
        // that disk's meaning depends on config, and this command is specifically
        // about the physical directory the old server wrote to.
        $source = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'throw' => true,
        ]);

        $files = collect($source->allFiles())
            // .gitignore and friends are scaffolding, not uploads.
            ->reject(fn (string $path) => str_starts_with(basename($path), '.'))
            ->values();

        if ($files->isEmpty()) {
            $this->info('Nothing in storage/app/public to copy.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $this->info(($dryRun ? 'Would copy ' : 'Copying ').$files->count().' file(s) to '.config('filesystems.disks.uploads.bucket'));
        $this->newLine();

        $copied = $skipped = 0;
        $failed = [];

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $path) {
            $bar->advance();

            $target = FileStorage::diskFor($path);
            $secure = FileStorage::isSecure($path);

            try {
                if (! $overwrite && $target->exists($path) && $target->size($path) === $source->size($path)) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $copied++;

                    continue;
                }

                // Streamed, not read into memory: a 40 MB brochure on a t3.micro is
                // otherwise a real chance of exhausting PHP's memory limit mid-run.
                $stream = $source->readStream($path);
                $target->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $copied++;
            } catch (Throwable $e) {
                $failed[] = [$path, $secure ? 'secure' : 'uploads', $e->getMessage()];
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->line(($dryRun ? '<comment>Would copy</comment> ' : '<info>Copied</info> ').$copied);
        $this->line('<comment>Skipped (already there)</comment> '.$skipped);

        if ($failed) {
            $this->newLine();
            $this->error(count($failed).' file(s) failed:');
            $this->table(['Path', 'Disk', 'Error'], $failed);

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->newLine();
            $this->line('Local copies were left in place. Remove storage/app/public only after checking the bucket.');
        }

        return self::SUCCESS;
    }
}
