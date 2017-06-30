<?php
class examplesTest extends PHPUnit_Framework_TestCase
{
    public function testJF2Examples()
    {
        $validator = new JF2Validator();
		$iterator = new \DirectoryIterator(__DIR__ . '/../samples/jf2/');
		foreach ( $iterator as $fileinfo )
		{
            if (!$fileinfo->isDot()) {
                $filename = $fileinfo->getFilename();
                if(preg_match('/^.*\.json$/', $filename)){
                    $contents = file_get_contents($fileinfo->getPath() . '/' . $filename);
                    $results = $validator->validate($contents);
                    $this->assertEmpty($results);
                }
            }
        }

    }

    public function testJF2FeedExamples()
    {
        $validator = new JF2Validator();
		$iterator = new \DirectoryIterator(__DIR__ . '/../samples/jf2feed/');
		foreach ( $iterator as $fileinfo )
		{
            if (!$fileinfo->isDot()) {
                $filename = $fileinfo->getFilename();
                if(preg_match('/^.*\.json$/', $filename)){
                    $contents = file_get_contents($fileinfo->getPath() . '/' . $filename);
                    $results = $validator->validate($contents);
                    $this->assertEmpty($results);
                }
            }
        }

    }

    public function testFailingExamples()
    {
        $validator = new JF2Validator();
		$iterator = new \DirectoryIterator(__DIR__ . '/../samples/invalid/');
		foreach ( $iterator as $fileinfo )
		{
            if (!$fileinfo->isDot()) {
                $filename = $fileinfo->getFilename();
                if(preg_match('/^.*\.json$/', $filename)){
                    $contents = file_get_contents($fileinfo->getPath() . '/' . $filename);
                    $results = $validator->validate($contents);
                    $this->assertNotEmpty($results);
                }
            }
        }

    }

}

