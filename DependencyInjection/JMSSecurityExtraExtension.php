<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\SecurityExtraBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;

use JMS\SecurityExtraBundle\Exception\RuntimeException;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * JMSSecurityExtraExtension.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JMSSecurityExtraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['JMSAopBundle'])) {
            throw new RuntimeException('The JMSSecurityExtraBundle requires the JMSAopBundle, please make sure to enable it in your AppKernel.');
        }

        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(array(__DIR__.'/../Resources/config/')));
        $loader->load('services.xml');

        $container->setParameter('security.access.secure_all_services', $config['secure_all_services']);

        $cacheDir = $container->getParameterBag()->resolveValue($config['cache_dir']);
        if (!is_dir($cacheDir)) {
            if (false === @mkdir($cacheDir, 0777, true)) {
                throw new RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
            }
        }
        $container->setParameter('security.extra.cache_dir', $cacheDir);

        if ($config['expressions']) {
            $loader->load('security_expressions.xml');

            if (!is_dir($cacheDir.'/expressions')) {
                if (false === @mkdir($cacheDir.'/expressions', 0777, true)) {
                    throw new RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir.'/expressions'));
                }
            }

            $container->getDefinition('security.expressions.voter')
                ->addMethodCall('setCacheDir', array($cacheDir.'/expressions'));
        }

        $disableAllVoters = !isset($config['voters']);
        $container->setParameter('security.authenticated_voter.disabled',
            $disableAllVoters || $config['voters']['disable_authenticated']);
        $container->setParameter('security.role_voter.disabled',
            $disableAllVoters || $config['voters']['disable_role']);
        $container->setParameter('security.acl_voter.disabled',
            $disableAllVoters || $config['voters']['disable_acl']);

        if ($config['enable_iddqd_attribute']) {
            $container
                ->getDefinition('security.extra.iddqd_voter')
                ->addTag('security.voter')
            ;

            // FIXME: Also add an iddqd after invocation provider
        }

        if ($config['method_access_control']) {
            $driverDef = $container->getDefinition('security.extra.driver_chain');
            $args = $driverDef->getArguments();
            array_unshift($args[0], new Reference('security.extra.config_driver'));
            $driverDef->setArguments($args);

            $container->setParameter('security.access.method_access_control',
                $config['method_access_control']);
        }
    }
}