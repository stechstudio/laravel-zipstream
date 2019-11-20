<?php

namespace STS\ZipStream\Tests\SizeEstimation;

class EmptyArchiveSizeEstimationTest extends SizeEstimationTestCase
{
    public function testBasic()
    {
        $this->assertSizeEstimation();
    }

    public function testEstimationWithArchiveComment()
    {
        $this->zip->opt->setComment('Comment content');

        $this->assertSizeEstimation();
    }

    /**
     * @dataProvider provideSupportedEnableZip64Options
     */
    public function testEstimationWithEnableZip64($enableZip64)
    {
        $this->zip->opt->setEnableZip64($enableZip64);

        $this->assertSizeEstimation();
    }

    /**
     * @dataProvider provideSupportedZeroHeaderOptions
     */
    public function testEstimationWithZeroHeader($zeroHeader)
    {
        $this->zip->opt->setZeroHeader($zeroHeader);

        $this->assertSizeEstimation();
    }
}
