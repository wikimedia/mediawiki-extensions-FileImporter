<?php

namespace FileImporter\Tests\Data;

use Config;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use Message;
use MessageLocalizer;
use TitleValue;

/**
 * @covers \FileImporter\Data\ImportPlan
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanTest extends \MediaWikiIntegrationTestCase {

	public function testConstruction() {
		$request = new ImportRequest( '//w.invalid' );
		$details = $this->createMock( ImportDetails::class );
		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$prefix = 'wiki';

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, $prefix );

		$this->assertSame( $request, $plan->getRequest() );
		$this->assertSame( $details, $plan->getDetails() );
		$this->assertSame( $prefix, $plan->getInterWikiPrefix() );
	}

	public function testSetters() {
		$request = new ImportRequest( '//w.invalid' );
		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getTextRevisions' )
			->willReturn( $this->createMock( TextRevisions::class ) );
		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );

		$this->assertSame( '', $plan->getCleanedLatestRevisionText() );
		$this->assertSame( 0, $plan->getNumberOfTemplateReplacements() );
		$this->assertFalse( $plan->getAutomateSourceWikiCleanUp() );
		$this->assertFalse( $plan->getAutomateSourceWikiDelete() );
		$this->assertSame( [], $plan->getActionStats() );
		$this->assertSame( [], $plan->getValidationWarnings() );

		$plan->setCleanedLatestRevisionText( 'T' );
		$plan->setNumberOfTemplateReplacements( 1 );
		$plan->setAutomateSourceWikiCleanUp( true );
		$plan->setAutomateSourceWikiDelete( true );
		$plan->setActionStats( [ 'a' => 1 ] );
		$plan->setValidationWarnings( [ 1, 2 ] );
		$plan->addValidationWarning( 3 );

		$this->assertSame( 'T', $plan->getCleanedLatestRevisionText() );
		$this->assertSame( 1, $plan->getNumberOfTemplateReplacements() );
		$this->assertTrue( $plan->getAutomateSourceWikiCleanUp() );
		$this->assertTrue( $plan->getAutomateSourceWikiDelete() );
		$this->assertSame( [ 'a' => 1 ], $plan->getActionStats() );
		$this->assertSame( [ 1, 2, 3 ], $plan->getValidationWarnings() );
	}

	public function testSetActionIsPerformed() {
		$request = new ImportRequest( '//w.invalid' );
		$details = $this->createMock( ImportDetails::class );
		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );

		$plan->setActionIsPerformed( 'lorem' );
		$this->assertSame( [ 'lorem' => 1 ], $plan->getActionStats() );

		$plan->setActionIsPerformed( 'lorem' );
		$this->assertSame( [ 'lorem' => 1 ], $plan->getActionStats() );
	}

	public function testGetTitleAndFileNameFromInitialTitle() {
		$request = $this->createMock( ImportRequest::class );
		$request->expects( $this->once() )
			->method( 'getIntendedName' );

		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getSourceLinkTarget' )
			->willReturn( new TitleValue( NS_FILE, 'TestFileName.EXT' ) );

		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );

		$this->assertSame( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertSame( 'TestFileName.EXT', $plan->getOriginalTitle()->getText() );
		$this->assertSame( 'TestFileName.EXT', $plan->getTitle()->getText() );
		$this->assertSame( 'TestFileName', $plan->getFileName() );
	}

	public function testGetTitleAndFileNameFromIntendedName() {
		$request = new ImportRequest( '//w.invalid', 'TestIntendedName' );

		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getSourceFileExtension' )
			->willReturn( 'EXT' );

		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );

		$this->assertSame( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertSame( 'TestIntendedName.EXT', $plan->getTitle()->getText() );
		$this->assertSame( 'TestIntendedName', $plan->getFileName() );
		$this->assertSame( 'EXT', $plan->getFileExtension() );
	}

	public function provideTexts() {
		return [
			[ 'Some Text', 'Some Text', null, '', '', 'Some Text', false ],
			[ 'Some Text', 'Some Text', 'Some Other Text', '', '', 'Some Other Text', true ],
			[ 'Some Text', 'Some Text', '', '', '', '', true ],
			[ 'Some unclean Text', 'Some Text', null, '', '', 'Some Text', true ],
			[ 'Some Text', null, null, '', '', 'Some Text', false ],
			[ 'Some Text', null, null, 'Comment', 'Annotation', "Comment\nAnnotation\nSome Text", true ],
			[ 'Some Text', null, null, '', 'Annotation', "Annotation\nSome Text", true ],
			[ 'Some Text', null, null, 'Comment', '', "Comment\nSome Text", true ],
		];
	}

	/**
	 * @dataProvider provideTexts
	 */
	public function testTextGetters(
		$originalText,
		$cleanedText,
		$intendedText,
		$postImportComment,
		$postImportAnnotation,
		$expectedText,
		$expectedChangedSignal
	) {
		$this->setMwGlobals( 'wgFileImporterTextForPostImportRevision', '' );

		$request = new ImportRequest( '//w.invalid', null, $intendedText );

		$textRevision = $this->createMock( TextRevision::class );
		$textRevision->method( 'getField' )
			->willReturn( $originalText );

		$textRevisions = $this->createMock( TextRevisions::class );
		$textRevisions->method( 'getLatest' )
			->willReturn( $textRevision );

		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getTextRevisions' )
			->willReturn( $textRevisions );

		$config = $this->createMock( Config::class );
		$config->method( 'get' )
			->with( 'FileImporterTextForPostImportRevision' )
			->willReturn( $postImportComment );

		$message = $this->createMock( Message::class );
		$message->method( 'inContentLanguage' )
			->willReturn( $message );
		$message->method( 'plain' )
			->willReturn( $postImportAnnotation );

		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->method( 'msg' )
			->with( 'fileimporter-post-import-revision-annotation' )
			->willReturn( $message );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );
		$plan->setCleanedLatestRevisionText( $cleanedText );

		$this->assertSame( $originalText, $plan->getInitialFileInfoText(), 'initialFileInfoText' );
		$this->assertSame(
			$cleanedText ?: $originalText,
			$plan->getCleanedLatestRevisionText(),
			'cleanedLatestRevisionText'
		);
		$this->assertSame( $expectedText, $plan->getFileInfoText(), 'fileInfoText' );
		$this->assertSame(
			$expectedChangedSignal,
			$plan->wasFileInfoTextChanged(),
			'wasFileInfoTextChanged'
		);
	}

	public function testGetInitialFileInfoTextWithNoTextRevision() {
		$request = new ImportRequest( '//w.invalid' );

		$textRevisions = $this->createMock( TextRevisions::class );

		$details = $this->createMock( ImportDetails::class );
		$details->method( 'getTextRevisions' )
			->willReturn( $textRevisions );

		$config = $this->createMock( Config::class );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, '' );
		$this->assertSame( '', $plan->getInitialFileInfoText() );
	}

}
