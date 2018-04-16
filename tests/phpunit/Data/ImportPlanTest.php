<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use PHPUnit4And6Compat;
use PHPUnit_Framework_MockObject_MockObject;
use TitleValue;

/**
 * @covers \FileImporter\Data\ImportPlan
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|ImportRequest
	 */
	private function getMockRequest() {
		return $this->getMock( ImportRequest::class, [], [], '', false );
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|ImportDetails
	 */
	private function getMockDetails() {
		return $this->getMock( ImportDetails::class, [], [], '', false );
	}

	public function testConstruction() {
		$request = $this->getMockRequest();
		$details = $this->getMockDetails();

		$plan = new ImportPlan( $request, $details );

		$this->assertSame( $request, $plan->getRequest() );
		$this->assertSame( $details, $plan->getDetails() );
	}

	public function testGetTitleAndFileNameFromInitialTitle() {
		$request = $this->getMockRequest();
		$details = $this->getMockDetails();

		$request->expects( $this->once() )
			->method( 'getIntendedName' )
			->willReturn( null );

		$details->expects( $this->once() )
			->method( 'getSourceLinkTarget' )
			->willReturn( new TitleValue( NS_FILE, 'TestFileName.EXT' ) );

		$plan = new ImportPlan( $request, $details );

		$this->assertEquals( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertEquals( 'TestFileName.EXT', $plan->getTitle()->getText() );
		$this->assertEquals( 'TestFileName', $plan->getFileName() );
	}

	public function testGetTitleAndFileNameFromIntendedName() {
		$request = $this->getMockRequest();
		$details = $this->getMockDetails();

		$request->expects( $this->once() )
			->method( 'getIntendedName' )
			->willReturn( 'TestIntendedName' );

		$details->expects( $this->once() )
			->method( 'getSourceFileExtension' )
			->willReturn( 'EXT' );

		$plan = new ImportPlan( $request, $details );

		$this->assertEquals( NS_FILE, $plan->getTitle()->getNamespace() );
		$this->assertEquals( 'TestIntendedName.EXT', $plan->getTitle()->getText() );
		$this->assertEquals( 'TestIntendedName', $plan->getFileName() );
	}

}
