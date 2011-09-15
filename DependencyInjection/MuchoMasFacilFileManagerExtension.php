<?php

namespace MuchoMasFacil\FileManagerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MuchoMasFacilFileManagerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        if (isset($config['options'])) {
            $container_options = $container->getParameter('mucho_mas_facil_file_manager.options');
            $user_options = $config['options'];
            foreach ($user_options as $name => $val) {
                if(isset($container_options[$name])) {
                    $container_options[$name] = array_merge($container_options[$name], $val);
                }
                else {
                    $container_options[$name] = $val;
                }
            }
            $container->setParameter('mucho_mas_facil_file_manager.options', $container_options);
        }//end if

    }
}

