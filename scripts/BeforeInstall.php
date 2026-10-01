<?php

use Espo\Core\Container;
use Espo\Core\Utils\Metadata;

class BeforeInstall
{
    public function run(Container $container): void
    {
        $metadata = $container->getByClass(Metadata::class);

        if (!$metadata->get(['scopes', 'CProduct', 'entity'])) {
            throw new Exception('此版本需要现有产品实体 CProduct；不会自动新建或覆盖产品主数据。');
        }

        $scope = $metadata->get(['scopes', 'Quote']);

        if ($scope && ($scope['module'] ?? null) !== 'QuoteManagement') {
            throw new Exception(
                "Entity type 'Quote' already exists (module: " .
                ($scope['module'] ?? 'unknown') .
                "). It may come from EspoCRM Sales Pack. " .
                "Uninstall the conflicting extension before installing Quote Management."
            );
        }
    }
}
