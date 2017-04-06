<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class MediaFileTest extends PHPUnit_Framework_TestCase
{

    public function setup()
    {
        $this->old_attachments_supported = common_config('attachments', 'supported');
        $GLOBALS['config']['attachments']['supported'] = true;
    }

    public function tearDown()
    {
        $GLOBALS['config']['attachments']['supported'] = $this->old_attachments_supported;
    }

    /**
     * @dataProvider fileTypeCases
     *
     */
    public function testMimeType($filename, $expectedType)
    {
        if (!file_exists($filename)) {
            throw new Exception("Test file $filename missing");
        }

        $type = MediaFile::getUploadedMimeType($filename, basename($filename));
        $this->assertEquals($expectedType, $type);
    }

    /**
     * @dataProvider fileTypeCases
     *
     */
    public function testUploadedMimeType($filename, $expectedType)
    {
        if (!file_exists($filename)) {
            throw new Exception("WTF? $filename test file missing");
        }
        $tmp = tmpfile();
        fwrite($tmp, file_get_contents($filename));

        $tmp_metadata = stream_get_meta_data($tmp);
        $type = MediaFile::getUploadedMimeType($tmp_metadata['uri'], basename($filename));
        $this->assertEquals($expectedType, $type);
    }

    static public function fileTypeCases()
    {
        $base = dirname(__FILE__);
        $dir = "$base/sample-uploads";
        $files = array(
            "image.png" => "image/png",
            "image.gif" => "image/gif",
            "image.jpg" => "image/jpeg",
            "image.jpeg" => "image/jpeg",
        
            "office.pdf" => "application/pdf",
            
            "wordproc.odt" => "application/vnd.oasis.opendocument.text",
            "wordproc.ott" => "application/vnd.oasis.opendocument.text-template",
            "wordproc.doc" => "application/msword",
            "wordproc.docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "wordproc.rtf" => "text/rtf",
            
            "spreadsheet.ods" => "application/vnd.oasis.opendocument.spreadsheet",
            "spreadsheet.ots" => "application/vnd.oasis.opendocument.spreadsheet-template",
            "spreadsheet.xls" => "application/vnd.ms-office", //"application/vnd.ms-excel",
            "spreadsheet.xlt" => "application/vnd.ms-office", //"application/vnd.ms-excel",
            "spreadsheet.xlsx" => "application/octet-stream", //"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            
            "presentation.odp" => "application/vnd.oasis.opendocument.presentation",
            "presentation.otp" => "application/vnd.oasis.opendocument.presentation-template",
            "presentation.ppt" => "application/vnd.ms-powerpoint",
            "presentation.pptx" => 'application/zip', //"application/vnd.openxmlformats-officedocument.presentationml.presentation",
        );

        $dataset = array();
        foreach ($files as $file => $type) {
            $dataset[] = array("$dir/$file", $type);
        }
        return $dataset;
    }

}

