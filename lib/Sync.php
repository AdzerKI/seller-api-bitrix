<?php

namespace Masstech\Opotopo;

use Bitrix\Main\Application;
use CAgent;
use Masstech\Opotopo\Catalog\ProductCatalog;
use Masstech\Opotopo\Files\TableFileStore;
use Masstech\Opotopo\Sync\TableItemStore;
use Masstech\Opotopo\Vendor\Nyholm\Psr7\Factory\Psr17Factory;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\FileUploader;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Sync\Synchronizer;
use Throwable;

final class Sync
{
    public const CATALOG = 'products';

    private const BATCH = 100;

    private const PERIOD = 3600;

    private const SCHEDULED = '\\'.self::class.'::scheduled();';

    public const QUEUED = '\\'.self::class.'::queued();';

    public const LOCK = Settings::PREFIX.'_sync';

    private const SORT = 100;

    public static function scheduled(): string
    {
        self::run();

        return self::SCHEDULED;
    }

    public static function queued(): string
    {
        CAgent::RemoveAgent(self::QUEUED, Settings::MODULE);
        self::run();

        return '';
    }

    public static function queue(): void
    {
        CAgent::AddAgent(self::QUEUED, Settings::MODULE, 'N', 0, '', 'Y', '', self::SORT, false, false);
    }

    public static function schedule(): void
    {
        CAgent::AddAgent(self::SCHEDULED, Settings::MODULE, 'N', self::PERIOD, '', 'Y', '', self::SORT, false, false);
    }

    private static function run(): void
    {
        if (! Settings::configured()) {
            return;
        }
        $connection = Application::getConnection();
        if (! $connection->lock(self::LOCK)) {
            self::queue();

            return;
        }
        try {
            $client = Platform::client();
            $report = (new Synchronizer(
                $client,
                new FileUploader($client, new TableFileStore, new Psr17Factory),
                new TableItemStore,
                self::BATCH,
            ))->run([self::CATALOG => new ProductCatalog]);
            Settings::saveReport([
                'finishedAt' => time(),
                'created' => $report->created,
                'updated' => $report->updated,
                'skipped' => $report->skipped,
                'archived' => $report->archived,
                'failed' => count($report->failures),
            ]);
        } catch (Throwable $failure) {
            Settings::saveReport(['finishedAt' => time(), 'error' => $failure->getMessage()]);

            throw $failure;
        } finally {
            $connection->unlock(self::LOCK);
        }
    }
}
