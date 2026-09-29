<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];

        if (!in_array('Quote', $tabList)) {
            $tabList[] = 'Quote';

            $configWriter->set('tabList', $tabList);
            $configWriter->save();
        }
    }
}
