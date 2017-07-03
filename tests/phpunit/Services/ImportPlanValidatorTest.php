<?php

namespace FileImporter\Services\Test;

use Exception;
use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\TitleConflictException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\ImportPlanValidator;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Title;

class ImportPlanValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @param int $callCount
	 * @param null|bool $allowed
	 *
	 * @return PHPUnit_Framework_MockObject_MockObject|ImportTitleChecker
	 */
	private function getMockImportTitleChecker( $callCount = 0, $allowed = null ) {
		$mock = $this->getMock( ImportTitleChecker::class, [], [], '', false );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'importAllowed' )
			->willReturn( $allowed );
		return $mock;
	}

	/**
	 * @param int $callCount
	 * @param int $arrayElements
	 *
	 * @return PHPUnit_Framework_MockObject_MockObject|DuplicateFileRevisionChecker
	 */
	private function getMockDuplicateFileRevisionChecker( $callCount = 0, $arrayElements = 0 ) {
		$mock = $this->getMock( DuplicateFileRevisionChecker::class, [], [], '', false );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'findDuplicates' )
			->willReturn( array_fill( 0, $arrayElements, 'value' ) );
		return $mock;
	}

	/**
	 * @param string $text
	 * @param bool $exists
	 *
	 * @return PHPUnit_Framework_MockObject_MockObject|Title
	 */
	private function getMockTitle( $text, $exists ) {
		$mock = $this->getMock( Title::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getText' )
			->willReturn( $text );
		$mock->expects( $this->any() )
			->method( 'exists' )
			->willReturn( $exists );
		return $mock;
	}

	private function getMockFileRevisions() {
		$mock = $this->getMock( FileRevisions::class, [], [], '', false );
		$mockFileRevision = $this->getMock( FileRevision::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		return $mock;
	}

	private function getMockImportDetails( Title $sourceTitle = null ) {
		$mock = $this->getMock( ImportDetails::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getSourceLinkTarget' )
			->willReturn( $sourceTitle );
		$mock->expects( $this->any() )
			->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		return $mock;
	}

	private function getMockImportRequest() {
		$mock = $this->getMock( ImportRequest::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getUrl' )
			->willReturn( new SourceUrl( 'http://test.test' ) );
		return $mock;
	}

	private function getMockImportPlan( Title $planTitle = null, Title $sourceTitle = null ) {
		$mock = $this->getMock( ImportPlan::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( $planTitle );
		$mock->expects( $this->any() )
			->method( 'getDetails' )
			->willReturn(
				$this->getMockImportDetails( $sourceTitle )
			);
		$mock->expects( $this->any() )
			->method( 'getRequest' )
			->willReturn(
				$this->getMockImportRequest()
			);
		return $mock;
	}

	public function provideValidate() {
		$emptyPlan = $this->getMockImportPlan();
		return [
			'Valid Plan' => [
				null,
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, true )
			],
			'Invalid, duplicate file found' => [
				new DuplicateFilesException( [ 'someFile' ] ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 1 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, title exists on target site' => [
				new TitleConflictException( $emptyPlan, 'Title conflict detected' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', true ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, title exists on source site' => [
				new TitleConflictException( $emptyPlan, 'Title conflict detected' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, false )
			],
			'Invalid, file extension has changed' => [
				new TitleException( 'Target file extension does not match original file' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.jpg', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, true )
			],
			'Invalid, Sub pages should not be allowed (No Extension on plan)' => [
				new TitleException( 'Planned file name does not have an extension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG/Foo', false ),
					$this->getMockTitle( 'SourceName.jpg', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, Sub pages should not be allowed (No Extension on source)' => [
				new TitleException( 'Source file name does not have an extension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.jpg/Foo', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
		];
	}

	/**
	 * @dataProvider provideValidate
	 * @param Exception|null $expected
	 * @param ImportPlan $plan
	 * @param DuplicateFileRevisionChecker $duplicateChecker
	 * @param ImportTitleChecker $titleChecker
	 */
	public function testValidate( $expected, $plan, $duplicateChecker, $titleChecker ) {
		if ( $expected !== null ) {
			$this->setExpectedException( get_class( $expected ), $expected->getMessage() );
		}

		$validator = new ImportPlanValidator( $duplicateChecker, $titleChecker );
		$validator->validate( $plan );

		if ( $expected === null ) {
			$this->assertTrue( true );
		}
	}

}
