<?php

namespace Islandora\Crayfish\Commons\Syn\DependencyInjection;

use Islandora\Crayfish\Commons\Syn\SettingsParserInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class IslandoraCrayfishCommonsSynExtension extends Extension
{

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->getDefinition(SettingsParserInterface::class)
          ->replaceArgument(0, $config['config_xml']);
    }
}
