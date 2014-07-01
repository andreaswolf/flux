<?php
namespace FluidTYPO3\Flux\Tests\Functional\Service;

/**
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

require_once 'typo3/sysext/core/Tests/Functional/DataHandling/AbstractDataHandlerActionTestCase.php';

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase;
use TYPO3\CMS\Core\Tests\FunctionalTestCase;


/**
 *
 *
 * @author Andreas Wolf <aw@foundata.net>
 */
class ContentServiceTest extends AbstractDataHandlerActionTestCase {

	/**
	 * @var array
	 */
	protected $testExtensionsToLoad = array('typo3conf/ext/flux');

	protected $scenarioDataSetDirectory = 'typo3conf/ext/flux/Tests/Functional/Service/DataSet/';

	const PAGE_ID_MAIN = 100;
	const PAGE_ID_TARGET = 101;
	const FLUIDCONTENT_CONTAINER_ID = 200;
	const FLUIDCONTENT_CONTENT_ID = 201;
	const CONTENT_ID = 202;

	public function setUp() {
		parent::setUp();

		$this->importScenarioDataSet('DefaultElements');

		$this->setUpFrontendRootPage(self::PAGE_ID_MAIN, array('typo3/sysext/core/Tests/Functional/Fixtures/Frontend/JsonRenderer.ts'));
	}

	/**
	 * @param string $table The table to paste the contents in
	 * @param int $targetPageId The target page id.
	 * @param int $relatedContentId The ID of the related element (= the element to paste after). If 0, element is inserted at the top of the column.
	 * @param int $parentUid The ID of the parent (Flux) element to insert to.
	 * @param string $targetArea The area in the parent element the new element should be inserted to.
	 * @param string|int $targetColumn The target column. Is only used if parent uid/target area are not set (?)
	 */
	protected function createGetEntryForClipboardPasteOperation($table, $targetPageId, $relatedContentId = 0, $parentUid = 0, $targetArea = '', $targetColumn = 0) {
		$_GET['CB'] = array(
			'paste' => $table . '|'
				. implode('-', array(
					$targetPageId,
					'paste',
					$relatedContentId,
					$parentUid,
					$targetArea,
					$targetColumn,
				))
		);
	}

	/**
	 * @param int $targetPageId
	 * @param int $targetContentId
	 */
	protected function pasteContentAfterOtherContentElement($targetPageId, $targetContentId) {
		$this->createGetEntryForClipboardPasteOperation('tt_content', $targetPageId, $targetContentId);
	}

	/**
	 * @param int $targetPageId
	 */
	protected function pasteContentAtBeginningOfPage($targetPageId) {
		$this->createGetEntryForClipboardPasteOperation('tt_content', $targetPageId, 0);
	}

	/**
	 * @param int $targetPageId
	 * @param int $targetContainerElementId
	 * @param string $targetColumnId
	 */
	protected function pasteContentToFluidcontentColumn($targetPageId, $targetContainerElementId, $targetColumnId) {
		$this->createGetEntryForClipboardPasteOperation('tt_content', $targetPageId, 0, $targetContainerElementId, $targetColumnId);
	}

	/**
	 * @test
	 */
	public function copyFromInsideFluidcontentElementToPageAfterFluidcontentElement() {
		$this->pasteContentAfterOtherContentElement(self::PAGE_ID_MAIN, self::FLUIDCONTENT_CONTAINER_ID);
		$mappingArray = $this->actionService->copyRecord('tt_content', self::FLUIDCONTENT_CONTENT_ID, self::PAGE_ID_MAIN);

		$newContentId = $mappingArray['tt_content'][self::FLUIDCONTENT_CONTENT_ID];
		$this->assertNotEmpty($newContentId);

		$newRecord = BackendUtility::getRecord('tt_content', $newContentId);

		// TODO assert correct sorting
		$this->assertEquals(0, $newRecord['colPos'], 'New content in wrong page column');
		$this->assertSame('0', $newRecord['tx_flux_parent'], 'New content still in Flux container');
		$this->assertSame('', $newRecord['tx_flux_column'], 'New content in wrong Flux column');
	}

	/**
	 * @test
	 */
	public function copyBetweenColumnsInFluidcontentElement() {
		$this->pasteContentToFluidcontentColumn(self::PAGE_ID_MAIN, self::FLUIDCONTENT_CONTAINER_ID, 'column2');
		$mappingArray = $this->actionService->copyRecord('tt_content', self::FLUIDCONTENT_CONTENT_ID, self::PAGE_ID_MAIN);

		$newContentId = $mappingArray['tt_content'][self::FLUIDCONTENT_CONTENT_ID];
		$this->assertGreaterThan(0, $newContentId);

		$newRecord = BackendUtility::getRecord('tt_content', $newContentId);

		$this->assertEquals(18181, $newRecord['colPos'], 'New content in wrong page column');
		$this->assertEquals(self::FLUIDCONTENT_CONTAINER_ID, $newRecord['tx_flux_parent'], 'New content not in Flux container');
		$this->assertSame('column2', $newRecord['tx_flux_column'], 'New content in wrong Flux column');
	}

	/**
	 * @test
	 */
	public function copyFromFluidcontentColumnToAfterSameElemeent() {
		$this->pasteContentAfterOtherContentElement(self::PAGE_ID_MAIN, self::FLUIDCONTENT_CONTENT_ID);
		$mappingArray = $this->actionService->copyRecord('tt_content', self::FLUIDCONTENT_CONTENT_ID, self::PAGE_ID_MAIN);

		$newContentId = $mappingArray['tt_content'][self::FLUIDCONTENT_CONTENT_ID];
		$this->assertGreaterThan(0, $newContentId);

		$newRecord = BackendUtility::getRecord('tt_content', $newContentId);

		$this->assertEquals(18181, $newRecord['colPos'], 'New content in wrong page column');
		$this->assertEquals(self::FLUIDCONTENT_CONTAINER_ID, $newRecord['tx_flux_parent'], 'New content not in Flux container');
		$this->assertSame('headline', $newRecord['tx_flux_column'], 'New content in wrong Flux column');
	}

	/**
	 * @test
	 */
	public function copyFromPageToColumnInFluidcontentElement() {
		$this->pasteContentToFluidcontentColumn(self::PAGE_ID_MAIN, self::FLUIDCONTENT_CONTAINER_ID, 'column1');
		$mappingArray = $this->actionService->copyRecord('tt_content', self::CONTENT_ID, self::PAGE_ID_MAIN);

		$newContentId = $mappingArray['tt_content'][self::CONTENT_ID];
		$this->assertGreaterThan(0, $newContentId);

		$newRecord = BackendUtility::getRecord('tt_content', $newContentId);

		$this->assertEquals(18181, $newRecord['colPos'], 'New content in wrong page column' . print_r($newRecord, TRUE));
		$this->assertEquals(self::FLUIDCONTENT_CONTAINER_ID, $newRecord['tx_flux_parent'], 'New content not in Flux container');
		$this->assertSame('column1', $newRecord['tx_flux_column'], 'New content in wrong Flux column');
	}

	/**
	 * @test
	 */
	public function copyFromPageToAfterElementInFluidcontentElement() {
		$this->pasteContentAfterOtherContentElement(self::PAGE_ID_MAIN, self::FLUIDCONTENT_CONTENT_ID);
		$mappingArray = $this->actionService->copyRecord('tt_content', self::CONTENT_ID, 0 - self::FLUIDCONTENT_CONTENT_ID);

		$newContentId = $mappingArray['tt_content'][self::CONTENT_ID];
		$this->assertNotEmpty($newContentId);

		$newRecord = BackendUtility::getRecord('tt_content', $newContentId);

		$this->assertEquals(18181, $newRecord['colPos'], 'New content in wrong page column');
		$this->assertEquals(self::FLUIDCONTENT_CONTAINER_ID, $newRecord['tx_flux_parent'], 'New content not in Flux container');
		$this->assertSame('headline', $newRecord['tx_flux_column'], 'New content in wrong Flux column');
	}

	/**
	 * @test
	 */
	public function copyFluidcontentElementToDifferentPage() {
		$this->pasteContentAtBeginningOfPage(self::PAGE_ID_TARGET);
		$mappingArray = $this->actionService->copyRecord('tt_content', self::FLUIDCONTENT_CONTAINER_ID, self::PAGE_ID_TARGET);

		$newContainerId = $mappingArray['tt_content'][self::FLUIDCONTENT_CONTAINER_ID];
		$newContentId = $mappingArray['tt_content'][self::FLUIDCONTENT_CONTENT_ID];
		$this->assertNotEmpty($newContainerId);
		$this->assertNotEmpty($newContentId);

		$newContainerRecord = BackendUtility::getRecord('tt_content', $newContainerId);
		$newContentRecord = BackendUtility::getRecord('tt_content', $newContentId);

		$this->assertEquals(0, $newContainerRecord['colPos'], 'Copied container in wrong page column');
		$this->assertEquals(0, $newContainerRecord['tx_flux_parent'], 'Copied container is in (another) Flux container');
		$this->assertSame('', $newContainerRecord['tx_flux_column'], 'Copied content in wrong Flux column');

		$this->assertEquals(18181, $newContentRecord['colPos'], 'Copied content in wrong page column');
		$this->assertEquals($newContainerRecord['uid'], $newContentRecord['tx_flux_parent'], 'Copied content not in new Flux container');
		$this->assertSame('headline', $newContentRecord['tx_flux_column'], 'Copied content in wrong Flux column');
	}

}
 