<?php

namespace FileImporter\Services\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\ImportPlanValidator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MalformedTitleException;
use PHPUnit_Framework_MockObject_MockObject;
use Title;
use UploadBase;

/**
 * @covers \FileImporter\Services\ImportPlanValidator
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanValidatorTest extends \PHPUnit\Framework\TestCase {

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
		if ( $arrayElements === 0 ) {
			// PHP 5.5 and below can't handle array_fill with the number of elements as 0
			$returnValue = [];
		} else {
			$returnValue = array_fill( 0, $arrayElements, 'value' );
		}
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'findDuplicates' )
			->willReturn( $returnValue );
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
		$mock->method( 'getText' )
			->willReturn( $text );
		$mock->method( 'exists' )
			->willReturn( $exists );
		return $mock;
	}

	private function getMockFileRevisions() {
		$mock = $this->getMock( FileRevisions::class, [], [], '', false );
		$mockFileRevision = $this->getMock( FileRevision::class, [], [], '', false );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		return $mock;
	}

	private function getMockImportDetails( Title $sourceTitle = null ) {
		$mock = $this->getMock( ImportDetails::class, [], [], '', false );
		$mock->method( 'getSourceLinkTarget' )
			->willReturn( $sourceTitle );
		$mock->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		return $mock;
	}

	private function getMockImportRequest() {
		$mock = $this->getMock( ImportRequest::class, [], [], '', false );
		$mock->method( 'getUrl' )
			->willReturn( new SourceUrl( 'http://test.test' ) );
		return $mock;
	}

	private function getMockImportPlan(
		Title $planTitle = null,
		Title $sourceTitle = null,
		$getTitleFails = false
	) {
		$mock = $this->getMock( ImportPlan::class, [], [], '', false );
		if ( !$getTitleFails ) {
			$mock->method( 'getTitle' )
				->willReturn( $planTitle );
		} else {
			$mock->method( 'getTitle' )
				->willThrowException( new MalformedTitleException( 'mockexception' ) );
		}

		$mock->method( 'getDetails' )
			->willReturn(
				$this->getMockImportDetails( $sourceTitle )
			);
		$mock->method( 'getRequest' )
			->willReturn(
				$this->getMockImportRequest()
			);
		return $mock;
	}

	private function getMockUploadBaseFactory( UploadBase $uploadBase ) {
		$mock = $this->getMock( UploadBaseFactory::class, [], [], '', false );
		$mock->method( 'newValidatingUploadBase' )
			->willReturn( $uploadBase );
		return $mock;
	}

	private function getMockValidatingUploadBase( $validTitle = true, $validFile = true ) {
		$mock = $this->getMock( ValidatingUploadBase::class, [], [], '', false );
		$mock->method( 'validateTitle' )
			->willReturn( $validTitle );
		$mock->method( 'validateFile' )
			->willReturn( $validFile );
		return $mock;
	}

	public function provideValidate() {
		$emptyPlan = $this->getMockImportPlan();

		$allowedFileTitles = [
			'Regular name' => 'SourceName.JPG',
			'Multiple extensions are allowed' => 'SourceName.JPG.JPG',
			'Change of extension case is allowed' => 'SourceName.jpg',
		];
		$validTests = [];
		foreach ( $allowedFileTitles as $description => $titleString ) {
			$validTests["Valid Plan - '$titleString' ($description)"] = [
				null,
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( $titleString, false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, true )
			];
		}

		$invalidTests = [
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
				new RecoverableTitleException(
					'fileimporter-localtitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', true ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, title exists on source site' => [
				new RecoverableTitleException(
					'fileimporter-sourcetitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, false )
			],
			'Invalid, file extension has changed' => [
				new TitleException( 'fileimporter-filenameerror-missmatchextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.PNG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 1, true )
			],
			'Invalid, No Extension on planned name' => [
				new TitleException( 'fileimporter-filenameerror-noplannedextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG/Foo', false ),
					$this->getMockTitle( 'SourceName.jpg', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, No Extension on source name' => [
				new TitleException( 'fileimporter-filenameerror-nosourceextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.jpg/Foo', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, Bad title (includes another namespace)' => [
				new RecoverableTitleException(
					'fileimporter-illegalfilenamechars',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'Talk:FinalName.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 0, true )
			],
			'Invalid, Bad filename (too long)' => [
				new RecoverableTitleException(
					'fileimporter-filenameerror-toolong',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( str_repeat( 'a', 242 ) . '.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 0 ),
				$this->getMockImportTitleChecker( 0, true ),
				$this->getMockValidatingUploadBase( UploadBase::FILENAME_TOO_LONG, true )
			],
			'Invalid, Bad characters "<", getTitle throws MalformedTitleException' => [
				new RecoverableTitleException(
					'mockexception',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'Name<Name.JPG', false ),
					$this->getMockTitle( 'SourceName.JPG', false ),
					true
				),
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true ),
				$this->getMockValidatingUploadBase( true, true )
			],
		];

		return $validTests + $invalidTests;
	}

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate(
		ImportException $expected = null,
		ImportPlan $plan,
		DuplicateFileRevisionChecker $duplicateChecker,
		ImportTitleChecker $titleChecker,
		ValidatingUploadBase $validatingUploadBase = null
	) {
		if ( $validatingUploadBase === null ) {
			$validatingUploadBase = $this->getMockValidatingUploadBase();
		}

		if ( $expected !== null ) {
			$this->setExpectedException( get_class( $expected ), $expected->getMessage() );
		}

		$validator = new ImportPlanValidator(
			$duplicateChecker,
			$titleChecker,
			$this->getMockUploadBaseFactory( $validatingUploadBase )
		);
		$validator->validate( $plan );

		if ( $expected === null ) {
			$this->addToAssertionCount( 1 );
		}
	}

	public function testValidateFailsWhenCoreChangesTheName() {
		$mockRequest = $this->getMockImportRequest();
		$mockRequest->expects( $this->atLeastOnce() )
			->method( 'getIntendedName' )
			->willReturn( 'Before#After' );
		$mockDetails = $this->getMockImportDetails( Title::newFromText( 'SourceTitle', NS_FILE ) );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails );

		$this->setExpectedException( RecoverableTitleException::class, '"Before"' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
			$this->getMockImportTitleChecker( 0, true ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() )
		);
		$validator->validate( $importPlan );
	}

}
