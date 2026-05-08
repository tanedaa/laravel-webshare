<?php

namespace Tanedaa\LaravelWebShare\Console\Commands;

use Illuminate\Console\Command;
use Throwable;
use Tanedaa\LaravelWebShare\Services\WebShare;

class UpdateWebShareProxiesCommand extends Command
{
    protected $signature = 'webshare:update-proxies {--page-size=100 : Page size used when fetching proxies from WebShare}';

    protected $description = 'Fetch proxies from WebShare and upsert them into the configured proxy table';

    public function handle(WebShare $webShare): int
    {
        $pageSize = max(1, (int) $this->option('page-size'));

        try {
            $processed = $webShare->updateProxyList($pageSize);
        } catch (Throwable $exception) {
            $this->error('Failed to update proxies: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info("WebShare proxy sync complete. Processed {$processed} proxy record(s).");

        return self::SUCCESS;
    }
}
