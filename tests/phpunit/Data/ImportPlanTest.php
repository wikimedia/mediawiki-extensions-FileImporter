<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use PHPUnit4And6Compat;
use TitleValue;

/**
 * @covers \FileImporter\Data\ImportPlan
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanTest extends \MediaWikiTestCase {
	use PHPUnit4And6Compat;

	public function testConstruction() {
		$request = $this->createMock( ImportRequest::class );
		$details = $this->createMock( ImportDetails::class );
		$prefix = 'wiki';

		$plan = new ImportPlan( $request, $details, $prefix );

		$this->assertSame( $request, $plan->getRequest() );
		$this->assertSame( $details, $plan->getDetails() );
		$this->assertSame( $prefix, $plan->getInterWikiPrefix() );
	}

	public function testGetTitleAndFileNameFromInitialTitle() {
		$request = $this->createMock( ImportRequest::class );
		$request->expects( $this->once() )
			->method( 'getIntendedName' )
			->willReturn( null );

		$details = $this->createMock( ImportDetails::class );
		$details->expects( $this->once() )
			->method( 'getSourceLinkTarget' )
			->willReturn( new TitleValue( NS_FILE, 'TestFileName.EXT' ) );

		$plan = new ImportPlan( $request, $details, '' );

		$this->assertSame( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertSame( 'TestFileName.EXT', $plan->getTitle()->getText() );
		$this->assertSame( 'TestFileName', $plan->getFileName() );
	}

	public function testGetTitleAndFileNameFromIntendedName() {
		$request = $this->createMock( ImportRequest::class );
		$request->expects( $this->once() )
			->method( 'getIntendedName' )
			->willReturn( 'TestIntendedName' );

		$details = $this->createMock( ImportDetails::class );
		$details->expects( $this->once() )
			->method( 'getSourceFileExtension' )
			->willReturn( 'EXT' );

		$plan = new ImportPlan( $request, $details, '' );

		$this->assertSame( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertSame( 'TestIntendedName.EXT', $plan->getTitle()->getText() );
		$this->assertSame( 'TestIntendedName', $plan->getFileName() );
	}

	public function provideGetFileInfoText() {
		return [
			[ 'Some Text', 'Some Text', null, 'Some Text' ],
			[ 'Some Text', 'Some Text', 'Some Other Text', 'Some Other Text' ],
			[ 'Some Text', 'Some Text', '', '' ],
			[ 'Some unclean Text', 'Some Text', null, 'Some Text' ],
			[ 'Some Text', null, null, 'Some Text' ],
		];
	}

	/**
	 * @dataProvider provideGetFileInfoText
	 * @param string $originalText
	 * @param string $intendedText
	 * @param string $expectedText
	 */
	public function testGetFileInfoText( $originalText, $cleanedText, $intendedText, $expectedText ) {
		$this->setMwGlobals( 'wgFileImporterTextForPostImportRevision', '' );

		$request = $this->createMock( ImportRequest::class );
		$request->expects( $this->once() )
			->method( 'getIntendedText' )
			->willReturn( $intendedText );

		$textRevision = $this->createMock( TextRevision::class );
		$textRevision->method( 'getField' )
			->willReturn( $originalText );

		$textRevisions = $this->createMock( TextRevisions::class );
		$textRevisions->method( 'getLatest' )
			->willReturn( $textRevision );

		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getTextRevisions' )
			->willReturn( $textRevisions );
		$details->method( 'getCleanedRevisionText' )
			->willReturn( $cleanedText );

		$plan = new ImportPlan( $request, $details, '' );
		$this->assertSame( $expectedText, $plan->getFileInfoText() );
	}

}
