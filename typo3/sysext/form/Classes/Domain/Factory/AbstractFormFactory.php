<?php
declare(strict_types=1);
namespace TYPO3\CMS\Form\Domain\Factory;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It originated from the Neos.Form package (www.neos.io)
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Base class for custom *Form Factories*. A Form Factory is responsible for building
 * a {@link TYPO3\CMS\Form\Domain\Model\FormDefinition}.
 *
 * {@inheritDoc}
 *
 * Example
 * =======
 *
 * Generally, you should use this class as follows:
 *
 * <pre>
 * class MyFooBarFactory extends AbstractFormFactory {
 *   public function build(array $configuration, $prototypeName) {
 *     $configurationService = GeneralUtility::makeInstance(ObjectManager::class)->get(ConfigurationService::class);
 *     $prototypeConfiguration = $configurationService->getPrototypeConfiguration($prototypeName);
 *     $formDefinition = GeneralUtility::makeInstance(ObjectManager::class)->get(FormDefinition::class, 'nameOfMyForm', $prototypeConfiguration);
 *
 *     // now, you should call methods on $formDefinition to add pages and form elements
 *
 *     return $formDefinition;
 *   }
 * }
 * </pre>
 *
 * Scope: frontend / backend
 * **This class is meant to be sub classed by developers.**
 * @api
 */
abstract class AbstractFormFactory implements FormFactoryInterface
{
    /**
     * Helper to be called by every AbstractFormFactory after everything has been built to dispatch the "onBuildingFinished"
     * signal on all form elements.
     *
     * @param FormDefinition $form
     * @return void
     * @api
     */
    protected function triggerFormBuildingFinished(FormDefinition $form)
    {
        foreach ($form->getRenderablesRecursively() as $renderable) {
            GeneralUtility::deprecationLog('EXT:form - calls for "onBuildingFinished" are deprecated since TYPO3 v8 and will be removed in TYPO3 v9');
            $renderable->onBuildingFinished();

            GeneralUtility::makeInstance(ObjectManager::class)
                ->get(Dispatcher::class)
                ->dispatch(
                    FormRuntime::class,
                    'onBuildingFinished',
                    [$renderable]
                );
        }
    }
}
