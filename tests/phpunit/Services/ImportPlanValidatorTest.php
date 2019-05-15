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
use User;

/**
 * @covers \FileImporter\Services\ImportPlanValidator
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanValidatorTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgHooks', [] );
	}

	/**
	 * @param int $callCount
	 * @param bool $allowed
	 *
	 * @return ImportTitleChecker
	 */
	private function getMockImportTitleChecker( $callCount, $allowed ) {
		$mock = $this->createMock( ImportTitleChecker::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'importAllowed' )
			->willReturn( $allowed );
		return $mock;
	}

	/**
	 * @param int $callCount
	 * @param int $arrayElements
	 *
	 * @return DuplicateFileRevisionChecker
	 */
	private function getMockDuplicateFileRevisionChecker( $callCount = 0, $arrayElements = 0 ) {
		$mock = $this->createMock( DuplicateFileRevisionChecker::class );
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
	 * @param array $userPermissionsErrors
	 *
	 * @return Title
	 */
	private function getMockTitle( $text, $exists, array $userPermissionsErrors = [] ) {
		$mock = $this->createMock( Title::class );
		$mock->method( 'getText' )
			->willReturn( $text );
		$mock->method( 'exists' )
			->willReturn( $exists );
		$mock->method( 'getUserPermissionsErrors' )
			->willReturn( $userPermissionsErrors );
		return $mock;
	}

	/**
	 * @return FileRevisions
	 */
	private function getMockFileRevisions() {
		$mock = $this->createMock( FileRevisions::class );
		$mockFileRevision = $this->createMock( FileRevision::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		return $mock;
	}

	/**
	 * @param Title|null $sourceTitle
	 *
	 * @return ImportDetails
	 */
	private function getMockImportDetails( Title $sourceTitle = null ) {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getSourceLinkTarget' )
			->willReturn( $sourceTitle );
		$mock->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		return $mock;
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|ImportRequest
	 */
	private function getMockImportRequest() {
		$mock = $this->createMock( ImportRequest::class );
		$mock->method( 'getUrl' )
			->willReturn( new SourceUrl( 'http://test.test' ) );
		return $mock;
	}

	/**
	 * @param Title|null $planTitle
	 * @param Title|null $sourceTitle
	 * @param bool $getTitleFails
	 *
	 * @return ImportPlan
	 */
	private function getMockImportPlan(
		Title $planTitle = null,
		Title $sourceTitle = null,
		$getTitleFails = false
	) {
		$mock = $this->createMock( ImportPlan::class );
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

	/**
	 * @param UploadBase $uploadBase
	 *
	 * @return UploadBaseFactory
	 */
	private function getMockUploadBaseFactory( UploadBase $uploadBase ) {
		$mock = $this->createMock( UploadBaseFactory::class );
		$mock->method( 'newValidatingUploadBase' )
			->willReturn( $uploadBase );
		return $mock;
	}

	/**
	 * @param bool $validTitle
	 * @param bool $validFile
	 *
	 * @return ValidatingUploadBase
	 */
	private function getMockValidatingUploadBase( $validTitle = true, $validFile = true ) {
		$mock = $this->createMock( ValidatingUploadBase::class );
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
				$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
				$this->getMockImportTitleChecker( 0, true )
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
		$validator->validate( $plan, $this->createMock( User::class ) );

		if ( $expected === null ) {
			$this->addToAssertionCount( 1 );
		}
	}

	public function testValidateFailsWhenCoreChangesTheName() {
		$mockRequest = $this->getMockImportRequest();
		$mockRequest->expects( $this->atLeastOnce() )
			->method( 'getIntendedName' )
			->willReturn( 'Before#After' );
		$mockDetails = $this->getMockImportDetails( Title::makeTitle( NS_FILE, __METHOD__ ) );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails, '' );

		$this->setExpectedException( RecoverableTitleException::class, '"Before"' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
			$this->getMockImportTitleChecker( 0, true ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() )
		);
		$validator->validate( $importPlan, $this->createMock( User::class ) );
	}

	public function testValidateFailsOnFailingTitlePermissionCheck() {
		$mockRequest = $this->getMockImportRequest();
		$mockTitle = $this->getMockTitle( 'Title', true, [ 'error' ] );
		$mockDetails = $this->getMockImportDetails( $mockTitle );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails, '' );

		$this->setExpectedException(
			RecoverableTitleException::class,
			'The action you have requested is limited to users in one of the groups'
		);

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 0, 0 ),
			$this->getMockImportTitleChecker( 0, true ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() )
		);
		$validator->validate( $importPlan, $this->createMock( User::class ) );
	}

}
