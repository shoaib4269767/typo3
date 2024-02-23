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

namespace TYPO3\CMS\Core\Tests\Functional\DataScenarios\ManyToMany\WorkspacesDiscard;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Tests\Functional\DataScenarios\ManyToMany\AbstractActionWorkspacesTestCase;

final class ActionTest extends AbstractActionWorkspacesTestCase
{
    #[Test]
    public function verifyCleanReferenceIndex(): void
    {
        // The test verifies the imported data set has a clean reference index by the check in tearDown()
        self::assertTrue(true);
    }

    #[Test]
    public function addCategoryRelation(): void
    {
        parent::addCategoryRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/addCategoryRelation.csv');
    }

    #[Test]
    public function deleteCategoryRelation(): void
    {
        parent::deleteCategoryRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/deleteCategoryRelation.csv');
    }

    #[Test]
    public function changeCategoryRelationSorting(): void
    {
        parent::changeCategoryRelationSorting();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/changeCategoryRelationSorting.csv');
    }

    #[Test]
    public function createContentAndAddRelation(): void
    {
        parent::createContentAndAddRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['newContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createContentNAddRelation.csv');
    }

    #[Test]
    public function createCategoryAndAddRelation(): void
    {
        parent::createCategoryAndAddRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Category, $this->recordIds['newCategoryId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createCategoryNAddRelation.csv');
    }

    #[Test]
    public function createContentAndCreateRelation(): void
    {
        parent::createContentAndCreateRelation();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Category => [$this->recordIds['newCategoryId']],
            self::TABLE_Content => [$this->recordIds['newContentId']],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createContentNCreateRelation.csv');
    }

    #[Test]
    public function createCategoryAndCreateRelation(): void
    {
        parent::createCategoryAndCreateRelation();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Content => [$this->recordIds['newContentId']],
            self::TABLE_Category => [$this->recordIds['newCategoryId']],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createCategoryNCreateRelation.csv');
    }

    #[Test]
    public function createContentWithCategoryAndAddRelation(): void
    {
        parent::createContentWithCategoryAndAddRelation();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Category => [$this->recordIds['newCategoryId']],
            self::TABLE_Content => [$this->recordIds['newContentId']],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createContentWCategoryNAddRelation.csv');
    }

    #[Test]
    public function createCategoryWithContentAndAddRelation(): void
    {
        parent::createCategoryWithContentAndAddRelation();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Content => [$this->recordIds['newContentId']],
            self::TABLE_Category => [$this->recordIds['newCategoryId']],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/createCategoryWContentNAddRelation.csv');
    }

    #[Test]
    public function modifyCategoryOfRelation(): void
    {
        parent::modifyCategoryOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Category, self::VALUE_CategoryIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/modifyCategoryOfRelation.csv');
    }

    #[Test]
    public function modifyContentOfRelation(): void
    {
        parent::modifyContentOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/modifyContentOfRelation.csv');
    }

    #[Test]
    public function modifyBothsOfRelation(): void
    {
        parent::modifyBothsOfRelation();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Content => [self::VALUE_ContentIdFirst],
            self::TABLE_Category => [self::VALUE_CategoryIdFirst],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/modifyBothsOfRelation.csv');
    }

    #[Test]
    public function deleteContentOfRelation(): void
    {
        parent::deleteContentOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdLast);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/deleteContentOfRelation.csv');
    }

    #[Test]
    public function deleteCategoryOfRelation(): void
    {
        parent::deleteCategoryOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Category, self::VALUE_CategoryIdFirst);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/deleteCategoryOfRelation.csv');
    }

    #[Test]
    public function copyContentOfRelation(): void
    {
        parent::copyContentOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['newContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/copyContentOfRelation.csv');
    }

    #[Test]
    public function copyContentToLanguageOfRelation(): void
    {
        parent::copyContentToLanguageOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['newContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/copyContentToLanguageOfRelation.csv');
    }

    #[Test]
    public function copyCategoryOfRelation(): void
    {
        parent::copyCategoryOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Category, $this->recordIds['newCategoryId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/copyCategoryOfRelation.csv');
    }

    /**
     * @todo: this is a faulty test, because the category should be discarded
     */
    #[Test]
    public function copyCategoryToLanguageOfRelation(): void
    {
        parent::copyCategoryToLanguageOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['newCategoryId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/copyCategoryToLanguageOfRelation.csv');
    }

    #[Test]
    public function localizeContentOfRelation(): void
    {
        parent::localizeContentOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeContentOfRelation.csv');
    }

    #[Test]
    public function localizeContentOfRelationWithLanguageSynchronization(): void
    {
        parent::localizeContentOfRelationWithLanguageSynchronization();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeContentOfRelationWSynchronization.csv');
    }

    #[Test]
    public function localizeContentOfRelationWithLanguageExclude(): void
    {
        parent::localizeContentOfRelationWithLanguageExclude();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeContentOfRelationWExclude.csv');
    }

    #[Test]
    public function localizeContentOfRelationAndAddCategoryWithLanguageSynchronization(): void
    {
        parent::localizeContentOfRelationAndAddCategoryWithLanguageSynchronization();
        // @todo: even if we discard this record, it is still showing up in the result
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
        // @todo: do we need to discard the references manually?
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeContentOfRelationNAddCategoryWSynchronization.csv');
    }

    #[Test]
    public function localizeContentChainOfRelationAndAddCategoryWithLanguageSynchronization(): void
    {
        parent::localizeContentChainOfRelationAndAddCategoryWithLanguageSynchronization();
        // @todo: even if we discard this record, it is still showing up in the result
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentId']);
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, $this->recordIds['localizedContentIdSecond']);
        // @todo: do we need to discard the references manually?
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeContentChainOfRelationNAddCategoryWSynchronization.csv');
    }

    #[Test]
    public function localizeCategoryOfRelation(): void
    {
        // Create translated page first
        $this->actionService->copyRecordToLanguage(self::TABLE_Page, self::VALUE_PageId, self::VALUE_LanguageId);
        parent::localizeCategoryOfRelation();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Category, $this->recordIds['localizedCategoryId']);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/localizeCategoryOfRelation.csv');
    }

    #[Test]
    public function moveContentOfRelationToDifferentPage(): void
    {
        parent::moveContentOfRelationToDifferentPage();
        $this->actionService->clearWorkspaceRecord(self::TABLE_Content, self::VALUE_ContentIdLast);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/moveContentOfRelationToDifferentPage.csv');
    }

    #[Test]
    public function copyPage(): void
    {
        parent::copyPage();
        $this->actionService->clearWorkspaceRecords([
            self::TABLE_Page => [$this->recordIds['newPageId']],
        ]);
        $this->assertCSVDataSet(__DIR__ . '/DataSet/copyPage.csv');
    }
}
