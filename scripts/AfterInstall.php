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

        // 增量加入项目报价面板，不替换用户已有文档等面板。
        $layouts = $container->getByClass(InjectableFactory::class)
            ->create(\Espo\Tools\Layout\Service::class);
        $panels = (object) ($layouts->getOriginal('Opportunity', 'bottomPanelsDetail') ?? new \stdClass());
        if (!isset($panels->quotes)) {
            $panels->quotes = (object) ['name' => 'quotes', 'index' => 0];
            $layouts->update('Opportunity', 'bottomPanelsDetail', null, $panels);
        }
        $relations = $layouts->getOriginal('Opportunity', 'relationships');
        if (is_array($relations) && !in_array('quotes', $relations, true)) {
            $relations[] = 'quotes';
            $layouts->update('Opportunity', 'relationships', null, $relations);
        }

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
