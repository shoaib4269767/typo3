<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
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

namespace TYPO3\CMS\Backend\Tests\Unit\Utility;

use TYPO3\CMS\Backend\Tests\Unit\Utility\Fixtures\LabelFromItemListMergedReturnsCorrectFieldsFixture;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BackendUtilityTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    ///////////////////////////////////////
    // Tests concerning calcAge
    ///////////////////////////////////////
    /**
     * Data provider for calcAge function
     */
    public static function calcAgeDataProvider(): array
    {
        return [
            'Single year' => [
                'seconds' => 60 * 60 * 24 * 365,
                'expectedLabel' => '1 year',
            ],
            'Plural years' => [
                'seconds' => 60 * 60 * 24 * 365 * 2,
                'expectedLabel' => '2 yrs',
            ],
            'Single negative year' => [
                'seconds' => 60 * 60 * 24 * 365 * -1,
                'expectedLabel' => '-1 year',
            ],
            'Plural negative years' => [
                'seconds' => 60 * 60 * 24 * 365 * 2 * -1,
                'expectedLabel' => '-2 yrs',
            ],
            'Single day' => [
                'seconds' => 60 * 60 * 24,
                'expectedLabel' => '1 day',
            ],
            'Plural days' => [
                'seconds' => 60 * 60 * 24 * 2,
                'expectedLabel' => '2 days',
            ],
            'Single negative day' => [
                'seconds' => 60 * 60 * 24 * -1,
                'expectedLabel' => '-1 day',
            ],
            'Plural negative days' => [
                'seconds' => 60 * 60 * 24 * 2 * -1,
                'expectedLabel' => '-2 days',
            ],
            'Single hour' => [
                'seconds' => 60 * 60,
                'expectedLabel' => '1 hour',
            ],
            'Plural hours' => [
                'seconds' => 60 * 60 * 2,
                'expectedLabel' => '2 hrs',
            ],
            'Single negative hour' => [
                'seconds' => 60 * 60 * -1,
                'expectedLabel' => '-1 hour',
            ],
            'Plural negative hours' => [
                'seconds' => 60 * 60 * 2 * -1,
                'expectedLabel' => '-2 hrs',
            ],
            'Single minute' => [
                'seconds' => 60,
                'expectedLabel' => '1 min',
            ],
            'Plural minutes' => [
                'seconds' => 60 * 2,
                'expectedLabel' => '2 min',
            ],
            'Single negative minute' => [
                'seconds' => 60 * -1,
                'expectedLabel' => '-1 min',
            ],
            'Plural negative minutes' => [
                'seconds' => 60 * 2 * -1,
                'expectedLabel' => '-2 min',
            ],
            'Zero seconds' => [
                'seconds' => 0,
                'expectedLabel' => '0 min',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider calcAgeDataProvider
     */
    public function calcAgeReturnsExpectedValues(int $seconds, string $expectedLabel): void
    {
        self::assertSame($expectedLabel, BackendUtility::calcAge($seconds));
    }

    ///////////////////////////////////////
    // Tests concerning getProcessedValue
    ///////////////////////////////////////
    /**
     * @test
     * @see https://forge.typo3.org/issues/20994
     */
    public function getProcessedValueForZeroStringIsZero(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);

        self::assertEquals('0', BackendUtility::getProcessedValue('tt_content', 'header', '0'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroup(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'multimedia' => [
                        'config' => [
                            'type' => 'group',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->expects(self::once())->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('testLabel', BackendUtility::getProcessedValue('tt_content', 'multimedia', '1,2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForFlexNull(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pi_flexform' => [
                        'config' => [
                            'type' => 'flex',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('', BackendUtility::getProcessedValue('tt_content', 'pi_flexform', null));
    }

    /**
     * @test
     */
    public function getProcessedValueForDatetimeDbTypeDateNull(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'datetime',
                            'dbType' => 'date',
                            'format' => 'date',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('', BackendUtility::getProcessedValue('tt_content', 'header', null));
    }

    /**
     * @test
     */
    public function getProcessedValueForDatetimeDbTypeDatetime(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'datetime',
                            'dbType' => 'datetime',
                        ],
                    ],
                ],
            ],
        ];
        $value = '2022-09-23 00:03:00';
        $expected = BackendUtility::datetime((int)strtotime($value));
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame($expected, BackendUtility::getProcessedValue('tt_content', 'header', $value));
    }

    /**
     * @test
     */
    public function getProcessedValueForDatetimeDbTypeDatetimeNull(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'datetime',
                            'dbType' => 'datetime',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('', BackendUtility::getProcessedValue('tt_content', 'header', null));
    }

    /**
     * @test
     */
    public function getProcessedValueForDatetimeDbTypeDate(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'date',
                            'dbType' => 'date',
                            'disableAgeDisplay' => true,
                        ],
                    ],
                ],
            ],
        ];
        $value = '2022-09-23';
        $expected = BackendUtility::date((int)strtotime($value));
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame($expected, BackendUtility::getProcessedValue('tt_content', 'header', $value));
    }

    /**
     * @test
     */
    public function getProcessedValueForFlex(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pi_flexform' => [
                        'config' => [
                            'type' => 'flex',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceMock;
        $expectation = "\n"
            . "\n    "
            . "\n        "
            . "\n            "
            . "\n                "
            . "\n                    bar"
            . "\n                "
            . "\n            "
            . "\n        "
            . "\n    "
            . "\n";

        self::assertSame($expectation, BackendUtility::getProcessedValue('tt_content', 'pi_flexform', '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="foo">
                    <value index="vDEF">bar</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroupWithOneAllowedTable(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pages' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => 'pages',
                            'maxitems' => 22,
                            'size' => 3,
                        ],
                    ],
                ],
            ],
            'pages' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->expects(self::any())->method('sL')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceMock;

        $relationHandlerMock = $this->getMockBuilder(RelationHandler::class)->getMock();
        $relationHandlerMock->expects(self::once())->method('start')->with('1,2');
        $relationHandlerMock->expects(self::once())->method('getFromDB')->willReturn([]);
        $relationHandlerMock->expects(self::once())->method('getResolvedItemArray')->willReturn([
            [
                'table' => 'pages',
                'uid' => 1,
                'record' => [
                    'uid' => 1,
                    'pid' => 0,
                    'title' => 'Page 1',
                ],
            ],
            [
                'table' => 'pages',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Page 2',
                ],
            ],
        ]);
        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerMock);

        self::assertSame('Page 1, Page 2', BackendUtility::getProcessedValue('tt_content', 'pages', '1,2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroupWithMultipleAllowedTables(): void
    {
        $GLOBALS['TCA'] = [
            'index_config' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                    'indexcfgs' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => 'index_config,pages',
                            'size' => 5,
                        ],
                    ],
                ],
            ],
            'pages' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceMock;

        $relationHandlerMock = $this->getMockBuilder(RelationHandler::class)->getMock();
        $relationHandlerMock->expects(self::once())->method('start')->with('pages_1,index_config_2');
        $relationHandlerMock->expects(self::once())->method('getFromDB')->willReturn([]);
        $relationHandlerMock->expects(self::once())->method('getResolvedItemArray')->willReturn([
            [
                'table' => 'pages',
                'uid' => 1,
                'record' => [
                    'uid' => 1,
                    'pid' => 0,
                    'title' => 'Page 1',
                ],
            ],
            [
                'table' => 'index_config',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Configuration 2',
                ],
            ],
        ]);
        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerMock);
        self::assertSame('Page 1, Configuration 2', BackendUtility::getProcessedValue('index_config', 'indexcfgs', 'pages_1,index_config_2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForSelectWithMMRelation(): void
    {
        $relationHandlerMock = $this->getMockBuilder(RelationHandler::class)->getMock();
        $relationHandlerMock->expects(self::once())->method('start')->with('2');
        $relationHandlerMock->expects(self::once())->method('getFromDB')->willReturn([]);
        $relationHandlerMock->expects(self::once())->method('getResolvedItemArray')->willReturn([
            [
                'table' => 'sys_category',
                'uid' => 1,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Category 1',
                ],
            ],
            [
                'table' => 'sys_category',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Category 2',
                ],
            ],
        ]);

        $relationHandlerInstance = $relationHandlerMock;
        $relationHandlerInstance->tableArray['sys_category'] = [1, 2];

        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerInstance);

        $GLOBALS['TCA'] = [
            'pages' => [
                'columns' => [
                    'categories' => [
                        'config' => [
                            'type' => 'select',
                            'foreign_table' => 'sys_category',
                            'MM' => 'sys_category_record_mm',
                            'MM_match_fields' => [
                                'fieldname' => 'categories',
                                'tablesnames' => 'pages',
                            ],
                            'MM_opposite_field' => 'items',
                        ],
                    ],
                ],
            ],
            'sys_category' => [
                'ctrl' => ['label' => 'title'],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                    'items' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => '*',
                            'MM' => 'sys_category_record_mm',
                            'MM_oppositeUsage' => [],
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->expects(self::any())->method('sL')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceMock;

        self::assertSame(
            'Category 1, Category 2',
            BackendUtility::getProcessedValue(
                'pages',
                'categories',
                '2',
                0,
                false,
                false,
                1
            )
        );
    }

    /**
     * @test
     */
    public function getProcessedValueDisplaysAgeForDateInputFieldsIfSettingAbsent(): void
    {
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn(' min| hrs| days| yrs| min| hour| day| year');
        $GLOBALS['LANG'] = $languageServiceMock;

        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 8, 30, 2015);

        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'date' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'date',
                        ],
                    ],
                ],
            ],
        ];
        self::assertSame('28-08-15 (-2 days)', BackendUtility::getProcessedValue('tt_content', 'date', mktime(0, 0, 0, 8, 28, 2015)));
    }

    public static function inputTypeDateDisplayOptions(): array
    {
        return [
            'typeSafe Setting' => [
                true,
                '28-08-15',
            ],
            'non typesafe setting' => [
                1,
                '28-08-15',
            ],
            'setting disabled typesafe' => [
                false,
                '28-08-15 (-2 days)',
            ],
            'setting disabled not typesafe' => [
                0,
                '28-08-15 (-2 days)',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider inputTypeDateDisplayOptions
     *
     * @param bool|int $input
     */
    public function getProcessedValueHandlesAgeDisplayCorrectly($input, string $expected): void
    {
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn(' min| hrs| days| yrs| min| hour| day| year');

        $GLOBALS['LANG'] = $languageServiceMock;

        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 8, 30, 2015);

        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'date' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'date',
                            'disableAgeDisplay' => $input,
                        ],
                    ],
                ],
            ],
        ];
        self::assertSame($expected, BackendUtility::getProcessedValue('tt_content', 'date', mktime(0, 0, 0, 8, 28, 2015)));
    }

    /**
     * @test
     */
    public function getProcessedValueForCheckWithSingleItem(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'hide' => [
                        'config' => [
                            'type' => 'check',
                            'items' => [
                                [
                                    0 => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturnMap(
            [
                ['LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:yes', 'Yes'],
                ['LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:no', 'No'],
            ]
        );
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('Yes', BackendUtility::getProcessedValue('tt_content', 'hide', 1));
    }

    /**
     * @test
     */
    public function getProcessedValueForCheckWithSingleItemInvertStateDisplay(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'hide' => [
                        'config' => [
                            'type' => 'check',
                            'items' => [
                                [
                                    0 => '',
                                    'invertStateDisplay' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturnMap(
            [
                ['LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:yes', 'Yes'],
                ['LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:no', 'No'],
            ]
        );
        $GLOBALS['LANG'] = $languageServiceMock;
        self::assertSame('No', BackendUtility::getProcessedValue('tt_content', 'hide', 1));
    }

    public static function getCommonSelectFieldsReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'minimum fields' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [],
                'expectedFields' => 'uid,pid',
            ],
            'label set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label' => 'label',
                    ],
                ],
                'expectedFields' => 'uid,pid,label',
            ],
            'label_alt set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label_alt' => 'label,label2',
                    ],
                ],
                'expectedFields' => 'uid,pid,label,label2',
            ],
            'versioningWS set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'versioningWS' => true,
                    ],
                ],
                'expectedFields' => 'uid,pid,t3ver_state,t3ver_wsid',
            ],
            'selicon_field set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'selicon_field' => 'field',
                    ],
                ],
                'expectedFields' => 'uid,pid,field',
            ],
            'typeicon_column set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'typeicon_column' => 'field',
                    ],
                ],
                'expectedFields' => 'uid,pid,field',
            ],
            'enablecolumns set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'enablecolumns' => [
                            'disabled' => 'hidden',
                            'starttime' => 'start',
                            'endtime' => 'stop',
                            'fe_group' => 'groups',
                        ],
                    ],
                ],
                'expectedFields' => 'uid,pid,hidden,start,stop,groups',
            ],
            'label set to uid' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label' => 'uid',
                    ],
                ],
                'expectedFields' => 'uid,pid',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getCommonSelectFieldsReturnsCorrectFieldsDataProvider
     */
    public function getCommonSelectFieldsReturnsCorrectFields(
        string $table,
        string $prefix,
        array $presetFields,
        array $tca,
        string $expectedFields = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;
        $selectFields = BackendUtility::getCommonSelectFields($table, $prefix, $presetFields);
        self::assertEquals($selectFields, $expectedFields);
    }

    public static function getLabelFromItemlistReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'item set' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'Item 1', 'value' => '0'],
                                    ['label' => 'Item 2', 'value' => '1'],
                                    ['label' => 'Item 3', 'value' => '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2',
            ],
            'item set twice' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'Item 1', 'value' => '0'],
                                    ['label' => 'Item 2a', 'value' => '1'],
                                    ['label' => 'Item 2b', 'value' => '1'],
                                    ['label' => 'Item 3', 'value' => '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2a',
            ],
            'item not found' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '5',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'Item 1', 'value' => '0'],
                                    ['label' => 'Item 2', 'value' => '1'],
                                    ['label' => 'Item 3', 'value' => '2'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => null,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelFromItemlistReturnsCorrectFieldsDataProvider
     */
    public function getLabelFromItemlistReturnsCorrectFields(
        string $table,
        string $col,
        string $key,
        array $tca,
        ?string $expectedLabel = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getLabelFromItemlist($table, $col, $key);
        self::assertEquals($label, $expectedLabel);
    }

    public static function getLabelFromItemListMergedReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'no field found' => [
                'pageId' => 123,
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '10',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'Item 1', 'value' => '0'],
                                    ['label' => 'Item 2', 'value' => '1'],
                                    ['label' => 'Item 3', 'value' => '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => '',
            ],
            'no tsconfig set' => [
                'pageId' => 123,
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'Item 1', 'value' => '0'],
                                    ['label' => 'Item 2', 'value' => '1'],
                                    ['label' => 'Item 3', 'value' => '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelFromItemListMergedReturnsCorrectFieldsDataProvider
     */
    public function getLabelFromItemListMergedReturnsCorrectFields(
        int $pageId,
        string $table,
        string $column,
        string $key,
        array $tca,
        string $expectedLabel = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;

        self::assertEquals($expectedLabel, LabelFromItemListMergedReturnsCorrectFieldsFixture::getLabelFromItemListMerged($pageId, $table, $column, $key));
    }

    public static function getLabelsFromItemsListDataProvider(): array
    {
        return [
            'return value if found' => [
                'foobar', // table
                'someColumn', // col
                'foo, bar', // keyList
                [ // TCA
                    'columns' => [
                        'someColumn' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'aFooLabel', 'value' => 'foo'],
                                    ['label' => 'aBarLabel', 'value' => 'bar'],
                                ],
                            ],
                        ],
                    ],
                ],
                [], // page TSconfig
                'aFooLabel, aBarLabel', // expected
            ],
            'page TSconfig overrules TCA' => [
                'foobar', // table
                'someColumn', // col
                'foo,bar, add', // keyList
                [ // TCA
                    'columns' => [
                        'someColumn' => [
                            'config' => [
                                'items' => [
                                    ['label' => 'aFooLabel', 'value' => 'foo'],
                                    ['label' => 'aBarLabel', 'value' => 'bar'],
                                ],
                            ],
                        ],
                    ],
                ],
                [ // page TSconfig
                    'addItems.' => ['add' => 'aNewLabel'],
                    'altLabels.' => ['bar' => 'aBarDiffLabel'],
                ],
                'aFooLabel, aBarDiffLabel, aNewLabel', // expected
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelsFromItemsListDataProvider
     */
    public function getLabelsFromItemsListReturnsCorrectValue(
        string $table,
        string $col,
        string $keyList,
        array $tca,
        array $pageTsConfig,
        string $expectedLabel
    ): void {
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getLabelsFromItemsList($table, $col, $keyList, $pageTsConfig);
        self::assertEquals($expectedLabel, $label);
    }

    /**
     * @test
     */
    public function getProcessedValueReturnsLabelsForExistingValuesSolely(): void
    {
        $table = 'foobar';
        $col = 'someColumn';
        $tca = [
            'columns' => [
                'someColumn' => [
                    'config' => [
                        'type' => 'select',
                        'items' => [
                            ['label' => 'aFooLabel', 'value' => 'foo'],
                            ['label' => 'aBarLabel', 'value' => 'bar'],
                        ],
                    ],
                ],
            ],
        ];
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getProcessedValue($table, $col, 'foo,invalidKey,bar');
        self::assertEquals('aFooLabel, aBarLabel', $label);
    }

    /**
     * @test
     */
    public function getProcessedValueReturnsPlainValueIfItemIsNotFound(): void
    {
        $table = 'foobar';
        $col = 'someColumn';
        $tca = [
            'columns' => [
                'someColumn' => [
                    'config' => [
                        'type' => 'select',
                        'items' => [
                            '0' => ['label' => 'aFooLabel', 'value' => 'foo'],
                        ],
                    ],
                ],
            ],
        ];
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getProcessedValue($table, $col, 'invalidKey');
        self::assertEquals('invalidKey', $label);
    }

    /**
     * @test
     */
    public function dateTimeAgeReturnsCorrectValues(): void
    {
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn(' min| hrs| days| yrs| min| hour| day| year');
        $GLOBALS['LANG'] = $languageServiceMock;
        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 3, 23, 2016);

        self::assertSame('24-03-16 00:00 (-1 day)', BackendUtility::dateTimeAge($GLOBALS['EXEC_TIME'] + 86400));
        self::assertSame('24-03-16 (-1 day)', BackendUtility::dateTimeAge($GLOBALS['EXEC_TIME'] + 86400, 1, 'date'));
    }

    /**
     * @test
     */
    public function purgeComputedPropertyNamesRemovesPropertiesStartingWithUnderscore(): void
    {
        $propertyNames = [
            'uid',
            'pid',
            '_ORIG_PID',
        ];
        $computedPropertyNames = BackendUtility::purgeComputedPropertyNames($propertyNames);
        self::assertSame(['uid', 'pid'], $computedPropertyNames);
    }

    /**
     * @test
     */
    public function purgeComputedPropertiesFromRecordRemovesPropertiesStartingWithUnderscore(): void
    {
        $record = [
            'uid'       => 1,
            'pid'       => 2,
            '_ORIG_PID' => 1,
        ];
        $expected = [
            'uid' => 1,
            'pid' => 2,
        ];
        $computedProperties = BackendUtility::purgeComputedPropertiesFromRecord($record);
        self::assertSame($expected, $computedProperties);
    }

    public static function splitTableUidDataProvider(): array
    {
        return [
            'simple' => [
                'pages_23',
                ['pages', '23'],
            ],
            'complex' => [
                'tt_content_13',
                ['tt_content', '13'],
            ],
            'multiple underscores' => [
                'tx_runaway_domain_model_crime_scene_1234',
                ['tx_runaway_domain_model_crime_scene', '1234'],
            ],
            'no underscore' => [
                'foo',
                ['', 'foo'],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider splitTableUidDataProvider
     */
    public function splitTableUid($input, $expected): void
    {
        $result = BackendUtility::splitTable_Uid($input);
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function returnNullForMissingTcaConfigInResolveFileReferences(): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = [];
        self::assertNull(BackendUtility::resolveFileReferences($tableName, $fieldName, []));
    }

    /**
     * @test
     * @dataProvider unfitResolveFileReferencesTableConfig
     */
    public function returnNullForUnfitTableConfigInResolveFileReferences(array $config): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = $config;
        self::assertNull(BackendUtility::resolveFileReferences($tableName, $fieldName, []));
    }

    public static function unfitResolveFileReferencesTableConfig(): array
    {
        return [
            'invalid table' => [
                [
                    'type' => 'inline',
                    'foreign_table' => 'table_b',
                ],
            ],
            'empty table' => [
                [
                    'type' => 'inline',
                    'foreign_table' => '',
                ],
            ],
            'invalid type' => [
                [
                    'type' => 'select',
                    'foreign_table' => 'sys_file_reference',
                ],
            ],
            'empty type' => [
                [
                    'type' => '',
                    'foreign_table' => 'sys_file_reference',
                ],
            ],
            'empty' => [
                [
                    'type' => '',
                    'foreign_table' => '',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function workspaceOLDoesNotChangeValuesForNoBeUserAvailable(): void
    {
        $GLOBALS['BE_USER'] = null;
        $tableName = 'table_a';
        $row = [
            'uid' => 1,
            'pid' => 17,
        ];
        $reference = $row;
        BackendUtility::workspaceOL($tableName, $row);
        self::assertSame($reference, $row);
    }

    /**
     * @test
     */
    public function resolveFileReferencesReturnsEmptyResultForNoReferencesAvailable(): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $relationHandlerMock = $this->createMock(RelationHandler::class);
        $relationHandlerMock->expects(self::once())->method('start')->with(
            'foo',
            'sys_file_reference',
            '',
            42,
            $tableName,
            ['type' => 'file', 'foreign_table' => 'sys_file_reference']
        );
        $relationHandlerMock->expects(self::once())->method('processDeletePlaceholder');
        $relationHandlerMock->tableArray = ['sys_file_reference' => []];
        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerMock);
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = [
            'type' => 'file',
            'foreign_table' => 'sys_file_reference',
        ];
        $elementData = [
            $fieldName => 'foo',
            'uid' => 42,
        ];

        self::assertEmpty(BackendUtility::resolveFileReferences($tableName, $fieldName, $elementData));
    }

    /**
     * @test
     */
    public function wsMapIdReturnsLiveIdIfNoBeUserIsAvailable(): void
    {
        $GLOBALS['BE_USER'] = null;
        $tableName = 'table_a';
        $uid = 42;
        self::assertSame(42, BackendUtility::wsMapId($tableName, $uid));
    }

    /**
     * @test
     */
    public function getAllowedFieldsForTableReturnsEmptyArrayOnBrokenTca(): void
    {
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();
        self::assertEmpty(BackendUtility::getAllowedFieldsForTable('myTable', false));
    }

    /**
     * @test
     */
    public function getAllowedFieldsForTableReturnsUniqueList(): void
    {
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();
        $GLOBALS['TCA']['myTable'] = [
            'ctrl' => [
                'tstamp' => 'updatedon',
                // Won't be added due to defined in "columns"
                'crdate' => 'createdon',
                'sortby' => 'sorting',
                'versioningWS' => true,
            ],
            'columns' => [
                // Regular field
                'title' => [
                    'config' => [
                        'type' => 'input',
                    ],
                ],
                // Overwrite automatically set management field from "ctrl"
                'createdon' => [
                    'config' => [
                        'type' => 'input',
                    ],
                ],
                // Won't be added due to type "none"
                'reference' => [
                    'config' => [
                        'type' => 'none',
                    ],
                ],
            ],
        ];

        self::assertEquals(
            ['title', 'createdon', 'uid', 'pid', 'updatedon', 'sorting', 't3ver_state', 't3ver_wsid', 't3ver_oid'],
            BackendUtility::getAllowedFieldsForTable('myTable', false)
        );
    }
}
