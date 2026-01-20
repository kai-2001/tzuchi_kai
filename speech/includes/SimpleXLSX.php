<?php
/**
 * SimpleXLSX PHP class
 * 
 * A lightweight class to parse XLSX files for the specific purpose of this application.
 * It uses standard PHP ZipArchive and SimpleXML extensions.
 */

class SimpleXLSX
{
    private $sheets = [];
    private $sharedStrings = [];
    private $sheetNames = [];
    private $zip;
    public $success = false;
    public $rows = [];
    public $error = '';

    public function __construct($filename = null, $is_data = false)
    {
        if ($filename) {
            $this->parse($filename, $is_data);
        }
    }

    public static function parse($filename, $is_data = false)
    {
        $xlsx = new self();
        $xlsx->_parse($filename, $is_data);
        return $xlsx;
    }

    private function _parse($filename, $is_data = false)
    {
        $this->zip = new ZipArchive();

        if (!file_exists($filename)) {
            $this->error = 'File not found: ' . $filename;
            return;
        }

        try {
            if ($this->zip->open($filename) === true) {
                $this->parseSharedStrings();
                $this->parseWorksheets();
                $this->zip->close();
                $this->success = true;
            } else {
                $this->error = 'Failed to open file .xlsx (ZipArchive Error)';
            }
        } catch (Exception $e) {
            $this->error = 'XML Parse Error: ' . $e->getMessage();
        }
    }

    private function parseSharedStrings()
    {
        if ($content = $this->zip->getFromName('xl/sharedStrings.xml')) {
            $xml = simplexml_load_string($content);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $this->sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r) && isset($si->r->t)) {
                        // Handle rich text
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $this->sharedStrings[] = $text;
                    }
                }
            }
        }
    }

    private function parseWorksheets()
    {
        // Try reading the workbook description to find sheet names
        // But for simplicity in this specific task, we often just need the first sheet
        // "xl/worksheets/sheet1.xml" is the standard first sheet

        if ($content = $this->zip->getFromName('xl/worksheets/sheet1.xml')) {
            $this->parseSheet($content);
        }
    }

    private function parseSheet($content)
    {
        $xml = simplexml_load_string($content);
        $rows = [];

        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $r = [];
                $cellIndex = 0;

                foreach ($row->c as $cell) {
                    $attr = $cell->attributes();
                    $val = (string) $cell->v;
                    $type = (string) $attr['t']; // s = shared string
                    $ref = (string) $attr['r']; // e.g. A1, B1

                    // Simple column index calculation could be added here to handle empty cells
                    // But for this implementation we assume standard consecutive cells or will handle mapping later

                    if ($type === 's') {
                        $val = $this->sharedStrings[(int) $val] ?? '';
                    }
                    $r[] = $val;
                }
                $rows[] = $r;
            }
        }
        $this->rows = $rows;
    }

    public function createrows()
    {
        return $this->rows;
    }

    public function rows()
    {
        return $this->rows;
    }
}
