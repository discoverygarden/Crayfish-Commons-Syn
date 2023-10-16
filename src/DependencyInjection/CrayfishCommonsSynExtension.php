<?php

namespace Islandora\Crayfish\Commons\Syn\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CrayfishCommonsSynExtension extends Extension
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

        $container->getDefinition('crayfish_commons_syn.settings_parser')
          ->replaceArgument(0, $config['crayfish_commons_syn']['config_xml']);
    }
}
