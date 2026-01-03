<?php

declare(strict_types=1);

namespace Tbessenreither\MultiLevelCache\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tbessenreither\MultiLevelCache\Bundle\DependencyInjection\Compiler\CompilerPass;
use Tbessenreither\MultiLevelCache\DataCollector\MultiLevelCacheDataCollector;
use Tbessenreither\PhpMultithread\DataCollector\PhpMultithreadDataCollector;
use Tbessenreither\PhpMultithread\Service\MultithreadService;


class PhpMultithreadBundle extends Bundle
{

	public function build(ContainerBuilder $container): void
	{
		parent::build($container);

		$container->addCompilerPass(new CompilerPass());

		$this->processMultiLevelCacheServiceCollector($container);
	}

	private function processMultiLevelCacheServiceCollector(ContainerBuilder $container): void
	{
		$definition = new Definition(MultithreadService::class);
		$definition->setPublic(true);
		$definition->addTag('data_collector', [
			'id' => PhpMultithreadDataCollector::NAME,
			'template' => PhpMultithreadDataCollector::TEMPLATE,
			'priority' => 334,
		]);
		$definition->setArgument('$appEnv', "%env(APP_ENV)%");
		$definition->setArgument('$enhancedDataCollection', '%env(bool:defined:MLC_COLLECT_ENHANCED_DATA)%');
		$definition->setAutowired(true);
		$definition->setAutoconfigured(true);

		$container->setDefinition(PhpMultithreadDataCollector::NAME, $definition);

		$container->setAlias(
			PhpMultithreadDataCollector::class,
			PhpMultithreadDataCollector::NAME
		)->setPublic(true);
	}

}
