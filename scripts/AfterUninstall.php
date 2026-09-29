<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterUninstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];

        $tabList = array_values(array_filter($tabList, fn($item) => $item !== 'Quote'));

        $configWriter->set('tabList', $tabList);
        $configWriter->save();
    }
}
